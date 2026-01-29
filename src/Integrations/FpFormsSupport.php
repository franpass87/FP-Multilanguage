<?php
/**
 * FP Forms Integration.
 *
 * Provides bidirectional integration between FP-Multilanguage and FP-Forms:
 * - Translate fp_form post type (title, content/description)
 * - Sync Form settings meta (_fp_form_settings) - messages, button text, email subjects/bodies are translatable
 * - Sync Form fields from custom table - labels, placeholders, descriptions, error messages are translatable
 * - Ensure fp_form post type is translatable
 *
 * @package FP_Multilanguage
 * @since 0.9.1
 */

namespace FP\Multilanguage\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FP Forms integration class.
 *
 * @since 0.9.1
 */
class FpFormsSupport {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * FP Forms meta keys.
	 */
	const FP_FORM_SETTINGS = '_fp_form_settings'; // TRANSLATABLE (contains messages, button text, email subjects/bodies)

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		// Only if FP-Forms is active
		if ( ! $this->is_fp_forms_active() ) {
			return;
		}

		// Add fp_form post type to translatable post types
		add_filter( '\FPML_translatable_post_types', array( $this, 'add_fp_form_post_type' ) );

		// Add FP-Forms meta to translatable whitelist
		add_filter( '\FPML_meta_whitelist', array( $this, 'add_fp_forms_meta_to_whitelist' ) );

		// Sync form meta and fields after translation
		add_action( 'fpml_after_translation_saved', array( $this, 'sync_form_meta_to_translation' ), 10, 2 );
	}

	/**
	 * Add fp_form post type to translatable post types.
	 *
	 * @param array $post_types Current translatable post types.
	 * @return array Extended post types.
	 */
	public function add_fp_form_post_type( $post_types ) {
		if ( ! in_array( 'fp_form', $post_types, true ) ) {
			$post_types[] = 'fp_form';
		}
		return $post_types;
	}

	/**
	 * Add FP-Forms meta keys to translatable whitelist.
	 *
	 * @param array $whitelist Current meta whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_fp_forms_meta_to_whitelist( $whitelist ) {
		$fp_forms_meta = array(
			// Form settings contain translatable content (messages, button text, email subjects/bodies)
			self::FP_FORM_SETTINGS,
		);

		return array_merge( $whitelist, $fp_forms_meta );
	}

	/**
	 * Check if FP-Forms is active.
	 *
	 * @return bool
	 */
	protected function is_fp_forms_active() {
		return class_exists( '\FPForms\Plugin' ) || 
		       defined( 'FP_FORMS_VERSION' ) ||
		       defined( 'FP_FORMS_PLUGIN_FILE' );
	}

	/**
	 * Sync form meta and fields from original to translated post.
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 */
	public function sync_form_meta_to_translation( $translated_id, $original_id ) {
		if ( ! $translated_id || ! $original_id ) {
			return;
		}

		// Only sync for fp_form post type
		$original_post = get_post( $original_id );
		if ( ! $original_post || 'fp_form' !== $original_post->post_type ) {
			return;
		}

		$synced_count = 0;

		// 1. FORM SETTINGS - TRANSLATE (contains messages, button text, email subjects/bodies)
		$original_settings = get_post_meta( $original_id, self::FP_FORM_SETTINGS, true );
		$translated_settings = get_post_meta( $translated_id, self::FP_FORM_SETTINGS, true );
		
		if ( empty( $translated_settings ) && ! empty( $original_settings ) && is_array( $original_settings ) ) {
			// Copy original settings first
			update_post_meta( $translated_id, self::FP_FORM_SETTINGS, $original_settings );
			// Enqueue for translation
			$this->enqueue_forms_meta_translation( $translated_id, self::FP_FORM_SETTINGS, $original_settings, $original_id );
			$synced_count++;
		}

		// 2. FORM FIELDS - Copy from custom table and enqueue translatable content
		$fields_synced = $this->sync_form_fields( $translated_id, $original_id );
		$synced_count += $fields_synced;

		/**
		 * Fires after form meta and fields sync.
		 *
		 * @param int $translated_id Translated post ID.
		 * @param int $original_id   Original post ID.
		 * @param int $synced_count  Number of meta fields and fields synced.
		 */
		do_action( 'fpml_forms_meta_synced', $translated_id, $original_id, $synced_count );

		$this->log_sync( $translated_id, "Form meta and fields sync completed: {$synced_count} items" );
	}

	/**
	 * Sync form fields from custom table.
	 *
	 * @param int $translated_id Translated form ID.
	 * @param int $original_id   Original form ID.
	 * @return int Number of fields synced.
	 */
	protected function sync_form_fields( $translated_id, $original_id ) {
		if ( ! class_exists( '\FPForms\Forms\Manager' ) ) {
			return 0;
		}

		$forms_manager = \FPForms\Plugin::instance()->forms;
		if ( ! $forms_manager ) {
			return 0;
		}

		// Get original form fields
		$original_fields = $forms_manager->get_fields( $original_id );
		if ( empty( $original_fields ) || ! is_array( $original_fields ) ) {
			return 0;
		}

		// Get translated form fields
		$translated_fields = $forms_manager->get_fields( $translated_id );
		
		// If translated form already has fields, skip
		if ( ! empty( $translated_fields ) && is_array( $translated_fields ) ) {
			return 0;
		}

		// Copy fields to translated form
		$fields_to_copy = array();
		foreach ( $original_fields as $field ) {
			// Skip if field is not an array
			if ( ! is_array( $field ) ) {
				continue;
			}
			
			$field_data = array(
				'type' => isset( $field['type'] ) ? $field['type'] : '',
				'label' => isset( $field['label'] ) ? $field['label'] : '',
				'name' => isset( $field['name'] ) ? $field['name'] : '',
				'required' => isset( $field['required'] ) ? $field['required'] : false,
				'options' => isset( $field['options'] ) ? $field['options'] : array(),
			);

			// Enqueue translatable field content for translation
			$this->enqueue_form_field_translation( $translated_id, $field_data, $original_id );

			$fields_to_copy[] = $field_data;
		}

		// Save fields to translated form
		if ( ! empty( $fields_to_copy ) ) {
			$forms_manager->update_fields( $translated_id, $fields_to_copy );
			return count( $fields_to_copy );
		}

		return 0;
	}

	/**
	 * Enqueue form field translatable content for translation.
	 *
	 * @param int    $translated_id Translated form ID.
	 * @param array  $field_data    Field data.
	 * @param int    $original_id   Original form ID.
	 */
	protected function enqueue_form_field_translation( $translated_id, $field_data, $original_id ) {
		$queue = \FP\Multilanguage\Core\Container::get( 'queue' );
		if ( ! $queue ) {
			$queue = fpml_get_queue();
		}

		if ( ! $queue || ! $original_id ) {
			return;
		}

		// Skip if field name is empty
		if ( empty( $field_data['name'] ) ) {
			return;
		}

		// Enqueue field label
		if ( ! empty( $field_data['label'] ) ) {
			$content_hash = md5( $field_data['label'] );
			$field_name = 'form_field:' . $field_data['name'] . ':label';
			$queue->enqueue( 'post', $original_id, $field_name, $content_hash );
		}

		// Enqueue field options (placeholder, description, error_message)
		if ( ! empty( $field_data['options'] ) && is_array( $field_data['options'] ) ) {
			$translatable_options = array( 'placeholder', 'description', 'error_message' );
			
			foreach ( $translatable_options as $option_key ) {
				if ( ! empty( $field_data['options'][ $option_key ] ) ) {
					$content_hash = md5( $field_data['options'][ $option_key ] );
					$field_name = 'form_field:' . $field_data['name'] . ':' . $option_key;
					$queue->enqueue( 'post', $original_id, $field_name, $content_hash );
				}
			}

			// Handle select/radio/checkbox options
			if ( ! empty( $field_data['type'] ) && in_array( $field_data['type'], array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $field_data['options']['options'] ) ) {
				$options = $field_data['options']['options'];
				if ( is_array( $options ) ) {
					foreach ( $options as $index => $option ) {
						if ( is_string( $option ) && ! empty( $option ) ) {
							$content_hash = md5( $option );
							$field_name = 'form_field:' . $field_data['name'] . ':option:' . $index;
							$queue->enqueue( 'post', $original_id, $field_name, $content_hash );
						}
					}
				}
			}
		}
	}

	/**
	 * Enqueue Forms meta field for translation.
	 *
	 * @param int    $translated_id Translated post ID (TARGET post).
	 * @param string $meta_key      Meta key to translate.
	 * @param mixed  $value         Original value to translate.
	 * @param int    $original_id   Optional. Original post ID (SOURCE). If not provided, will be retrieved from meta.
	 */
	protected function enqueue_forms_meta_translation( $translated_id, $meta_key, $value, $original_id = null ) {
		// Get queue instance
		$queue = \FP\Multilanguage\Core\Container::get( 'queue' );
		if ( ! $queue ) {
			$queue = fpml_get_queue();
		}

		if ( ! $queue ) {
			return;
		}

		// Get source post ID (use provided original_id or retrieve from meta)
		if ( null === $original_id ) {
			$original_id = (int) get_post_meta( $translated_id, '_fpml_pair_source_id', true );
		}

		if ( ! $original_id ) {
			// If no source found, log error and return
			$this->log_sync( $translated_id, "ERROR: Could not find source post ID for {$meta_key}" );
			return;
		}

		// For _fp_form_settings, enqueue translatable sub-fields
		if ( self::FP_FORM_SETTINGS === $meta_key && is_array( $value ) ) {
			$translatable_settings = array(
				'submit_button_text',
				'success_message',
				'notification_subject',
				'notification_message',
				'staff_notification_subject',
				'staff_notification_message',
			);

			foreach ( $translatable_settings as $setting_key ) {
				if ( ! empty( $value[ $setting_key ] ) && is_string( $value[ $setting_key ] ) ) {
					$content_hash = md5( $value[ $setting_key ] );
					$field_name = 'meta:' . $meta_key . ':' . $setting_key;
					$queue->enqueue( 'post', $original_id, $field_name, $content_hash );
				}
			}
		} else {
			// Convert value to string for hashing
			if ( is_array( $value ) ) {
				$value_string = wp_json_encode( $value );
				// wp_json_encode can return false on error
				if ( false === $value_string ) {
					$value_string = '';
				}
			} else {
				$value_string = (string) $value;
			}
			$content_hash = md5( $value_string );

			// Enqueue meta field for translation (format: "meta:meta_key")
			$field_name = 'meta:' . $meta_key;
			$queue->enqueue( 'post', $original_id, $field_name, $content_hash );
		}

		$this->log_sync( $translated_id, "Enqueued {$meta_key} for translation (source: {$original_id})" );
	}

	/**
	 * Log sync action.
	 *
	 * @param int    $post_id Translated post ID.
	 * @param string $message Log message.
	 */
	protected function log_sync( $post_id, $message ) {
		if ( class_exists( '\FP\Multilanguage\Logger' ) ) {
			fpml_get_logger()->log(
				'info',
				'FP-Forms Integration: ' . $message,
				array(
					'post_id' => $post_id,
					'context' => 'forms_sync',
				)
			);
		}
	}
}

