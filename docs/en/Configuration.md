# Analytics.JS Yaml Configuration

## Basic (required) configuration:

```yaml
Axllent\AnalyticsJS\AnalyticsJS:
  tracker:
    - ['create', 'UA-1234567-1', 'auto']
```


## Other options

Other options can be seen below:

```yaml
Axllent\AnalyticsJS\AnalyticsJS:
  global_name:         ga                    # Set a tracker function name (default "ga")
  compress_js:         true                  # Compress inline JavaScript
  track_links:         true                  # Enable external link / asset GA event tracking
  ignore_link_class:   false                 # Ignore external link tracking for links matching <classname>
  link_category:       "Outgoing Links"      # Outgoing link category name for GA event logging
  email_category:      "Email Links"         # Email link category name for GA event logging
  phone_category:      "Phone Links"         # Phone link category name for GA event logging
  downloads_category:  "Downloads"           # Download link category name for GA event logging
  page_404_category:   "Page Not Found"      # 404 page category name for GA event logging
  page_error_category: "Page Error"          # Error page category (not 404) for GA event logging
  cache_analytics_js:  false                 # Cache a local copy of the GA JavaScipt
  cache_hours:         48                    # Link to a local copy of the cached Google `analytics.js`
  track_in_dev_mode:   true                  # Allow live tracking in dev/staging mode
```
