<?php
/**
 * Admin page for Daily Registration Monitor.
 *
 * @package Daily_Registration_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page controller.
 */
class DRM_Admin_Page {
	/**
	 * Core plugin instance.
	 *
	 * @var Daily_Registration_Monitor
	 */
	protected $core;

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	protected $menu_slug = 'drm-todays-registrations';

	/**
	 * Constructor.
	 *
	 * @param Daily_Registration_Monitor $core Core plugin instance.
	 */
	public function __construct( $core ) {
		$this->core = $core;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the submenu under Users.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_users_page(
			__( "Today's Registrations", 'daily-registration-monitor' ),
			__( "Today's Registrations", 'daily-registration-monitor' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin styles and scripts for our page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Load assets only on our screen.
		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->id ) ) {
			return;
		}

		if ( 'users_page_' . $this->menu_slug !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'drm-admin-style', DRM_PLUGIN_URL . 'assets/css/admin-style.css', array(), DRM_VERSION );
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'daily-registration-monitor' ) );
		}

		// Nonce for any future actions.
		$nonce_action = 'drm_view_todays_registrations';
		$nonce_field  = 'drm_nonce';
		$nonce        = wp_create_nonce( $nonce_action );

		$users = $this->core->get_todays_users();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( "Today's Registrations", 'daily-registration-monitor' ); ?></h1>
			<form method="post">
				<input type="hidden" name="<?php echo esc_attr( $nonce_field ); ?>" value="<?php echo esc_attr( $nonce ); ?>" />
			</form>

			<?php if ( empty( $users ) ) : ?>
				<p><?php echo esc_html__( 'No users registered today.', 'daily-registration-monitor' ); ?></p>
			<?php else : ?>
				<div class="drm-table-container">
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'User', 'daily-registration-monitor' ); ?></th>
								<th><?php echo esc_html__( 'Email', 'daily-registration-monitor' ); ?></th>
								<th><?php echo esc_html__( 'Registered', 'daily-registration-monitor' ); ?></th>
								<th><?php echo esc_html__( 'BuddyBoss', 'daily-registration-monitor' ); ?></th>
								<th><?php echo esc_html__( 'Verified', 'daily-registration-monitor' ); ?></th>
								<th><?php echo esc_html__( 'WooCommerce', 'daily-registration-monitor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $users as $user ) :
								$data = $this->core->get_user_profile_data( $user->ID );
								$bb_name = trim( ( $data['bp']['first_name'] ?? '' ) . ' ' . ( $data['bp']['last_name'] ?? '' ) );
								$wc_name = trim( ( $data['wc']['billing_first_name'] ?? '' ) . ' ' . ( $data['wc']['billing_last_name'] ?? '' ) );
							?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( get_edit_user_link( (int) $data['user_id'] ) ); ?>"><?php echo esc_html( $data['display_name'] ?: $data['user_login'] ); ?></a></strong>
								</td>
								<td><?php echo esc_html( $data['user_email'] ); ?></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $data['registered'] ) ); ?></td>
								<td><?php echo esc_html( $bb_name ); ?></td>
								<td>
									<?php echo $data['verified'] ? '<span class="drm-badge drm-badge--success">' . esc_html__( 'Verified', 'daily-registration-monitor' ) . '</span>' : '<span class="drm-badge">' . esc_html__( 'Unverified', 'daily-registration-monitor' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</td>
								<td><?php echo esc_html( $wc_name ); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

