<?php
/**
 * Cache Stats - Handles cache statistics.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

namespace FP\Multilanguage\Core\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cache statistics collection and reporting.
 *
 * @since 0.4.0
 */
class CacheStats {
	/**
	 * Cache storage instance.
	 *
	 * @var CacheStorage
	 */
	protected $storage;

	/**
	 * Constructor.
	 *
	 * @param CacheStorage $storage Cache storage instance.
	 */
	public function __construct( CacheStorage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Get cache statistics.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_stats() {
		$stats = $this->storage->get_stats();
		$total     = $stats['hits'] + $stats['misses'];
		$hit_rate  = $total > 0 ? ( $stats['hits'] / $total ) * 100 : 0;

		return array(
			'hits'     => $stats['hits'],
			'misses'   => $stats['misses'],
			'total'    => $total,
			'hit_rate' => round( $hit_rate, 2 ),
		);
	}

	/**
	 * Reset statistics.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function reset_stats() {
		$this->storage->reset_stats();
	}
}















