<?php
/**
 * ACF Support Field Whitelist - Manages ACF field whitelist.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\ACF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages ACF field whitelist.
 *
 * @since 0.10.0
 */
class FieldWhitelist {
	/**
	 * Add ACF fields to whitelist.
	 *
	 * @since 0.10.0
	 *
	 * @param array $whitelist Current whitelist.
	 * @return array
	 */
	public function add_acf_fields_to_whitelist( array $whitelist ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $whitelist;
		}

		// Get all field groups
		$field_groups = acf_get_field_groups();

		foreach ( $field_groups as $group ) {
			if ( ! function_exists( 'acf_get_fields' ) ) {
				continue;
			}

			$fields = acf_get_fields( $group['key'] );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				// Add translatable fields
				if ( $this->is_translatable_field_type( $field['type'] ) ) {
					$field_name = $field['name'];

					if ( ! in_array( $field_name, $whitelist, true ) ) {
						$whitelist[] = $field_name;
					}
				}
			}
		}

		return $whitelist;
	}

	/**
	 * Check if a field type is translatable.
	 *
	 * @since 0.10.0
	 *
	 * @param string $type ACF field type.
	 * @return bool
	 */
	protected function is_translatable_field_type( string $type ): bool {
		$translatable_types = array(
			'text',
			'textarea',
			'wysiwyg',
			'email',
			'url',
			'post_object',
			'relationship',
			'taxonomy',
			'repeater',
			'flexible_content',
			'clone',
		);

		return in_array( $type, $translatable_types, true );
	}
}















