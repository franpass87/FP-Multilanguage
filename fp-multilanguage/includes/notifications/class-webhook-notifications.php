<?php
/**
 * Webhook Notifications
 *
 * Send notifications to Slack, Discord, and Microsoft Teams.
 *
 * @package FP_Multilanguage
 * @subpackage Notifications
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Webhook_Notifications
 *
 * Sends notifications to external services:
 * - Slack integration
 * - Discord webhooks
 * - Microsoft Teams channels
 * - Custom webhook endpoints
 *
 * @since 0.5.0
 */
class FPML_Webhook_Notifications {

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Webhook_Notifications
	 */
	private static $instance = null;

	/**
	 * Supported platforms.
	 *
	 * @var array
	 */
	private $platforms = array( 'slack', 'discord', 'teams', 'custom' );

	/**
	 * Get singleton instance.
	 *
	 * @return FPML_Webhook_Notifications
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Listen to important events.
		add_action( 'fpml_bulk_job_completed', array( $this, 'on_bulk_job_completed' ) );
		add_action( 'fpml_translation_error', array( $this, 'on_translation_error' ), 10, 2 );
		add_action( 'fpml_high_cost_alert', array( $this, 'on_high_cost_alert' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_fpml_test_webhook', array( $this, 'ajax_test_webhook' ) );
	}

	/**
	 * Send notification to webhook.
	 *
	 * @param string $platform Platform (slack, discord, teams, custom).
	 * @param string $webhook_url Webhook URL.
	 * @param array  $data Notification data.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function send_notification( $platform, $webhook_url, $data ) {
		if ( ! in_array( $platform, $this->platforms, true ) ) {
			return new WP_Error( 'invalid_platform', __( 'Invalid platform.', 'fp-multilanguage' ) );
		}

		if ( empty( $webhook_url ) ) {
			return new WP_Error( 'empty_webhook', __( 'Webhook URL is required.', 'fp-multilanguage' ) );
		}

		// Format message for platform.
		$payload = $this->format_payload( $platform, $data );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		// Send HTTP request.
		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'webhook_failed',
				sprintf(
					__( 'Webhook request failed with status %d', 'fp-multilanguage' ),
					$status_code
				)
			);
		}

		return true;
	}

	/**
	 * Format payload for specific platform.
	 *
	 * @param string $platform Platform name.
	 * @param array  $data Notification data.
	 * @return array|WP_Error Formatted payload or error.
	 */
	private function format_payload( $platform, $data ) {
		$title = $data['title'] ?? __( 'FP Multilanguage Notification', 'fp-multilanguage' );
		$message = $data['message'] ?? '';
		$color = $data['color'] ?? 'good'; // good, warning, danger.
		$fields = $data['fields'] ?? array();

		switch ( $platform ) {
			case 'slack':
				return $this->format_slack_payload( $title, $message, $color, $fields );

			case 'discord':
				return $this->format_discord_payload( $title, $message, $color, $fields );

			case 'teams':
				return $this->format_teams_payload( $title, $message, $color, $fields );

			case 'custom':
				return $data; // Return raw data for custom webhooks.

			default:
				return new WP_Error( 'invalid_platform', __( 'Invalid platform.', 'fp-multilanguage' ) );
		}
	}

	/**
	 * Format Slack payload.
	 *
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $color Color (good, warning, danger).
	 * @param array  $fields Fields.
	 * @return array Payload.
	 */
	private function format_slack_payload( $title, $message, $color, $fields ) {
		$attachment_fields = array();

		foreach ( $fields as $key => $value ) {
			$attachment_fields[] = array(
				'title' => $key,
				'value' => $value,
				'short' => true,
			);
		}

		return array(
			'text' => $title,
			'attachments' => array(
				array(
					'text' => $message,
					'color' => $color,
					'fields' => $attachment_fields,
					'footer' => 'FP Multilanguage',
					'ts' => time(),
				),
			),
		);
	}

	/**
	 * Format Discord payload.
	 *
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $color Color.
	 * @param array  $fields Fields.
	 * @return array Payload.
	 */
	private function format_discord_payload( $title, $message, $color, $fields ) {
		// Convert color name to hex.
		$color_map = array(
			'good' => 0x00ff00,
			'warning' => 0xffa500,
			'danger' => 0xff0000,
		);

		$embed_color = isset( $color_map[ $color ] ) ? $color_map[ $color ] : 0x0073aa;

		$embed_fields = array();
		foreach ( $fields as $key => $value ) {
			$embed_fields[] = array(
				'name' => $key,
				'value' => $value,
				'inline' => true,
			);
		}

		return array(
			'embeds' => array(
				array(
					'title' => $title,
					'description' => $message,
					'color' => $embed_color,
					'fields' => $embed_fields,
					'footer' => array(
						'text' => 'FP Multilanguage',
					),
					'timestamp' => gmdate( 'c' ),
				),
			),
		);
	}

	/**
	 * Format Microsoft Teams payload.
	 *
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $color Color.
	 * @param array  $fields Fields.
	 * @return array Payload.
	 */
	private function format_teams_payload( $title, $message, $color, $fields ) {
		// Convert color name to hex.
		$color_map = array(
			'good' => '00ff00',
			'warning' => 'ffa500',
			'danger' => 'ff0000',
		);

		$theme_color = isset( $color_map[ $color ] ) ? $color_map[ $color ] : '0073aa';

		$facts = array();
		foreach ( $fields as $key => $value ) {
			$facts[] = array(
				'name' => $key,
				'value' => $value,
			);
		}

		return array(
			'@type' => 'MessageCard',
			'@context' => 'https://schema.org/extensions',
			'summary' => $title,
			'themeColor' => $theme_color,
			'title' => $title,
			'text' => $message,
			'sections' => array(
				array(
					'facts' => $facts,
				),
			),
		);
	}

	/**
	 * Send notification to all configured webhooks.
	 *
	 * @param array $data Notification data.
	 * @return void
	 */
	public function send_to_all( $data ) {
		$settings = FPML_Container::resolve( 'settings' );

		foreach ( $this->platforms as $platform ) {
			$webhook_url = $settings->get( "webhook_{$platform}_url" );
			$enabled = $settings->get( "webhook_{$platform}_enabled", false );

			if ( $enabled && ! empty( $webhook_url ) ) {
				$this->send_notification( $platform, $webhook_url, $data );
			}
		}
	}

	/**
	 * Handle bulk job completion.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	public function on_bulk_job_completed( $job_id ) {
		$bulk_manager = FPML_Container::resolve( 'bulk_translation_manager' );
		
		if ( ! $bulk_manager ) {
			return;
		}

		$job = $bulk_manager->get_job_status( $job_id );

		if ( ! $job ) {
			return;
		}

		$data = array(
			'title' => '✅ ' . __( 'Bulk Translation Completed', 'fp-multilanguage' ),
			'message' => sprintf(
				__( 'Bulk translation job #%d has been completed successfully.', 'fp-multilanguage' ),
				$job_id
			),
			'color' => 'good',
			'fields' => array(
				__( 'Total Posts', 'fp-multilanguage' ) => $job['total_posts'],
				__( 'Successful', 'fp-multilanguage' ) => $job['processed_posts'],
				__( 'Failed', 'fp-multilanguage' ) => $job['failed_posts'],
				__( 'Duration', 'fp-multilanguage' ) => $this->calculate_duration( $job['started_at'], $job['completed_at'] ),
			),
		);

		$this->send_to_all( $data );
	}

	/**
	 * Handle translation error.
	 *
	 * @param string $error_message Error message.
	 * @param array  $context Error context.
	 * @return void
	 */
	public function on_translation_error( $error_message, $context = array() ) {
		$data = array(
			'title' => '❌ ' . __( 'Translation Error', 'fp-multilanguage' ),
			'message' => $error_message,
			'color' => 'danger',
			'fields' => array(
				__( 'Provider', 'fp-multilanguage' ) => $context['provider'] ?? 'Unknown',
				__( 'Content ID', 'fp-multilanguage' ) => $context['content_id'] ?? 'N/A',
				__( 'Time', 'fp-multilanguage' ) => current_time( 'mysql' ),
			),
		);

		$this->send_to_all( $data );
	}

	/**
	 * Handle high cost alert.
	 *
	 * @param float $cost Cost amount.
	 * @param array $details Cost details.
	 * @return void
	 */
	public function on_high_cost_alert( $cost, $details = array() ) {
		$data = array(
			'title' => '⚠️ ' . __( 'High Cost Alert', 'fp-multilanguage' ),
			'message' => sprintf(
				__( 'Translation costs have reached $%.2f this month.', 'fp-multilanguage' ),
				$cost
			),
			'color' => 'warning',
			'fields' => array(
				__( 'Monthly Cost', 'fp-multilanguage' ) => '$' . number_format( $cost, 2 ),
				__( 'Translations', 'fp-multilanguage' ) => $details['count'] ?? 'N/A',
				__( 'Top Provider', 'fp-multilanguage' ) => $details['top_provider'] ?? 'N/A',
			),
		);

		$this->send_to_all( $data );
	}

	/**
	 * Calculate duration between two dates.
	 *
	 * @param string $start Start datetime.
	 * @param string $end End datetime.
	 * @return string Duration string.
	 */
	private function calculate_duration( $start, $end ) {
		$start_time = strtotime( $start );
		$end_time = strtotime( $end );
		$duration = $end_time - $start_time;

		if ( $duration < 60 ) {
			return sprintf( __( '%d seconds', 'fp-multilanguage' ), $duration );
		} elseif ( $duration < 3600 ) {
			return sprintf( __( '%d minutes', 'fp-multilanguage' ), floor( $duration / 60 ) );
		} else {
			$hours = floor( $duration / 3600 );
			$minutes = floor( ( $duration % 3600 ) / 60 );
			return sprintf( __( '%d hours %d minutes', 'fp-multilanguage' ), $hours, $minutes );
		}
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'fpml',
			__( 'Webhooks', 'fp-multilanguage' ),
			__( 'Webhooks', 'fp-multilanguage' ),
			'manage_options',
			'fpml-webhooks',
			array( $this, 'render_webhooks_page' )
		);
	}

	/**
	 * Render webhooks settings page.
	 *
	 * @return void
	 */
	public function render_webhooks_page() {
		$settings = FPML_Container::resolve( 'settings' );

		if ( isset( $_POST['fpml_save_webhooks'] ) && check_admin_referer( 'fpml_webhooks_settings' ) ) {
			// Save webhook settings.
			foreach ( $this->platforms as $platform ) {
				$enabled = isset( $_POST["webhook_{$platform}_enabled"] );
				$url = sanitize_text_field( $_POST["webhook_{$platform}_url"] ?? '' );

				$settings->set( "webhook_{$platform}_enabled", $enabled );
				$settings->set( "webhook_{$platform}_url", $url );
			}

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Webhook settings saved.', 'fp-multilanguage' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Webhook Notifications', 'fp-multilanguage' ); ?></h1>
			
			<p><?php esc_html_e( 'Configure webhooks to receive notifications about translation events in Slack, Discord, or Microsoft Teams.', 'fp-multilanguage' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'fpml_webhooks_settings' ); ?>

				<!-- Slack -->
				<div class="fpml-webhook-section" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
					<h2>
						<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='%23E01E5A' d='M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z'/%3E%3C/svg%3E" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 10px;" alt="Slack">
						Slack
					</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Slack', 'fp-multilanguage' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="webhook_slack_enabled" value="1" <?php checked( $settings->get( 'webhook_slack_enabled', false ) ); ?>>
									<?php esc_html_e( 'Send notifications to Slack', 'fp-multilanguage' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook URL', 'fp-multilanguage' ); ?></th>
							<td>
								<input type="url" name="webhook_slack_url" value="<?php echo esc_attr( $settings->get( 'webhook_slack_url', '' ) ); ?>" class="regular-text" placeholder="https://hooks.slack.com/services/...">
								<p class="description">
									<?php
									printf(
										// translators: %s: Link to Slack webhook documentation.
										esc_html__( 'Create an incoming webhook in your Slack workspace. %s', 'fp-multilanguage' ),
										'<a href="https://api.slack.com/messaging/webhooks" target="_blank">' . esc_html__( 'Learn more', 'fp-multilanguage' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Discord -->
				<div class="fpml-webhook-section" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
					<h2>
						<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='%235865F2' d='M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z'/%3E%3C/svg%3E" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 10px;" alt="Discord">
						Discord
					</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Discord', 'fp-multilanguage' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="webhook_discord_enabled" value="1" <?php checked( $settings->get( 'webhook_discord_enabled', false ) ); ?>>
									<?php esc_html_e( 'Send notifications to Discord', 'fp-multilanguage' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook URL', 'fp-multilanguage' ); ?></th>
							<td>
								<input type="url" name="webhook_discord_url" value="<?php echo esc_attr( $settings->get( 'webhook_discord_url', '' ) ); ?>" class="regular-text" placeholder="https://discord.com/api/webhooks/...">
								<p class="description">
									<?php
									printf(
										// translators: %s: Link to Discord webhook documentation.
										esc_html__( 'Create a webhook in your Discord server settings. %s', 'fp-multilanguage' ),
										'<a href="https://support.discord.com/hc/en-us/articles/228383668" target="_blank">' . esc_html__( 'Learn more', 'fp-multilanguage' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Microsoft Teams -->
				<div class="fpml-webhook-section" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
					<h2>
						<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='%236264A7' d='M19.2 8.4v7.2c0 2-1.6 3.6-3.6 3.6H8.4c-2 0-3.6-1.6-3.6-3.6V8.4c0-2 1.6-3.6 3.6-3.6h7.2c2 0 3.6 1.6 3.6 3.6z'/%3E%3Cpath fill='%235059C9' d='M0 12v7.2C0 21.3 2.7 24 6 24h7.2v-12H0z'/%3E%3Cpath fill='%237B83EB' d='M13.2 0H6C2.7 0 0 2.7 0 6v6h13.2V0z'/%3E%3C/svg%3E" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 10px;" alt="Microsoft Teams">
						Microsoft Teams
					</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Teams', 'fp-multilanguage' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="webhook_teams_enabled" value="1" <?php checked( $settings->get( 'webhook_teams_enabled', false ) ); ?>>
									<?php esc_html_e( 'Send notifications to Microsoft Teams', 'fp-multilanguage' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook URL', 'fp-multilanguage' ); ?></th>
							<td>
								<input type="url" name="webhook_teams_url" value="<?php echo esc_attr( $settings->get( 'webhook_teams_url', '' ) ); ?>" class="regular-text" placeholder="https://outlook.office.com/webhook/...">
								<p class="description">
									<?php
									printf(
										// translators: %s: Link to Teams webhook documentation.
										esc_html__( 'Add an incoming webhook connector to your Teams channel. %s', 'fp-multilanguage' ),
										'<a href="https://docs.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook" target="_blank">' . esc_html__( 'Learn more', 'fp-multilanguage' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<button type="submit" name="fpml_save_webhooks" class="button button-primary">
						<?php esc_html_e( 'Save Webhook Settings', 'fp-multilanguage' ); ?>
					</button>
					<button type="button" id="fpml-test-webhooks" class="button">
						<?php esc_html_e( 'Send Test Notification', 'fp-multilanguage' ); ?>
					</button>
				</p>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#fpml-test-webhooks').on('click', function() {
				const button = $(this);
				button.prop('disabled', true).text('<?php esc_html_e( 'Sending...', 'fp-multilanguage' ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fpml_test_webhook',
						nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_test_webhook' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							alert('<?php esc_html_e( 'Test notification sent successfully!', 'fp-multilanguage' ); ?>');
						} else {
							alert('<?php esc_html_e( 'Failed to send test notification.', 'fp-multilanguage' ); ?>');
						}
					},
					complete: function() {
						button.prop('disabled', false).text('<?php esc_html_e( 'Send Test Notification', 'fp-multilanguage' ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Test webhook.
	 *
	 * @return void
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'fpml_test_webhook', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$data = array(
			'title' => '🧪 ' . __( 'Test Notification', 'fp-multilanguage' ),
			'message' => __( 'This is a test notification from FP Multilanguage plugin.', 'fp-multilanguage' ),
			'color' => 'good',
			'fields' => array(
				__( 'Status', 'fp-multilanguage' ) => __( 'Working correctly', 'fp-multilanguage' ),
				__( 'Time', 'fp-multilanguage' ) => current_time( 'mysql' ),
			),
		);

		$this->send_to_all( $data );

		wp_send_json_success();
	}
}
