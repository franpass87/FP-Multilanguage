<?php
/**
 * Validator Implementation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validator implementation.
 *
 * @since 1.0.0
 */
class Validator implements ValidatorInterface {
	/**
	 * Validate a value.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $rule  Validation rule.
	 * @param array  $args  Additional arguments for the rule.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate( $value, string $rule, array $args = array() ): bool {
		$method = 'validate' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $rule ) ) );

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $value, $args );
		}

		// Default: non-empty check
		return ! empty( $value );
	}

	/**
	 * Validate multiple values against rules.
	 *
	 * @param array $data   Data to validate.
	 * @param array $rules  Validation rules (key => rule or key => [rule, args]).
	 * @return array Validation errors (empty if valid).
	 */
	public function validateAll( array $data, array $rules ): array {
		$errors = array();

		foreach ( $rules as $key => $rule ) {
			$value = $data[ $key ] ?? null;
			$rule_args = array();

			if ( is_array( $rule ) ) {
				$rule_name = $rule[0] ?? 'required';
				$rule_args = $rule[1] ?? array();
			} else {
				$rule_name = $rule;
			}

			if ( ! $this->validate( $value, $rule_name, $rule_args ) ) {
				$errors[ $key ] = sprintf( 'Validation failed for %s with rule %s', $key, $rule_name );
			}
		}

		return $errors;
	}

	/**
	 * Validate required.
	 *
	 * @param mixed $value Value.
	 * @param array $args  Arguments.
	 * @return bool
	 */
	protected function validateRequired( $value, array $args = array() ): bool {
		return ! empty( $value );
	}

	/**
	 * Validate email.
	 *
	 * @param mixed $value Value.
	 * @param array $args  Arguments.
	 * @return bool
	 */
	protected function validateEmail( $value, array $args = array() ): bool {
		return is_email( $value );
	}

	/**
	 * Validate URL.
	 *
	 * @param mixed $value Value.
	 * @param array $args  Arguments.
	 * @return bool
	 */
	protected function validateUrl( $value, array $args = array() ): bool {
		return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Validate min length.
	 *
	 * @param mixed $value Value.
	 * @param array $args  Arguments (min).
	 * @return bool
	 */
	protected function validateMin( $value, array $args = array() ): bool {
		$min = $args[0] ?? 0;
		return strlen( (string) $value ) >= $min;
	}

	/**
	 * Validate max length.
	 *
	 * @param mixed $value Value.
	 * @param array $args  Arguments (max).
	 * @return bool
	 */
	protected function validateMax( $value, array $args = array() ): bool {
		$max = $args[0] ?? PHP_INT_MAX;
		return strlen( (string) $value ) <= $max;
	}

	/**
	 * Validate numeric.
	 *
	 * @param mixed $value Value.
	 * @param array $args  Arguments.
	 * @return bool
	 */
	protected function validateNumeric( $value, array $args = array() ): bool {
		return is_numeric( $value );
	}

	/**
	 * Validate integer.
	 *
	 * @param mixed $value Value.
	 * @param array $args  Arguments.
	 * @return bool
	 */
	protected function validateInteger( $value, array $args = array() ): bool {
		return filter_var( $value, FILTER_VALIDATE_INT ) !== false;
	}
}













