<?php
/**
 * Plugin Detector Field Detector - Detects translatable fields from plugins.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\PluginDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects translatable fields from plugins.
 *
 * @since 0.10.0
 */
class FieldDetector {
	/**
	 * Detect Meta Box fields dynamically.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function detect_metabox_fields(): array {
		if ( ! class_exists( 'RWMB_Core' ) ) {
			return array();
		}

		$fields = array();

		// Get all registered meta boxes
		$meta_boxes = apply_filters( 'rwmb_meta_boxes', array() );

		foreach ( $meta_boxes as $meta_box ) {
			if ( empty( $meta_box['fields'] ) ) {
				continue;
			}

			foreach ( $meta_box['fields'] as $field ) {
				if ( $this->is_translatable_metabox_field( $field ) ) {
					$fields[] = $field['id'];
				}
			}
		}

		return $fields;
	}

	/**
	 * Check if Meta Box field is translatable.
	 *
	 * @since 0.10.0
	 *
	 * @param array $field Field config.
	 * @return bool
	 */
	protected function is_translatable_metabox_field( array $field ): bool {
		$translatable_types = array(
			'text',
			'textarea',
			'wysiwyg',
			'email',
			'url',
			'post',
			'taxonomy',
		);

		return isset( $field['type'] ) && in_array( $field['type'], $translatable_types, true );
	}

	/**
	 * Detect Pods fields dynamically.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function detect_pods_fields(): array {
		if ( ! function_exists( 'pods_api' ) ) {
			return array();
		}

		$fields = array();

		try {
			$api = pods_api();
			$pods = $api->load_pods();

			foreach ( $pods as $pod ) {
				if ( empty( $pod['fields'] ) ) {
					continue;
				}

				foreach ( $pod['fields'] as $field ) {
					if ( $this->is_translatable_pods_field( $field ) ) {
						$fields[] = $field['name'];
					}
				}
			}
		} catch ( \Exception $e ) {
			// Silently fail if Pods API is not available
		}

		return $fields;
	}

	/**
	 * Check if Pods field is translatable.
	 *
	 * @since 0.10.0
	 *
	 * @param array $field Field config.
	 * @return bool
	 */
	protected function is_translatable_pods_field( array $field ): bool {
		$translatable_types = array(
			'text',
			'wysiwyg',
			'paragraph',
			'email',
			'website',
		);

		return isset( $field['type'] ) && in_array( $field['type'], $translatable_types, true );
	}

	/**
	 * Handle Elementor data (JSON structure).
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function handle_elementor_data(): array {
		// Elementor data is stored as JSON and requires special handling
		// This is handled by the processor, we just register the field
		return array();
	}
}















