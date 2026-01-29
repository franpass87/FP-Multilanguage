<?php
/**
 * Validator Interface.
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
 * Validator interface for input validation.
 *
 * @since 1.0.0
 */
interface ValidatorInterface {
	/**
	 * Validate a value.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $rule  Validation rule.
	 * @param array  $args  Additional arguments for the rule.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate( $value, string $rule, array $args = array() ): bool;

	/**
	 * Validate multiple values against rules.
	 *
	 * @param array $data   Data to validate.
	 * @param array $rules  Validation rules (key => rule).
	 * @return array Validation errors (empty if valid).
	 */
	public function validateAll( array $data, array $rules ): array;
}













