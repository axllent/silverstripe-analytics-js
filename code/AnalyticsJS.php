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

		$ga_insert = false;

		$ErrorCode = Controller::curr()->ErrorCode;

		if ($ErrorCode) {
			$ecode = ($ErrorCode == 404) ? '404 Page Not Found' : $ErrorCode . ' Page Error';
			foreach (self::$tracker_names as $t) {
				$ga_insert .= self::$global_name . '("' . $t . 'send","event","' . $ecode . '",window.location.pathname+window.location.search,window.referrer);'."\n";
			}
		} else {
			foreach (self::$tracker_names as $t) {
				$ga_insert .= self::$global_name . '("' . $t . 'send","pageview");' . "\n";
			}
		}

		$headerscript = '(function(i,s,o,g,r,a,m){i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){' . "\n" .
			'(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),' . "\n" .
			'm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)' . "\n" .
			'})(window,document,"script","//www.google-analytics.com/analytics.js","' . self::$global_name . '");' . "\n" .
			self::$ga_trackers.$ga_insert;

		Requirements::insertHeadTags('<script type="text/javascript">//<![CDATA[' . "\n" . $this->compressGUACode($headerscript) . "\n" . '//]]></script>');

	}


	/*
	 * Generate and inject customScript() JavaScript link-tracking code
	 */
	protected function generateUniversalAnalyticsLinkCode() {

		if (!self::$track_links || count(self::$tracker_names) == 0) {
			return false;
		}

		$trackers = '';

		foreach (self::$tracker_names as $t) {
			$trackers .= self::$global_name . '("'. $t .'send","event",c,a,l,{"hitCallback":hb(h,t),"nonInteraction":ni});';
		}

		/*
		 * JavaScript code for link tracking
		 */
		$js = 'function _guaLt(event){
			var el = event.srcElement || event.target;

			/* Loop through parent elements if clicked element is not a link (ie: <a><img /></a> */
			while(el && (typeof el.tagName == "undefined" || el.tagName.toLowerCase() != "a" || !el.href))
				el = el.parentNode;

			if(el && el.href){
				var l = window.pathname + window.search;
				var h = el.href;
				var c = !1;
				var t = el.target; /* new window? */
				var ni = 1; /* count as bounce */

				if(!t || t.match(/^_(self|parent|top)$/i)){
					t = !1; /* unset target */
					ni = 0; /* calculate outgoing link as bounce */
				}

				/* if external link then track event as "Outgoing Links" */
				if(h.indexOf(location.host) == -1){
					c = "Outgoing Links";
					a = h;
				}

				/* else if /assets/ (not images) track as "Downloads" */
				else if(h.match(/\/assets\//) && !h.match(/\.(jpe?g|bmp|png|gif|tiff?)$/i)){
					c = "Downloads";
					a = h.match(/\/assets\/(.*)/)[1];
					ni = 1; /* do not count as bounce */
				}

				if(c){
					/* hitCallback function for GA */
					var hb = function(u,t){
						t ? window.open(u,t) : window.location.href = u;
					};
					/* Add GA tracker(s) */
					' . $trackers . '
					/* prevent default action */
					event.preventDefault ? event.preventDefault() : event.returnValue = !1;
				}
			}
		}

		/* Attach the event to all clicks in the document after page has loaded */
		window.addEventListener ? document.body.addEventListener("click",_guaLt,!1)
		 : window.attachEvent && document.body.attachEvent("onclick",_guaLt);';

		Requirements::customScript($this->compressGUACode($js));

	}

	/*
	 * Compress inline JavaScript
	 * @param str
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