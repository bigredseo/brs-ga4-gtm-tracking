<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default plugin options.
 */
function brs_ga4_gtm_tracking_get_default_options() {
    return array(
        'tracking_method'              => 'gtm',
        'gtm_container_id'             => '',
        'ga4_measurement_id'           => '',
        'exclude_admins'               => 1,
        'enable_content_context'       => 1,
        'enable_read_tracking'         => 1,
        'enable_click_form_tracking'   => 1,
        'enable_woocommerce_tracking'  => 1,
        'likely_read_scroll_threshold' => 50,
        'likely_read_time_seconds'     => 120,
    );
}

/**
 * Get merged plugin options.
 */
function brs_ga4_gtm_tracking_get_options() {
    $options = get_option( 'brs_ga4_gtm_tracking_options', array() );

    if ( ! is_array( $options ) ) {
        $options = array();
    }

    return array_merge( brs_ga4_gtm_tracking_get_default_options(), $options );
}

/**
 * Get the active tracking method.
 */
function brs_ga4_gtm_tracking_get_method( $options = null ) {
    if ( ! is_array( $options ) ) {
        $options = brs_ga4_gtm_tracking_get_options();
    }

    $method = isset( $options['tracking_method'] ) ? sanitize_key( $options['tracking_method'] ) : 'gtm';

    return in_array( $method, array( 'direct', 'gtm' ), true ) ? $method : 'gtm';
}

/**
 * Clean a GTM container ID.
 */
function brs_ga4_gtm_tracking_clean_gtm_id( $value ) {
    $value = strtoupper( trim( (string) $value ) );
    $value = preg_replace( '/[^A-Z0-9\-]/', '', $value );

    return $value;
}

/**
 * Clean a GA4 measurement ID.
 */
function brs_ga4_gtm_tracking_clean_ga4_id( $value ) {
    $value = strtoupper( trim( (string) $value ) );
    $value = preg_replace( '/[^A-Z0-9\-]/', '', $value );

    return $value;
}

/**
 * Is frontend tracking excluded for the current request/user?
 */
function brs_ga4_gtm_tracking_is_tracking_excluded() {
    if ( is_admin() ) {
        return true;
    }

    $options = brs_ga4_gtm_tracking_get_options();

    if ( ! empty( $options['exclude_admins'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
        return true;
    }

    return false;
}

/**
 * Convert values to a clean positive integer with boundaries.
 */
function brs_ga4_gtm_tracking_clean_int( $value, $default, $min, $max ) {
    $value = absint( $value );

    if ( $value < $min || $value > $max ) {
        return absint( $default );
    }

    return $value;
}

/**
 * Echo a JSON encoded script assignment safely.
 */
function brs_ga4_gtm_tracking_echo_json_assignment( $object_name, $data ) {
    printf(
        '<script>window.%1$s = %2$s;</script>' . "\n",
        esc_js( $object_name ),
        wp_json_encode( $data )
    );
}

/**
 * Get the current absolute URL without fragments.
 */
function brs_ga4_gtm_tracking_get_current_url() {
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    $uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

    if ( empty( $host ) ) {
        return home_url( add_query_arg( null, null ) );
    }

    return esc_url_raw( $scheme . $host . $uri );
}
