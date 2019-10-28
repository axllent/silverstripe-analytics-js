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

    /**
     * Tracker function name
     *
     * @config
     */
    private static $global_name = 'ga';

    /**
     * Allow live tracking in dev/staging mode
     *
     * @config
     */
    private static $track_in_dev_mode = false;

    /**
     * Compress inline JavaScript
     *
     * @config
     */
    private static $compress_js = true;

    /**
     * Enable external link / asset GA event tracking
     *
     * @config
     */
    private static $track_links = true;

    /**
     * Ignore external link tracking for links matching <class>
     *
     * @config
     */
    private static $ignore_link_class = false;

    /**
     * Outgoing link category name for GA event logging
     *
     * @config
     */
    private static $link_category = 'Outgoing Links';

    /**
     * Email link category name for GA event logging
     *
     * @config
     */
    private static $email_category = 'Email Links';

    /**
     * Phone link category name for GA event logging
     *
     * @config
     */
    private static $phone_category = 'Phone Links';

    /**
     * Download link category name for GA event logging
     *
     * @config
     */
    private static $downloads_category = 'Downloads';

    /**
     * 404 page category name for GA event logging
     *
     * @config
     */
    private static $page_404_category = 'Page Not Found';

    /**
     * Error page category (not 404) for GA event logging
     *
     * @config
     */
    private static $page_error_category = 'Page Error';

    /**
     * Use a local (cached) copy of the analytics.js rather than link to live version
     *
     * @config
     */
    private static $cache_analytics_js = false;

    /**
     * Cache local analytics.js for xx hours
     *
     * @config
     */
    private static $cache_hours = 48;

    /**
     * Tracker config
     *
     * @var array
     */
    protected $tracker_config = [];

    /**
     * Trackers
     *
     * @var mixed
     */
    protected $ga_trackers = false;

    /**
     * Tracker names
     *
     * @var array
     */
    protected $tracker_names = [];

    /**
     * Config
     *
     * @var array
     */
    protected $ga_configs = [];

    /**
     * Counter
     *
     * @var int
     */
    protected $tracker_counter = 1;

    /**
     * Casting
     *
     * @var array
     */
    private static $casting = [
        'CallbackTrackers'    => 'HTMLText',
        'NonCallbackTrackers' => 'HTMLText',
    ];

    /**
     * Automatically initiate the code
     * Injects GA tracking code into <head>
     * Optional: inline code to track downloads & outgoing links
     *
     * @return void
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

    /**
     * Parse configs
     *
     * @return void
     */
    protected function parseAnalyticsConfigs()
    {
        // Check in place for old configs
        $this->_testForDeprecatedConfigs();

        // Set trackers from yaml
        if ($trackers = Config::inst()->get(self::class, 'tracker')) {
            foreach ($trackers as $tracker) {
                array_push($this->tracker_config, $tracker);
            }
        }

        // Return false if no trackers are set
        if (count($this->tracker_config) == 0) {
            return false;
        }

        // Set GA global name, typically "ga"
        $this->tracker_name = Config::inst()->get(self::class, 'global_name');

        $track_in_dev_mode = Config::inst()->get(self::class, 'track_in_dev_mode');

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
                            ->addWarning(
                                'Tracker "' . $ufname . '" already set, please use a unique name'
                            );

                        return false;
                    }

                    return false;
                } else {
                    $this->ga_configs[$ufname] = $conf[1];
                }

                // Replace Tracker IDs with fake ones if not in LIVE mode
                if ($skip_tracking) {
                    $conf[1] = preg_replace(
                        '/[0-9]{4,}-[0-9]+/',
                        'DEV-' . $this->tracker_counter++,
                        $conf[1]
                    );
                }

                array_push($this->tracker_names, $tname);
            }

            foreach ($conf as $i) {
                array_push($args, json_encode($i));
            }

            $this->ga_trackers .= $this->tracker_name . '(' . implode(',', $args) . ');' . "\n";
        }
    }

    /**
     * Generates and inject insertHeadTags() JavaScript code into <head>
     * for tracking if at least one tracking config has been specified
     *
     * @return void
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
            Config::inst()->get(self::class, 'page_404_category')
            : $ErrorCode . Config::inst()->get(self::class, 'page_error_category');

            foreach ($this->tracker_names as $t) {
                $ga_insert .= $this->tracker_name . '("' . $t .
                    'send","event","' . $ecode .
                    '",window.location.pathname+window.location.search,window.referrer);' .
                    "\n";
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

        Requirements::insertHeadTags(
            '<script type="text/javascript">//<![CDATA[' . "\n" .
            $this->compressGUACode($headerscript) . "\n" . '//]]></script>'
        );
    }

    /**
     * Get the location of the tracking script
     *
     * @return string
     */
    protected function getAnalyticsScript()
    {
        $cache_allowed = Config::inst()->get(self::class, 'cache_analytics_js');
        if ($cache_allowed && !class_exists('GuzzleHttp\Client')) {
            Injector::inst()->get('Logger')
                ->addWarning(
                    'Please install Guzzle if you wish to use Analytics-JS caching'
                );

            return 'https://www.google-analytics.com/analytics.js';
        }

        return $cache_allowed
        ? Director::baseURL() . '_ga/analytics.js'
        : 'https://www.google-analytics.com/analytics.js';
    }

    /**
     * Generate and inject customScript() JavaScript link-tracking code
     *
     * @return void
     */
    protected function genLinkTrackingCode()
    {
        if (count($this->tracker_names) == 0
            || !Config::inst()->get(self::class, 'track_links')
        ) {
            return false;
        }

        $non_callback_trackers = '';
        $callback_trackers     = '';

        foreach ($this->tracker_names as $t) {
            $non_callback_trackers .= $this->tracker_name . '("' . $t . 'send","event",c,a,l);';
            $callback_trackers .= $this->tracker_name . '("' . $t . 'send","event",c,a,l,{"hitCallback":hb});';
        }

        $js = $this->owner->customise(
            ArrayData::create(
                [
                    'GlobalName'          => $this->tracker_name,
                    'CallbackTrackers'    => DBField::create_field('HTMLText', $callback_trackers),
                    'NonCallbackTrackers' => DBField::create_field('HTMLText', $non_callback_trackers),
                    'LinkCategory'        => Config::inst()->get(self::class, 'link_category'),
                    'EmailCategory'       => Config::inst()->get(self::class, 'email_category'),
                    'PhoneCategory'       => Config::inst()->get(self::class, 'phone_category'),
                    'DownloadsCategory'   => Config::inst()->get(self::class, 'downloads_category'),
                    'IgnoreClass'         => Config::inst()->get(self::class, 'ignore_link_class'),
                ]
            )
        )->renderWith('OutboundLinkTracking');

        Requirements::customScript($this->compressGUACode($js));
    }

    /**
     * Compress inline JavaScript
     *
     * @param string $data JavaScript code
     *
     * @return string
     */
    protected function compressGUACode($data)
    {
        $repl = [
            '!/\*[^*]*\*+([^/][^*]*\*+)*/!' => '', // Comments
            '/(    |\n|\t)/'                => '', // soft tabs / new lines
            '/\s?=\s?/'                     => '=',
            '/\s?==\s?/'                    => '==',
            '/\s?!=\s?/'                    => '!=',
            '/\s?;\s?/'                     => ';',
            '/\s?:\s?/'                     => ':',
            '/\s?\+\s?/'                    => '+',
            '/\s?\?\s?/'                    => '?',
            '/\s?&&\s?/'                    => '&&',
            '/\s?\(\s?/'                    => '(',
            '/\s?\)\s?/'                    => ')',
            '/\s?\|\s?/'                    => '|',
            '/\s<\s?/'                      => '<',
            '/\s>\s?/'                      => '>',
        ];

        return Config::inst()->get(self::class, 'compress_js')
        ? preg_replace(array_keys($repl), array_values($repl), $data)
        : $data;
    }

    /**
     * Test for deprecated yaml configs when upgrading from 3 -> 4
     *
     * @return void
     */
    private function _testForDeprecatedConfigs()
    {
        if (Director::isDev()) {
            $conf = Config::inst()->get('AnalyticsJS', 'tracker');
            if ($conf) {
                Injector::inst()->get('Logger')
                    ->addWarning(
                        'Update your "AnalyticsJS" yaml configs to use "Axllent\AnalyticsJS\AnalyticsJS"'
                    );
            }
        }
    }
}
