<?php

namespace Axllent\AnalyticsJS;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
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
        $this->config = Config::inst();

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
        if ($trackers = $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'tracker')) {
            foreach ($trackers as $tracker) {
                array_push($this->tracker_config, $tracker);
            }
        }

        // Return false if no trackers are set
        if (count($this->tracker_config) == 0) {
            return false;
        }

        // Set GA global name, typically "ga"
        $this->global_name = $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'global_name');

        $skip_tracking = (!Director::isLive() || isset($_GET['flush'])) ? true : false;

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
                            ->addError('Tracker "' . $ufname .'" already set, please use a unique name');
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

            $this->ga_trackers .= $this->global_name . '(' . implode(',', $args) .');'."\n";
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
                $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', '404_category')
                : $ErrorCode . $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'error_category');

            foreach ($this->tracker_names as $t) {
                $ga_insert .= $this->global_name . '("' . $t . 'send","event","' . $ecode . '",window.location.pathname+window.location.search,window.referrer);'."\n";
            }
        } else {
            foreach ($this->tracker_names as $t) {
                $ga_insert .= $this->global_name . '("' . $t . 'send","pageview");' . "\n";
            }
        }

        $headerscript = '(function(i,s,o,g,r,a,m){i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){' . "\n" .
            '(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),' . "\n" .
            'm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)' . "\n" .
            '})(window,document,"script","' . $this->getAnalyticsScript() . '","' . $this->global_name . '");' . "\n" .
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
        return $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'cache_analytics_js') ?
            Director::baseURL() . '_ga/analytics.js' :
                '//www.google-analytics.com/analytics.js';
    }

    /*
     * Generate and inject customScript() JavaScript link-tracking code
     */
    protected function genLinkTrackingCode()
    {
        if (
            count($this->tracker_names) == 0 ||
            !$this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'track_links')
        ) {
            return false;
        }

        $non_callback_trackers = '';
        $callback_trackers = '';

        foreach ($this->tracker_names as $t) {
            $non_callback_trackers .= $this->global_name . '("'. $t .'send","event",c,a,l);';
            $callback_trackers .= $this->global_name . '("'. $t .'send","event",c,a,l,{"hitCallback":hb});';
        }

        $js = $this->owner->customise(ArrayData::create(array(
            'GlobalName' => $this->global_name,
            'CallbackTrackers' => DBField::create_field('HTMLText', $callback_trackers),
            'NonCallbackTrackers' => DBField::create_field('HTMLText', $non_callback_trackers),
            'LinkCategory' => $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'link_category'),
            'EmailCategory' => $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'email_category'),
            'PhoneCategory' => $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'phone_category'),
            'DownloadsCategory' => $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'downloads_category'),
            'IgnoreClass' => $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'ignore_link_class')
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
        return $this->config->get('Axllent\AnalyticsJS\AnalyticsJS', 'compress_js')
            ? preg_replace(array_keys($repl), array_values($repl), $data)
            : $data;
    }

    /**
     * Test for deprecated yaml configs when upgrading from 3 -> 4
     */
    private function testForDeprecatedConfigs()
    {
        if (Director::isDev()) {
            $conf = $this->config->get('AnalyticsJS', 'tracker');
            if ($conf) {
                Injector::inst()->get('Logger')
                    ->addError('Update your "AnalyticsJS" yaml configs to use "Axllent\AnalyticsJS\AnalyticsJS"');
            }
        }
    }
}
