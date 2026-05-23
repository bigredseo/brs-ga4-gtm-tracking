# Big Red SEO GA4 GTM Tracking

WordPress plugin by Big Red SEO for loading Google Tag Manager and supporting Google Analytics 4 tracking across WordPress and WooCommerce sites.

## What this plugin does

This plugin provides a reusable GA4/GTM tracking setup for WordPress websites.

It handles the WordPress-side tracking setup, outputs the needed data layer values, loads the configured Google Tag Manager container, and supports a bundled GTM import file that can be downloaded from the plugin settings screen.

The goal is to keep the GA4/GTM setup reusable across clients without manually editing the GTM JSON import for each site.

## Main features

- Loads the configured Google Tag Manager container on the frontend.
- Excludes Administrators by default.
- Outputs the GA4 Measurement ID to the page so the GTM import can stay reusable across clients.
- Pushes WordPress content context into the data layer.
- Supports content grouping for different page types.
- Supports scroll, time, and likely-read engagement tracking.
- Supports contact form interaction and submission tracking where available.
- Supports CTA, internal link, and outbound link tracking.
- Supports optional WooCommerce ecommerce tracking.
- Does not load WooCommerce-specific hooks or scripts when WooCommerce is inactive or disabled in the plugin settings.
- Includes an admin-only download link for the bundled GTM import JSON.

## WordPress content groups

The plugin can pass WordPress content context into the data layer so GA4 can separate different types of pages.

Supported content groups include:

- Blog Posts
- Static Pages
- Product Archives
- Products
- Ecommerce
- Search
- Error Pages
- Other

Additional context may include:

- content_group
- content_type
- post_type
- content_id
- content_title
- primary_category
- template_type

## Required GTM import

The matching GTM import file is bundled with the plugin.

The active import file should use the stable filename:

`gtm-import/brs-ga4-gtm-complete-tracking-import.json`

After activating the plugin, go to:

`Settings > BRS GA4/GTM Tracking`

Use the "Download GTM Import JSON" button to download the bundled import file.

Import the JSON into the client's Google Tag Manager container.

For a new setup, use:

- Merge
- Rename conflicting tags, triggers, and variables

For updating an existing BRS tracking setup, use:

- Merge
- Overwrite conflicting tags, triggers, and variables

Do not overwrite the entire GTM container unless the container is empty.

## Plugin settings

After activation, go to:

`Settings > BRS GA4/GTM Tracking`

Set:

- GTM Container ID, for example `GTM-XXXXXXX`
- GA4 Measurement ID, for example `G-XXXXXXXXXX`

The JSON import does not need to be edited per client because it reads the GA4 Measurement ID from the plugin output.

## Events tracked

### Base events

- page_view
- brs_content_context
- content_scroll
- engaged_30_seconds
- engaged_60_seconds
- engaged_120_seconds
- engaged_180_seconds
- article_likely_read
- contact_form_start
- contact_form_submit_attempt
- contact_form_submission
- cta_contact_click
- internal_article_click
- outbound_click

### WooCommerce events

These events are only available when WooCommerce is active and WooCommerce tracking is enabled in the plugin settings.

- view_item
- view_item_list
- add_to_cart
- remove_from_cart
- view_cart
- begin_checkout
- add_shipping_info
- add_payment_info
- purchase
- product_form_start
- product_option_change
- product_variation_selected
- product_add_to_cart_attempt
- checkout_form_start

WooCommerce ecommerce events may include product and order details such as:

- Product name
- SKU / item ID
- Product category
- Quantity
- Price
- Cart value
- Order value
- Currency
- Transaction ID

## Product option tracking

Product option and variation tracking is guarded so preloaded WooCommerce options, modal option lists, and default page-load behavior are not counted as visitor interaction.

Product option and variation events should only be sent when the event is marked as user initiated.

This helps prevent custom product option pages from flooding GA4 with false product option change events during page load.

## Suggested GA4 custom dimensions

Register these as event-scoped custom dimensions in GA4:

- content_type
- post_type
- primary_category
- template_type
- likely_read_scroll
- likely_read_time
- product_option_name
- product_option_value
- link_url
- link_text
- form_id
- form_name
- form_plugin

`content_group` is a standard GA4 content grouping parameter.

## Installation steps

1. Upload the plugin to:

   `/wp-content/plugins/brs-ga4-gtm-tracking/`

2. Activate the plugin in WordPress.

3. Go to:

   `Settings > BRS GA4/GTM Tracking`

4. Enter the client's GTM Container ID and GA4 Measurement ID.

5. Download the GTM import JSON from the plugin settings page.

6. Import the JSON into Google Tag Manager.

7. Use GTM Preview mode to test pageviews, engagement events, click/form events, and WooCommerce events if applicable.

8. Publish the GTM workspace after testing.

## Important notes

Disable any other GA4 pageview tracking before publishing this setup.

This may include tracking from:

- Rank Math
- MonsterInsights
- Site Kit
- WooCommerce Google Analytics plugins
- Hard-coded `gtag.js` snippets
- Other direct GA4 tracking plugins

Only one system should send the main GA4 pageview event. Running multiple GA4 pageview tags can double-count traffic.

## Caching and optimization notes

If the site uses a caching or optimization plugin that minifies or combines JavaScript, exclude this plugin's frontend tracking scripts while testing.

For WooCommerce tracking, this file may need to be excluded from JS minification/combine:

`/wp-content/plugins/brs-ga4-gtm-tracking/assets/js/brs-ga4-woocommerce.js`

Minified or cached versions of this script can cause old tracking logic to remain active after updates.

## Testing

Use GTM Preview mode and GA4 DebugView before publishing.

Check that:

- GTM loads correctly.
- GA4 page_view fires once per page load.
- Content context appears in the data layer.
- Scroll and engagement events fire correctly.
- Contact form and click events fire correctly.
- WooCommerce events fire only when WooCommerce is active and enabled.
- Product option events do not fire during page load.
- Product option events only fire after real visitor interaction.

## Development notes

This is a Big Red SEO maintained plugin.

The plugin uses procedural WordPress code and a simple includes-based structure. It is intentionally not organized into separate public/private classes.

The plugin is managed through GitHub for version control, updates, and client-specific forks.