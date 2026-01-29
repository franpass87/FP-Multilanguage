<?php
/**
 * OpenAI Provider Retry Manager - Manages retry logic and backoff.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Providers\OpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages retry logic and exponential backoff.
 *
 * @since 0.10.0
 */
class RetryManager {
	/**
	 * Apply exponential backoff delay.
	 *
	 * @since 0.10.0
	 *
	 * @param int $attempt Current attempt number.
	 * @return void
	 */
	public function backoff( int $attempt ): void {
		$delay = min( 30, pow( 2, $attempt ) );
		usleep( $delay * 1000000 );
	}

	/**
	 * Check if error is retryable.
	 *
	 * @since 0.10.0
	 *
	 * @param int $code HTTP status code.
	 * @return bool
	 */
	public function is_retryable_error( int $code ): bool {
		return in_array( $code, array( 429, 500, 502, 503, 504 ), true );
	}

	/**
	 * Check if error is quota exceeded.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $code        HTTP status code.
	 * @param string $error_type  Error type from API.
	 * @param string $api_message API error message.
	 * @return bool
	 */
	public function is_quota_error( int $code, string $error_type, string $api_message ): bool {
		return 429 === $code && (
			'insufficient_quota' === $error_type ||
			false !== stripos( $api_message, 'quota' ) ||
			false !== stripos( $api_message, 'billing' )
		);
	}
}















