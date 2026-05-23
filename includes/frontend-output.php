<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Output frontend config and content context before GTM loads.
 */
function brs_ga4_gtm_tracking_output_frontend_config() {
    if ( brs_ga4_gtm_tracking_is_tracking_excluded() ) {
        return;
    }

    $options = brs_ga4_gtm_tracking_get_options();

    $ga4_id = brs_ga4_gtm_tracking_clean_ga4_id( $options['ga4_measurement_id'] );

    if ( empty( $ga4_id ) ) {
        return;
    }

    $content_context = brs_ga4_gtm_tracking_get_content_context();

    $config = array(
        'ga4_measurement_id' => $ga4_id,
        'site_url'           => home_url(),
        'current_url'        => brs_ga4_gtm_tracking_get_current_url(),
        'content_context'    => $content_context,
        'features'           => array(
            'content_context'      => ! empty( $options['enable_content_context'] ),
            'read_tracking'        => ! empty( $options['enable_read_tracking'] ),
            'click_form_tracking'  => ! empty( $options['enable_click_form_tracking'] ),
            'woocommerce_tracking' => ! empty( $options['enable_woocommerce_tracking'] ) && class_exists( 'WooCommerce' ),
        ),
        'likely_read'        => array(
            'scroll_threshold' => brs_ga4_gtm_tracking_clean_int( $options['likely_read_scroll_threshold'], 50, 1, 100 ),
            'time_seconds'     => brs_ga4_gtm_tracking_clean_int( $options['likely_read_time_seconds'], 120, 5, 3600 ),
        ),
    );
    ?>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.BRS_GA4_GTM_TRACKING = <?php echo wp_json_encode( $config ); ?>;
        window.dataLayer.push(Object.assign({ event: 'brs_page_context' }, window.BRS_GA4_GTM_TRACKING.content_context || {}));
    </script>
    <?php
}
add_action( 'wp_head', 'brs_ga4_gtm_tracking_output_frontend_config', 0 );

/**
 * Output the GTM head script.
 */
function brs_ga4_gtm_tracking_output_gtm_head() {
    if ( brs_ga4_gtm_tracking_is_tracking_excluded() ) {
        return;
    }

    $options = brs_ga4_gtm_tracking_get_options();
    $gtm_id  = brs_ga4_gtm_tracking_clean_gtm_id( $options['gtm_container_id'] );

    if ( empty( $gtm_id ) ) {
        return;
    }
    ?>
    <!-- Google Tag Manager - BRS GA4 GTM Tracking -->
    <script>
    (function(w,d,s,l,i){
        w[l]=w[l]||[];
        w[l].push({'gtm.start': new Date().getTime(), event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),
            dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;
        j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
        f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');
    </script>
    <!-- End Google Tag Manager - BRS GA4 GTM Tracking -->
    <?php
}
add_action( 'wp_head', 'brs_ga4_gtm_tracking_output_gtm_head', 1 );

/**
 * Output the GTM noscript iframe after the opening body tag.
 */
function brs_ga4_gtm_tracking_output_gtm_body() {
    if ( brs_ga4_gtm_tracking_is_tracking_excluded() ) {
        return;
    }

    $options = brs_ga4_gtm_tracking_get_options();
    $gtm_id  = brs_ga4_gtm_tracking_clean_gtm_id( $options['gtm_container_id'] );

    if ( empty( $gtm_id ) ) {
        return;
    }
    ?>
    <!-- Google Tag Manager (noscript) - BRS GA4 GTM Tracking -->
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
                height="0"
                width="0"
                style="display:none;visibility:hidden"></iframe>
    </noscript>
    <!-- End Google Tag Manager (noscript) - BRS GA4 GTM Tracking -->
    <?php
}
add_action( 'wp_body_open', 'brs_ga4_gtm_tracking_output_gtm_body', 1 );
