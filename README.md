# Big Red SEO GA4 / GTM Tracking

WordPress tracking plugin by Big Red SEO for Google Analytics 4, Google Tag Manager, and optional WooCommerce ecommerce events.

## Tracking methods

The plugin supports two mutually exclusive tracking methods.

### Direct GA4

Enter a GA4 Measurement ID such as `G-XXXXXXXXXX` and select **Direct GA4**. The plugin loads Google Analytics and sends the supported events directly. Google Tag Manager is not required.

### Google Tag Manager

Enter both a GTM Container ID and GA4 Measurement ID, select **Google Tag Manager**, download the bundled GTM import, import it using Merge mode, test it, and publish the GTM container.

Only one method should be selected. Running this plugin alongside another GA4 pageview implementation may duplicate traffic.

## Settings

Go to:

`Settings > BRS GA4/GTM Tracking`

A **Settings** shortcut is also available on the WordPress Plugins page.

Available settings include:

- Tracking method: Direct GA4 or Google Tag Manager
- GTM Container ID
- GA4 Measurement ID
- Administrator exclusion
- Content grouping and context
- Scroll, time, and likely-read tracking
- Form and click tracking
- WooCommerce ecommerce tracking
- Likely-read scroll and time thresholds

## Main events

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

## WooCommerce events

When WooCommerce is active and tracking is enabled, the plugin supports:

### Standard GA4 ecommerce events

- view_item
- view_item_list
- add_to_cart
- remove_from_cart
- view_cart
- begin_checkout
- add_shipping_info
- add_payment_info
- purchase

### Additional product and checkout interaction events

- product_form_start
- product_option_change
- product_variation_selected
- product_add_to_cart_attempt
- `checkout_form_start`

## Google Tag Manager import

The reusable GTM import is stored at:

`gtm-import/brs-ga4-gtm-complete-tracking-import.json`

It can also be downloaded from the plugin settings page. Import it using **Merge** mode. Use Rename conflicts for a fresh setup or Overwrite conflicts when updating an existing BRS tracking tag.

## Changelogs

- `CHANGELOG.md` contains the condensed user-facing release history.
- `TECHNICAL-CHANGELOG.md` contains implementation details.
- Both are viewable from the plugin settings page through the **Changelog** link on the Plugins page.

## Suggested GA4 custom dimensions

Consider registering these as event-scoped custom dimensions:

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

`content_group` is a standard GA4 parameter.

## Testing

For Direct GA4 mode, use GA4 Realtime and DebugView.

For GTM mode, use GTM Preview plus GA4 Realtime and DebugView. Confirm that the main `page_view` fires only once and that Administrator exclusion behaves as expected.

## Update changelog display

The local Changelog link is always available after the plugin is installed. Displaying release notes inside WordPress's newer-version update popup depends on the update service supplying a `sections.changelog` field in the plugin update metadata. The Markdown files alone cannot populate that remote update popup.
