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

class AnalyticsJS extends Extension
{

    /* Name of the Google Analytics object */
    public static $global_name = 'ga';

    /* Whether to track outbound links & assets downloads */
    public static $track_links = true;

    /* Whether to compress the JavaScript */
    public static $compress_js = true;


    protected static $tracker_config = array();
    protected static $ga_trackers = false;
    protected static $tracker_names = array();
    protected static $ga_configs = array();
    protected static $tracker_counter = 1;

    /*
     * Automatically initiate the code
     */
    public function onAfterInit()
    {
        $this->injectGoogleUniversalAnalyticsCode();
    }

    /*
     * Injects GA tracking code into <head>
     * Optional: inline code to track downloads & outgoing links
     */
    protected function injectGoogleUniversalAnalyticsCode()
    {
        /* Parse static configs */
            $this->parseGoogleUniversalAnalyticsConfigs();
            /* Generate header code with configs */
            $this->genUniversalAnalyticsCode();
            /* Add link tracking code */
            $this->generateUniversalAnalyticsLinkCode();
    }


    /*
     * Parse configs including yaml & static
     * @param null
     * @return null
     */
    protected function parseGoogleUniversalAnalyticsConfigs()
    {
        /* Set trackers from yaml */
        if ($trackers = Config::inst()->get('AnalyticsJS', 'tracker')) {
            foreach ($trackers as $tracker) {
                array_push(self::$tracker_config, $tracker);
            }
        }

        /* return false if no trackers are set */
        if (count(self::$tracker_config) == 0) {
            return false;
        }

        /* set GA global name, typically "ga" */
        if ($global_name = Config::inst()->get('AnalyticsJS', 'global_name')) {
            self::$global_name = $global_name;
        }

        /* compress inline JavaScript? */
        if (!$compress_js = Config::inst()->get('AnalyticsJS', 'compress_js')) {
            self::$compress_js = false;
        }

        $skip_tracking = (!Director::isLive() || isset($_GET['flush'])) ? true : false;

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
                            'Tracker ' . $ufname .' already set, please use a unique name',
                            E_USER_WARNING
                        );
                    }
                    return false;
                } else {
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
    protected function genUniversalAnalyticsCode()
    {
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
            self::$ga_trackers . $ga_insert;

        Requirements::insertHeadTags('<script type="text/javascript">//<![CDATA[' . "\n" . $this->compressGUACode($headerscript) . "\n" . '//]]></script>');
    }


    /*
     * Generate and inject customScript() JavaScript link-tracking code
     */
    protected function generateUniversalAnalyticsLinkCode()
    {
        if (
            count(self::$tracker_names) == 0 ||
            !$track_links = Config::inst()->get('AnalyticsJS', 'track_links')
        ) {
            return false;
        }

        $non_callbrack_trackers = '';
        $callback_trackers = '';

        foreach (self::$tracker_names as $t) {
            $non_callbrack_trackers .= self::$global_name . '("'. $t .'send","event",c,a,l);';
            $callback_trackers .= self::$global_name . '("'. $t .'send","event",c,a,l,{"hitCallback":hb});';
        }

        /*
         * JavaScript code for link tracking
         */
        $js = 'function _guaLt(e){

            /* If GA is blocked or not loaded then abort */
            if (
                !' . self::$global_name . '.hasOwnProperty("loaded") ||
                1 != ' . self::$global_name . '.loaded
            ) return;

            var el = e.srcElement || e.target;

            /* Loop through parent elements if clicked element is not a link (eg: <a><img /></a> */
            while(el && (typeof el.tagName == "undefined" || el.tagName.toLowerCase() != "a" || !el.href)){
                el = el.parentNode;
            }

            if(el && el.href){
                var dl = document.location;
                var l = dl.pathname + dl.search; /* event label = referer */
                var h = el.href; /* event link */
                var a = h; /* clone link for processing */
                var c = !1; /* event category */
                var t = el.target; /* link target */

                /* telephone links */
                if(h.match(/^tel\:/i)){
                    c = "Phone Links";
                    a = h.replace(/\D/g,"");
                }

                /* sms links */
                else if(h.match(/^sms\:/i)){
                    c = "SMS Links";
                    a = h.replace(/\D/g,"");
                }

                /* email links */
                else if(h.match(/^mailto\:/i)){
                    c = "Email Links";
                    a = h.slice(7);
                }

                /* if external (and not JS) link then track event as "Outgoing Links" */
                else if(h.indexOf(location.host) == -1 && !h.match(/^javascript\:/i)){
                    c = "Outgoing Links";
                }

                /* else if /assets/ (not images) track as "Downloads" */
                else if(h.match(/\/assets\//) && !h.match(/\.(jpe?g|bmp|png|gif|tiff?)$/i)){
                    c = "Downloads";
                    a = h.match(/\/assets\/(.*)/)[1];
                }

                if(c){
                    /* link opens in same window & requires callback */
                    if(!t || t.match(/^_(self|parent|top)$/i)){

                        var hbrun = false;

                        /* hitCallback function for GA */
                        var hb = function(){
                            /* run once only */
                            if(hbrun) return;
                            hbrun = true;
                            window.location.href = h;
                        };

                        /* Add GA tracker(s) */
                        ' . $callback_trackers . '

                        /* Run hitCallback function if GA takes too long */
                        setTimeout(hb,1000);

                        /* prevent default action (ie: click) */
                        e.preventDefault ? e.preventDefault() : e.returnValue = !1;

                    } else {
                        /* link opens a new window already - just track */
                        ' . $non_callbrack_trackers . '
                    }
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
    protected function compressGUACode($data)
    {
        $repl = array(
            '!/\*[^*]*\*+([^/][^*]*\*+)*/!' => '', // Comments
            '/(    |\n)/' => '', // soft tabs / new lines
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
            '/\s>\s?/' => '>'
        );
        return self::$compress_js ? preg_replace(array_keys($repl), array_values($repl), $data) : $data;
    }

    /*
     * Statically add Universal Analytics configs
     * Kept for backwards compatibility
     * @param array (see README.md)
     * @return null
     */
    public static function add_ga()
    {
        Deprecation::notice('3.2.0', 'Use the "AnalyticsJS.tracker" yaml config instead');
        $arg_list = func_get_args();
        if (count($arg_list) < 2) {
            trigger_error('GaTracker::add_ga() requires at least two arguments', E_USER_ERROR);
            return false;
        }
        array_push(self::$tracker_config, $arg_list);
    }
}
