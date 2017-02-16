<?php

namespace Axllent\AnalyticsJS;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Cache;
use SilverStripe\Core\Config\Config;

/**
 * Caching controller for Google Analytics
 * If used, the controller will download/cache a local copy
 * of https://www.google-analytics.com/analytics.js and
 * the page tracking JS will point to `_ga/analytics.js`
 *
 * Default cache time = 48 hours
 */
class AnalyticsJSCache_Controller extends Controller
{
    private $ga_script = 'https://www.google-analytics.com/analytics.js';

    public function index($request)
    {
        $seconds_to_cache = Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'cache_hours') * 60 * 60;

        Cache::set_cache_lifetime('analyticsjs', $seconds_to_cache);

        $cache = Cache::factory('analyticsjs');

        if (!$javascript = $cache->load('javascript')) {
            $client = new Client([
                'timeout'  => 5, // seconds
                'headers' => [ // Appear like a web browser
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:51.0) Gecko/20100101 Firefox/51.0',
                    'Accept-Language' => 'en-US,en;q=0.5'
                ]
            ]);

            try {
                $res = $client->request('GET', $this->ga_script);
                $javascript = (string) $res->getBody();
            } catch (RequestException $e) {
                return $this->Redirect($this->ga_script);
            }
            $cache->save($javascript, 'javascript');
        }

        $this->response->addHeader('Content-type', 'application/javascript');
        $this->response->addHeader('Expires', gmdate('D, d M Y H:i:s', time() + $seconds_to_cache) . ' GMT');
        $this->response->addHeader('Pragma', 'cache');
        $this->response->addHeader('Cache-Control', 'max-age=' . $seconds_to_cache);
        $this->response->setBody($javascript);
        return $this->response;
    }
}
