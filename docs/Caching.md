# Caching Google Analytics' `analytics.js`

The theory behind caching a local copy of `analytics.js` is a faster load time for your website. The
browser connecting to your server normally has to make a separate request to Google's server to fetch
`https://www.google-analytics.com/analytics.js` which can some extra latency if a DNS lookup is required.
Google also set a 2-hour cache limit, meaning a fresh copy of the `analytics.js` will be fetched every
two hours.

When enabled, this module will cache a local copy of the `analytics.js` file for the same duration as
the local cache is set to (default is 48 hours), and serve that copy rather than
`https://www.google-analytics.com/analytics.js`

There is no modification done to the cached copy, so the tracking works exactly the same as if the file
was loaded off the Google servers.

To enable local caching, set the option in your yaml config (eg: `mysite/_config/analytics.yaml`)
```yaml
Axllent\Analytics\AnalyticsJS:
  cache_analytics_js: true     # defalt false
  cache_hours:        48       # default 48
```
