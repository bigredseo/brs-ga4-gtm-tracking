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

    $tracking_method = isset( $input['tracking_method'] ) ? sanitize_key( $input['tracking_method'] ) : $defaults['tracking_method'];
    $output['tracking_method'] = in_array( $tracking_method, array( 'direct', 'gtm' ), true ) ? $tracking_method : $defaults['tracking_method'];
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
        <p>Choose direct Google Analytics 4 tracking or Google Tag Manager. Both methods use the same WordPress context, engagement, click, form, and WooCommerce tracking engine.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'brs_ga4_gtm_tracking_settings' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Tracking Method</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="brs_ga4_gtm_tracking_options[tracking_method]" value="direct" <?php checked( 'direct', brs_ga4_gtm_tracking_get_method( $options ) ); ?>>
                                <strong>Direct GA4</strong> - load Google Analytics directly from this plugin. No GTM container is required.
                            </label><br>
                            <label>
                                <input type="radio" name="brs_ga4_gtm_tracking_options[tracking_method]" value="gtm" <?php checked( 'gtm', brs_ga4_gtm_tracking_get_method( $options ) ); ?>>
                                <strong>Google Tag Manager</strong> - load a GTM container and use the bundled GTM import.
                            </label>
                        </fieldset>
                        <p class="description">Use only one method to avoid duplicate pageviews and events.</p>
                    </td>
                </tr>

                <tr class="brs-gtm-setting">
                    <th scope="row"><label for="brs_gtm_container_id">GTM Container ID</label></th>
                    <td>
                        <input type="text" id="brs_gtm_container_id" class="regular-text" name="brs_ga4_gtm_tracking_options[gtm_container_id]" value="<?php echo esc_attr( $options['gtm_container_id'] ); ?>" placeholder="GTM-XXXXXXX">
                        <p class="description">Example: GTM-XXXXXXX</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="brs_ga4_measurement_id">GA4 Measurement ID</label></th>
                    <td>
                        <input type="text" id="brs_ga4_measurement_id" class="regular-text" name="brs_ga4_gtm_tracking_options[ga4_measurement_id]" value="<?php echo esc_attr( $options['ga4_measurement_id'] ); ?>" placeholder="G-XXXXXXXXXX">
                        <p class="description">Required for both methods. In Direct GA4 mode, the plugin loads GA4 itself. In GTM mode, the imported tag reads this value from the page.</p>
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

        <div class="brs-gtm-setting">
        <h2>Google Tag Manager Import</h2>

        <p>Download the bundled GTM import JSON file when Google Tag Manager is selected. Direct GA4 mode does not require this file.</p>

        <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brs_ga4_gtm_download_json' ), 'brs_ga4_gtm_download_json' ) ); ?>">
                    Download GTM Import JSON
                </a>
            </p>
        <?php endif; ?>

        <p class="description">Recommended GTM import setting: Merge. Use Rename conflicting tags for a fresh setup, or Overwrite conflicting tags when updating an existing BRS tracking tag.</p>
        </div>

        <hr>

        <h2>Status</h2>
        <table class="widefat striped" style="max-width: 760px;">
            <tbody>
                <tr><td>Selected tracking method</td><td><?php echo 'direct' === brs_ga4_gtm_tracking_get_method( $options ) ? 'Direct GA4' : 'Google Tag Manager'; ?></td></tr>
                <tr><td>WooCommerce detected</td><td><?php echo class_exists( 'WooCommerce' ) ? 'Yes' : 'No'; ?></td></tr>
                <tr><td>Tracking excluded for current user</td><td><?php echo brs_ga4_gtm_tracking_is_tracking_excluded() ? 'Yes' : 'No'; ?></td></tr>
                <tr><td>GA4 Measurement ID configured</td><td><?php echo ! empty( brs_ga4_gtm_tracking_clean_ga4_id( $options['ga4_measurement_id'] ) ) ? 'Yes' : 'No'; ?></td></tr>
                <tr><td>GTM Container ID configured</td><td><?php echo ! empty( brs_ga4_gtm_tracking_clean_gtm_id( $options['gtm_container_id'] ) ) ? 'Yes' : 'No'; ?></td></tr>
            </tbody>
        </table>

        <script>
        (function() {
            var radios = document.querySelectorAll('input[name="brs_ga4_gtm_tracking_options[tracking_method]"]');
            var gtmRows = document.querySelectorAll('.brs-gtm-setting');

            function updateTrackingMethodFields() {
                var selected = document.querySelector('input[name="brs_ga4_gtm_tracking_options[tracking_method]"]:checked');
                var showGtm = selected && selected.value === 'gtm';

                gtmRows.forEach(function(row) {
                    row.style.display = showGtm ? '' : 'none';
                });
            }

            radios.forEach(function(radio) {
                radio.addEventListener('change', updateTrackingMethodFields);
            });

            updateTrackingMethodFields();
        })();
        </script>
    </div>
    <?php
}
