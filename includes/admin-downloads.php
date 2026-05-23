<?php
/**
 * Handles admin-only downloads for the BRS GA4/GTM Tracking plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Download the bundled GTM import JSON file.
 */
function brs_ga4_gtm_download_json() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to download this file.', 'brs-ga4-gtm-tracking' ) );
    }

    check_admin_referer( 'brs_ga4_gtm_download_json' );

    $file_path = BRS_GA4_GTM_TRACKING_DIR . 'gtm-import/brs-ga4-gtm-complete-tracking-import.json';

    if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
        wp_die(
            esc_html__(
                'The GTM import file could not be found or is not readable.',
                'brs-ga4-gtm-tracking'
            )
        );
    }

    nocache_headers();

    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="brs-ga4-gtm-complete-tracking-import.json"' );
    header( 'Content-Length: ' . filesize( $file_path ) );

    readfile( $file_path );
    exit;
}
add_action( 'admin_post_brs_ga4_gtm_download_json', 'brs_ga4_gtm_download_json' );