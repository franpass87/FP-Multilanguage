<?php
/**
 * Rate Limiter for REST API endpoints.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevent API abuse with rate limiting.
 *
 * @since 0.5.0
 */
class ApiRateLimiter {
	/**
	 * Max requests per minute.
	 */
	const MAX_REQUESTS_PER_MINUTE = 60;

	/**
	 * Check if request is within rate limit.
	 *
	 * @param string $ip IP address.
	 *
	 * @return bool|\WP_Error True if OK, WP_Error if limited.
	 */
	public static function check( $ip = null ) {
		if ( null === $ip ) {
			$ip = self::get_client_ip();
		}

		$key   = 'fpml_api_limit_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_REQUESTS_PER_MINUTE ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Troppi requ request. Riprova tra 1 minuto.', 'fp-multilanguage' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	protected static function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}

