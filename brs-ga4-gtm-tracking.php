<?php
/**
 * Plugin Name: BRS GA4 GTM Tracking
 * Plugin URI: https://github.com/bigredseo/brs-ga4-gtm-tracking
 * Description: WordPress plugin by Big Red SEO for direct Google Analytics 4 or Google Tag Manager tracking across WordPress and WooCommerce sites.
 * Version: 1.1.0
 * Author: Big Red SEO
 * Author URI: https://www.bigredseo.com/
 * Text Domain: brs-ga4-gtm-tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BRS_GA4_GTM_TRACKING_VERSION', '1.1.0' );
define( 'BRS_GA4_GTM_TRACKING_FILE', __FILE__ );
define( 'BRS_GA4_GTM_TRACKING_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRS_GA4_GTM_TRACKING_URL', plugin_dir_url( __FILE__ ) );

require_once BRS_GA4_GTM_TRACKING_DIR . 'includes/helpers.php';
require_once BRS_GA4_GTM_TRACKING_DIR . 'includes/content-context.php';
require_once BRS_GA4_GTM_TRACKING_DIR . 'includes/frontend-output.php';

if ( is_admin() ) {
    require_once BRS_GA4_GTM_TRACKING_DIR . 'includes/admin-settings.php';
    require_once BRS_GA4_GTM_TRACKING_DIR . 'includes/admin-downloads.php';
}

/**
 * Load WooCommerce tracking only when WooCommerce is active and the feature is enabled.
 */
function brs_ga4_gtm_tracking_maybe_load_woocommerce() {
    $options = brs_ga4_gtm_tracking_get_options();

    if ( empty( $options['enable_woocommerce_tracking'] ) ) {
        return;
    }

    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    require_once BRS_GA4_GTM_TRACKING_DIR . 'includes/woocommerce-tracking.php';
}
add_action( 'plugins_loaded', 'brs_ga4_gtm_tracking_maybe_load_woocommerce', 20 );

/**
 * Add default options on activation.
 */
function brs_ga4_gtm_tracking_activate() {
    $defaults = brs_ga4_gtm_tracking_get_default_options();
    $existing = get_option( 'brs_ga4_gtm_tracking_options' );

    if ( ! is_array( $existing ) ) {
        add_option( 'brs_ga4_gtm_tracking_options', $defaults );
        return;
    }

    update_option( 'brs_ga4_gtm_tracking_options', array_merge( $defaults, $existing ) );
}
register_activation_hook( __FILE__, 'brs_ga4_gtm_tracking_activate' );


/**
 * Add a Settings shortcut to the plugin action links.
 */
function brs_ga4_gtm_tracking_plugin_action_links( $links ) {
    $settings_url = admin_url( 'options-general.php?page=brs-ga4-gtm-tracking' );

    array_unshift(
        $links,
        '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'brs-ga4-gtm-tracking' ) . '</a>'
    );

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'brs_ga4_gtm_tracking_plugin_action_links' );

/**
 * Add plugin site and changelog links to the plugin row metadata.
 */
function brs_ga4_gtm_tracking_plugin_row_meta( $links, $file ) {
    if ( plugin_basename( __FILE__ ) !== $file ) {
        return $links;
    }

    $links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=brs-ga4-gtm-tracking#brs-changelog' ) ) . '">' . esc_html__( 'Changelog', 'brs-ga4-gtm-tracking' ) . '</a>';

    return $links;
}
add_filter( 'plugin_row_meta', 'brs_ga4_gtm_tracking_plugin_row_meta', 10, 2 );
