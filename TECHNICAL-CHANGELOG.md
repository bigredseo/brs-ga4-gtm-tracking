# Technical Changelog

## [1.1.0] - 2026-07-22 - Direct GA4 or GTM Setup
### Added
- Added the `tracking_method` option with supported values of `direct` and `gtm`.
- Added `brs_ga4_gtm_tracking_get_method()` to normalize the selected tracking method.
- Extracted the complete custom HTML tracking engine from the bundled GTM container into `assets/js/brs-ga4-direct-tracking.js`.
- Added direct-mode script enqueueing when a valid GA4 Measurement ID is configured.
- Added plugin action and row-meta filters for Settings, Visit Plugin Site, and Changelog links.
- Added condensed and technical changelog display to the settings screen.

### Changed
- Existing installations default to GTM mode for backward compatibility.
- GTM head and body output now run only when GTM mode is selected.
- Frontend configuration now includes the selected tracking method.
- Updated plugin description, metadata, documentation, and version constants.

### Notes
- Direct mode and GTM mode use the same tracking engine and event definitions.
- Only one mode runs at a time to prevent duplicate GA4 pageviews and events.
- The bundled GTM JSON remains available for sites that prefer GTM.

## [1.0.2] - 2026-05-22 - Bundled GTM Import Download
### Added
- Added an admin-only download link for the bundled GTM import JSON.
- Included the GTM import file inside the plugin package.
- Used a stable GTM import filename to simplify future updates.

### Fixed
- Updated the GTM JSON download handler to use the stable bundled import filename.

## [1.0.1] - 2026-05-22 - Product Option Change Tracking Guard
### Changed
- Required visitor interaction before product option change events are sent.
- Ignored initialization-only product option changes from WooCommerce and custom product-option loading.
- Prevented modal or preloaded product options from flooding GA4 events on page load.

## [1.0.0] - 2026-05-22 - Complete GA4 GTM Tracking Package
### Added
- Added settings for GTM Container ID and GA4 Measurement ID.
- Added frontend GTM output with Administrator exclusion.
- Added WordPress content grouping data-layer context.
- Added a reusable GTM import that reads the GA4 ID from plugin output.
- Added scroll, time, likely-read, contact form, CTA, internal article click, and outbound click tracking.
- Added optional WooCommerce ecommerce tracking.
