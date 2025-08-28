<?php
/**
 * Notifications handler for Daily Registration Monitor.
 *
 * @package Daily_Registration_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRM_Notifications {
	/** @var Daily_Registration_Monitor */
	protected $core;

	public function __construct( $core ) {
		$this->core = $core;
		add_action( 'user_register', array( $this, 'maybe_notify_new_registration' ), 20, 1 );
		add_action( 'drm_daily_summary', array( $this, 'send_daily_summary_email' ) );
	}

	/**
	 * Schedule daily summary event.
	 */
	public static function schedule_daily_event() {
		if ( ! wp_next_scheduled( 'drm_daily_summary' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 00:05:00' ), 'daily', 'drm_daily_summary' );
		}
	}

	/**
	 * Clear scheduled event.
	 */
	public static function clear_scheduled_event() {
		$timestamp = wp_next_scheduled( 'drm_daily_summary' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'drm_daily_summary' );
		}
	}

	/**
	 * Notify on new registration if enabled.
	 *
	 * @param int $user_id
	 */
	public function maybe_notify_new_registration( $user_id ) {
		$settings = $this->get_settings();
		if ( empty( $settings['email_enabled'] ) ) {
			return;
		}
		$recipients = $this->split_recipients( (string) $settings['email_recipients'] );
		if ( empty( $recipients ) ) {
			return;
		}
		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$subject = sprintf( __( '[DRM] New registration: %s', 'daily-registration-monitor' ), $user->user_login );
		$body    = sprintf( __( 'A new user has registered: %1$s (%2$s)', 'daily-registration-monitor' ), $user->display_name, $user->user_email );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		foreach ( $recipients as $to ) {
			wp_mail( $to, $subject, $body, $headers );
		}

		$this->maybe_send_sms( $user );
	}

	/**
	 * Send daily summary email if enabled.
	 */
	public function send_daily_summary_email() {
		$settings = $this->get_settings();
		if ( empty( $settings['daily_summary'] ) ) {
			return;
		}
		$recipients = $this->split_recipients( (string) $settings['email_recipients'] );
		if ( empty( $recipients ) ) {
			return;
		}

		$count = (int) $this->core->get_registration_count_today();
		$subject = __( '[DRM] Daily Registration Summary', 'daily-registration-monitor' );
		$body    = sprintf( __( 'Today\'s registration count: %d', 'daily-registration-monitor' ), $count );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		foreach ( $recipients as $to ) {
			wp_mail( $to, $subject, $body, $headers );
		}
	}

	/**
	 * Option helpers
	 */
	protected function get_settings() {
		return function_exists( 'DRM_Settings_Page' ) ? get_option( DRM_Settings_Page::OPTION, array() ) : get_option( 'drm_settings', array() );
	}

	protected function split_recipients( $s ) {
		$parts = array_filter( array_map( 'trim', explode( ',', (string) $s ) ) );
		return array_values( array_filter( $parts, 'is_email' ) );
	}

	/**
	 * Send SMS via Twilio if configured.
	 *
	 * @param WP_User $user Newly registered user.
	 */
	protected function maybe_send_sms( $user ) {
		$settings = $this->get_settings();
		if ( empty( $settings['twilio_enable'] ) ) {
			return;
		}
		$sid   = (string) ( $settings['twilio_sid'] ?? '' );
		$token = (string) ( $settings['twilio_token'] ?? '' );
		$from  = (string) ( $settings['twilio_from'] ?? '' );
		$to_s  = (string) ( $settings['sms_recipients'] ?? '' );
		$numbers = array_filter( array_map( 'trim', explode( ',', $to_s ) ) );
		if ( '' === $sid || '' === $token || '' === $from || empty( $numbers ) ) {
			return;
		}

		$message = sprintf( 'New user: %s (%s)', $user->display_name, $user->user_email );
		foreach ( $numbers as $to ) {
			$this->twilio_send_sms( $sid, $token, $from, $to, $message );
		}
	}

	/**
	 * Minimal Twilio SMS sender via REST API.
	 *
	 * @param string $sid
	 * @param string $token
	 * @param string $from
	 * @param string $to
	 * @param string $message
	 */
	protected function twilio_send_sms( $sid, $token, $from, $to, $message ) {
		$url  = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json';
		$args = array(
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ) ),
			'body'    => array( 'From' => $from, 'To' => $to, 'Body' => $message ),
			'timeout' => 15,
		);
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'DRM Twilio error: ' . $response->get_error_message() );
		}
	}
}

