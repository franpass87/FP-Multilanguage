<?php
/**
 * Sanitizer Implementation.
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
 * Sanitizer implementation using WordPress sanitization functions.
 *
 * @since 1.0.0
 */
class Sanitizer implements SanitizerInterface {
	/**
	 * Sanitize a value.
	 *
	 * @param mixed  $value Value to sanitize.
	 * @param string $type  Sanitization type.
	 * @return mixed Sanitized value.
	 */
	public function sanitize( $value, string $type = 'text' ) {
		$method = 'sanitize' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $type ) ) );

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $value );
		}

		// Default: text sanitization
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize multiple values.
	 *
	 * @param array $data  Data to sanitize (key => value).
	 * @param array $rules Sanitization rules (key => type).
	 * @return array Sanitized data.
	 */
	public function sanitizeAll( array $data, array $rules ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$type = $rules[ $key ] ?? 'text';
			$sanitized[ $key ] = $this->sanitize( $value, $type );
		}

		return $sanitized;
	}

	/**
	 * Sanitize text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function sanitizeText( $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize textarea.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function sanitizeTextarea( $value ): string {
		return sanitize_textarea_field( $value );
	}

	/**
	 * Sanitize email.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function sanitizeEmail( $value ): string {
		return sanitize_email( $value );
	}

	/**
	 * Sanitize URL.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function sanitizeUrl( $value ): string {
		return esc_url_raw( $value );
	}

	/**
	 * Sanitize integer.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	protected function sanitizeInteger( $value ): int {
		return absint( $value );
	}

	/**
	 * Sanitize float.
	 *
	 * @param mixed $value Value.
	 * @return float
	 */
	protected function sanitizeFloat( $value ): float {
		return (float) filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
	}

	/**
	 * Sanitize key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function sanitizeKey( $value ): string {
		return sanitize_key( $value );
	}

	/**
	 * Sanitize HTML.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function sanitizeHtml( $value ): string {
		return wp_kses_post( $value );
	}

	/**
	 * Sanitize array.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	protected function sanitizeArray( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $value );
	}
}













