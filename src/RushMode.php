<?php
/**
 * Modalità Rush - Auto-tuning parametri basato su carico coda.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ottimizza automaticamente performance quando coda è grande.
 *
 * @since 0.4.0
 */
class RushMode {
	use ContainerAwareTrait;
	/**
	 * Soglia per attivare rush mode.
	 */
	const RUSH_THRESHOLD = 500;

	/**
	 * Soglia per disattivare rush mode.
	 */
	const NORMAL_THRESHOLD = 50;

	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Rush_Mode|null
	 */
	protected static $instance = null;

	/**
	 * Queue reference.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Settings reference.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Logger reference.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Stato rush mode.
	 *
	 * @var bool
	 */
	protected $is_rush_active = false;

	/**
	 * Impostazioni originali (prima di rush).
	 *
	 * @var array
	 */
	protected $original_settings = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return \FPML_Rush_Mode
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
		$container = $this->getContainer();
		$this->queue = $container && $container->has( 'queue' ) ? $container->get( 'queue' ) : fpml_get_queue();
		$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : \FPML_fpml_get_logger();

		// Check automatico prima di ogni run della coda.
		add_action( '\FPML_before_queue_run', array( $this, 'check_rush_mode' ) );

		// Hook per modificare parametri in rush mode.
		add_filter( '\FPML_batch_size', array( $this, 'adjust_batch_size' ), 10, 1 );
		add_filter( '\FPML_max_chars_per_batch', array( $this, 'adjust_max_chars' ), 10, 1 );

		// Check stato attuale.
		$this->is_rush_active = get_option( '\FPML_rush_mode_active', false );
	}

	/**
	 * Controlla se attivare/disattivare rush mode.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function check_rush_mode() {
		$counts = $this->queue->get_state_counts();
		$pending = isset( $counts['pending'] ) ? $counts['pending'] : 0;
		$pending += isset( $counts['outdated'] ) ? $counts['outdated'] : 0;

		if ( ! $this->is_rush_active && $pending >= self::RUSH_THRESHOLD ) {
			$this->activate_rush_mode( $pending );
		} elseif ( $this->is_rush_active && $pending <= self::NORMAL_THRESHOLD ) {
			$this->deactivate_rush_mode();
		}
	}

	/**
	 * Attiva rush mode.
	 *
	 * @since 0.4.0
	 *
	 * @param int $queue_size Dimensione coda.
	 *
	 * @return void
	 */
	protected function activate_rush_mode( $queue_size ) {
		if ( ! $this->settings ) {
			return;
		}

		// Salva impostazioni originali.
		$this->original_settings = array(
			'batch_size'          => $this->settings->get( 'batch_size', 5 ),
			'max_chars_per_batch' => $this->settings->get( 'max_chars_per_batch', 20000 ),
			'cron_frequency'      => $this->settings->get( 'cron_frequency', '15min' ),
		);

		update_option( '\FPML_rush_original_settings', $this->original_settings, false );

		// Calcola parametri ottimizzati.
		$optimized = $this->calculate_optimized_params( $queue_size );

		// Applica temporaneamente.
		$current_settings = $this->settings->all();
		$current_settings['batch_size'] = $optimized['batch_size'];
		$current_settings['max_chars_per_batch'] = $optimized['max_chars_per_batch'];
		update_option( \FPML_Settings::OPTION_KEY, $current_settings );

		// Aumenta frequenza cron (se possibile).
		$this->reschedule_cron( '5min' );

		update_option( '\FPML_rush_mode_active', true, false );
		update_option( '\FPML_rush_activated_at', current_time( 'timestamp', true ), false );

		$this->is_rush_active = true;

		$this->logger->log(
			'info',
			sprintf(
				'🚀 Rush Mode ATTIVATO! Coda: %d job. Batch aumentato a %d.',
				$queue_size,
				$optimized['batch_size']
			),
			array(
				'queue_size'          => $queue_size,
				'new_batch_size'      => $optimized['batch_size'],
				'new_max_chars_batch' => $optimized['max_chars_per_batch'],
				'original_settings'   => $this->original_settings,
			)
		);
	}

	/**
	 * Disattiva rush mode.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function deactivate_rush_mode() {
		// Ripristina impostazioni originali.
		$original = get_option( '\FPML_rush_original_settings', array() );

		if ( ! empty( $original ) && $this->settings ) {
			$current_settings = $this->settings->all();
			$current_settings['batch_size'] = $original['batch_size'];
			$current_settings['max_chars_per_batch'] = $original['max_chars_per_batch'];
			update_option( \FPML_Settings::OPTION_KEY, $current_settings );

			// Ripristina cron originale.
			$this->reschedule_cron( $original['cron_frequency'] );
		}

		update_option( '\FPML_rush_mode_active', false, false );
		delete_option( '\FPML_rush_activated_at' );

		$this->is_rush_active = false;

		$this->logger->log(
			'info',
			'✓ Rush Mode DISATTIVATO. Ritorno a parametri normali.',
			array(
				'restored_settings' => $original,
			)
		);
	}

	/**
	 * Calcola parametri ottimizzati.
	 *
	 * @since 0.4.0
	 *
	 * @param int $queue_size Dimensione coda.
	 *
	 * @return array
	 */
	protected function calculate_optimized_params( $queue_size ) {
		// Logica adattiva basata su dimensione coda.
		$base_batch = $this->original_settings['batch_size'] ?? 5;

		if ( $queue_size > 2000 ) {
			$multiplier = 4; // 4x più aggressivo.
		} elseif ( $queue_size > 1000 ) {
			$multiplier = 3;
		} else {
			$multiplier = 2;
		}

		$new_batch = min( 50, $base_batch * $multiplier ); // Max 50.
		$new_max_chars = min( 50000, 20000 * $multiplier ); // Max 50K.

		return array(
			'batch_size'          => $new_batch,
			'max_chars_per_batch' => $new_max_chars,
		);
	}

	/**
	 * Reschedula cron.
	 *
	 * @since 0.4.0
	 *
	 * @param string $frequency Frequenza.
	 *
	 * @return void
	 */
	protected function reschedule_cron( $frequency ) {
		$hook = '\FPML_run_queue';

		// Rimuovi schedule esistente.
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}

		// Mappa frequenza.
		$schedule_map = array(
			'5min'  => '\FPML_five_minutes',
			'15min' => '\FPML_fifteen_minutes',
			'hourly' => 'hourly',
		);

		$schedule = isset( $schedule_map[ $frequency ] ) ? $schedule_map[ $frequency ] : '\FPML_fifteen_minutes';

		// Reschedula.
		wp_schedule_event( time() + 60, $schedule, $hook );
	}

	/**
	 * Ajusta batch size in rush mode.
	 *
	 * @since 0.4.0
	 *
	 * @param int $size Dimensione originale.
	 *
	 * @return int
	 */
	public function adjust_batch_size( $size ) {
		if ( ! $this->is_rush_active ) {
			return $size;
		}

		// Ritorna dimensione ottimizzata.
		return $this->settings ? $this->settings->get( 'batch_size', $size ) : $size;
	}

	/**
	 * Ajusta max chars in rush mode.
	 *
	 * @since 0.4.0
	 *
	 * @param int $max Max chars originale.
	 *
	 * @return int
	 */
	public function adjust_max_chars( $max ) {
		if ( ! $this->is_rush_active ) {
			return $max;
		}

		return $this->settings ? $this->settings->get( 'max_chars_per_batch', $max ) : $max;
	}

	/**
	 * Controlla se rush mode è attivo.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->is_rush_active;
	}

	/**
	 * Ottieni statistiche rush mode.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_stats() {
		$activated_at = get_option( '\FPML_rush_activated_at', 0 );

		return array(
			'is_active'    => $this->is_rush_active,
			'activated_at' => $activated_at,
			'duration'     => $activated_at ? current_time( 'timestamp', true ) - $activated_at : 0,
			'original'     => $this->original_settings,
		);
	}
}

