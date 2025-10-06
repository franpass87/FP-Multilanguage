<?php
/**
 * Webhook notifications for queue events.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send webhook notifications for important events.
 *
 * @since 0.3.2
 */
class FPML_Webhooks {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Webhooks|null
	 */
	protected static $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.3.2
	 *
	 * @return FPML_Webhooks
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
	protected function __construct() {
		$this->settings = FPML_Settings::instance();

		// Hook into queue events
		add_action( 'fpml_queue_batch_complete', array( $this, 'send_batch_complete' ), 10, 1 );
		add_action( 'fpml_queue_after_cleanup', array( $this, 'send_cleanup_complete' ), 10, 3 );
	}

	/**
	 * Send webhook when batch processing completes.
	 *
	 * @since 0.3.2
	 *
	 * @param array $summary Batch processing summary.
	 *
	 * @return void
	 */
	public function send_batch_complete( $summary ) {
		$webhook_url = $this->settings->get( 'webhook_url' );

		if ( empty( $webhook_url ) || ! is_string( $webhook_url ) ) {
			return;
		}

		// Only send if significant work done
		$processed = isset( $summary['processed'] ) ? (int) $summary['processed'] : 0;
		if ( $processed < 1 ) {
			return;
		}

		$payload = array(
			'event'     => 'batch.complete',
			'summary'   => $summary,
			'timestamp' => current_time( 'mysql', true ),
			'site_url'  => home_url(),
			'site_name' => get_bloginfo( 'name' ),
		);

		$this->send_webhook( $webhook_url, $payload );
	}

	/**
	 * Send webhook when cleanup completes.
	 *
	 * @since 0.3.2
	 *
	 * @param int   $deleted Number of jobs deleted.
	 * @param array $states  States that were cleaned.
	 * @param int   $days    Retention period.
	 *
	 * @return void
	 */
	public function send_cleanup_complete( $deleted, $states, $days ) {
		$webhook_url = $this->settings->get( 'webhook_url' );

		if ( empty( $webhook_url ) || ! is_string( $webhook_url ) ) {
			return;
		}

		// Only send if significant cleanup
		if ( $deleted < 10 ) {
			return;
		}

		$payload = array(
			'event'     => 'cleanup.complete',
			'deleted'   => $deleted,
			'states'    => $states,
			'days'      => $days,
			'timestamp' => current_time( 'mysql', true ),
			'site_url'  => home_url(),
			'site_name' => get_bloginfo( 'name' ),
		);

		$this->send_webhook( $webhook_url, $payload );
	}

	/**
	 * Send webhook to configured URL.
	 *
	 * @since 0.3.2
	 *
	 * @param string $url     Webhook URL.
	 * @param array  $payload Data to send.
	 *
	 * @return void
	 */
	protected function send_webhook( $url, $payload ) {
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'FP-Multilanguage/' . FPML_PLUGIN_VERSION,
				),
				'timeout' => 5,
			)
		);

		// Log webhook errors
		if ( is_wp_error( $response ) ) {
			if ( class_exists( 'FPML_Logger' ) ) {
				FPML_Logger::instance()->log(
					'warn',
					sprintf( 'Webhook failed: %s', $response->get_error_message() ),
					array(
						'event'       => 'webhook.error',
						'webhook_url' => $url,
						'payload'     => $payload,
					)
				);
			}
		}
	}

	/**
	 * Test webhook configuration.
	 *
	 * @since 0.3.2
	 *
	 * @param string $url Optional webhook URL to test.
	 *
	 * @return bool|WP_Error
	 */
	public function test_webhook( $url = '' ) {
		if ( empty( $url ) ) {
			$url = $this->settings->get( 'webhook_url' );
		}

		if ( empty( $url ) || ! is_string( $url ) ) {
			return new WP_Error( 'fpml_webhook_missing_url', __( 'Webhook URL non configurato.', 'fp-multilanguage' ) );
		}

		$test_payload = array(
			'event'     => 'test.webhook',
			'message'   => 'Test webhook from FP Multilanguage',
			'timestamp' => current_time( 'mysql', true ),
			'site_url'  => home_url(),
		);

		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode( $test_payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'FP-Multilanguage/' . FPML_PLUGIN_VERSION,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'fpml_webhook_test_failed',
				sprintf( __( 'Test webhook fallito: %s', 'fp-multilanguage' ), $response->get_error_message() )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'fpml_webhook_test_failed',
				sprintf( __( 'Webhook ha restituito codice %d', 'fp-multilanguage' ), $code )
			);
		}

		return true;
	}
}
