<?php
/**
 * Core class for Daily Registration Monitor.
 *
 * @package Daily_Registration_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Daily_Registration_Monitor {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register integration hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Placeholder for integration hooks with BuddyBoss, Verified Members, WooCommerce if needed.
	}

	/**
	 * Get users registered today.
	 *
	 * Uses a direct, prepared SQL query to ensure performance and correctness.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return array Array of WP_User objects for users registered today.
	 */
	public function get_todays_users() {
		global $wpdb;

		$users_table = $wpdb->users;
		$query       = "SELECT ID FROM {$users_table} WHERE DATE(user_registered) = CURDATE()";
		$user_ids    = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$users = array();
		if ( ! empty( $user_ids ) && is_array( $user_ids ) ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_user_by( 'id', (int) $user_id );
				if ( $user instanceof WP_User ) {
					$users[] = $user;
				}
			}
		}

		return $users;
	}

	/**
	 * Get enriched user meta, including BuddyBoss and WooCommerce fields when available.
	 *
	 * @param int $user_id User ID.
	 * @return array Sanitized user profile data.
	 */
	public function get_user_profile_data( $user_id ) {
		$user_id = (int) $user_id;

		$display_name = get_the_author_meta( 'display_name', $user_id );
		$user_email   = get_the_author_meta( 'user_email', $user_id );
		$user_login   = get_the_author_meta( 'user_login', $user_id );
		$registered   = get_the_author_meta( 'user_registered', $user_id );

		// BuddyBoss/BuddyPress profile fields (xProfile) if available.
		$bp_fields = array();
		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'xprofile' ) && function_exists( 'xprofile_get_field_data' ) ) {
			$first_name = xprofile_get_field_data( 'First Name', $user_id );
			$last_name  = xprofile_get_field_data( 'Last Name', $user_id );
			$bp_fields  = array(
				'first_name' => is_string( $first_name ) ? $first_name : '',
				'last_name'  => is_string( $last_name ) ? $last_name : '',
			);
		}

		// Verified Members for BuddyPress - check a common meta flag if present.
		$verified = false;
		if ( function_exists( 'bp_is_active' ) ) {
			$verified_meta = get_user_meta( $user_id, 'bp_verified', true );
			$verified      = (bool) $verified_meta;
		}

		// WooCommerce customer meta if WC is active.
		$wc_fields = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$billing_first_name = get_user_meta( $user_id, 'billing_first_name', true );
			$billing_last_name  = get_user_meta( $user_id, 'billing_last_name', true );
			$wc_fields          = array(
				'billing_first_name' => is_string( $billing_first_name ) ? $billing_first_name : '',
				'billing_last_name'  => is_string( $billing_last_name ) ? $billing_last_name : '',
			);
		}

		return array(
			'user_id'      => $user_id,
			'display_name' => is_string( $display_name ) ? $display_name : '',
			'user_email'   => is_string( $user_email ) ? $user_email : '',
			'user_login'   => is_string( $user_login ) ? $user_login : '',
			'registered'   => is_string( $registered ) ? $registered : '',
			'bp'           => $bp_fields,
			'verified'     => $verified,
			'wc'           => $wc_fields,
		);
	}
}

