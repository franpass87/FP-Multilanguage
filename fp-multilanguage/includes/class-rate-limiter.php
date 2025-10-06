<?php
/**
 * Rate limiter for API requests.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage rate limiting for translation providers.
 *
 * @since 0.3.2
 */
class FPML_Rate_Limiter {
	/**
	 * Transient prefix for rate limit data.
	 */
	const TRANSIENT_PREFIX = 'fpml_rate_limit_';

	/**
	 * Default requests per minute.
	 */
	const DEFAULT_RPM = 60;

	/**
	 * Check if a request can be made.
	 *
	 * @since 0.3.2
	 *
	 * @param string $provider       Provider slug.
	 * @param int    $max_per_minute Maximum requests per minute.
	 *
	 * @return bool
	 */
	public static function can_make_request( $provider, $max_per_minute = self::DEFAULT_RPM ) {
		$key  = self::TRANSIENT_PREFIX . sanitize_key( $provider );
		$data = get_transient( $key );

		if ( false === $data || ! is_array( $data ) ) {
			return true;
		}

		// Reset if period expired
		$now = time();
		if ( $now >= $data['reset'] ) {
			return true;
		}

		// Check if under limit
		$count = isset( $data['count'] ) ? (int) $data['count'] : 0;

		return $count < $max_per_minute;
	}

	/**
	 * Record a request.
	 *
	 * @since 0.3.2
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return void
	 */
	public static function record_request( $provider ) {
		$key  = self::TRANSIENT_PREFIX . sanitize_key( $provider );
		$data = get_transient( $key );
		$now  = time();

		if ( false === $data || ! is_array( $data ) ) {
			$data = array(
				'count' => 0,
				'reset' => $now + MINUTE_IN_SECONDS,
			);
		}

		// Reset if period expired
		if ( $now >= $data['reset'] ) {
			$data = array(
				'count' => 0,
				'reset' => $now + MINUTE_IN_SECONDS,
			);
		}

		$data['count']++;

		set_transient( $key, $data, MINUTE_IN_SECONDS );
	}

	/**
	 * Get current rate limit status.
	 *
	 * @since 0.3.2
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return array
	 */
	public static function get_status( $provider ) {
		$key  = self::TRANSIENT_PREFIX . sanitize_key( $provider );
		$data = get_transient( $key );
		$now  = time();

		if ( false === $data || ! is_array( $data ) ) {
			return array(
				'count'     => 0,
				'reset_in'  => 0,
				'available' => true,
			);
		}

		$count    = isset( $data['count'] ) ? (int) $data['count'] : 0;
		$reset    = isset( $data['reset'] ) ? (int) $data['reset'] : $now;
		$reset_in = max( 0, $reset - $now );

		return array(
			'count'     => $count,
			'reset_in'  => $reset_in,
			'available' => $now >= $reset || $count < self::DEFAULT_RPM,
		);
	}

	/**
	 * Reset rate limit for a provider.
	 *
	 * @since 0.3.2
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return void
	 */
	public static function reset( $provider ) {
		$key = self::TRANSIENT_PREFIX . sanitize_key( $provider );
		delete_transient( $key );
	}

	/**
	 * Wait until rate limit allows next request.
	 *
	 * @since 0.3.2
	 *
	 * @param string $provider       Provider slug.
	 * @param int    $max_per_minute Maximum requests per minute.
	 *
	 * @return void
	 */
	public static function wait_if_needed( $provider, $max_per_minute = self::DEFAULT_RPM ) {
		$status = self::get_status( $provider );

		if ( ! $status['available'] && $status['reset_in'] > 0 ) {
			// Wait until reset (max 60 seconds)
			$wait_seconds = min( $status['reset_in'], 60 );
			
			if ( function_exists( 'wp_sleep' ) ) {
				wp_sleep( $wait_seconds );
			} else {
				sleep( $wait_seconds );
			}
		}
	}
}
