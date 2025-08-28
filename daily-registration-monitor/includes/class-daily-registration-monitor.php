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
	 * Cache lifetime in seconds for transients.
	 *
	 * @var int
	 */
	const CACHE_TTL = 300; // 5 minutes.

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
		// Invalidate cache when users change during the day.
		add_action( 'user_register', array( $this, 'clear_cache' ) );
		add_action( 'profile_update', array( $this, 'clear_cache' ) );
		add_action( 'deleted_user', array( $this, 'clear_cache' ) );
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
		$rows = $this->get_todays_registrations();
		$users = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$user = get_user_by( 'id', (int) $row->ID );
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
		$data    = $this->prepare_user_display_data( $user_id );

		// Maintain backward-compatible shape for admin renderer (bp and wc nested arrays).
		$bp_fields = array(
			'first_name' => isset( $data['first_name'] ) && is_string( $data['first_name'] ) ? $data['first_name'] : '',
			'last_name'  => isset( $data['last_name'] ) && is_string( $data['last_name'] ) ? $data['last_name'] : '',
		);

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
			'display_name' => isset( $data['display_name'] ) ? (string) $data['display_name'] : '',
			'user_email'   => isset( $data['user_email'] ) ? (string) $data['user_email'] : '',
			'user_login'   => isset( $data['user_login'] ) ? (string) $data['user_login'] : '',
			'registered'   => isset( $data['registered'] ) ? (string) $data['registered'] : '',
			'bp'           => $bp_fields,
			'verified'     => ! empty( $data['verified'] ),
			'wc'           => $wc_fields,
		);
	}

	/* ==========================================================
	 * Database and data retrieval methods
	 * ======================================================= */

	/**
	 * Build a date-based transient key suffix for today's cache keys.
	 *
	 * @param string $suffix Key suffix.
	 * @return string
	 */
	protected function build_today_key( $suffix ) {
		$today = current_time( 'Ymd' );
		return 'drm_' . $today . '_' . sanitize_key( (string) $suffix );
	}

	/**
	 * Query users registered today only.
	 *
	 * SQL: SELECT * FROM {$wpdb->users} WHERE DATE(user_registered) = CURDATE() ORDER BY user_registered DESC
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return array Array of stdClass rows from the users table.
	 */
	public function get_todays_registrations() {
		$key  = $this->build_today_key( 'user_rows' );
		$rows = get_transient( $key );
		if ( false !== $rows && is_array( $rows ) ) {
			return $rows;
		}

		global $wpdb;
		$table = $wpdb->users;
		$sql   = "SELECT * FROM {$table} WHERE DATE(user_registered) = CURDATE() ORDER BY user_registered DESC";

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $rows ) {
			$this->log_error( 'Database error in get_todays_registrations: ' . (string) $wpdb->last_error );
			$rows = array();
		}

		set_transient( $key, $rows, self::CACHE_TTL );
		return $rows;
	}

	/**
	 * Get all user meta and BuddyBoss field data for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_meta_data( $user_id ) {
		$user_id   = (int) $user_id;
		$all_meta  = get_user_meta( $user_id );
		$bb_fields = $this->get_buddyboss_profile_data( $user_id );
		$verified  = $this->check_verified_status( $user_id );

		return array(
			'meta'     => is_array( $all_meta ) ? $all_meta : array(),
			'buddyboss' => $bb_fields,
			'verified' => (bool) $verified,
		);
	}

	/**
	 * Get BuddyBoss-specific profile fields and helpful user context.
	 *
	 * - first_name, last_name
	 * - user_email, user_role
	 * - avatar_url (BuddyBoss/BuddyPress avatar if available, else WP avatar)
	 * - member_type (BuddyBoss member type if set)
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_buddyboss_profile_data( $user_id ) {
		$user_id = (int) $user_id;
		$user    = get_user_by( 'id', $user_id );

		$first_name = get_user_meta( $user_id, 'first_name', true );
		$last_name  = get_user_meta( $user_id, 'last_name', true );

		// Override with xProfile data if available.
		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'xprofile' ) && function_exists( 'xprofile_get_field_data' ) ) {
			$x_first = xprofile_get_field_data( 'First Name', $user_id );
			$x_last  = xprofile_get_field_data( 'Last Name', $user_id );
			$first_name = is_string( $x_first ) && '' !== $x_first ? $x_first : $first_name;
			$last_name  = is_string( $x_last ) && '' !== $x_last ? $x_last : $last_name;
		}

		$email = $user instanceof WP_User ? (string) $user->user_email : '';
		$role  = $user instanceof WP_User && ! empty( $user->roles ) ? (string) reset( $user->roles ) : '';

		// Avatar via BuddyBoss/BuddyPress if available; otherwise WP avatar.
		$avatar_url = '';
		if ( function_exists( 'bp_core_fetch_avatar' ) ) {
			$avatar_url = (string) bp_core_fetch_avatar( array(
				'item_id' => $user_id,
				'html'    => false,
				'type'    => 'thumb',
			) );
		}
		if ( '' === $avatar_url ) {
			$avatar_url = (string) get_avatar_url( $user_id );
		}

		// Member type (BuddyBoss/BuddyPress) if set.
		$member_type = '';
		if ( function_exists( 'bp_get_member_type' ) ) {
			$mt = bp_get_member_type( $user_id, false );
			if ( is_array( $mt ) ) {
				$member_type = implode( ',', array_map( 'sanitize_key', $mt ) );
			} elseif ( is_string( $mt ) ) {
				$member_type = sanitize_key( $mt );
			}
		}

		return array(
			'first_name'  => is_string( $first_name ) ? $first_name : '',
			'last_name'   => is_string( $last_name ) ? $last_name : '',
			'user_email'  => $email,
			'user_role'   => $role,
			'avatar_url'  => $avatar_url,
			'member_type' => $member_type,
		);
	}

	/**
	 * Retrieve custom BuddyBoss profile fields and common social/location/bio fields.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_buddyboss_custom_fields( $user_id ) {
		$user_id = (int) $user_id;
		$fields = array(
			'facebook'  => '',
			'twitter'   => '',
			'instagram' => '',
			'location'  => '',
			'bio'       => '',
		);

		// Try xProfile fields by common names.
		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'xprofile' ) && function_exists( 'xprofile_get_field_data' ) ) {
			$map = array(
				'facebook'  => array( 'Facebook', 'facebook', 'Facebook URL' ),
				'twitter'   => array( 'Twitter', 'twitter', 'Twitter URL' ),
				'instagram' => array( 'Instagram', 'instagram', 'Instagram URL' ),
				'location'  => array( 'Location', 'City', 'Country' ),
				'bio'       => array( 'Bio', 'About', 'About Me' ),
			);
			foreach ( $map as $key => $labels ) {
				foreach ( $labels as $label ) {
					$value = xprofile_get_field_data( $label, $user_id );
					if ( is_string( $value ) && '' !== $value ) {
						$fields[ $key ] = $value;
						break;
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Get basic verification details: status, date, and optional level.
	 *
	 * @param int $user_id User ID.
	 * @return array{status:bool,date:string,level:string}
	 */
	public function get_verification_details( $user_id ) {
		$user_id = (int) $user_id;
		$status  = $this->check_verified_status( $user_id );
		$date    = (string) get_user_meta( $user_id, 'verified_date', true );
		$level   = (string) get_user_meta( $user_id, 'verified_level', true );
		return array(
			'status' => (bool) $status,
			'date'   => $date,
			'level'  => $level,
		);
	}

	/**
	 * Attempt to fetch recent BuddyBoss activity entries for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of entries.
	 * @return array Array of associative arrays with 'content' and 'date'.
	 */
	public function get_buddyboss_activity( $user_id, $limit = 5 ) {
		$user_id = (int) $user_id;
		$items = array();
		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'activity' ) && function_exists( 'bp_activity_get' ) ) {
			$args = array(
				'user_id' => $user_id,
				'per_page' => max( 1, (int) $limit ),
				'sort' => 'DESC',
			);
			$result = bp_activity_get( $args );
			if ( is_array( $result ) && ! empty( $result['activities'] ) ) {
				foreach ( $result['activities'] as $act ) {
					$items[] = array(
						'content' => wp_strip_all_tags( (string) ( $act->content ?? '' ) ),
						'date'    => (string) ( $act->date_recorded ?? '' ),
					);
				}
			}
		}
		return $items;
	}

	/**
	 * Unverify a user by clearing common verification meta keys.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function unverify_user( $user_id ) {
		$user_id = (int) $user_id;
		$keys = array( 'bp_verified', 'verified_member', 'is_verified', 'bp_verified_member', 'bb_verified' );
		foreach ( $keys as $key ) {
			delete_user_meta( $user_id, $key );
		}
		delete_user_meta( $user_id, 'verified_date' );
		delete_user_meta( $user_id, 'verified_level' );
		$this->clear_cache( $user_id );
	}

	/**
	 * Check if the user has a Verified Members badge.
	 *
	 * This checks several plausible meta keys used by verified member plugins.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function check_verified_status( $user_id ) {
		$user_id = (int) $user_id;
		$keys    = array( 'bp_verified', 'verified_member', 'is_verified', 'bp_verified_member', 'bb_verified' );
		foreach ( $keys as $key ) {
			$value = get_user_meta( $user_id, $key, true );
			if ( is_string( $value ) ) {
				$value = strtolower( trim( $value ) );
			}
			if ( true === $value || '1' === $value || 1 === $value || 'yes' === $value || 'true' === $value || 'on' === $value ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Prepare normalized, display-ready user data.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function prepare_user_display_data( $user_id ) {
		$user_id = (int) $user_id;
		$key     = $this->build_today_key( 'display_' . $user_id );
		$cached  = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! ( $user instanceof WP_User ) ) {
			return array();
		}

		$bb     = $this->get_buddyboss_profile_data( $user_id );
		$verified = $this->check_verified_status( $user_id );

		$display_name = $user->display_name;
		if ( empty( $display_name ) ) {
			$display_name = trim( ( $bb['first_name'] ?? '' ) . ' ' . ( $bb['last_name'] ?? '' ) );
		}
		$display_name = $display_name ?: $user->user_login;

		$data = array(
			'user_id'      => $user_id,
			'user_login'   => (string) $user->user_login,
			'user_email'   => (string) $user->user_email,
			'display_name' => (string) $display_name,
			'first_name'   => isset( $bb['first_name'] ) ? (string) $bb['first_name'] : '',
			'last_name'    => isset( $bb['last_name'] ) ? (string) $bb['last_name'] : '',
			'avatar_url'   => isset( $bb['avatar_url'] ) ? (string) $bb['avatar_url'] : '',
			'role'         => isset( $bb['user_role'] ) ? (string) $bb['user_role'] : '',
			'member_type'  => isset( $bb['member_type'] ) ? (string) $bb['member_type'] : '',
			'verified'     => (bool) $verified,
			'registered'   => (string) get_the_author_meta( 'user_registered', $user_id ),
			'registered_h' => $this->format_registration_time( get_the_author_meta( 'user_registered', $user_id ) ),
		);

		set_transient( $key, $data, self::CACHE_TTL );
		return $data;
	}

	/* ==========================================================
	 * Utilities and counts
	 * ======================================================= */

	/**
	 * Format a MySQL datetime string into site-localized readable datetime.
	 *
	 * @param string $mysql_datetime MySQL DATETIME string.
	 * @return string
	 */
	public function format_registration_time( $mysql_datetime ) {
		if ( ! is_string( $mysql_datetime ) || '' === $mysql_datetime ) {
			return '';
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return mysql2date( $format, $mysql_datetime );
	}

	/**
	 * Get the count of today's registrations.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return int
	 */
	public function get_registration_count_today() {
		$key   = $this->build_today_key( 'count' );
		$count = get_transient( $key );
		if ( false !== $count && is_numeric( $count ) ) {
			return (int) $count;
		}

		global $wpdb;
		$table = $wpdb->users;
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE DATE(user_registered) = CURDATE()";
		$count = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $count ) {
			$this->log_error( 'Database error in get_registration_count_today: ' . (string) $wpdb->last_error );
			$count = 0;
		}

		set_transient( $key, $count, self::CACHE_TTL );
		return $count;
	}

	/**
	 * Clear cached data for today.
	 *
	 * Optionally clear a specific user's display cache as well.
	 *
	 * @param int $user_id Optional user ID to clear individual display cache.
	 * @return void
	 */
	public function clear_cache( $user_id = 0 ) {
		delete_transient( $this->build_today_key( 'user_rows' ) );
		delete_transient( $this->build_today_key( 'count' ) );
		if ( $user_id ) {
			delete_transient( $this->build_today_key( 'display_' . (int) $user_id ) );
		}
	}

	/**
	 * Internal logger wrapper.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	protected function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'DRM: ' . $message );
		}
	}
}

