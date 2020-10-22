# Analytics.JS Yaml Configuration

## Basic (required) configuration:

```yaml
Axllent\AnalyticsJS\AnalyticsJS:
  tracker:
    - ['config', 'UA-1234567-1']
```


## Other options

Other options can be seen below:

```yaml
Axllent\AnalyticsJS\AnalyticsJS:
  compress_js:         true                  # Compress inline JavaScript
  track_links:         true                  # Enable external link / asset GA event tracking
  ignore_link_class:   false                 # Ignore external link tracking for links matching <classname>
  link_category:       "Outgoing Links"      # Outgoing link category name for GA event logging
  email_category:      "Email Links"         # Email link category name for GA event logging
  phone_category:      "Phone Links"         # Phone link category name for GA event logging
  downloads_category:  "Downloads"           # Download link category name for GA event logging
  page_404_category:   "Page Not Found"      # 404 page category name for GA event logging
  page_error_category: "Page Error"          # Error page category (not 404) for GA event logging
  track_in_dev_mode:   true                  # Allow live tracking in dev/staging mode
  primary_gtag_id: "UA-1234567-1"            # Set the default tracking id to be used when loading gtag (defaults to the first tracker)
```
