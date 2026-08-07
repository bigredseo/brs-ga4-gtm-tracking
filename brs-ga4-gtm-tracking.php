<?php
/**
 * Plugin Name: BRS GA4 GTM Tracking
 * Update URI: https://github.com/bigredseo/brs-ga4-gtm-tracking
 * Plugin URI: https://github.com/bigredseo/brs-ga4-gtm-tracking
 * Description: WordPress plugin by Big Red SEO for direct Google Analytics 4 or Google Tag Manager tracking across WordPress and WooCommerce sites.
 * Version: 1.2.1
 * Author: Big Red SEO
 * Author URI: https://www.bigredseo.com/
 * Text Domain: brs-ga4-gtm-tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BRS_GA4_GTM_TRACKING_VERSION', '1.2.1' );
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
 * Add a changelog link to the plugin row metadata.
 */
function brs_ga4_gtm_tracking_plugin_row_meta( $links, $file ) {
    if ( plugin_basename( __FILE__ ) !== $file ) {
        return $links;
    }

    $changelog_url = admin_url(
        'admin.php?page=brs-ga4-gtm-tracking-changelog'
    );

    $links[] = sprintf(
        '<a href="%1$s">%2$s</a>',
        esc_url( $changelog_url ),
        esc_html__( 'Changelog', 'brs-ga4-gtm-tracking' )
    );

    return $links;
}
add_filter(
    'plugin_row_meta',
    'brs_ga4_gtm_tracking_plugin_row_meta',
    10,
    2
);

/**
 * Register a hidden admin page for the user-facing changelog.
 */
function brs_ga4_gtm_tracking_register_changelog_page() {
    add_submenu_page(
        null,
        __( 'BRS GA4 GTM Tracking Changelog', 'brs-ga4-gtm-tracking' ),
        __( 'Changelog', 'brs-ga4-gtm-tracking' ),
        'manage_options',
        'brs-ga4-gtm-tracking-changelog',
        'brs_ga4_gtm_tracking_render_changelog_page'
    );
}
add_action(
    'admin_menu',
    'brs_ga4_gtm_tracking_register_changelog_page'
);

/**
 * Render the user-facing plugin changelog.
 */
function brs_ga4_gtm_tracking_render_changelog_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            esc_html__(
                'You do not have permission to view this page.',
                'brs-ga4-gtm-tracking'
            )
        );
    }

    $changelog_file = BRS_GA4_GTM_TRACKING_DIR . 'CHANGELOG.md';
    ?>
    <div class="wrap">
        <h1>
            <?php esc_html_e(
                'BRS GA4 GTM Tracking Changelog',
                'brs-ga4-gtm-tracking'
            ); ?>
        </h1>

        <p>
            <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
                &larr;
                <?php esc_html_e(
                    'Back to Plugins',
                    'brs-ga4-gtm-tracking'
                ); ?>
            </a>
        </p>

        <?php if ( is_readable( $changelog_file ) ) : ?>
            <pre style="max-width: 1000px; padding: 20px; overflow: auto; white-space: pre-wrap; background: #fff; border: 1px solid #c3c4c7;"><?php
                echo esc_html( file_get_contents( $changelog_file ) );
            ?></pre>
        <?php else : ?>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e(
                        'The CHANGELOG.md file could not be found.',
                        'brs-ga4-gtm-tracking'
                    ); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

if ( ! class_exists( 'BRS_Public_GitHub_Updater', false ) ) {
	require_once plugin_dir_path( __FILE__ )
		. 'includes/class-brs-public-github-updater.php';
}

if ( class_exists( 'BRS_Public_GitHub_Updater', false ) ) {
	BRS_Public_GitHub_Updater::register(
		array(
			'plugin_file'  => __FILE__,
			'owner'        => 'bigredseo',
			'repository'   => 'brs-ga4-gtm-tracking',
			'asset_name'   => 'brs-ga4-gtm-tracking-{version}.zip',
			'slug'         => 'brs-ga4-gtm-tracking',
			'name'         => 'BRS GA4 GTM Tracking',
			'description'  => '<p>WordPress plugin by Big Red SEO for loading Google Tag Manager and supporting GA4 tracking across WordPress and WooCommerce sites.</p>',
			'author'       => 'Big Red SEO',
			'homepage'     => '',
			'requires_php' => '',
			'requires_wp'  => '',
			'tested_wp'    => '',
		)
	);
}
