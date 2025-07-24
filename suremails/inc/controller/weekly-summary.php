<?php
/**
 * WeeklySummary Class
 *
 * Sends weekly email summary based on stats collected via SureMails logs.
 *
 * @package SureMails\Inc\Controller
 */

namespace SureMails\Inc\Controller;

use SureMails\Inc\DB\EmailLog;
use SureMails\Inc\Settings;
use SureMails\Inc\Traits\Instance;
use SureMails\Inc\Traits\SendEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WeeklySummary
 */
class WeeklySummary {

	use Instance;
	use SendEmail;

	/**
	 * Miscellaneous plugin settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Constructor.
	 *
	 * Initializes settings.
	 */
	public function __construct() {
		$this->settings = Settings::instance()->get_misc_settings();
	}

	/**
	 * Called via daily cron. Sends summary only on configured day.
	 *
	 * @return void
	 */
	public function maybe_send_summary(): void {
		$active            = $this->settings['email_summary_active'] ?? 'yes';
		$day               = $this->settings['email_summary_day'] ?? 'monday';
		$today             = strtolower( gmdate( 'l' ) );
		$connections_count = count( Settings::instance()->get_raw_settings()['connections'] ?? [] );

		if ( 'no' === $active || $today !== $day || $connections_count < 1 ) {
			return;
		}

		$this->send_summary_email();
		$index = (int) ( $this->settings['email_summary_index'] ?? 1 ) + 1;

		if ( $index > 4 ) {
			$index = 1;
		}
		Settings::instance()->update_misc_settings( 'email_summary_index', $index );
	}

	/**
	 * Handles logic to compile and send the summary email.
	 *
	 * @return void
	 */
	private function send_summary_email(): void {
		$email = $this->get_email_content();
		if ( empty( $email ) || empty( $email['to'] ) || empty( $email['body'] ) ) {
			return;
		}
		$this->send( $email['to'], $email['subject'], $email['body'], $this->get_html_headers(), [] );
	}

	/**
	 * Builds the subject, body, and recipient for the summary email.
	 *
	 * @return array
	 */
	private function get_email_content(): array {
		$stats = $this->get_statistics();
		$to    = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: 1: From date, 2: To date */
			esc_html__( 'Email summary of your last week — %1$s to %2$s', 'suremails' ),
			esc_html( date_i18n( 'F j, Y', strtotime( '-7 days' ) ) ),
			esc_html( date_i18n( 'F j, Y', strtotime( '-1 day' ) ) )
		);

		$body = $this->build_email_header()
				. $this->build_email_body( $stats )
				. $this->build_email_footer();

		return [
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
		];
	}

	/**
	 * Get or copy image to uploads folder.
	 *
	 * @param string $filename The image filename.
	 * @param string $source_path The source path relative to plugin directory.
	 * @return string The public URL of the image.
	 */
	private function get_public_image_url( $filename, $source_path ): string {
		// Get upload directory info.
		$upload_dir           = wp_upload_dir();
		$suremails_upload_dir = trailingslashit( $upload_dir['basedir'] ) . 'suremails/email-summary/';
		$suremails_upload_url = trailingslashit( $upload_dir['baseurl'] ) . 'suremails/email-summary/';

		// Create directory if it doesn't exist.
		if ( ! file_exists( $suremails_upload_dir ) ) {
			if ( ! wp_mkdir_p( $suremails_upload_dir ) ) {
				// Fallback to plugin URL.
				return esc_url( SUREMAILS_PLUGIN_URL . $source_path . $filename );
			}
		}

		$target_file = $suremails_upload_dir . $filename;
		$target_url  = $suremails_upload_url . $filename;

		// If file doesn't exist in uploads, copy it from plugin assets.
		if ( ! file_exists( $target_file ) ) {
			$source_file = SUREMAILS_DIR . $source_path . $filename;
			if ( file_exists( $source_file ) ) {
				// Attempt to copy the file.
				$copy_result = copy( $source_file, $target_file );
				if ( ! $copy_result ) {
					// Fallback to plugin URL.
					return esc_url( SUREMAILS_PLUGIN_URL . $source_path . $filename );
				}
			} else {
				// Fallback to plugin URL.
				return esc_url( SUREMAILS_PLUGIN_URL . $source_path . $filename );
			}
		}

		return esc_url( $target_url );
	}

	/**
	 * Build email header HTML.
	 *
	 * @return string
	 */
	private function build_email_header(): string {
		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<meta http-equiv="X-UA-Compatible" content="IE=edge">
			<title><?php esc_html_e( 'Weekly Summary', 'suremails' ); ?></title>
			<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
		</head>
		<body style="font-family:Figtree,Arial,sans-serif;background-color:#F1F5F9;margin:0;padding:32px;">
			<div style="max-width:640px;margin:0 auto;">
				<div style="margin-bottom:24px;text-align:left;">
					<img src="<?php echo esc_url( $this->get_public_image_url( 'suremail.png', 'src/assets/logos/' ) ); ?>" 
						alt="<?php esc_attr_e( 'SureMail Logo', 'suremails' ); ?>" 
						width="162" height="32" 
						style="display:inline-block;">
				</div>
				<div style="background-color:#FFFFFF;padding-bottom:40px;">
		<?php
		$content = ob_get_clean();
		return $content !== false ? $content : '';
	}

	/**
	 * Generate logs URL with date range for the past 7 days.
	 *
	 * @return string
	 */
	private function get_logs_url(): string {
		$from_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$to_date   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		$base_url   = admin_url( 'options-general.php' );
		$query_args = [
			'page' => 'suremail',
		];

		return add_query_arg( $query_args, $base_url ) . "#/logs?from={$from_date}&to={$to_date}";
	}

	/**
	 * Build email body with statistics.
	 *
	 * @param array $stats Email statistics.
	 * @return string
	 */
	private function build_email_body( array $stats ): string {
		$logs_url = $this->get_logs_url();

		ob_start();
		?>
		<div style="padding:24px;">
			<p style="font-size:18px;font-weight:600;color:#111827;margin:0 0 8px;">
				<?php esc_html_e( 'Hey There,', 'suremails' ); ?>
			</p>
			<p style="font-size:14px;color:#4B5563;margin:0 0 16px;">
				<?php
				printf(
					/* translators: %s: Number of days */
					esc_html__( 'Here\'s your SureMail report for the last %s', 'suremails' ),
					'<strong>7 days.</strong>'
				);
				?>
			</p>

			<?php echo wp_kses_post( $this->build_statistics_table( $stats ) ); ?>

			<a href="<?php echo esc_url( $logs_url ); ?>" 
				style="display:inline-block;background-color:#2563EB;color:#FFFFFF;padding:8px 12px;border-radius:4px;text-decoration:none;font-size:12px;font-weight:600;margin-top:16px;">
				<?php esc_html_e( 'View Email Logs', 'suremails' ); ?>
			</a>
		</div>
		<?php
		$content = ob_get_clean();
		return $content !== false ? $content : '';
	}

	/**
	 * Build statistics table HTML.
	 *
	 * @param array $stats Email statistics.
	 * @return string
	 */
	private function build_statistics_table( array $stats ): string {
		$stats_labels = [
			'sent'    => esc_html__( 'Emails Sent Successfully', 'suremails' ),
			'failed'  => esc_html__( 'Emails Failed to Send', 'suremails' ),
			'blocked' => esc_html__( 'Emails Blocked by Reputation Shield', 'suremails' ),
		];

		ob_start();
		?>
		<table style="border:1px solid #E5E7EB;border-radius:8px;box-shadow:0 1px 1px rgba(0,0,0,0.05);margin-top:16px;width:100%;border-collapse:separate;border-spacing:0;">
			<thead>
				<tr style="background-color:#F9FAFB;">
					<th style="padding:8px 12px;font-size:14px;font-weight:500;color:#111827;text-align:left;border-top-left-radius:8px;">
						<?php esc_html_e( 'Emails', 'suremails' ); ?>
					</th>
					<th style="padding:8px 12px;font-size:14px;font-weight:500;color:#111827;text-align:right;width:146px;border-top-right-radius:8px;">
						<?php esc_html_e( 'Last Week', 'suremails' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$row_index   = 0;
				$stats_count = count( $stats_labels );

				foreach ( $stats_labels as $key => $label ) {
					$bg_color            = $row_index % 2 === 0 ? '#FFFFFF' : '#F9FAFB';
					$is_last             = $row_index === $stats_count - 1;
					$border_top          = $row_index > 0 ? '0.5px solid #E5E7EB' : 'none';
					$border_radius_left  = $is_last ? 'border-bottom-left-radius:8px;' : '';
					$border_radius_right = $is_last ? 'border-bottom-right-radius:8px;' : '';
					?>
				<tr style="background-color:<?php echo esc_attr( $bg_color ); ?>;">
					<td style="padding:12px;font-size:14px;color:#4B5563;border-top:<?php echo esc_attr( $border_top ); ?>;<?php echo esc_attr( $border_radius_left ); ?>">
						<?php echo esc_html( $label ); ?>
					</td>
					<td style="padding:12px;font-size:14px;color:#4B5563;text-align:right;width:146px;border-top:<?php echo esc_attr( $border_top ); ?>;<?php echo esc_attr( $border_radius_right ); ?>">
						<?php echo esc_html( $stats[ $key ] ?? 0 ); ?>
					</td>
				</tr>
					<?php
					$row_index++;
				}
				?>
			</tbody>
		</table>
		<?php
		$content = ob_get_clean();
		return $content !== false ? $content : '';
	}

	/**
	 * Build email footer HTML.
	 *
	 * @return string
	 */
	private function build_email_footer(): string {
		$all_products = [
			'astra'            => 'https://wpastra.com',
			'surecart'         => 'https://surecart.com',
			'sureforms'        => 'https://sureforms.com',
			'prestoplayer'     => 'https://prestoplayer.com/',
			'suredash'         => 'https://suredash.com',
			'cartflows'        => 'https://cartflows.com',
			'suremembers'      => 'https://suremembers.com',
			'startertemplates' => 'https://startertemplates.com/',
			'zipwp'            => 'https://zipwp.com',
			'ottokit'          => 'https://ottokit.com',
			'surefeedback'     => 'https://surefeedback.com',
			'surerank'         => 'https://surerank.com',
		];

		$index = isset( $this->settings['email_summary_index'] ) ? (int) $this->settings['email_summary_index'] : 1;
		$idx   = max( 1, $index );
		$group = ( ( $idx - 1 ) % 4 ) * 3;
		$pick  = array_slice( array_keys( $all_products ), $group, 3 );

		ob_start();
		?>
				<hr style="margin:24px 24px;border:none;border-top:1px solid #eee;">
				<p style="font-size:14px;text-align:center;margin-bottom:10px;">
					<?php esc_html_e( 'Learn more about our other products', 'suremails' ); ?>
				</p>
				<table align="center" cellpadding="0" cellspacing="0">
					<tr>
						<?php foreach ( $pick as $index => $slug ) { ?>
							<?php
							$filename = $slug . '.png';
							$url      = $this->get_public_image_url( $filename, 'src/assets/logos/' );
							$link     = esc_url( $all_products[ $slug ] );
							$alt      = sprintf(
								/* translators: %s: Product name */
								esc_attr__( '%s logo', 'suremails' ),
								esc_attr( ucfirst( $slug ) )
							);
							?>
							<td style="padding:0 10px;">
								<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
									<img src="<?php echo esc_url( $url ); ?>" 
										alt="<?php echo esc_attr( $alt ); ?>" 
										height="20" 
										style="display:block;">
								</a>
							</td>
							<?php if ( $index < count( $pick ) - 1 ) { ?>
								<td style="width:1px;">
									<div style="width:1px;height:20px;background:#E5E7EB;"></div>
								</td>
							<?php } ?>
						<?php } ?>
					</tr>
				</table>
				<hr style="margin:24px 24px;border:none;border-top:1px solid #eee;">
				<div>
					<p style="font-size:12px;color:#9CA3AF;text-align:center;margin:16px 0;">
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=suremail#/settings' ) ); ?>" 
							style="color:#9CA3AF;text-decoration:none;">
							<?php esc_html_e( 'Manage Email Summaries from your website settings', 'suremails' ); ?>
						</a>
					</p>
					<hr style="border:none;border-top:1px solid #eee;margin:0 24px;">
					<div style="text-align:center;margin-top:16px;">
						<a href="<?php echo esc_url( 'https://suremails.com' ); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url( $this->get_public_image_url( 'suremail.png', 'src/assets/logos/' ) ); ?>" 
								alt="<?php esc_attr_e( 'SureMail Logo', 'suremails' ); ?>" 
								height="20" 
								style="display:block;margin:0 auto;">
						</a>
					</div>
				</div>
			</div>
		</div>
	</body>
	</html>
		<?php
		$content = ob_get_clean();
		return $content !== false ? $content : '';
	}

	/**
	 * Get email statistics for the past 7 days.
	 *
	 * @return array
	 */
	private function get_statistics(): array {
		$email_log  = EmailLog::instance();
		$start_date = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$end_date   = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day 23:59:59' ) );

		$results = $email_log->get(
			[
				'select'   => 'status, COUNT(*) as count',
				'where'    => [
					'updated_at >=' => $start_date,
					'updated_at <=' => $end_date,
				],
				'group_by' => 'status',
			]
		);

		$counts = [
			'sent'    => 0,
			'failed'  => 0,
			'blocked' => 0,
		];

		if ( is_array( $results ) && ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$status = $row['status'];
				$count  = (int) $row['count'];
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = $count;
				}
			}
		}

		return $counts;
	}
}
