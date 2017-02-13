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
    private static $global_name = 'ga';

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
            $this->parseAnalyticsConfigs();
            /* Generate header code with configs */
            $this->genAnalyticsCodeTrackingCode();
            /* Add link tracking code */
            $this->genLinkTrackingCode();
    }


    /*
     * Parse configs
     * @param null
     * @return null
     */
    protected function parseAnalyticsConfigs()
    {
        $this->config = Config::inst();
        /* Set trackers from yaml */
        if ($trackers = $this->config->get('AnalyticsJS', 'tracker')) {
            foreach ($trackers as $tracker) {
                array_push(self::$tracker_config, $tracker);
            }
        }

        /* return false if no trackers are set */
        if (count(self::$tracker_config) == 0) {
            return false;
        }

        /* set GA global name, typically "ga" */
        self::$global_name = $this->config->get('AnalyticsJS', 'global_name');

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
     * Generates and inject insertHeadTags() JavaScript code into <head>
     * for tracking if at least one tracking config has been specified
     * @param null
     * @return null
     */
    protected function genAnalyticsCodeTrackingCode()
    {
        if (count(self::$tracker_names) == 0) {
            return false;
        }

        $ga_insert = false;

        $ErrorCode = Controller::curr()->ErrorCode;

        if ($ErrorCode) {
            $ecode = ($ErrorCode == 404) ?
                $this->config->get('AnalyticsJS', '404_category')
                : $ErrorCode . $this->config->get('AnalyticsJS', 'error_category');

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
            '})(window,document,"script","' . $this->getAnalyticsScript() . '","' . self::$global_name . '");' . "\n" .
            self::$ga_trackers . $ga_insert;

        Requirements::insertHeadTags('<script type="text/javascript">//<![CDATA[' . "\n" . $this->compressGUACode($headerscript) . "\n" . '//]]></script>');
    }

    protected function getAnalyticsScript()
    {
        return $this->config->get('AnalyticsJS', 'cache_analytics_js') ?
            Director::baseURL() . '_ga/analytics.js' :
                '//www.google-analytics.com/analytics.js';
    }

    /*
     * Generate and inject customScript() JavaScript link-tracking code
     */
    protected function genLinkTrackingCode()
    {
        if (
            count(self::$tracker_names) == 0 ||
            !$this->config->get('AnalyticsJS', 'track_links')
        ) {
            return false;
        }

        $non_callbrack_trackers = '';
        $callback_trackers = '';

        foreach (self::$tracker_names as $t) {
            $non_callbrack_trackers .= self::$global_name . '("'. $t .'send","event",c,a,l);';
            $callback_trackers .= self::$global_name . '("'. $t .'send","event",c,a,l,{"hitCallback":hb});';
        }

        $js = $this->owner->customise(ArrayData::create(array(
            'GlobalName' => self::$global_name,
            'CallbackTrackers' => $callback_trackers,
            'NonCallbackTrackers' => $non_callbrack_trackers,
            'LinkCategory' => $this->config->get('AnalyticsJS', 'link_category'),
            'EmailCategory' => $this->config->get('AnalyticsJS', 'email_category'),
            'PhoneCategory' => $this->config->get('AnalyticsJS', 'phone_category'),
            'DownloadsCategory' => $this->config->get('AnalyticsJS', 'downloads_category'),
        )))->renderWith('OutboundLinkTracking');

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
        return $this->config->get('AnalyticsJS', 'compress_js')
            ? preg_replace(array_keys($repl), array_values($repl), $data)
            : $data;
    }

    /*
     * Statically add Universal Analytics configs
     * Kept for backwards compatibility
     * @param array
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
