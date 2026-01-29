<?php
/**
 * Health Check automatico e auto-recovery per il sistema di traduzione.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\HealthCheck\JobChecker;
use FP\Multilanguage\HealthCheck\SystemChecker;
use FP\Multilanguage\HealthCheck\QueueMonitor;
use FP\Multilanguage\HealthCheck\AlertManager;
use FP\Multilanguage\HealthCheck\Notifier;
use FP\Multilanguage\HealthCheck\HealthCheckAdmin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Monitora lo stato del sistema e applica correzioni automatiche.
 *
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */
class HealthCheck {
	use ContainerAwareTrait;
	/**
	 * Opzione per memorizzare l'ultimo health check.
	 */
	const OPTION_LAST_CHECK = '\FPML_last_health_check';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Queue reference.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Processor reference.
	 *
	 * @var \FPML_Processor
	 */
	protected $processor;

	/**
	 * Logger reference.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Settings reference.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Report del controllo corrente.
	 *
	 * @var array
	 */
	protected $report = array();

	/**
	 * Job checker instance.
	 *
	 * @since 0.10.0
	 *
	 * @var JobChecker
	 */
	protected JobChecker $job_checker;

	/**
	 * System checker instance.
	 *
	 * @since 0.10.0
	 *
	 * @var SystemChecker
	 */
	protected SystemChecker $system_checker;

	/**
	 * Queue monitor instance.
	 *
	 * @since 0.10.0
	 *
	 * @var QueueMonitor
	 */
	protected QueueMonitor $queue_monitor;

	/**
	 * Alert manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var AlertManager
	 */
	protected AlertManager $alert_manager;

	/**
	 * Notifier instance.
	 *
	 * @since 0.10.0
	 *
	 * @var Notifier
	 */
	protected Notifier $notifier;

	/**
	 * Admin instance.
	 *
	 * @since 0.10.0
	 *
	 * @var HealthCheckAdmin
	 */
	protected HealthCheckAdmin $admin;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		$container = $this->getContainer();
		$this->queue = $container && $container->has( 'queue' ) ? $container->get( 'queue' ) : fpml_get_queue();
		$this->processor = \FPML_fpml_get_processor();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : \FPML_fpml_get_logger();
		$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();

		// Initialize modules
		$this->job_checker = new JobChecker( $this->queue, $this->logger );
		$this->system_checker = new SystemChecker( $this->processor, $this->settings, $this->queue, $this->logger );
		$this->queue_monitor = new QueueMonitor( $this->queue );
		$this->alert_manager = new AlertManager();
		$this->notifier = new Notifier();
		$this->admin = new HealthCheckAdmin( $this->alert_manager );

		// Registra il cron per l'health check orario.
		add_action( 'init', array( $this, 'schedule_health_check' ) );
		add_action( '\FPML_health_check', array( $this, 'run_health_check' ) );

		// Aggiungi notice admin se ci sono problemi.
		add_action( 'admin_notices', array( $this->admin, 'show_health_notices' ) );
	}

	/**
	 * Schedula l'health check se non è già schedulato.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function schedule_health_check(): void {
		if ( ! wp_next_scheduled( '\FPML_health_check' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', '\FPML_health_check' );
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
	public function run_health_check( bool $force_recovery = false ): array {
		$this->report = array(
			'timestamp'     => current_time( 'mysql', true ),
			'checks'        => array(),
			'issues'        => array(),
			'actions_taken' => array(),
			'status'        => 'healthy',
		);

		// 1. Controlla job bloccati.
		$this->job_checker->check_stuck_jobs( $this->report, $force_recovery );

		// 2. Controlla lock processore scaduto.
		$this->system_checker->check_processor_lock( $this->report, $force_recovery );

		// 3. Controlla job con troppi errori.
		$this->job_checker->check_failed_jobs( $this->report, $force_recovery );

		// 4. Controlla provider configurato.
		$this->system_checker->check_provider_configuration( $this->report );

		// 5. Controlla crescita coda.
		$this->queue_monitor->check_queue_growth( $this->report );

		// 6. Controlla spazio disco (per i log).
		$this->system_checker->check_disk_space( $this->report );

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
		$this->alert_manager->update_active_alerts( $this->report );

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
		$this->notifier->maybe_send_notifications( $this->report );

		return $this->report;
	}

	/**
	 * Ottieni l'ultimo report health check.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_last_report(): array {
		return $this->report;
	}

	/**
	 * Ottieni gli alert attivi.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_active_alerts(): array {
		return $this->alert_manager->get_active_alerts();
	}

	/**
	 * Ottieni il timestamp dell'ultimo health check.
	 *
	 * @since 0.4.0
	 *
	 * @return int
	 */
	public function get_last_check_time(): int {
		return (int) get_option( self::OPTION_LAST_CHECK, 0 );
	}
}
