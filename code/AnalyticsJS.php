<?php
/**
 * Google Universal Analytics Tracker
 * ==================================
 *
 * Extension to add Google Universal Analytics.js tracking code to SilverStripe 3
 *
 * License: MIT-style license http://opensource.org/licenses/MIT
 * Authors: Techno Joy development team (www.technojoy.co.nz)
 */

class AnalyticsJS extends Extension {

	/* Name of the Google Analytics object */
	public static $global_name = 'ga';

	/* Whether to track outbound links & assets downloads */
	public static $track_links = true;

	/* Ignore tracking pages with ?flush=[?] */
	public static $ignore_flushes = true;

	/* Whether to compress the JavaScript */
	public static $compress_js = true;

	protected static $tracker_config = array();
	protected static $ga_trackers = false;
	protected static $tracker_names = array();
	protected static $ga_configs = array();
	protected static $tracker_counter = 1;

	/*
	 * Statically add Universal Analytics configs
	 * @param array (see README.md)
	 * @return null
	 */
	public static function add_ga() {
		$arg_list = func_get_args();
		if (count($arg_list) < 2) {
			trigger_error('GaTracker::add_ga() requires at least two arguments', E_USER_ERROR);
			return false;
		}
		array_push(self::$tracker_config, $arg_list);
	}

	/*
	 * Automatically initiate the code
	 */
	public function onAfterInit() {
		$this->injectGoogleUniversalAnalyticsCode();
	}

	/*
	 * Injects GA tracking code into <head>
	 * Optional: inline code to track downloads & outgoing links
	 */
	protected function injectGoogleUniversalAnalyticsCode() {
			/* Parse static configs */
			$this->parseGoogleUniversalAnalyticsConfigs();
			/* Generate header code with configs */
			$this->genUniversalAnalyticsCode();
			/* Add link tracking code */
			$this->generateUniversalAnalyticsLinkCode();
	}


	/*
	 * Parse static config array
	 * Sets static self::$tracker_names array
	 * Sets static self::$ga_trackers code
	 * @param null
	 * @return null
	 */
	protected function parseGoogleUniversalAnalyticsConfigs() {

		if (count(self::$tracker_config) == 0) {
			return false;
		}

		$skip_tracking = (!Director::isLive() || (self::$ignore_flushes && isset($_GET['flush']))) ? true : false;

		foreach (self::$tracker_config as $conf) {

			$args = array();

			if ($conf[0] == 'create') {
				$tname = false;

				foreach ($conf as $i) {
					if (is_array($i) && isset($i['name'])) {
						$tname = $i['name'] . '.';
						break;
					}
				}

				$ufname = ($tname === false) ? 'Default' : $tname;

				/* check if config has already been set (or _config.php has been run a second time) */
				if (isset(self::$ga_configs[$ufname])) {
					/* no unique name has been specified for additional tracker */
					if (self::$ga_configs[$ufname] != $conf[1]) {
						trigger_error(
							'GaTracker::add_ga(): ' . $ufname .' Tracker already set, please use unique name eg: ' .
							'AnalyticsJS::add_ga("create", "UA-12345679-1", "auto", array("name" => "MyOtherTracker"));',
							E_USER_WARNING
						);
					}
					return false;
				}
				else {
					self::$ga_configs[$ufname] = $conf[1];
				}

				/* Replace Tracker IDs with fake ones if not in LIVE mode */
				if ($skip_tracking) {
					$conf[1] = preg_replace('/[0-9]{4,}-[0-9]+/', 'DEV-' . self::$tracker_counter++, $conf[1]);
				}

				array_push(self::$tracker_names, $tname);

			}

			foreach ($conf as $i) {
				array_push($args, json_encode($i));
			}

			self::$ga_trackers .= self::$global_name . '(' . implode(',', $args) .');'."\n";
		}

	}


	/*
	 * Generates <head> and inject insertHeadTags() JavaScript code
	 * for tracking if at least one tracking config has been specified
	 * @param null
	 * @return null
	 */
	protected function genUniversalAnalyticsCode() {

		if (count(self::$tracker_names) == 0) {
			return false;
		}

		$ErrorCode = Controller::curr()->ErrorCode;

		if ($ErrorCode) {
			$ecode = ($ErrorCode == 404) ? '404 Page Not Found' : $ErrorCode . ' Page Error';
			foreach (self::$tracker_names as $t) {
				self::$ga_trackers .= self::$global_name . '("' . $t . 'send","event","' . $ecode . '",document.location.pathname+document.location.search,document.referrer);'."\n";
			}
		} else {
			foreach (self::$tracker_names as $t) {
				self::$ga_trackers .= self::$global_name . '("' . $t . 'send","pageview");' . "\n";
			}
		}

		$headerscript = '(function(i,s,o,g,r,a,m){i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){' . "\n" .
			'(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),' . "\n" .
			'm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)' . "\n" .
			'})(window,document,"script","//www.google-analytics.com/analytics.js","' . self::$global_name . '");' . "\n" .
			self::$ga_trackers;

		Requirements::insertHeadTags('<script type="text/javascript">//<![CDATA[' . "\n" . $this->compressGUACode($headerscript) . "\n" . '//]]></script>');

	}


	/*
	 * Generate and inject customScript() JavaScript link-tracking code
	 * @param null
	 * @return FieldSet
	 */
	protected function generateUniversalAnalyticsLinkCode() {

		if (!self::$track_links || count(self::$tracker_names) == 0) {
			return false;
		}

		$trackers = '';

		foreach (self::$tracker_names as $t) {
			$trackers .= self::$global_name . '("'. $t .'send","event",c,a,l);';
		}


		$js = 'function _guaLt(event){
		var el = event.srcElement || event.target;

			/* Loop through parent elements if clicked element is not a link (ie: <a><img /></a> */
			while(el && (typeof el.tagName == "undefined" || el.tagName.toLowerCase() != "a" || !el.href))
				el = el.parentNode;

			if(el && el.href){
				dl = document.location;
				l = dl.pathname + dl.search;
				h = el.href;
				c = !1;
				if(h.indexOf(location.host) == -1){
					c = "Outgoing Links";
					a = h;
				}
				else if(h.match(/\/assets\//) && !h.match(/\.(jpe?g|bmp|png|gif|tiff?)$/i)){
					c = "Downloads";
					a = h.match(/\/assets\/(.*)/)[1];
				}
				if(c){
					/* Add trackers */
					' . $trackers . '
					/* If target not set delay opening of window by 0.5s to allow tracking */
					if(!el.target || el.target.match(/^_(self|parent|top)$/i)){
						setTimeout(function(){
							document.location.href = el.href;
						}.bind(el),500);
						/* Prevent standard click */
						event.preventDefault ? event.preventDefault() : event.returnValue = !1;
					}
				}

			}
		}

		/* Attach the event to all clicks in the document after page has loaded */
		var w = window;
		w.addEventListener ? w.addEventListener("load",function(){document.body.addEventListener("click",_guaLt,!1)},!1)
		 : w.attachEvent && w.attachEvent("onload",function(){document.body.attachEvent("onclick",_guaLt)});';

		Requirements::customScript($this->compressGUACode($js));

	}

	/*
	 * Compress inline JavaScript
	 * @param str data
	 * @return str
	 */
	protected function compressGUACode($data) {
		$repl = array(
			'!/\*[^*]*\*+([^/][^*]*\*+)*/!' => '', // Comments
			'/(\n|\t)/' => '',
			'/\s?=\s?/' => '=',
			'/\s?==\s?/' => '==',
			'/\s?!=\s?/' => '!=',
			'/\s?;\s?/' => ';',
			'/\s?:\s?/' => ':',
			'/\s?\+\s?/' => '+',
			'/\s?\?\s?/' => '?',
			'/\s?&&\s?/' => '&&',
			'/\s?\(\s?/' => '(',
			'/\s?\)\s?/' => ')',
			'/\s?\|\s?/' => '|',
			'/\s<\s?/' => '<',
			'/\s>\s?/' => '>',
		);
		return self::$compress_js ? preg_replace(array_keys($repl), array_values($repl), $data) : $data;
	}

}