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
	 * Per-page items.
	 *
	 * @var int
	 */
	protected $per_page = 25;

	/**
	 * Constructor.
	 *
	 * @param Daily_Registration_Monitor $core Core plugin instance.
	 */
	public function __construct( $core ) {
		$this->core = $core;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX endpoints.
		add_action( 'wp_ajax_drm_fetch', array( $this, 'ajax_fetch' ) );
		add_action( 'wp_ajax_drm_verify_member', array( $this, 'ajax_verify_member' ) );

		// CSV export via admin-post.
		add_action( 'admin_post_drm_export_csv', array( $this, 'handle_export_csv' ) );
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
		wp_enqueue_script( 'drm-admin-js', DRM_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), DRM_VERSION, true );
		wp_localize_script( 'drm-admin-js', 'DRM_Admin', array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'drm_admin_nonce' ),
			'per_page'   => (int) $this->per_page,
			'i18n'       => array(
				'confirmVerify' => __( 'Verify this member?', 'daily-registration-monitor' ),
				'loading'       => __( 'Loading…', 'daily-registration-monitor' ),
				'noResults'     => __( 'No matching results.', 'daily-registration-monitor' ),
			),
		) );
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

		$today_label = date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) );
		$total_count = (int) $this->core->get_registration_count_today();

		// Initial dataset (server-side) for no-JS fallback.
		$rows = $this->core->get_todays_registrations();
		$search_query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page         = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$filtered     = $this->filter_rows( $rows, $search_query );
		$paginated    = $this->paginate_rows( $filtered, $page, $this->per_page );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( "Today's Registrations", 'daily-registration-monitor' ); ?></h1>
			<span class="drm-today-date">&mdash; <?php echo esc_html( $today_label ); ?></span>
			<hr class="wp-header-end" />

			<div class="drm-header">
				<div class="drm-stats">
					<span class="drm-count"><strong><?php echo (int) $total_count; ?></strong> <?php echo esc_html__( 'members registered today', 'daily-registration-monitor' ); ?></span>
				</div>
				<div class="drm-actions">
					<form class="drm-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="drm_export_csv" />
						<input type="hidden" name="<?php echo esc_attr( $nonce_field ); ?>" value="<?php echo esc_attr( $nonce ); ?>" />
						<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Export All (CSV)', 'daily-registration-monitor' ); ?></button>
					</form>
					<button type="button" class="button button-primary drm-refresh"><?php echo esc_html__( 'Refresh', 'daily-registration-monitor' ); ?></button>
				</div>
			</div>

			<div class="drm-toolbar">
				<form method="get" class="drm-search-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_slug ); ?>" />
					<p class="search-box">
						<label class="screen-reader-text" for="drm-search-input"><?php echo esc_html__( 'Search members:', 'daily-registration-monitor' ); ?></label>
						<input type="search" id="drm-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" />
						<input type="submit" id="search-submit" class="button" value="<?php echo esc_attr__( 'Search', 'daily-registration-monitor' ); ?>">
					</p>
				</form>
			</div>

			<div class="drm-table-container">
				<table class="wp-list-table widefat fixed striped drm-list">
					<thead>
						<tr>
							<th class="column-avatar">&nbsp;</th>
							<th class="column-fullname"><?php echo esc_html__( 'Full Name', 'daily-registration-monitor' ); ?></th>
							<th class="column-username"><?php echo esc_html__( 'Username', 'daily-registration-monitor' ); ?></th>
							<th class="column-email"><?php echo esc_html__( 'Email Address', 'daily-registration-monitor' ); ?></th>
							<th class="column-role"><?php echo esc_html__( 'Role', 'daily-registration-monitor' ); ?></th>
							<th class="column-registered sorted desc"><?php echo esc_html__( 'Registration Time', 'daily-registration-monitor' ); ?></th>
							<th class="column-verified"><?php echo esc_html__( 'Verification Status', 'daily-registration-monitor' ); ?></th>
							<th class="column-actions"><?php echo esc_html__( 'Quick Actions', 'daily-registration-monitor' ); ?></th>
						</tr>
					</thead>
					<tbody id="the-list">
						<?php echo $this->render_table_rows( $paginated['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</tbody>
				</table>
			</div>

			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php echo $this->render_pagination( $page, $paginated['total_pages'], $search_query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<div class="drm-empty-state" <?php echo empty( $paginated['items'] ) ? '' : 'style="display:none"'; ?>>
				<p><?php echo esc_html__( 'No new registrations today', 'daily-registration-monitor' ); ?></p>
				<p><?php echo esc_html__( 'Check back later for new members.', 'daily-registration-monitor' ); ?></p>
			</div>

			<div class="drm-loading" aria-hidden="true" style="display:none;">
				<span class="spinner is-active" style="float:none"></span>
				<span class="drm-loading-text"><?php echo esc_html__( 'Loading…', 'daily-registration-monitor' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Filter rows by a search query.
	 *
	 * @param array  $rows Rows from users table.
	 * @param string $search Search query.
	 * @return array Filtered rows.
	 */
	protected function filter_rows( $rows, $search ) {
		$search = is_string( $search ) ? trim( $search ) : '';
		if ( '' === $search ) {
			return $rows;
		}
		$search_l = mb_strtolower( $search );
		$out = array();
		foreach ( (array) $rows as $row ) {
			$user = get_user_by( 'id', (int) $row->ID );
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}
			$bb   = $this->core->get_buddyboss_profile_data( (int) $row->ID );
			$hay  = mb_strtolower( implode( ' ', array(
				(string) $user->user_login,
				(string) $user->user_email,
				(string) $user->display_name,
				(string) ( $bb['first_name'] ?? '' ),
				(string) ( $bb['last_name'] ?? '' ),
			) ) );
			if ( false !== mb_strpos( $hay, $search_l ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Paginate an array of rows.
	 *
	 * @param array $rows Rows.
	 * @param int   $page Page number.
	 * @param int   $per_page Items per page.
	 * @return array{items: array, total_pages: int}
	 */
	protected function paginate_rows( $rows, $page, $per_page ) {
		$total = count( (array) $rows );
		$total_pages = max( 1, (int) ceil( $total / max( 1, (int) $per_page ) ) );
		$page  = min( max( 1, (int) $page ), $total_pages );
		$offset = ( $page - 1 ) * (int) $per_page;
		$items  = array_slice( (array) $rows, $offset, (int) $per_page );
		return array(
			'items'       => $items,
			'total_pages' => $total_pages,
		);
	}

	/**
	 * Render table rows HTML for a set of user rows.
	 *
	 * @param array $rows Rows.
	 * @return string HTML string.
	 */
	protected function render_table_rows( $rows ) {
		if ( empty( $rows ) ) {
			return '';
		}
		$out = '';
		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row->ID;
			$data    = $this->core->prepare_user_display_data( $user_id );
			$full    = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
			$full    = $full ?: ( $data['display_name'] ?? '' );
			$avatar  = ! empty( $data['avatar_url'] ) ? $data['avatar_url'] : '';
			$profile = function_exists( 'bp_core_get_user_domain' ) ? bp_core_get_user_domain( $user_id ) : get_edit_user_link( $user_id );
			$verified = ! empty( $data['verified'] );
			$role     = isset( $data['role'] ) ? $data['role'] : '';
			$reg_h    = isset( $data['registered_h'] ) ? $data['registered_h'] : '';
			$message_url = '';
			if ( function_exists( 'bp_is_active' ) && bp_is_active( 'messages' ) && function_exists( 'bp_core_get_user_domain' ) ) {
				// BuddyBoss/BuddyPress compose message URL fallback pattern.
				$message_url = trailingslashit( bp_core_get_user_domain( $user_id ) ) . 'messages/compose/';
			}

			$out .= '<tr>';
			$out .= '<td class="column-avatar"><img alt="" src="' . esc_url( $avatar ) . '" class="drm-avatar" /></td>';
			$out .= '<td class="column-fullname"><strong><a href="' . esc_url( $profile ) . '">' . esc_html( $full ) . '</a></strong></td>';
			$out .= '<td class="column-username">' . esc_html( (string) ( $data['user_login'] ?? '' ) ) . '</td>';
			$out .= '<td class="column-email"><a href="mailto:' . esc_attr( (string) ( $data['user_email'] ?? '' ) ) . '">' . esc_html( (string) ( $data['user_email'] ?? '' ) ) . '</a></td>';
			$out .= '<td class="column-role">' . esc_html( (string) $role ) . '</td>';
			$out .= '<td class="column-registered">' . esc_html( (string) $reg_h ) . '</td>';
			$out .= '<td class="column-verified">' . ( $verified ? '<span class="drm-badge drm-badge--success">' . esc_html__( 'Verified', 'daily-registration-monitor' ) . '</span>' : '<span class="drm-badge">' . esc_html__( 'Not Verified', 'daily-registration-monitor' ) . '</span>' ) . '</td>';
			$out .= '<td class="column-actions">';
			$out .= '<a class="button button-small" href="' . esc_url( $profile ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Profile', 'daily-registration-monitor' ) . '</a> ';
			if ( ! $verified ) {
				$out .= '<button type="button" class="button button-small drm-verify" data-user-id="' . (int) $user_id . '">' . esc_html__( 'Verify', 'daily-registration-monitor' ) . '</button> ';
			}
			if ( ! empty( $message_url ) ) {
				$out .= '<a class="button button-small" href="' . esc_url( $message_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Message', 'daily-registration-monitor' ) . '</a> ';
			}
			$out .= '</td>';
			$out .= '</tr>';
		}
		return $out;
	}

	/**
	 * Render pagination HTML similar to wp_list_table.
	 *
	 * @param int    $current Current page.
	 * @param int    $total_pages Total pages.
	 * @param string $search Current search query.
	 * @return string
	 */
	protected function render_pagination( $current, $total_pages, $search ) {
		$current     = max( 1, (int) $current );
		$total_pages = max( 1, (int) $total_pages );
		$base_url = add_query_arg( array(
			'page'  => $this->menu_slug,
			's'     => $search,
		), admin_url( 'users.php' ) );
		$links = '';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$url = add_query_arg( 'paged', $i, $base_url );
			$cls = $i === $current ? ' class="page-numbers current"' : ' class="page-numbers"';
			$links .= '<a' . $cls . ' href="' . esc_url( $url ) . '">' . (int) $i . '</a>';
		}
		return '<span class="displaying-num">' . esc_html( sprintf( _n( '%s item', '%s items', (int) $total_pages, 'daily-registration-monitor' ), number_format_i18n( $total_pages ) ) ) . '</span> ' . $links;
	}

	/**
	 * AJAX: Fetch rows for the table with search and pagination.
	 *
	 * @return void
	 */
	public function ajax_fetch() {
		check_ajax_referer( 'drm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'daily-registration-monitor' ) ), 403 );
		}

		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$page   = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$rows   = $this->core->get_todays_registrations();
		$filtered  = $this->filter_rows( $rows, $search );
		$paginated = $this->paginate_rows( $filtered, $page, $this->per_page );

		$table_rows_html = $this->render_table_rows( $paginated['items'] );
		$pagination_html = $this->render_pagination( $page, $paginated['total_pages'], $search );
		$count_today     = (int) $this->core->get_registration_count_today();
		$date_label      = date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) );

		wp_send_json_success( array(
			'rows_html'       => $table_rows_html,
			'pagination_html' => $pagination_html,
			'count_today'     => $count_today,
			'date_label'      => $date_label,
		) );
	}

	/**
	 * AJAX: Verify member action.
	 *
	 * @return void
	 */
	public function ajax_verify_member() {
		check_ajax_referer( 'drm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'daily-registration-monitor' ) ), 403 );
		}
		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'daily-registration-monitor' ) ), 400 );
		}

		// Set a common verified meta key.
		update_user_meta( $user_id, 'verified_member', '1' );
		// Also set alternative keys for compatibility.
		update_user_meta( $user_id, 'bp_verified', '1' );

		$this->core->clear_cache( $user_id );
		wp_send_json_success( array( 'user_id' => $user_id, 'verified' => true ) );
	}

	/**
	 * Handle CSV export for today's registrations.
	 *
	 * @return void
	 */
	public function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'daily-registration-monitor' ), 403 );
		}
		check_admin_referer( 'drm_view_todays_registrations', 'drm_nonce' );

		$rows = $this->core->get_todays_registrations();
		$filename = 'todays-registrations-' . date_i18n( 'Ymd-His', current_time( 'timestamp' ) ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$fh = fopen( 'php://output', 'w' );
		// Header row.
		fputcsv( $fh, array( 'User ID', 'Full Name', 'Username', 'Email', 'Role', 'Registered', 'Verified' ) );
		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row->ID;
			$data    = $this->core->prepare_user_display_data( $user_id );
			$full    = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
			$full    = $full ?: ( $data['display_name'] ?? '' );
			fputcsv( $fh, array(
				$user_id,
				$full,
				(string) ( $data['user_login'] ?? '' ),
				(string) ( $data['user_email'] ?? '' ),
				(string) ( $data['role'] ?? '' ),
				(string) ( $data['registered_h'] ?? '' ),
				! empty( $data['verified'] ) ? 'Yes' : 'No',
			) );
		}
		fclose( $fh );
		exit;
	}
}

