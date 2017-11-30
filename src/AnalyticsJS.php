<?php

namespace Axllent\AnalyticsJS;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

/**
 * Google Universal Analytics Tracker
 * ==================================
 *
 * Extension to add Google Universal Analytics.js tracking code to SilverStripe
 *
 * License: MIT-style license http://opensource.org/licenses/MIT
 * Authors: Techno Joy development team (www.technojoy.co.nz)
 */

class AnalyticsJS extends Extension
{

    /**
     * @config
     * Tracker function name
     */
    private static $global_name = 'ga';

    /**
     * @config
     * Allow live tracking in dev/staging mode
     */
    private static $track_in_dev_mode = false;

    /**
     * @config
     * Compress inline JavaScript
     */
    private static $compress_js = true;

    /**
     * @config
     * Enable external link / asset GA event tracking
     */
    private static $track_links = true;

    /**
     * @config
     * Ignore external link tracking for links matching <class>
     */
    private static $ignore_link_class = false;

    /**
     * @config
     * Outgoing link category name for GA event logging
     */
    private static $link_category = 'Outgoing Links';

    /**
     * @config
     * Email link category name for GA event logging
     */
    private static $email_category = 'Email Links';

    /**
     * @config
     * Phone link category name for GA event logging
     */
    private static $phone_category = 'Phone Links';

    /**
     * @config
     * Download link category name for GA event logging
     */
    private static $downloads_category = 'Downloads';

    /**
     * @config
     * 404 page category name for GA event logging
     */
    private static $page_404_category = 'Page Not Found';

    /**
     * @config
     * Error page category (not 404) for GA event logging
     */
    private static $page_error_category = 'Page Error';

    /**
     * @config
     * Use a local (cached) copy of the analytics.js rather than link to live version
     */
    private static $cache_analytics_js = false;

    /**
     * @config
     * Cache local analytics.js for xx hours
     */
    private static $cache_hours = 48;

    /* @end Config */

    protected $tracker_config = [];
    protected $ga_trackers = false;
    protected $tracker_names = [];
    protected $ga_configs = [];
    protected $tracker_counter = 1;

    private static $casting = array(
        'CallbackTrackers' => 'HTMLText',
        'NonCallbackTrackers' => 'HTMLText',
    );

    /*
     * Automatically initiate the code
     * Injects GA tracking code into <head>
     * Optional: inline code to track downloads & outgoing links
     */
    public function onAfterInit()
    {
        // $this->config = Config::inst();

        // Parse configs
        $this->parseAnalyticsConfigs();

        // Generate header code with configs
        $this->genAnalyticsCodeTrackingCode();

        // Add link tracking code
        $this->genLinkTrackingCode();
    }

    /*
     * Parse configs
     * @param null
     * @return null
     */
    protected function parseAnalyticsConfigs()
    {
        // Check in place for old configs
        $this->testForDeprecatedConfigs();

        // Set trackers from yaml
        if ($trackers = Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'tracker')) {
            foreach ($trackers as $tracker) {
                array_push($this->tracker_config, $tracker);
            }
        }

        // Return false if no trackers are set
        if (count($this->tracker_config) == 0) {
            return false;
        }

        // Set GA global name, typically "ga"
        $this->tracker_name = Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'global_name');

        $track_in_dev_mode = Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'track_in_dev_mode');

        $skip_tracking = (
            (!Director::isLive() && !$track_in_dev_mode) ||
            isset($_GET['flush'])
        ) ? true : false;

        foreach ($this->tracker_config as $conf) {
            $args = [];

            if ($conf[0] == 'create') {
                $tname = false;

                foreach ($conf as $i) {
                    if (is_array($i) && isset($i['name'])) {
                        $tname = $i['name'] . '.';
                        break;
                    }
                }

                $ufname = ($tname === false) ? 'Default' : $tname;

                // Check if config has already been set (or _config.php has been run a second time)
                if (isset($this->ga_configs[$ufname])) {
                    // no unique name has been specified for additional tracker
                    if ($this->ga_configs[$ufname] != $conf[1]) {
                        Injector::inst()->get('Logger')
                            ->addWarning('Tracker "' . $ufname .'" already set, please use a unique name');
                        return false;
                    }
                    return false;
                } else {
                    $this->ga_configs[$ufname] = $conf[1];
                }

                // Replace Tracker IDs with fake ones if not in LIVE mode
                if ($skip_tracking) {
                    $conf[1] = preg_replace('/[0-9]{4,}-[0-9]+/', 'DEV-' . $this->tracker_counter++, $conf[1]);
                }

                array_push($this->tracker_names, $tname);
            }

            foreach ($conf as $i) {
                array_push($args, json_encode($i));
            }

            $this->ga_trackers .= $this->tracker_name . '(' . implode(',', $args) .');'."\n";
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
        if (count($this->tracker_names) == 0) {
            return false;
        }

        $ga_insert = false;

        $ErrorCode = Controller::curr()->ErrorCode;

        if ($ErrorCode) {
            $ecode = ($ErrorCode == 404) ?
                Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'page_404_category')
                : $ErrorCode . Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'page_error_category');

            foreach ($this->tracker_names as $t) {
                $ga_insert .= $this->tracker_name . '("' . $t . 'send","event","' . $ecode . '",window.location.pathname+window.location.search,window.referrer);'."\n";
            }
        } else {
            foreach ($this->tracker_names as $t) {
                $ga_insert .= $this->tracker_name . '("' . $t . 'send","pageview");' . "\n";
            }
        }

        $headerscript = '(function(i,s,o,g,r,a,m){i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){' . "\n" .
            '(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),' . "\n" .
            'm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)' . "\n" .
            '})(window,document,"script","' . $this->getAnalyticsScript() . '","' . $this->tracker_name . '");' . "\n" .
            $this->ga_trackers . $ga_insert;

        Requirements::insertHeadTags('<script type="text/javascript">//<![CDATA[' . "\n" . $this->compressGUACode($headerscript) . "\n" . '//]]></script>');
    }

    /**
     * Get the location of the tracking script
     * @param null
     * @return String
     */
    protected function getAnalyticsScript()
    {
        $cache_allowed = Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'cache_analytics_js');
        if ($cache_allowed && !class_exists('GuzzleHttp\Client')) {
            Injector::inst()->get('Logger')
                ->addWarning('Please install Guzzle if you wish to use Analytics-JS caching');
            return '//www.google-analytics.com/analytics.js';
        }
        return $cache_allowed ? Director::baseURL() . '_ga/analytics.js' : '//www.google-analytics.com/analytics.js';
    }

    /*
     * Generate and inject customScript() JavaScript link-tracking code
     */
    protected function genLinkTrackingCode()
    {
        if (
            count($this->tracker_names) == 0 ||
            !Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'track_links')
        ) {
            return false;
        }

        $non_callback_trackers = '';
        $callback_trackers = '';

        foreach ($this->tracker_names as $t) {
            $non_callback_trackers .= $this->tracker_name . '("'. $t .'send","event",c,a,l);';
            $callback_trackers .= $this->tracker_name . '("'. $t .'send","event",c,a,l,{"hitCallback":hb});';
        }

        $js = $this->owner->customise(ArrayData::create(array(
            'GlobalName' => $this->tracker_name,
            'CallbackTrackers' => DBField::create_field('HTMLText', $callback_trackers),
            'NonCallbackTrackers' => DBField::create_field('HTMLText', $non_callback_trackers),
            'LinkCategory' => Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'link_category'),
            'EmailCategory' => Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'email_category'),
            'PhoneCategory' => Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'phone_category'),
            'DownloadsCategory' => Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'downloads_category'),
            'IgnoreClass' => Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'ignore_link_class')
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
            '/(    |\n|\t)/' => '', // soft tabs / new lines
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
        return Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'compress_js')
            ? preg_replace(array_keys($repl), array_values($repl), $data)
            : $data;
    }

    /**
     * Test for deprecated yaml configs when upgrading from 3 -> 4
     */
    private function testForDeprecatedConfigs()
    {
        if (Director::isDev()) {
            $conf = Config::inst()->get('AnalyticsJS', 'tracker');
            if ($conf) {
                Injector::inst()->get('Logger')
                    ->addWarning('Update your "AnalyticsJS" yaml configs to use "Axllent\AnalyticsJS\AnalyticsJS"');
            }
        }
    }
}
