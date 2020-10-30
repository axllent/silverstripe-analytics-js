<?php
namespace Axllent\AnalyticsJS;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
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
     * The primary tracking id for the gtag script, defaults to first
     *
     * @config
     */
    private static $primary_gtag_id = '';

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
     * Automatically initiate the code
     * Injects GA tracking code into <head>
     * Optional: inline code to track downloads & outgoing links
     *
     * @return void
     */
    public function onAfterInit()
    {
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
        $c = Config::inst();
        // Set trackers from yaml
        if ($trackers = $c->get(self::class, 'tracker')) {
            foreach ($trackers as $tracker) {
                array_push($this->tracker_config, $tracker);
            }
        }

        // Return false if no trackers are set
        if (count($this->tracker_config) == 0) {
            return false;
        }

        $track_in_dev_mode = $c->get(self::class, 'track_in_dev_mode');

        $skip_tracking = (
            (!Director::isLive() && !$track_in_dev_mode) ||
            isset($_GET['flush'])
        ) ? true : false;

        foreach ($this->tracker_config as $conf) {
            $args = [];

            if ($conf[0] == 'config' || $conf[0] == 'create') {
                if (isset($this->ga_configs[$conf[1]])) {
                    if ($this->ga_configs[$conf[1]] != $conf[1]) {
                        user_error(
                            'Tracker "' . $conf[1] . '" already set',
                            E_USER_WARNING
                        );

                        return false;
                    }

                    return false;
                } else {
                    $this->ga_configs[$conf[1]] = $conf[1];
                }

                // Backwards compatibility
                if ($conf[0] == 'create') {
                    $conf[0] = 'config';

                    $extraConfig = [];
                    if (isset($conf[2]) && is_string($conf[2])) {
                        if ($conf[2] != 'auto') {
                            $extraConfig['cookie_domain'] = $conf[2];
                        }

                        unset($conf[2]); // Remove the cookie domain
                    }

                    if (isset($conf[3]) && is_string($conf[3])) {
                        $extraConfig['groups'] = $conf[3];
                        unset($conf[3]);
                    }

                    $conf = array_values($conf); // Re-key the array

                    if (!empty($extraConfig)) {
                        $conf[] = $extraConfig;
                    }

                    user_error(
                        'Tracker "' . $conf[1] . '" automatically migrated, ' .
                        'you should update your tracking config',
                        E_USER_DEPRECATED
                    );
                }

                // Replace Tracker IDs with fake ones if not in LIVE mode
                if ($skip_tracking) {
                    $conf[1] = preg_replace(
                        '/[0-9]{4,}-[0-9]+/',
                        'DEV-' . $this->tracker_counter++,
                        $conf[1]
                    );
                }

                array_push($this->tracker_names, $conf[1]);
            }

            if (!$c->get(self::class, 'primary_gtag_id')) {
                Config::modify()
                    ->set(self::class, 'primary_gtag_id', $this->tracker_names[0]);
            }

            foreach ($conf as $i) {
                array_push($args, json_encode($i));
            }

            $this->ga_trackers .= 'gtag(' . implode(',', $args) . ');' . "\n";
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
        $c = Config::inst();
        if (count($this->tracker_names) == 0) {
            return false;
        }

        $ga_insert = false;

        $ErrorCode = Controller::curr()->ErrorCode;

        if ($ErrorCode) {
            $ecode = ($ErrorCode == 404) ?
            $c->get(self::class, 'page_404_category')
            : $ErrorCode . $c->get(self::class, 'page_error_category');
            // track error as event
            $ga_insert .= 'gtag("event",' .
                'window.location.pathname+window.location.search,{' .
                '"event_category":"' . $ecode . '",' .
                '"event_label":window.referrer});' . "\n";
        }

        Requirements::insertHeadTags(
            '<script async src="https://www.googletagmanager.com/gtag/js?id=' .
            urlencode($c->get(self::class, 'primary_gtag_id')) .
            '"></script>',
            'analyticsjs-gtag'
        );

        $headerscript = "window.dataLayer = window.dataLayer || [];\n" .
        "function gtag(){dataLayer.push(arguments);}\n" .
        "gtag('js',new Date());\n" .
        $this->ga_trackers . $ga_insert;

        Requirements::insertHeadTags(
            '<script type="text/javascript">//<![CDATA[' . "\n" .
            $this->compressGUACode($headerscript) . "\n" . '//]]></script>'
        );
    }

    /**
     * Generate and inject customScript() JavaScript link-tracking code
     *
     * @return void
     */
    protected function genLinkTrackingCode()
    {
        $c = Config::inst();
        if (count($this->tracker_names) == 0
            || !$c->get(self::class, 'track_links')
        ) {
            return false;
        }

        $js = $this->owner->customise(
            ArrayData::create(
                [
                    'LinkCategory'      => $c->get(self::class, 'link_category'),
                    'EmailCategory'     => $c->get(self::class, 'email_category'),
                    'PhoneCategory'     => $c->get(self::class, 'phone_category'),
                    'DownloadsCategory' => $c->get(self::class, 'downloads_category'),
                    'IgnoreClass'       => $c->get(self::class, 'ignore_link_class'),
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
            '!/\*[^*]*\*+([^/][^*]*\*+)*/!' => '', // strip comments
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
}
