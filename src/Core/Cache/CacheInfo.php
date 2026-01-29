<?php
/**
 * Cache Info - Provides information about cache size and count.
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
 * Provides information about cache size and item count.
 *
 * @since 0.4.0
 */
class CacheInfo {
	/**
	 * Get approximate cache size (transients only).
	 *
	 * @since 0.4.0
	 *
	 * @return int Size in bytes.
	 */
	public function get_cache_size() {
		global $wpdb;

		$result = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) 
			 FROM {$wpdb->options} 
			 WHERE option_name LIKE '_transient_fpml_%'"
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Get number of cached items.
	 *
	 * @since 0.4.0
	 *
	 * @return int
	 */
	public function get_cache_count() {
		global $wpdb;

		$result = $wpdb->get_var(
			"SELECT COUNT(*) 
			 FROM {$wpdb->options} 
			 WHERE option_name LIKE '_transient_fpml_%' 
			 AND option_name NOT LIKE '_transient_timeout_%'"
		);

		return $result ? (int) $result : 0;
	}
}















