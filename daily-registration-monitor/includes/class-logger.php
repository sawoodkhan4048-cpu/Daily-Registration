<?php
/**
 * Simple logger for Daily Registration Monitor.
 *
 * @package Daily_Registration_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRM_Logger {
	/**
	 * Log a message to the debug log and store a small ring buffer in options.
	 *
	 * @param string $message Message.
	 * @param string $level   Level (info|warning|error).
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		$entry = array(
			'time'   => current_time( 'mysql' ),
			'level'  => sanitize_key( $level ),
			'message'=> wp_kses_post( (string) $message ),
		);
		$logs = (array) get_option( 'drm_logs', array() );
		$logs[] = $entry;
		if ( count( $logs ) > 200 ) {
			$logs = array_slice( $logs, -200 );
		}
		update_option( 'drm_logs', $logs, false );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'DRM [' . strtoupper( $entry['level'] ) . ']: ' . $entry['message'] );
		}
	}
}

