<?php
/**
 * Plugin Name: Daily Registration Monitor
 * Description: Displays only today's registered members and integrates with BuddyBoss Platform Pro, Verified Members for BuddyPress, and WooCommerce.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: daily-registration-monitor
 * Domain Path: /languages
 *
 * @package Daily_Registration_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'DRM_VERSION', '1.0.0' );
define( 'DRM_PLUGIN_FILE', __FILE__ );
define( 'DRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin activation callback.
 *
 * @return void
 */
function drm_activate() {
	// Reserved for future use (e.g., scheduled hooks). No DB schema required.
}

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function drm_deactivate() {
	// Reserved for future use (e.g., unschedule hooks).
}

register_activation_hook( DRM_PLUGIN_FILE, 'drm_activate' );
register_deactivation_hook( DRM_PLUGIN_FILE, 'drm_deactivate' );

/**
 * Initialize the plugin: load textdomain, classes, and admin.
 *
 * @return void
 */
function drm_init_plugin() {
	// Load translations.
	load_plugin_textdomain( 'daily-registration-monitor', false, dirname( plugin_basename( DRM_PLUGIN_FILE ) ) . '/languages' );

	// Load core class.
	require_once DRM_PLUGIN_DIR . 'includes/class-daily-registration-monitor.php';

	// Initialize core and expose via global for convenience.
	$GLOBALS['drm_plugin'] = new Daily_Registration_Monitor();

	// Load admin functionality.
	if ( is_admin() ) {
		require_once DRM_PLUGIN_DIR . 'admin/class-admin-page.php';
		new DRM_Admin_Page( $GLOBALS['drm_plugin'] );
	}
}
add_action( 'init', 'drm_init_plugin' );

