# Changelog

Notable changes to this project will be documented in this file.

## [3.1.2]

- Switch to new SilverStripe caching

## [3.1.0]

- Add option to use a locally cached Google analytics.js (default off - requires Guzzle)
- Add cache_hours option to set caching period (default: 48 hours)

## [3.0.0]

- - Support for SilverStripe 4
- Updated yaml config (please take note of changes FQCNs!)
- Updated tracker properties to HTMLText required for templating
- Add optional "no tracking" classname for external links

## [2.0.1]

- Add option to use a locally cached Google analytics.js (default off)
- Add cache_hours option to set caching period (default: 48 hours)

## [2.0.0]

Major rewrite of entire module! No changes needed if you're upgrading.

- Yaml configs for event category names for Google Analytics (yaml)
- Use templating system for generating tracking code
- Better JavaScript to cater for Ctrl|Alt|Meta-click combinations
- Use `mousedown` event to fire tracking (except for touchscreen devices) to bypass limitations in Internet Explorer which ignores `click` events with Ctrl|Alt|Meta-click

## [1.1.0]

Unfortunately the previous "silverstripe-analytics.js" folder name was causing some issues upstream (SilverStripe modules site), so I have had to rename the project to "silverstripe-analytics-js". Apologies in advance is this mucks up your composer config (simple fix however).

## [1.0.0]

- Adopt semantic versioning releases
- Release versions
