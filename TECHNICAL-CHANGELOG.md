# Technical Changelog
---

## 1.2.2 - WooCommerce Product Loop Compatibility Fix

- Updated WooCommerce product-loop handling in `brs_ga4_gtm_tracking_wc_output_page_events()`.
- Added support for `$wp_query->posts` entries returned as either `WP_Post` objects or numeric post IDs.
- Prevented PHP warnings caused by attempting to access `->ID` on integer values.
- Added validation for invalid or unsupported post values before processing.
- Added a defensive check to skip products when `wc_get_product()` does not return a valid product object.
- Preserved existing GA4 `view_item_list` event behavior and item indexing.

## 1.2.1 - 2026-08-08 - Plugin Update Functionality

### Added
- Added `includes/class-brs-public-github-updater.php`.
- Registered the BRS Public GitHub Updater in the main plugin bootstrap.
- Added the GitHub `Update URI` plugin header.

### Changed
- Updated `.github/workflows/release-plugin.yml`.
- GitHub Releases now use the full `CHANGELOG.md` as release notes.
- Updated Composer configuration/dependencies as required by the current BRS plugin development standard.

## 1.2.0 - 2026-08-07 - Improved content tracking and changelog access

- Added SEO-aware primary taxonomy detection.
  - Rank Math primary terms are checked first.
  - Yoast SEO primary terms are checked second.
  - The deepest assigned hierarchical term is used as a fallback.
- Added validation to ensure an SEO-selected primary term is actually assigned to the post.
- Added `primary_category_source` reporting with `rank_math`, `yoast`, or `fallback` values.
- Added `secondary_categories` containing all directly assigned categories except the selected primary category.
- Added `categories` containing all directly assigned categories in a deterministic, alphabetically sorted list.
- Added `category_root` reporting for the highest-level category in the selected primary category hierarchy.
- Added `category_path` reporting for the full selected category hierarchy, such as `Email > Gmail`.
- Extended taxonomy context handling to WooCommerce product categories.
- Kept the existing `primary_category` field for backwards compatibility.
- Updated the backwards-compatible primary-term helper to use SEO-defined primary terms before hierarchical fallback.
- Removed the redundant `category_child` field from the planned category context.
- Existing Direct GA4 and GTM event handling automatically inherits the expanded content context without requiring separate event-level changes.
- Added a dedicated changelog page in the WordPress admin.
- Added easier changelog access from the Plugins screen.
- Added a return link and fallback notice when changelog content is unavailable.

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
