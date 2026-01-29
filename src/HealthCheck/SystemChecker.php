<?php
/**
 * Health Check System Checker - Checks processor lock, provider, and disk space.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\HealthCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks processor lock, provider, and disk space.
 *
 * @since 0.10.0
 */
class SystemChecker {
	/**
	 * Processor instance.
	 *
	 * @var \FPML_Processor
	 */
	protected $processor;

	/**
	 * Settings instance.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Queue instance.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Logger instance.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Processor $processor Processor instance.
	 * @param \FPML_Settings  $settings  Settings instance.
	 * @param \FPML_Queue     $queue     Queue instance.
	 * @param \FPML_Logger    $logger    Logger instance.
	 */
	public function __construct( $processor, $settings, $queue, $logger ) {
		$this->processor = $processor;
		$this->settings = $settings;
		$this->queue = $queue;
		$this->logger = $logger;
	}

	/**
	 * Controlla se il lock del processore è scaduto.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array to update.
	 * @param bool  $apply_recovery Applica auto-recovery.
	 *
	 * @return void
	 */
	public function check_processor_lock( array &$report, bool $apply_recovery ): void {
		$is_locked = $this->processor->is_locked();

		$report['checks']['processor_lock'] = array(
			'label'  => 'Lock processore',
			'value'  => $is_locked ? 'attivo' : 'libero',
			'status' => $is_locked ? 'warning' : 'ok',
		);

		if ( $is_locked && $apply_recovery ) {
			// Verifica se il lock è davvero scaduto (non ci sono job in translating recenti).
			global $wpdb;
			$table         = $this->queue->get_table();
			$recent_cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - 300 ); // 5 minuti

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$recent_translating = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE state = %s AND updated_at > %s",
					'translating',
					$recent_cutoff
				)
			);

			if ( 0 === (int) $recent_translating ) {
				// Lock orfano, rilascialo.
				$this->processor->force_release_lock();

				$report['actions_taken'][] = array(
					'action'  => 'release_processor_lock',
					'message' => __( 'Lock processore scaduto rilasciato.', 'fp-multilanguage' ),
				);

				$this->logger->log(
					'info',
					'Auto-recovery: lock processore scaduto rilasciato',
					array()
				);
			}
		}
	}

	/**
	 * Controlla se il provider è configurato correttamente.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array to update.
	 *
	 * @return void
	 */
	public function check_provider_configuration( array &$report ): void {
		$translator = $this->processor->get_translator_instance();
		$provider   = $this->settings ? $this->settings->get( 'provider', '' ) : '';

		$is_configured = ! is_wp_error( $translator ) && $translator instanceof \FPML_TranslatorInterface;

		$report['checks']['provider'] = array(
			'label'  => 'Provider traduzione',
			'value'  => $provider ? $provider : 'non configurato',
			'status' => $is_configured ? 'ok' : 'critical',
		);

		if ( ! $is_configured ) {
			$error_message = is_wp_error( $translator ) ? $translator->get_error_message() : __( 'Nessun provider configurato.', 'fp-multilanguage' );

			$report['issues'][] = array(
				'code'        => 'provider_not_configured',
				'severity'    => 'critical',
				'message'     => sprintf(
					/* translators: %s: messaggio di errore */
					__( 'Provider non configurato o non raggiungibile: %s', 'fp-multilanguage' ),
					$error_message
				),
				'auto_fixable' => false,
			);
		}
	}

	/**
	 * Controlla spazio disco disponibile.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array to update.
	 *
	 * @return void
	 */
	public function check_disk_space( array &$report ): void {
		$upload_dir = wp_upload_dir();
		$path       = $upload_dir['basedir'];

		if ( ! is_dir( $path ) ) {
			return;
		}

		$free_space = @disk_free_space( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $free_space ) {
			return;
		}

		$free_mb = round( $free_space / 1024 / 1024, 2 );
		$status  = 'ok';

		if ( $free_mb < 100 ) {
			$status = 'critical';
		} elseif ( $free_mb < 500 ) {
			$status = 'warning';
		}

		$report['checks']['disk_space'] = array(
			'label'  => 'Spazio disco disponibile',
			'value'  => $free_mb . ' MB',
			'status' => $status,
		);

		if ( 'ok' !== $status ) {
			$report['issues'][] = array(
				'code'        => 'low_disk_space',
				'severity'    => 'critical' === $status ? 'critical' : 'warning',
				'message'     => sprintf(
					/* translators: %s: spazio libero */
					__( 'Spazio disco insufficiente: %s MB disponibili.', 'fp-multilanguage' ),
					$free_mb
				),
				'auto_fixable' => false,
			);
		}
	}
}
















