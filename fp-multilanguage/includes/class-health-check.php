<?php
/**
 * Health Check automatico e auto-recovery per il sistema di traduzione.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Monitora lo stato del sistema e applica correzioni automatiche.
 *
 * @since 0.4.0
 */
class FPML_Health_Check {
	/**
	 * Opzione per memorizzare l'ultimo health check.
	 */
	const OPTION_LAST_CHECK = 'fpml_last_health_check';

	/**
	 * Opzione per memorizzare gli alert attivi.
	 */
	const OPTION_ACTIVE_ALERTS = 'fpml_active_health_alerts';

	/**
	 * Soglia per considerare un job bloccato (in secondi).
	 */
	const STUCK_JOB_THRESHOLD = 7200; // 2 ore

	/**
	 * Numero massimo di retry prima di considerare un job fallito.
	 */
	const MAX_RETRY_THRESHOLD = 5;

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Health_Check|null
	 */
	protected static $instance = null;

	/**
	 * Queue reference.
	 *
	 * @var FPML_Queue
	 */
	protected $queue;

	/**
	 * Processor reference.
	 *
	 * @var FPML_Processor
	 */
	protected $processor;

	/**
	 * Logger reference.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Settings reference.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Report del controllo corrente.
	 *
	 * @var array
	 */
	protected $report = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Health_Check
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
		$this->queue     = FPML_Queue::instance();
		$this->processor = FPML_Processor::instance();
		$this->logger    = FPML_Logger::instance();
		$this->settings  = FPML_Settings::instance();

		// Registra il cron per l'health check orario.
		add_action( 'init', array( $this, 'schedule_health_check' ) );
		add_action( 'fpml_health_check', array( $this, 'run_health_check' ) );

		// Aggiungi notice admin se ci sono problemi.
		add_action( 'admin_notices', array( $this, 'show_health_notices' ) );
	}

	/**
	 * Schedula l'health check se non è già schedulato.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function schedule_health_check() {
		if ( ! wp_next_scheduled( 'fpml_health_check' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'fpml_health_check' );
		}
	}

	/**
	 * Esegue un controllo completo dello stato del sistema.
	 *
	 * @since 0.4.0
	 *
	 * @param bool $force_recovery Forza il recovery anche in modalità dry-run.
	 *
	 * @return array Report del controllo.
	 */
	public function run_health_check( $force_recovery = false ) {
		$this->report = array(
			'timestamp'     => current_time( 'mysql', true ),
			'checks'        => array(),
			'issues'        => array(),
			'actions_taken' => array(),
			'status'        => 'healthy',
		);

		// 1. Controlla job bloccati.
		$this->check_stuck_jobs( $force_recovery );

		// 2. Controlla lock processore scaduto.
		$this->check_processor_lock( $force_recovery );

		// 3. Controlla job con troppi errori.
		$this->check_failed_jobs( $force_recovery );

		// 4. Controlla provider configurato.
		$this->check_provider_configuration();

		// 5. Controlla crescita coda.
		$this->check_queue_growth();

		// 6. Controlla spazio disco (per i log).
		$this->check_disk_space();

		// Determina stato generale.
		if ( ! empty( $this->report['issues'] ) ) {
			$critical_count = 0;
			foreach ( $this->report['issues'] as $issue ) {
				if ( 'critical' === $issue['severity'] ) {
					$critical_count++;
				}
			}

			$this->report['status'] = $critical_count > 0 ? 'critical' : 'warning';
		}

		// Aggiorna gli alert attivi.
		$this->update_active_alerts();

		// Salva timestamp dell'ultimo controllo.
		update_option( self::OPTION_LAST_CHECK, current_time( 'timestamp', true ), false );

		// Logga il risultato.
		$this->logger->log(
			'critical' === $this->report['status'] ? 'error' : 'info',
			sprintf(
				'Health check completato: %d problemi rilevati, %d azioni applicate',
				count( $this->report['issues'] ),
				count( $this->report['actions_taken'] )
			),
			array(
				'status'        => $this->report['status'],
				'issues'        => count( $this->report['issues'] ),
				'actions_taken' => count( $this->report['actions_taken'] ),
			)
		);

		// Invia notifiche se necessario.
		$this->maybe_send_notifications();

		return $this->report;
	}

	/**
	 * Controlla job bloccati in stato "translating" da troppo tempo.
	 *
	 * @since 0.4.0
	 *
	 * @param bool $apply_recovery Applica auto-recovery.
	 *
	 * @return void
	 */
	protected function check_stuck_jobs( $apply_recovery ) {
		global $wpdb;

		$table     = $this->queue->get_table();
		$threshold = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - self::STUCK_JOB_THRESHOLD );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stuck_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE state = %s AND updated_at < %s",
				'translating',
				$threshold
			)
		);

		$this->report['checks']['stuck_jobs'] = array(
			'label'  => 'Job bloccati',
			'value'  => (int) $stuck_count,
			'status' => 0 === (int) $stuck_count ? 'ok' : 'warning',
		);

		if ( $stuck_count > 0 ) {
			$this->report['issues'][] = array(
				'code'        => 'stuck_jobs',
				'severity'    => 'warning',
				'message'     => sprintf(
					/* translators: %d: numero di job bloccati */
					__( '%d job sono bloccati in stato "translating" da più di 2 ore.', 'fp-multilanguage' ),
					$stuck_count
				),
				'auto_fixable' => true,
			);

			if ( $apply_recovery ) {
				// Reset job bloccati a "pending".
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$reset = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET state = %s, retries = 0, last_error = %s, updated_at = %s WHERE state = %s AND updated_at < %s",
						'pending',
						'Auto-recovery: job bloccato reimpostato',
						current_time( 'mysql', true ),
						'translating',
						$threshold
					)
				);

				if ( $reset > 0 ) {
					$this->report['actions_taken'][] = array(
						'action'  => 'reset_stuck_jobs',
						'message' => sprintf(
							/* translators: %d: numero di job reimpostati */
							__( '%d job bloccati sono stati reimpostati a "pending".', 'fp-multilanguage' ),
							$reset
						),
					);

					$this->logger->log(
						'info',
						sprintf( 'Auto-recovery: %d job bloccati reimpostati', $reset ),
						array( 'count' => $reset )
					);
				}
			}
		}
	}

	/**
	 * Controlla se il lock del processore è scaduto.
	 *
	 * @since 0.4.0
	 *
	 * @param bool $apply_recovery Applica auto-recovery.
	 *
	 * @return void
	 */
	protected function check_processor_lock( $apply_recovery ) {
		$is_locked = $this->processor->is_locked();

		$this->report['checks']['processor_lock'] = array(
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

				$this->report['actions_taken'][] = array(
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
	 * Controlla job con troppi tentativi falliti.
	 *
	 * @since 0.4.0
	 *
	 * @param bool $apply_recovery Applica auto-recovery.
	 *
	 * @return void
	 */
	protected function check_failed_jobs( $apply_recovery ) {
		global $wpdb;

		$table = $this->queue->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$failed_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE state = %s AND retries >= %d",
				'error',
				self::MAX_RETRY_THRESHOLD
			)
		);

		$this->report['checks']['failed_jobs'] = array(
			'label'  => 'Job falliti permanentemente',
			'value'  => (int) $failed_count,
			'status' => 0 === (int) $failed_count ? 'ok' : 'warning',
		);

		if ( $failed_count > 0 ) {
			$this->report['issues'][] = array(
				'code'        => 'failed_jobs',
				'severity'    => 'warning',
				'message'     => sprintf(
					/* translators: %d: numero di job falliti */
					__( '%d job hanno superato il numero massimo di tentativi e sono in errore permanente.', 'fp-multilanguage' ),
					$failed_count
				),
				'auto_fixable' => true,
			);

			if ( $apply_recovery ) {
				// Marca come "skipped" i job con troppi errori.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$skipped = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET state = %s, updated_at = %s WHERE state = %s AND retries >= %d",
						'skipped',
						current_time( 'mysql', true ),
						'error',
						self::MAX_RETRY_THRESHOLD
					)
				);

				if ( $skipped > 0 ) {
					$this->report['actions_taken'][] = array(
						'action'  => 'skip_failed_jobs',
						'message' => sprintf(
							/* translators: %d: numero di job saltati */
							__( '%d job falliti sono stati marcati come "skipped".', 'fp-multilanguage' ),
							$skipped
						),
					);

					$this->logger->log(
						'info',
						sprintf( 'Auto-recovery: %d job falliti marcati come skipped', $skipped ),
						array( 'count' => $skipped )
					);
				}
			}
		}
	}

	/**
	 * Controlla se il provider è configurato correttamente.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function check_provider_configuration() {
		$translator = $this->processor->get_translator_instance();
		$provider   = $this->settings ? $this->settings->get( 'provider', '' ) : '';

		$is_configured = ! is_wp_error( $translator ) && $translator instanceof FPML_TranslatorInterface;

		$this->report['checks']['provider'] = array(
			'label'  => 'Provider traduzione',
			'value'  => $provider ? $provider : 'non configurato',
			'status' => $is_configured ? 'ok' : 'critical',
		);

		if ( ! $is_configured ) {
			$error_message = is_wp_error( $translator ) ? $translator->get_error_message() : __( 'Nessun provider configurato.', 'fp-multilanguage' );

			$this->report['issues'][] = array(
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
	 * Controlla la crescita della coda.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function check_queue_growth() {
		$counts        = $this->queue->get_state_counts();
		$pending_count = isset( $counts['pending'] ) ? $counts['pending'] : 0;
		$pending_count += isset( $counts['outdated'] ) ? $counts['outdated'] : 0;

		$status = 'ok';
		if ( $pending_count > 1000 ) {
			$status = 'critical';
		} elseif ( $pending_count > 500 ) {
			$status = 'warning';
		}

		$this->report['checks']['queue_size'] = array(
			'label'  => 'Dimensione coda',
			'value'  => $pending_count,
			'status' => $status,
		);

		if ( 'ok' !== $status ) {
			$this->report['issues'][] = array(
				'code'     => 'large_queue',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %d: numero di job in coda */
					__( 'La coda contiene %d job in attesa. Considera di aumentare la frequenza del cron o il batch size.', 'fp-multilanguage' ),
					$pending_count
				),
				'auto_fixable' => false,
			);
		}
	}

	/**
	 * Controlla spazio disco disponibile.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function check_disk_space() {
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

		$this->report['checks']['disk_space'] = array(
			'label'  => 'Spazio disco disponibile',
			'value'  => $free_mb . ' MB',
			'status' => $status,
		);

		if ( 'ok' !== $status ) {
			$this->report['issues'][] = array(
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

	/**
	 * Aggiorna gli alert attivi.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function update_active_alerts() {
		$alerts = array();

		foreach ( $this->report['issues'] as $issue ) {
			$alerts[ $issue['code'] ] = array(
				'severity'   => $issue['severity'],
				'message'    => $issue['message'],
				'first_seen' => current_time( 'timestamp', true ),
			);
		}

		// Mantieni la data di first_seen per alert già esistenti.
		$existing = get_option( self::OPTION_ACTIVE_ALERTS, array() );

		foreach ( $alerts as $code => $alert ) {
			if ( isset( $existing[ $code ]['first_seen'] ) ) {
				$alerts[ $code ]['first_seen'] = $existing[ $code ]['first_seen'];
			}
		}

		update_option( self::OPTION_ACTIVE_ALERTS, $alerts, false );
	}

	/**
	 * Invia notifiche se ci sono problemi critici.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function maybe_send_notifications() {
		if ( 'critical' !== $this->report['status'] ) {
			return;
		}

		// Verifica se abbiamo già notificato nelle ultime 24 ore.
		$last_notification = get_option( 'fpml_last_critical_notification', 0 );

		if ( $last_notification && ( current_time( 'timestamp', true ) - $last_notification < DAY_IN_SECONDS ) ) {
			return;
		}

		// Invia email all'admin.
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_option( 'blogname' );

		$subject = sprintf(
			/* translators: %s: nome del sito */
			__( '[%s] FP Multilanguage: Problemi critici rilevati', 'fp-multilanguage' ),
			$site_name
		);

		$message = __( 'Sono stati rilevati problemi critici nel sistema di traduzione:', 'fp-multilanguage' ) . "\n\n";

		foreach ( $this->report['issues'] as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				$message .= '- ' . $issue['message'] . "\n";
			}
		}

		$message .= "\n" . sprintf(
			/* translators: %s: URL admin */
			__( 'Verifica lo stato su: %s', 'fp-multilanguage' ),
			admin_url( 'options-general.php?page=fp-multilanguage&tab=diagnostics' )
		);

		wp_mail( $admin_email, $subject, $message );

		update_option( 'fpml_last_critical_notification', current_time( 'timestamp', true ), false );

		$this->logger->log(
			'info',
			'Notifica email inviata per problemi critici',
			array( 'recipient' => $admin_email )
		);
	}

	/**
	 * Mostra notice admin se ci sono alert attivi.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function show_health_notices() {
		$alerts = get_option( self::OPTION_ACTIVE_ALERTS, array() );

		if ( empty( $alerts ) ) {
			return;
		}

		$critical_alerts = array_filter(
			$alerts,
			static function( $alert ) {
				return 'critical' === $alert['severity'];
			}
		);

		if ( empty( $critical_alerts ) ) {
			return;
		}

		$message = '<strong>' . __( 'FP Multilanguage: Problemi critici rilevati', 'fp-multilanguage' ) . '</strong><ul>';

		foreach ( $critical_alerts as $alert ) {
			$message .= '<li>' . esc_html( $alert['message'] ) . '</li>';
		}

		$message .= '</ul>';
		$message .= sprintf(
			'<p><a href="%s" class="button button-primary">%s</a></p>',
			admin_url( 'options-general.php?page=fp-multilanguage&tab=diagnostics' ),
			__( 'Vai alla diagnostica', 'fp-multilanguage' )
		);

		printf(
			'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
			wp_kses_post( $message )
		);
	}

	/**
	 * Ottieni l'ultimo report health check.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_last_report() {
		return $this->report;
	}

	/**
	 * Ottieni gli alert attivi.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_active_alerts() {
		return get_option( self::OPTION_ACTIVE_ALERTS, array() );
	}

	/**
	 * Ottieni il timestamp dell'ultimo health check.
	 *
	 * @since 0.4.0
	 *
	 * @return int
	 */
	public function get_last_check_time() {
		return (int) get_option( self::OPTION_LAST_CHECK, 0 );
	}
}
