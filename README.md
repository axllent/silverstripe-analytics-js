# Google Universal Analytics-js tracking code for SilverStripe

An extension to add Google **Universal Analytics.js** tracking code (`ga()`) to your SilverStripe templates.

It automatically includes optional unobtrustive Google Analytics **event tracking** for all outbound links & "asset downloads", as well as event tracking for 404 and error pages, email & telephone links.

## Features

- Google Universal Analytics `Analytics.js` code injected automatically into `<head>` of page to prevent JavaScript conflicts due to loading order (if you are using custom `ga()` functions in your other code), as per GA recommendation.
- Automatic `pageview` tracking for all configured accounts, including tracking of 404 & 500 page errors (tracked as "Page Not Found" / "Page Error" events).
- Unobtrustive oubound, download, email & tel: link tracking - monitors all page clicks, rather than on page load (ie: works with links including those generated by Ajax etc on the page after page load).
- Uses Google Analytics `hitCallback` for tracking for outgoing links to register before load (ie: when no link `target` is set) to ensure tracker is successfully run before redirection.
- Tracking codes are automatically changed to `UA-DEV-[1-9]` if `SS_ENVIRONMENT_TYPE` is **not** live, or if page URL matches ?flush= to prevent bad data capture.
- Optionally use a locally cached `analytics.js` instead of the Google-hosted version. For more information please see [docs/Caching.md](docs/Caching.md).

### Event tracking

Additional event tracking is automatically enabled by default for:

- File downloads (all non-image files in the assets folder) are tracked as "**Downloads**".
- Outgoing links are tracked as "**Outgoing Links**".
- Email (`mailto:`) links are tracked as "**Email Links**".
- Phone (`tel:`) links are tracked as "**Phone Links**".

Event category names (eg: "Outgoing Links", "Downloads" etc) can be configured in your yaml config.

**Note:** Event tracking only works with regular (left-or-middle) mouse button clicks (including combinations with Ctrl/Shift/Meta keys). Tracking is bypassed if the user right-clicks on a link and selects an action from the context menu (open in new tab, save as etc...). Unfortunately there is no way around this without disabeling the content menu entirely.

## Requirements

- SilverStripe 4.*
- [SilverStripe 3 branch](https://github.com/axllent/silverstripe-analytics-js/tree/silverstripe3)

## Installation via Composer

You can install it via composer with
```
composer require axllent/silverstripe-analytics-js
```

## Basic usage

Once installed the extension is automatically loaded if you provide at least one tracking account in your config yaml file (eg) `mysite/_config/analytics.yml`

```yaml
Axllent\Analytics\AnalyticsJS:
  tracker:
    - ['create', 'UA-1234567-1', 'auto']
```

The syntax is very similar to the official documentation, so things like secondary trackers or other configurations can be easily added. Please note that secondary trackers must contain a unique `"name"`.

```yaml
Axllent\Analytics\AnalyticsJS:
  tracker:
    - ['create', 'UA-1234567-1', 'auto']  # default account [required]
    - ['create', 'UA-1237654-1', 'auto', {'name':'MyOtherTracker'}] # add secondary tracker
    - ['set', 'forceSSL', true]           # force tracking to use SSL
    - ['require', 'ecommerce', 'ecommerce.js']  # load ecommerce extension
  global_name: 'myGATracker'              # set a different tracker function name (defaults to "ga")
  track_links: false                      # disable external link / asset tracking
  ignore_link_class:  "notrack"           # if "track_links", then ignore external links with the "notrack" class
  compress_js: false                      # do not compress inline JavaScript
  cache_analytics_js:  true               # link to a local copy of the cached Google `analytics.js`
```

Please refer to the `_config/defaults.yml` for all configuration options.

To start live tracking, make sure your website is in `live` mode:

```php
define('SS_ENVIRONMENT_TYPE', 'live');
```
