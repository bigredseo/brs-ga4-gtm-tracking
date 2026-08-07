# Changelog

## 1.2.1 - 2026-08-08 - Plugin Update Functionality

### Changed
- Added support for automatic updates from public GitHub releases.
- Updated the plugin release packaging process.

## 1.2.0 - 2026-08-07 - Improved content tracking and changelog access

- Improved category tracking for WordPress posts and WooCommerce products.
- Added support for Rank Math and Yoast SEO primary categories, with automatic fallback when no primary category is set.
- Added tracking for secondary categories and category hierarchy.
- Added a dedicated changelog page and easier access from the Plugins screen.
- Improved changelog navigation and fallback handling.

## [1.1.0] - 2026-07-22 - Direct GA4 or GTM Setup
- Added a choice between Direct GA4 and Google Tag Manager tracking.
- Direct GA4 mode now works with only a GA4 Measurement ID.
- Added a Settings shortcut on the WordPress Plugins page.
- Added Visit Plugin Site and Changelog links on the Plugins page.
- Added an in-admin changelog viewer.
- Added a separate technical changelog for developer details.

## [1.0.2] - 2026-05-22 - GTM Import Download
- Added an admin download button for the bundled GTM import file.
- Included the reusable GTM import inside the plugin package.

## [1.0.1] - 2026-05-22 - Product Option Tracking Fix
- Prevented product option events from firing during initialization.
- Product option changes now require actual visitor interaction.

## [1.0.0] - 2026-05-22 - Initial Release
- Added GA4 and GTM configuration.
- Added WordPress content, engagement, click, form, and WooCommerce tracking.
