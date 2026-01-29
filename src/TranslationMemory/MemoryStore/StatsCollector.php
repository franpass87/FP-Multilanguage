<?php
/**
 * Translation Memory Store Stats Collector - Collects translation memory statistics.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\TranslationMemory\MemoryStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects translation memory statistics.
 *
 * @since 0.10.0
 */
class StatsCollector {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Constructor.
	 *
	 * @param string $table Table name.
	 */
	public function __construct( string $table ) {
		$this->table = $table;
	}

	/**
	 * Get statistics.
	 *
	 * @since 0.10.0
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		global $wpdb;

		// Cache stats for 1 hour
		$stats_cache_key = 'fpml_tm_stats';
		$cached_stats    = get_transient( $stats_cache_key );

		if ( false !== $cached_stats && is_array( $cached_stats ) ) {
			return $cached_stats;
		}

		$stats = array(
			'total_segments' => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ),
			'total_reuse'    => $wpdb->get_var( "SELECT SUM(use_count) FROM {$this->table}" ),
			'avg_quality'    => $wpdb->get_var( "SELECT AVG(quality_score) FROM {$this->table} WHERE quality_score IS NOT NULL" ),
		);

		set_transient( $stats_cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}
}















