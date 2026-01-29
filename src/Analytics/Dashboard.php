<?php
/**
 * Analytics Dashboard.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Analytics;

use FP\Multilanguage\Queue;
use FP\Multilanguage\Logger;
use FP\Multilanguage\Core\TranslationCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard {
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 0.5.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Add dashboard widget.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function add_dashboard_widget(): void {
		wp_add_dashboard_widget(
			'fpml_analytics_widget',
			__( '📊 FP Multilanguage Analytics', 'fp-multilanguage' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render dashboard widget.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$stats = $this->get_stats();
		?>
		<div class="fpml-analytics-dashboard">
			<div class="fpml-stats-grid">
				<div class="fpml-stat-card">
					<div class="fpml-stat-value"><?php echo esc_html( number_format( $stats['total_translated'] ) ); ?></div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Contenuti Tradotti', 'fp-multilanguage' ); ?></div>
				</div>
				<div class="fpml-stat-card">
					<div class="fpml-stat-value"><?php echo esc_html( $stats['queue_pending'] ); ?></div>
					<div class="fpml-stat-label"><?php esc_html_e( 'In Coda', 'fp-multilanguage' ); ?></div>
				</div>
				<div class="fpml-stat-card">
					<div class="fpml-stat-value"><?php echo esc_html( $stats['translations_today'] ); ?></div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Oggi', 'fp-multilanguage' ); ?></div>
				</div>
				<div class="fpml-stat-card">
					<div class="fpml-stat-value">€<?php echo esc_html( number_format( $stats['estimated_cost'], 4 ) ); ?></div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Costo Stimato (Coda)', 'fp-multilanguage' ); ?></div>
				</div>
				<div class="fpml-stat-card">
					<div class="fpml-stat-value">€<?php echo esc_html( number_format( $stats['total_cost'], 4 ) ); ?></div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Costo Totale API', 'fp-multilanguage' ); ?></div>
				</div>
				<div class="fpml-stat-card">
					<div class="fpml-stat-value"><?php echo esc_html( $stats['cache_hit_rate'] ); ?>%</div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Cache Hit Rate', 'fp-multilanguage' ); ?></div>
				</div>
				<?php if ( $stats['avg_translation_time'] > 0 ) : ?>
				<div class="fpml-stat-card">
					<div class="fpml-stat-value"><?php echo esc_html( number_format( $stats['avg_translation_time'], 2 ) ); ?>s</div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Tempo Medio', 'fp-multilanguage' ); ?></div>
				</div>
				<?php endif; ?>
				<?php if ( $stats['queue_completed'] > 0 ) : ?>
				<div class="fpml-stat-card">
					<div class="fpml-stat-value"><?php echo esc_html( number_format( $stats['queue_completed'] ) ); ?></div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Completate', 'fp-multilanguage' ); ?></div>
				</div>
				<?php endif; ?>
				<?php if ( $stats['queue_failed'] > 0 ) : ?>
				<div class="fpml-stat-card fpml-stat-error">
					<div class="fpml-stat-value"><?php echo esc_html( number_format( $stats['queue_failed'] ) ); ?></div>
					<div class="fpml-stat-label"><?php esc_html_e( 'Fallite', 'fp-multilanguage' ); ?></div>
				</div>
				<?php endif; ?>
			</div>

			<style>
				.fpml-stats-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
					gap: 15px;
					margin-top: 15px;
				}
				.fpml-stat-card {
					text-align: center;
					padding: 15px;
					background: #f0f0f1;
					border-radius: 4px;
					transition: transform 0.2s, box-shadow 0.2s;
				}
				.fpml-stat-card:hover {
					transform: translateY(-2px);
					box-shadow: 0 2px 8px rgba(0,0,0,0.1);
				}
				.fpml-stat-card.fpml-stat-error {
					background: #fef7f1;
					border-left: 3px solid #d63638;
				}
				.fpml-stat-value {
					font-size: 32px;
					font-weight: bold;
					color: #2271b1;
				}
				.fpml-stat-card.fpml-stat-error .fpml-stat-value {
					color: #d63638;
				}
				.fpml-stat-label {
					font-size: 13px;
					color: #646970;
					margin-top: 5px;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Get dashboard statistics.
	 * Cached for 5 minutes to reduce database load.
	 *
	 * @since 0.5.0
	 * @since 0.10.0 Optimized with caching
	 * @since 0.10.0 Added performance metrics and cost tracking
	 *
	 * @return array Array with stats including performance and cost tracking.
	 */
	protected function get_stats(): array {
		// Cache stats for 5 minutes to reduce database load
		$cache_key = 'fpml_dashboard_stats';
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		global $wpdb;

		$queue = function_exists( 'fpml_get_queue' ) ? fpml_get_queue() : fpml_get_queue();
		$cache = TranslationCache::instance();

		// Optimized query: use COUNT with DISTINCT post_id for accurate count
		// LIKE '_fpml_pair_id%' already covers both '_fpml_pair_id' and '_fpml_pair_id_*'
		$total_translated = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) 
				 FROM {$wpdb->postmeta} 
				 WHERE meta_key LIKE %s",
				$wpdb->esc_like( '_fpml_pair_id' ) . '%'
			)
		);

		$state_counts = $queue->get_state_counts();
		$queue_pending = isset( $state_counts['pending'] ) ? $state_counts['pending'] : 0;
		$queue_completed = isset( $state_counts['completed'] ) ? $state_counts['completed'] : 0;
		$queue_failed = isset( $state_counts['failed'] ) ? $state_counts['failed'] : 0;

		$cache_stats = $cache ? $cache->get_stats() : array( 'hits' => 0, 'misses' => 1 );
		$cache_hit_rate = round( 
			( $cache_stats['hits'] / max( 1, $cache_stats['hits'] + $cache_stats['misses'] ) ) * 100 
		);

		// Calculate estimated cost from pending queue
		$estimated_cost = $this->calculate_queue_cost( $queue );

		// Get total cost from completed jobs (if tracked)
		$total_cost = $this->get_total_api_cost();

		// Get average translation time (if tracked)
		$avg_translation_time = $this->get_average_translation_time();

		// Get translations today
		$translations_today = $this->get_translations_today();

		$stats = array(
			'total_translated'     => (int) $total_translated,
			'queue_pending'        => (int) $queue_pending,
			'queue_completed'      => (int) $queue_completed,
			'queue_failed'         => (int) $queue_failed,
			'estimated_cost'       => (float) $estimated_cost,
			'total_cost'           => (float) $total_cost,
			'cache_hit_rate'       => (int) $cache_hit_rate,
			'avg_translation_time' => (float) $avg_translation_time,
			'translations_today'   => (int) $translations_today,
		);

		// Cache for 5 minutes
		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Calculate estimated cost from queue.
	 *
	 * @since 0.5.0
	 * @since 0.10.0 Improved calculation with actual queue job data
	 *
	 * @param Queue $queue Queue instance.
	 * @return float Estimated cost.
	 */
	protected function calculate_queue_cost( Queue $queue ): float {
		// Get pending jobs to estimate cost
		$pending_jobs = $queue->get_by_state( [ 'pending' ], 100 ); // Limit to 100 for performance
		
		if ( empty( $pending_jobs ) ) {
			return 0.0;
		}

		$total_chars = 0;
		foreach ( $pending_jobs as $job ) {
			// Estimate characters from job data
			$job_text = isset( $job->data ) ? (string) $job->data : '';
			$total_chars += mb_strlen( $job_text, 'UTF-8' );
		}

		// OpenAI GPT-5 Nano pricing: ~€0.00011 per 1000 characters
		// Using average of 500 chars per job if no data available
		if ( $total_chars === 0 ) {
			$total_chars = count( $pending_jobs ) * 500;
		}

		// Cost per 1000 characters: €0.00011
		$cost_per_1k_chars = 0.00011;
		$estimated_cost = ( $total_chars / 1000 ) * $cost_per_1k_chars;

		return round( $estimated_cost, 4 );
	}

	/**
	 * Get total API cost from completed translations.
	 *
	 * @since 0.10.0
	 *
	 * @return float Total cost tracked.
	 */
	protected function get_total_api_cost(): float {
		global $wpdb;

		// Try to get total cost from queue meta if tracked
		$total_cost = $wpdb->get_var(
			"SELECT SUM(CAST(meta_value AS DECIMAL(10,4)))
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_fpml_translation_cost'
			 AND meta_value > 0"
		);

		return (float) ( $total_cost ?: 0.0 );
	}

	/**
	 * Get average translation time in seconds.
	 *
	 * @since 0.10.0
	 *
	 * @return float Average time in seconds, or 0 if not tracked.
	 */
	protected function get_average_translation_time(): float {
		global $wpdb;

		// Try to get average time from queue meta if tracked
		$avg_time = $wpdb->get_var(
			"SELECT AVG(CAST(meta_value AS DECIMAL(10,2)))
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_fpml_translation_time'
			 AND meta_value > 0"
		);

		return (float) ( $avg_time ?: 0.0 );
	}

	/**
	 * Get number of translations created today.
	 *
	 * @since 0.10.0
	 *
	 * @return int Number of translations today.
	 */
	protected function get_translations_today(): int {
		global $wpdb;

		$today_start = gmdate( 'Y-m-d 00:00:00' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id)
				 FROM {$wpdb->postmeta}
				 WHERE meta_key LIKE %s
				 AND post_id IN (
					 SELECT ID FROM {$wpdb->posts}
					 WHERE post_date >= %s
					 AND post_type != 'revision'
				 )",
				$wpdb->esc_like( '_fpml_pair_id' ) . '%',
				$today_start
			)
		);

		return (int) ( $count ?: 0 );
	}
}

