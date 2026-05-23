# Changelog

## [1.0.2] - 2026-05-22 - Bundled GTM Import Download
### Added
- Add admin-only download link for the bundled GTM import JSON.
- Include GTM import file inside the plugin package.
- Use stable GTM import filename to simplify future updates.

### Fixed
- Update GTM JSON download handler to use the stable bundled import filename.

---

## [1.0.1] - 2026-05-22 - Product Option Change Tracking Guard
### Changed
- Require visitor interaction before product option change events are sent.
- Ignore initialization-only product option changes from WooCommerce/custom product option loading.
- Prevent modal/preloaded product options from flooding GA4 events on page load.

---

## [1.0.0] - 2026-05-22 - Complete GA4 GTM Tracking Package
### Added
- Add plugin settings for GTM Container ID and GA4 Measurement ID.
- Add frontend GTM output with Administrator exclusion.
- Add WordPress content grouping data layer context.
- Add reusable GTM import that reads the GA4 ID from plugin output.
- Add scroll, time, likely-read, contact form, CTA, internal article click, and outbound click tracking.
- Add optional WooCommerce ecommerce tracking for product views, cart actions, checkout steps, purchases, and product form interactions.
