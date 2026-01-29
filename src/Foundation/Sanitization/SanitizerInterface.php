<?php
/**
 * Sanitizer Interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Sanitization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizer interface for input sanitization.
 *
 * @since 1.0.0
 */
interface SanitizerInterface {
	/**
	 * Sanitize a value.
	 *
	 * @param mixed  $value Value to sanitize.
	 * @param string $type  Sanitization type.
	 * @return mixed Sanitized value.
	 */
	public function sanitize( $value, string $type = 'text' );

	/**
	 * Sanitize multiple values.
	 *
	 * @param array $data Data to sanitize (key => value).
	 * @param array $rules Sanitization rules (key => type).
	 * @return array Sanitized data.
	 */
	public function sanitizeAll( array $data, array $rules ): array;
}













