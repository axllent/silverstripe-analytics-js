<?php
namespace Axllent\AnalyticsJS;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

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
    /**
     * Script URL
     *
     * @var string
     */
    private $ga_script = 'https://www.google-analytics.com/analytics.js';

    /**
     * Index
     *
     * @param HTTPRequest $request HTTP request
     *
     * @return HTTPResponse
     */
    public function index($request)
    {
        $cache_allowed = Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'cache_analytics_js');

        if (!$cache_allowed || !class_exists('GuzzleHttp\Client')) {
            return $this->httpError(404);
        }

        $seconds_to_cache = Config::inst()->get('Axllent\AnalyticsJS\AnalyticsJS', 'cache_hours') * 60 * 60;

        $cache = Injector::inst()->get(CacheInterface::class . '.analyticsJs');

        if (!$javascript = $cache->get('javascript')) {
            $client = new \GuzzleHttp\Client([
                'timeout' => 5, // seconds
                'headers' => [ // Appear like a web browser
                    'User-Agent'      => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:51.0) Gecko/20100101 Firefox/51.0',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ],
            ]);

            try {
                $res        = $client->request('GET', $this->ga_script);
                $javascript = (string) $res->getBody();
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                Injector::inst()->get('Logger')
                    ->addWarning('Error fetching ' . $this->ga_script . ': ' . \GuzzleHttp\Psr7\str($e->getResponse()));
                if (Director::isLive()) {
                    return $this->Redirect($this->ga_script);
                } else {
                    return;
                }
            }
            $cache->set('javascript', $javascript, $seconds_to_cache);
        }

        $this->response->addHeader('Content-type', 'application/javascript');
        $this->response->addHeader('Expires', gmdate('D, d M Y H:i:s', time() + $seconds_to_cache) . ' GMT');
        $this->response->addHeader('Pragma', 'cache');
        $this->response->addHeader('Cache-Control', 'max-age=' . $seconds_to_cache);
        $this->response->setBody($javascript);

        return $this->response;
    }
}
