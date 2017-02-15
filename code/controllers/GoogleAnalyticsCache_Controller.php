<?php
/**
 * Caching controller for Google Analytics
 * If used, the controller will download/cache a local copy
 * of https://www.google-analytics.com/analytics.js and
 * the page tracking JS will point to `_ga/analytics.js`
 *
 * Default cache time = 48 hours
 */
class GoogleAnalyticsCache_Controller extends Controller
{
    public function index($request)
    {
        $seconds_to_cache = Config::inst()->get('AnalyticsJS', 'cache_hours') * 60 * 60;

        $url = 'https://www.google-analytics.com/analytics.js';
        $req = new RestfulService($url, $seconds_to_cache);
        $conn = $req->request();
        $js = @$conn->getBody();

        // if caching error, redirect to official script
        if (strlen($js) < 500) {
            return $this->Redirect('https://www.google-analytics.com/analytics.js');
        }

        $this->response->addHeader('Content-type', 'application/javascript');
        $this->response->addHeader('Expires', gmdate('D, d M Y H:i:s', time() + $seconds_to_cache) . ' GMT');
        $this->response->addHeader('Pragma', 'cache');
        $this->response->addHeader('Cache-Control', 'max-age=' . $seconds_to_cache);
        $this->response->setBody($js);
        return $this->response;
    }
}
