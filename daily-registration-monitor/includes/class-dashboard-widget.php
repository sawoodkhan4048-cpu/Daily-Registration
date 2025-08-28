<?php
/**
 * Dashboard widget for Daily Registration Monitor.
 *
 * @package Daily_Registration_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders a dashboard widget with today's registration stats.
 */
class DRM_Dashboard_Widget {
	/**
	 * Core plugin instance.
	 *
	 * @var Daily_Registration_Monitor
	 */
	protected $core;

	/**
	 * Constructor.
	 *
	 * @param Daily_Registration_Monitor $core Core instance.
	 */
	public function __construct( $core ) {
		$this->core = $core;
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Register widget.
	 *
	 * @return void
	 */
	public function register_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'drm_dashboard_widget', __( "Today's Registrations", 'daily-registration-monitor' ), array( $this, 'render_widget' ) );
	}

	/**
	 * Render widget content.
	 *
	 * @return void
	 */
	public function render_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count_today   = (int) $this->core->get_registration_count_today();
		$total_members = (int) count_users()['total_users'];
		// Rough verified total count by meta keys.
		$verified_total = $this->count_verified_members();

		$rows = $this->core->get_todays_registrations();
		$recent = array_slice( (array) $rows, 0, 5 );

		echo '<div class="drm-dashboard-widget">';
		echo '<p><strong>' . esc_html( $count_today ) . '</strong> ' . esc_html__( 'members registered today', 'daily-registration-monitor' ) . '</p>';
		echo '<p>' . esc_html__( 'Total members:', 'daily-registration-monitor' ) . ' ' . esc_html( number_format_i18n( $total_members ) ) . ' · ' . esc_html__( 'Verified:', 'daily-registration-monitor' ) . ' ' . esc_html( number_format_i18n( $verified_total ) ) . '</p>';

		if ( ! empty( $recent ) ) {
			echo '<ul class="drm-recent-list">';
			foreach ( $recent as $row ) {
				$user_id = (int) $row->ID;
				$data    = $this->core->prepare_user_display_data( $user_id );
				$link    = function_exists( 'bp_core_get_user_domain' ) ? bp_core_get_user_domain( $user_id ) : get_edit_user_link( $user_id );
				echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $data['display_name'] ?? '' ) . '</a></li>';
			}
			echo '</ul>';
		}

		$admin_url = add_query_arg( array( 'page' => 'drm-todays-registrations' ), admin_url( 'users.php' ) );
		echo '<p><a class="button" href="' . esc_url( $admin_url ) . '">' . esc_html__( 'View Today’s Registrations', 'daily-registration-monitor' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Count verified members using common meta keys.
	 *
	 * @return int
	 */
	protected function count_verified_members() {
		$meta_keys = array( 'bp_verified', 'verified_member', 'is_verified', 'bp_verified_member', 'bb_verified' );
		$args = array(
			'fields'     => 'ID',
			'number'     => 1,
			'count_total'=> true,
			'meta_query' => array(
				'relation' => 'OR',
			),
		);
		foreach ( $meta_keys as $k ) {
			$args['meta_query'][] = array(
				'key'   => $k,
				'value' => array( '1', 1, true, 'yes', 'on', 'true' ),
				'compare' => 'IN',
			);
		}
		$q = new WP_User_Query( $args );
		return (int) $q->get_total();
	}
}

