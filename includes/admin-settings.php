<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register settings.
 */
function brs_ga4_gtm_tracking_register_settings() {
    register_setting(
        'brs_ga4_gtm_tracking_settings',
        'brs_ga4_gtm_tracking_options',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'brs_ga4_gtm_tracking_sanitize_options',
            'default'           => brs_ga4_gtm_tracking_get_default_options(),
        )
    );
}
add_action( 'admin_init', 'brs_ga4_gtm_tracking_register_settings' );

/**
 * Sanitize settings.
 */
function brs_ga4_gtm_tracking_sanitize_options( $input ) {
    $defaults = brs_ga4_gtm_tracking_get_default_options();
    $input    = is_array( $input ) ? $input : array();

    $output = array();

    $output['gtm_container_id']             = brs_ga4_gtm_tracking_clean_gtm_id( isset( $input['gtm_container_id'] ) ? $input['gtm_container_id'] : '' );
    $output['ga4_measurement_id']           = brs_ga4_gtm_tracking_clean_ga4_id( isset( $input['ga4_measurement_id'] ) ? $input['ga4_measurement_id'] : '' );
    $output['exclude_admins']               = ! empty( $input['exclude_admins'] ) ? 1 : 0;
    $output['enable_content_context']       = ! empty( $input['enable_content_context'] ) ? 1 : 0;
    $output['enable_read_tracking']         = ! empty( $input['enable_read_tracking'] ) ? 1 : 0;
    $output['enable_click_form_tracking']   = ! empty( $input['enable_click_form_tracking'] ) ? 1 : 0;
    $output['enable_woocommerce_tracking']  = ! empty( $input['enable_woocommerce_tracking'] ) ? 1 : 0;
    $output['likely_read_scroll_threshold'] = brs_ga4_gtm_tracking_clean_int( isset( $input['likely_read_scroll_threshold'] ) ? $input['likely_read_scroll_threshold'] : $defaults['likely_read_scroll_threshold'], $defaults['likely_read_scroll_threshold'], 1, 100 );
    $output['likely_read_time_seconds']     = brs_ga4_gtm_tracking_clean_int( isset( $input['likely_read_time_seconds'] ) ? $input['likely_read_time_seconds'] : $defaults['likely_read_time_seconds'], $defaults['likely_read_time_seconds'], 5, 3600 );

    return array_merge( $defaults, $output );
}

/**
 * Add settings page.
 */
function brs_ga4_gtm_tracking_add_settings_page() {
    add_options_page(
        'BRS GA4/GTM Tracking',
        'BRS GA4/GTM Tracking',
        'manage_options',
        'brs-ga4-gtm-tracking',
        'brs_ga4_gtm_tracking_render_settings_page'
    );
}
add_action( 'admin_menu', 'brs_ga4_gtm_tracking_add_settings_page' );

/**
 * Render a checkbox field.
 */
function brs_ga4_gtm_tracking_render_checkbox( $options, $key, $label ) {
    ?>
    <label>
        <input type="checkbox" name="brs_ga4_gtm_tracking_options[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $options[ $key ] ) ); ?>>
        <?php echo esc_html( $label ); ?>
    </label>
    <?php
}

/**
 * Render settings page.
 */
function brs_ga4_gtm_tracking_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = brs_ga4_gtm_tracking_get_options();
    ?>
    <div class="wrap">
        <h1>BRS GA4/GTM Tracking</h1>
        <p>Use this private plugin to load Google Tag Manager, pass WordPress context into the data layer, and support GA4 engagement, form, click, and WooCommerce ecommerce tracking.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'brs_ga4_gtm_tracking_settings' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="brs_gtm_container_id">GTM Container ID</label></th>
                    <td>
                        <input type="text" id="brs_gtm_container_id" class="regular-text" name="brs_ga4_gtm_tracking_options[gtm_container_id]" value="<?php echo esc_attr( $options['gtm_container_id'] ); ?>" placeholder="GTM-XXXXXXX">
                        <p class="description">Example: GTM-W967ZNG</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="brs_ga4_measurement_id">GA4 Measurement ID</label></th>
                    <td>
                        <input type="text" id="brs_ga4_measurement_id" class="regular-text" name="brs_ga4_gtm_tracking_options[ga4_measurement_id]" value="<?php echo esc_attr( $options['ga4_measurement_id'] ); ?>" placeholder="G-XXXXXXXXXX">
                        <p class="description">The GTM import reads this value from the page, so the JSON file does not need to be edited for each client.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">General</th>
                    <td>
                        <?php brs_ga4_gtm_tracking_render_checkbox( $options, 'exclude_admins', 'Do not load tracking for Administrators' ); ?><br>
                        <?php brs_ga4_gtm_tracking_render_checkbox( $options, 'enable_content_context', 'Enable content grouping/context tracking' ); ?><br>
                        <?php brs_ga4_gtm_tracking_render_checkbox( $options, 'enable_read_tracking', 'Enable scroll, time, and likely-read tracking' ); ?><br>
                        <?php brs_ga4_gtm_tracking_render_checkbox( $options, 'enable_click_form_tracking', 'Enable contact form, CTA, internal article, and outbound click tracking' ); ?><br>
                        <?php brs_ga4_gtm_tracking_render_checkbox( $options, 'enable_woocommerce_tracking', 'Enable WooCommerce ecommerce tracking when WooCommerce is active' ); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="brs_likely_read_scroll_threshold">Likely Read Scroll Threshold</label></th>
                    <td>
                        <input type="number" id="brs_likely_read_scroll_threshold" min="1" max="100" name="brs_ga4_gtm_tracking_options[likely_read_scroll_threshold]" value="<?php echo esc_attr( $options['likely_read_scroll_threshold'] ); ?>"> %
                        <p class="description">Default is 50%. This is combined with the time threshold below.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="brs_likely_read_time_seconds">Likely Read Time Threshold</label></th>
                    <td>
                        <input type="number" id="brs_likely_read_time_seconds" min="5" max="3600" name="brs_ga4_gtm_tracking_options[likely_read_time_seconds]" value="<?php echo esc_attr( $options['likely_read_time_seconds'] ); ?>"> seconds
                        <p class="description">Default is 120 seconds. The article_likely_read event fires only after both thresholds are met.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2>Google Tag Manager Import</h2>

        <p>
            Download the bundled GTM import JSON file and import it into Google Tag Manager using Merge mode.
        </p>

        <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brs_ga4_gtm_download_json' ), 'brs_ga4_gtm_download_json' ) ); ?>">
                    Download GTM Import JSON
                </a>
            </p>
        <?php endif; ?>

        <p class="description">
            Recommended GTM import setting: Merge. Use Rename conflicting tags for a fresh setup, or Overwrite conflicting tags when updating an existing BRS tracking tag.
        </p>

        <hr>        

        <h2>Status</h2>
        <table class="widefat striped" style="max-width: 760px;">
            <tbody>
                <tr>
                    <td>WooCommerce detected</td>
                    <td><?php echo class_exists( 'WooCommerce' ) ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td>Tracking excluded for current user</td>
                    <td><?php echo brs_ga4_gtm_tracking_is_tracking_excluded() ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td>GTM will load when both conditions are true</td>
                    <td>GTM Container ID is set and current user is not excluded</td>
                </tr>
                <tr>
                    <td>GA4 events will send when both conditions are true</td>
                    <td>GA4 Measurement ID is set and the matching GTM JSON has been imported/published</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}
