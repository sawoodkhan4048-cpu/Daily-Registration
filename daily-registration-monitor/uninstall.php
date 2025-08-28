<?php
/**
 * Uninstall cleanup for Daily Registration Monitor.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove options and transients.
delete_option( 'drm_settings' );
delete_option( 'drm_logs' );

// Best-effort: clear today-based transients (prefix drm_ + date) is not predictable, so flush common keys.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%drm_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%drm_%'" );

// Unschedule cron event.
$timestamp = wp_next_scheduled( 'drm_daily_summary' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'drm_daily_summary' );
}

