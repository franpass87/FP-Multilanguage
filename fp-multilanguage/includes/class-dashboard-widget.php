<?php
/**
 * Dashboard widget con statistiche traduzioni.
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
 * Widget dashboard con metriche real-time.
 *
 * @since 0.4.0
 */
class FPML_Dashboard_Widget {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Dashboard_Widget|null
	 */
	protected static $instance = null;

	/**
	 * Queue reference.
	 *
	 * @var FPML_Queue
	 */
	protected $queue;

	/**
	 * Logger reference.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Dashboard_Widget
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
		$this->queue  = FPML_Queue::instance();
		$this->logger = FPML_Logger::instance();

		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Aggiunge widget alla dashboard.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'fpml_dashboard_widget',
			'🌍 ' . __( 'FP Multilanguage - Stato Traduzioni', 'fp-multilanguage' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Renderizza widget.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function render_widget() {
		$stats = $this->get_stats();
		?>
		<style>
			.fpml-dashboard-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 15px 0; }
			.fpml-stat-box { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #2271b1; }
			.fpml-stat-box.pending { border-left-color: #f0ad4e; }
			.fpml-stat-box.done { border-left-color: #46b450; }
			.fpml-stat-box.error { border-left-color: #dc3545; }
			.fpml-stat-number { font-size: 32px; font-weight: bold; line-height: 1; margin-bottom: 5px; }
			.fpml-stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
			.fpml-progress-bar { background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden; margin: 10px 0; }
			.fpml-progress-fill { background: #46b450; height: 100%; transition: width 0.3s; }
			.fpml-quick-actions { margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
			.fpml-quick-action { flex: 1; min-width: 140px; }
		</style>

		<div class="fpml-dashboard-stats">
			<div class="fpml-stat-box pending">
				<div class="fpml-stat-number"><?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?></div>
				<div class="fpml-stat-label"><?php esc_html_e( 'In Coda', 'fp-multilanguage' ); ?></div>
			</div>

			<div class="fpml-stat-box">
				<div class="fpml-stat-number"><?php echo esc_html( number_format_i18n( $stats['translating'] ) ); ?></div>
				<div class="fpml-stat-label"><?php esc_html_e( 'In Corso', 'fp-multilanguage' ); ?></div>
			</div>

			<div class="fpml-stat-box done">
				<div class="fpml-stat-number"><?php echo esc_html( number_format_i18n( $stats['done'] ) ); ?></div>
				<div class="fpml-stat-label"><?php esc_html_e( 'Completate', 'fp-multilanguage' ); ?></div>
			</div>

			<div class="fpml-stat-box error">
				<div class="fpml-stat-number"><?php echo esc_html( number_format_i18n( $stats['error'] ) ); ?></div>
				<div class="fpml-stat-label"><?php esc_html_e( 'Errori', 'fp-multilanguage' ); ?></div>
			</div>
		</div>

		<?php if ( $stats['total'] > 0 ) : ?>
			<div style="margin: 15px 0;">
				<strong><?php esc_html_e( 'Progresso:', 'fp-multilanguage' ); ?></strong>
				<div class="fpml-progress-bar">
					<div class="fpml-progress-fill" style="width: <?php echo esc_attr( $stats['progress_percent'] ); ?>%;"></div>
				</div>
				<p style="margin: 5px 0; font-size: 13px; color: #666;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: completate, 2: totali, 3: percentuale */
							__( '%1$s di %2$s traduzioni completate (%3$s%%)', 'fp-multilanguage' ),
							number_format_i18n( $stats['done'] ),
							number_format_i18n( $stats['total'] ),
							number_format_i18n( $stats['progress_percent'], 1 )
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $stats['health_alerts'] ) ) : ?>
			<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0; border-radius: 4px;">
				<strong>⚠️ <?php esc_html_e( 'Attenzione:', 'fp-multilanguage' ); ?></strong>
				<ul style="margin: 5px 0 0 20px;">
					<?php foreach ( $stats['health_alerts'] as $alert ) : ?>
						<li><?php echo esc_html( $alert ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="fpml-quick-actions">
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=fp-multilanguage&tab=diagnostics' ) ); ?>" class="button fpml-quick-action">
				📊 <?php esc_html_e( 'Diagnostica', 'fp-multilanguage' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=fp-multilanguage' ) ); ?>" class="button fpml-quick-action">
				⚙️ <?php esc_html_e( 'Impostazioni', 'fp-multilanguage' ); ?>
			</a>
		</div>

		<?php if ( $stats['recent_activity'] ) : ?>
			<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
				<strong><?php esc_html_e( 'Attività Recente:', 'fp-multilanguage' ); ?></strong>
				<ul style="margin: 10px 0 0 0; padding: 0; list-style: none; font-size: 12px;">
					<?php foreach ( array_slice( $stats['recent_activity'], 0, 3 ) as $activity ) : ?>
						<li style="padding: 5px 0; color: #666;">
							<span style="color: #46b450;">●</span> <?php echo esc_html( $activity['message'] ); ?>
							<small style="color: #999;">(<?php echo esc_html( human_time_diff( $activity['timestamp'], current_time( 'timestamp', true ) ) ); ?> <?php esc_html_e( 'fa', 'fp-multilanguage' ); ?>)</small>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Ottieni statistiche.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	protected function get_stats() {
		$counts = $this->queue->get_state_counts();

		$pending     = isset( $counts['pending'] ) ? $counts['pending'] : 0;
		$pending    += isset( $counts['outdated'] ) ? $counts['outdated'] : 0;
		$translating = isset( $counts['translating'] ) ? $counts['translating'] : 0;
		$done        = isset( $counts['done'] ) ? $counts['done'] : 0;
		$error       = isset( $counts['error'] ) ? $counts['error'] : 0;
		$skipped     = isset( $counts['skipped'] ) ? $counts['skipped'] : 0;

		$total = $pending + $translating + $done + $error + $skipped;
		$progress_percent = $total > 0 ? ( $done / $total ) * 100 : 0;

		// Health alerts.
		$alerts = array();
		if ( class_exists( 'FPML_Health_Check' ) ) {
			$health = FPML_Health_Check::instance();
			$active_alerts = $health->get_active_alerts();

			foreach ( $active_alerts as $alert ) {
				if ( 'critical' === $alert['severity'] || 'warning' === $alert['severity'] ) {
					$alerts[] = $alert['message'];
				}
			}
		}

		// Attività recente.
		$recent_activity = array();
		if ( $this->logger ) {
			$logs = $this->logger->get_logs( 10 );
			foreach ( $logs as $log ) {
				if ( in_array( $log['level'], array( 'success', 'info' ), true ) ) {
					$recent_activity[] = array(
						'message'   => $log['message'],
						'timestamp' => isset( $log['timestamp'] ) ? strtotime( $log['timestamp'] ) : 0,
					);
				}
			}
		}

		return array(
			'pending'          => $pending,
			'translating'      => $translating,
			'done'             => $done,
			'error'            => $error,
			'skipped'          => $skipped,
			'total'            => $total,
			'progress_percent' => round( $progress_percent, 1 ),
			'health_alerts'    => $alerts,
			'recent_activity'  => $recent_activity,
		);
	}
}
