<?php
/**
 * Export handler for Daily Registration Monitor.
 *
 * @package Daily_Registration_Monitor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRM_Export_Handler {
	/** @var Daily_Registration_Monitor */
	protected $core;

	public function __construct( $core ) {
		$this->core = $core;
	}

	/**
	 * Generate CSV for today's registrations with enriched fields.
	 *
	 * @param bool $include_all_fields Include social/location/bio/activity columns.
	 * @return string CSV content.
	 */
	public function generate_csv_todays( $include_all_fields = true ) {
		$rows = $this->core->get_todays_registrations();
		$fh   = fopen( 'php://temp', 'w+' );
		$headers = array( 'User ID', 'Full Name', 'Username', 'Email', 'Role', 'Member Type', 'Registered', 'Verified', 'Verification Date' );
		if ( $include_all_fields ) {
			$headers = array_merge( $headers, array( 'Facebook', 'Twitter', 'Instagram', 'Location', 'Bio' ) );
		}
		fputcsv( $fh, $headers );

		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row->ID;
			$data    = $this->core->prepare_user_display_data( $user_id );
			$bb      = $this->core->get_buddyboss_profile_data( $user_id );
			$details = $this->core->get_verification_details( $user_id );
			$full    = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
			$full    = $full ?: ( $data['display_name'] ?? '' );
			$row_out = array(
				$user_id,
				$full,
				(string) ( $data['user_login'] ?? '' ),
				(string) ( $data['user_email'] ?? '' ),
				(string) ( $data['role'] ?? '' ),
				(string) ( $bb['member_type'] ?? '' ),
				(string) ( $data['registered_h'] ?? '' ),
				! empty( $data['verified'] ) ? 'Yes' : 'No',
				(string) ( $details['date'] ?? '' ),
			);
			if ( $include_all_fields ) {
				$custom = $this->core->get_buddyboss_custom_fields( $user_id );
				$row_out = array_merge( $row_out, array(
					(string) ( $custom['facebook'] ?? '' ),
					(string) ( $custom['twitter'] ?? '' ),
					(string) ( $custom['instagram'] ?? '' ),
					(string) ( $custom['location'] ?? '' ),
					(string) ( $custom['bio'] ?? '' ),
				) );
			}
			fputcsv( $fh, $row_out );
		}

		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return (string) $csv;
	}

	/**
	 * Generate a PDF of today's registrations if a PDF library is present.
	 *
	 * @return array{filename:string,content:string}|WP_Error PDF data or error.
	 */
	public function generate_pdf_todays() {
		// Support Dompdf if available.
		if ( class_exists( '\\Dompdf\\Dompdf' ) ) {
			$rows = $this->core->get_todays_registrations();
			$html = '<h1>Today\'s Registrations</h1><table border="1" cellpadding="6" cellspacing="0" width="100%"><tr><th>Photo</th><th>Name</th><th>Username</th><th>Email</th><th>Registered</th></tr>';
			foreach ( (array) $rows as $row ) {
				$user_id = (int) $row->ID;
				$data    = $this->core->prepare_user_display_data( $user_id );
				$full    = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
				$full    = $full ?: ( $data['display_name'] ?? '' );
				$img     = ! empty( $data['avatar_url'] ) ? '<img src="' . esc_url( $data['avatar_url'] ) . '" width="40" height="40" style="border-radius:20px" />' : '';
				$html   .= '<tr><td>' . $img . '</td><td>' . esc_html( $full ) . '</td><td>' . esc_html( (string) ( $data['user_login'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $data['user_email'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $data['registered_h'] ?? '' ) ) . '</td></tr>';
			}
			$html .= '</table>';

			$dompdf = new \Dompdf\Dompdf();
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$output   = $dompdf->output();
			$filename = 'todays-registrations-' . date_i18n( 'Ymd-His', current_time( 'timestamp' ) ) . '.pdf';
			return array( 'filename' => $filename, 'content' => $output );
		}

		return new WP_Error( 'drm_pdf_unavailable', __( 'PDF export requires a PDF library (e.g., Dompdf).', 'daily-registration-monitor' ) );
	}
}

