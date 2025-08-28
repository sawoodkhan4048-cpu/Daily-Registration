<?php
/**
 * Settings page for Daily Registration Monitor.
 *
 * @package Daily_Registration_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRM_Settings_Page {
	/**
	 * Option name for plugin settings array.
	 *
	 * @var string
	 */
	const OPTION = 'drm_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register options page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Daily Registration Monitor', 'daily-registration-monitor' ),
			__( 'Daily Registration Monitor', 'daily-registration-monitor' ),
			'manage_options',
			'drm-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'drm_settings_group', self::OPTION, array( $this, 'sanitize_settings' ) );

		add_settings_section( 'drm_general', __( 'General', 'daily-registration-monitor' ), '__return_false', 'drm-settings' );
		add_settings_field( 'auto_refresh', __( 'Auto-refresh interval (minutes)', 'daily-registration-monitor' ), array( $this, 'field_auto_refresh' ), 'drm-settings', 'drm_general' );

		add_settings_section( 'drm_notifications', __( 'Notifications', 'daily-registration-monitor' ), '__return_false', 'drm-settings' );
		add_settings_field( 'email_enabled', __( 'Email notifications', 'daily-registration-monitor' ), array( $this, 'field_email_enabled' ), 'drm-settings', 'drm_notifications' );
		add_settings_field( 'email_recipients', __( 'Email recipients', 'daily-registration-monitor' ), array( $this, 'field_email_recipients' ), 'drm-settings', 'drm_notifications' );
		add_settings_field( 'daily_summary', __( 'Daily summary email', 'daily-registration-monitor' ), array( $this, 'field_daily_summary' ), 'drm-settings', 'drm_notifications' );

		add_settings_section( 'drm_sms', __( 'SMS (Twilio)', 'daily-registration-monitor' ), '__return_false', 'drm-settings' );
		add_settings_field( 'twilio_enable', __( 'Enable SMS', 'daily-registration-monitor' ), array( $this, 'field_twilio_enable' ), 'drm-settings', 'drm_sms' );
		add_settings_field( 'twilio_sid', __( 'Twilio SID', 'daily-registration-monitor' ), array( $this, 'field_twilio_sid' ), 'drm-settings', 'drm_sms' );
		add_settings_field( 'twilio_token', __( 'Twilio Auth Token', 'daily-registration-monitor' ), array( $this, 'field_twilio_token' ), 'drm-settings', 'drm_sms' );
		add_settings_field( 'twilio_from', __( 'Twilio From Number', 'daily-registration-monitor' ), array( $this, 'field_twilio_from' ), 'drm-settings', 'drm_sms' );
		add_settings_field( 'sms_recipients', __( 'SMS recipients (comma separated)', 'daily-registration-monitor' ), array( $this, 'field_sms_recipients' ), 'drm-settings', 'drm_sms' );

		add_settings_section( 'drm_display', __( 'Display Fields', 'daily-registration-monitor' ), '__return_false', 'drm-settings' );
		add_settings_field( 'display_fields', __( 'Choose fields to display', 'daily-registration-monitor' ), array( $this, 'field_display_fields' ), 'drm-settings', 'drm_display' );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = $this->get_defaults();
		$output   = wp_parse_args( (array) $input, $defaults );
		$output['auto_refresh']   = max( 1, (int) $output['auto_refresh'] );
		$output['email_enabled']  = ! empty( $output['email_enabled'] ) ? 1 : 0;
		$output['daily_summary']  = ! empty( $output['daily_summary'] ) ? 1 : 0;
		$output['email_recipients'] = sanitize_text_field( (string) $output['email_recipients'] );
		$output['twilio_enable']  = ! empty( $output['twilio_enable'] ) ? 1 : 0;
		$output['twilio_sid']     = sanitize_text_field( (string) $output['twilio_sid'] );
		$output['twilio_token']   = sanitize_text_field( (string) $output['twilio_token'] );
		$output['twilio_from']    = sanitize_text_field( (string) $output['twilio_from'] );
		$output['sms_recipients'] = sanitize_text_field( (string) $output['sms_recipients'] );
		$output['display_fields'] = array_map( 'sanitize_key', (array) $output['display_fields'] );
		return $output;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Daily Registration Monitor Settings', 'daily-registration-monitor' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'drm_settings_group' );
				do_settings_sections( 'drm-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Fields
	 */
	public function field_auto_refresh() {
		$opt = $this->get_options();
		$value = (int) $opt['auto_refresh'];
		printf( '<input type="number" min="1" name="%s[auto_refresh]" value="%d" class="small-text" />', esc_attr( self::OPTION ), (int) $value );
		echo ' ' . esc_html__( 'minutes', 'daily-registration-monitor' );
	}

	public function field_email_enabled() {
		$opt = $this->get_options();
		printf( '<label><input type="checkbox" name="%s[email_enabled]" value="1" %s /> %s</label>', esc_attr( self::OPTION ), checked( 1, (int) $opt['email_enabled'], false ), esc_html__( 'Send email on each new registration', 'daily-registration-monitor' ) );
	}

	public function field_daily_summary() {
		$opt = $this->get_options();
		printf( '<label><input type="checkbox" name="%s[daily_summary]" value="1" %s /> %s</label>', esc_attr( self::OPTION ), checked( 1, (int) $opt['daily_summary'], false ), esc_html__( 'Send a daily summary email', 'daily-registration-monitor' ) );
	}

	public function field_email_recipients() {
		$opt = $this->get_options();
		printf( '<input type="text" name="%s[email_recipients]" value="%s" class="regular-text" placeholder="admin@example.com, team@example.com" />', esc_attr( self::OPTION ), esc_attr( (string) $opt['email_recipients'] ) );
	}

	public function field_twilio_enable() {
		$opt = $this->get_options();
		printf( '<label><input type="checkbox" name="%s[twilio_enable]" value="1" %s /> %s</label>', esc_attr( self::OPTION ), checked(  1, (int) $opt['twilio_enable'], false ), esc_html__( 'Enable SMS notifications via Twilio', 'daily-registration-monitor' ) );
	}

	public function field_twilio_sid() {
		$opt = $this->get_options();
		printf( '<input type="text" name="%s[twilio_sid]" value="%s" class="regular-text" />', esc_attr( self::OPTION ), esc_attr( (string) $opt['twilio_sid'] ) );
	}

	public function field_twilio_token() {
		$opt = $this->get_options();
		printf( '<input type="password" name="%s[twilio_token]" value="%s" class="regular-text" />', esc_attr( self::OPTION ), esc_attr( (string) $opt['twilio_token'] ) );
	}

	public function field_twilio_from() {
		$opt = $this->get_options();
		printf( '<input type="text" name="%s[twilio_from]" value="%s" class="regular-text" />', esc_attr( self::OPTION ), esc_attr( (string) $opt['twilio_from'] ) );
	}

	public function field_sms_recipients() {
		$opt = $this->get_options();
		printf( '<input type="text" name="%s[sms_recipients]" value="%s" class="regular-text" placeholder="+15551234567, +15557654321" />', esc_attr( self::OPTION ), esc_attr( (string) $opt['sms_recipients'] ) );
	}

	public function field_display_fields() {
		$opt = $this->get_options();
		$choices = array(
			'avatar' => __( 'Avatar', 'daily-registration-monitor' ),
			'name' => __( 'Name', 'daily-registration-monitor' ),
			'username' => __( 'Username', 'daily-registration-monitor' ),
			'email' => __( 'Email', 'daily-registration-monitor' ),
			'role' => __( 'Role', 'daily-registration-monitor' ),
			'registered' => __( 'Registration Time', 'daily-registration-monitor' ),
			'verified' => __( 'Verification', 'daily-registration-monitor' ),
			'social' => __( 'Social Profiles', 'daily-registration-monitor' ),
			'member_type' => __( 'Member Type', 'daily-registration-monitor' ),
			'location' => __( 'Location', 'daily-registration-monitor' ),
			'bio' => __( 'Bio', 'daily-registration-monitor' ),
			'activity' => __( 'Recent Activity', 'daily-registration-monitor' ),
		);
		foreach ( $choices as $key => $label ) {
			printf(
				'<label style="display:block;margin:4px 0"><input type="checkbox" name="%s[display_fields][]" value="%s" %s /> %s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $key ),
				checked( in_array( $key, (array) $opt['display_fields'], true ), true, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Helpers
	 */
	public function get_options() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), $this->get_defaults() );
	}

	protected function get_defaults() {
		return array(
			'auto_refresh' => 5,
			'email_enabled' => 0,
			'email_recipients' => get_option( 'admin_email' ),
			'daily_summary' => 0,
			'twilio_enable' => 0,
			'twilio_sid' => '',
			'twilio_token' => '',
			'twilio_from' => '',
			'sms_recipients' => '',
			'display_fields' => array( 'avatar','name','username','email','role','registered','verified' ),
		);
	}
}

