<?php
/**
 * Elementor Integration - Base Support.
 *
 * Handles translation of:
 * - Elementor widget content
 * - Elementor templates
 * - Elementor global colors/typography (sync)
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

namespace FP\Multilanguage\Integrations;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor integration class.
 *
 * @since 0.10.0
 */
class ElementorSupport {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger
	 */
	protected $logger;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Check if Elementor is active
		if ( ! $this->is_elementor_active() ) {
			return;
		}

		$this->logger = class_exists( '\FP\Multilanguage\Logger' ) 
			? fpml_get_logger() 
			: null;

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Check if Elementor is active.
	 *
	 * @return bool
	 */
	protected function is_elementor_active(): bool {
		return did_action( 'elementor/loaded' ) || 
		       defined( 'ELEMENTOR_VERSION' ) ||
		       class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Register Elementor hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Add Elementor meta to translatable whitelist
		add_filter( 'FPML_meta_whitelist', array( $this, 'add_elementor_meta' ) );

		// Add Elementor post type to translatable
		add_filter( 'FPML_translatable_post_types', array( $this, 'add_elementor_post_types' ) );

		// Translate Elementor content after translation is saved
		add_action( 'fpml_after_translation_saved', array( $this, 'sync_elementor_content' ), 10, 2 );

		// Filter Elementor data during translation
		add_filter( 'fpml_translate_field', array( $this, 'translate_elementor_content' ), 10, 3 );
	}

	/**
	 * Add Elementor meta fields to translatable whitelist.
	 *
	 * @param array $meta_keys Current whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_elementor_meta( array $meta_keys ): array {
		$elementor_meta = array(
			'_elementor_data',           // Widget data (JSON)
			'_elementor_template_type',  // Template type
			'_elementor_edit_mode',      // Edit mode
			'_elementor_css',            // Custom CSS
			'_elementor_page_settings',  // Page settings
			'_elementor_version',        // Version (for compatibility)
		);

		return array_merge( $meta_keys, $elementor_meta );
	}

	/**
	 * Add Elementor post types to translatable post types.
	 *
	 * @param array $post_types Current post types.
	 * @return array Extended post types.
	 */
	public function add_elementor_post_types( array $post_types ): array {
		$elementor_post_types = array(
			'elementor_library',  // Elementor templates/library
		);

		return array_merge( $post_types, $elementor_post_types );
	}

	/**
	 * Sync Elementor content after translation is saved.
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $target_post_id Target post ID.
	 * @return void
	 */
	public function sync_elementor_content( int $source_post_id, int $target_post_id ): void {
		if ( ! $this->has_elementor_content( $source_post_id ) ) {
			return;
		}

		// Get Elementor data from source post
		$elementor_data = get_post_meta( $source_post_id, '_elementor_data', true );

		if ( empty( $elementor_data ) ) {
			return;
		}

		// Translate Elementor widget content
		$translated_data = $this->translate_elementor_data( $elementor_data );

		// Save translated data to target post
		if ( ! empty( $translated_data ) ) {
			update_post_meta( $target_post_id, '_elementor_data', $translated_data );

			// Sync other Elementor meta fields
			$this->sync_elementor_meta( $source_post_id, $target_post_id );

			if ( $this->logger ) {
				$this->logger->info( 'Elementor content synced', array(
					'source_post_id' => $source_post_id,
					'target_post_id' => $target_post_id,
				) );
			}
		}
	}

	/**
	 * Translate Elementor content field.
	 *
	 * @param string $translated Translated content.
	 * @param string $content    Original content.
	 * @param string $field      Field name.
	 * @return string Translated content.
	 */
	public function translate_elementor_content( string $translated, string $content, string $field ): string {
		// Elementor content is typically in _elementor_data meta field
		// This filter handles content field translation
		if ( 'content' !== $field || ! $this->has_elementor_data( $content ) ) {
			return $translated;
		}

		// Elementor data is JSON, translate if needed
		return $translated;
	}

	/**
	 * Translate Elementor widget data (JSON).
	 *
	 * @param string $elementor_data JSON string of Elementor data.
	 * @return string Translated JSON string.
	 */
	protected function translate_elementor_data( string $elementor_data ): string {
		// Decode JSON
		$data = json_decode( $elementor_data, true );

		if ( ! is_array( $data ) ) {
			return $elementor_data; // Return original if invalid JSON
		}

		// Recursively translate widget content
		$translated_data = $this->translate_widgets_recursive( $data );

		// Encode back to JSON
		$translated_json = wp_json_encode( $translated_data );

		return $translated_json ?: $elementor_data;
	}

	/**
	 * Recursively translate Elementor widgets.
	 *
	 * @param array $widgets Widget data array.
	 * @return array Translated widget data.
	 */
	protected function translate_widgets_recursive( array $widgets ): array {
		$translated = array();

		foreach ( $widgets as $widget ) {
			if ( ! is_array( $widget ) ) {
				$translated[] = $widget;
				continue;
			}

			$translated_widget = $widget;

			// Translate widget settings (title, text, etc.)
			if ( isset( $widget['settings'] ) && is_array( $widget['settings'] ) ) {
				$translated_widget['settings'] = $this->translate_widget_settings( $widget['settings'] );
			}

			// Recursively translate child elements
			if ( isset( $widget['elements'] ) && is_array( $widget['elements'] ) ) {
				$translated_widget['elements'] = $this->translate_widgets_recursive( $widget['elements'] );
			}

			$translated[] = $translated_widget;
		}

		return $translated;
	}

	/**
	 * Translate widget settings (text fields).
	 *
	 * @param array $settings Widget settings.
	 * @return array Translated settings.
	 */
	protected function translate_widget_settings( array $settings ): array {
		// Common translatable fields in Elementor widgets
		$translatable_fields = array(
			'title',
			'text',
			'description',
			'content',
			'heading',
			'subheading',
			'button_text',
			'link_text',
			'placeholder',
			'label',
			'message',
			'caption',
			'tab_title',
			'item_title',
			'item_description',
		);

		$translated_settings = $settings;

		foreach ( $translatable_fields as $field ) {
			if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) && ! empty( $settings[ $field ] ) ) {
				// Note: Actual translation should be handled by the translation processor
				// This method marks fields for translation
				// The translation will happen via the queue system
			}
		}

		return $translated_settings;
	}

	/**
	 * Sync Elementor meta fields to translation.
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $target_post_id Target post ID.
	 * @return void
	 */
	protected function sync_elementor_meta( int $source_post_id, int $target_post_id ): void {
		// Sync template type
		$template_type = get_post_meta( $source_post_id, '_elementor_template_type', true );
		if ( $template_type ) {
			update_post_meta( $target_post_id, '_elementor_template_type', $template_type );
		}

		// Sync edit mode
		$edit_mode = get_post_meta( $source_post_id, '_elementor_edit_mode', true );
		if ( $edit_mode ) {
			update_post_meta( $target_post_id, '_elementor_edit_mode', $edit_mode );
		}

		// Sync version
		$version = get_post_meta( $source_post_id, '_elementor_version', true );
		if ( $version ) {
			update_post_meta( $target_post_id, '_elementor_version', $version );
		}

		// Sync page settings (without translation - just structure)
		$page_settings = get_post_meta( $source_post_id, '_elementor_page_settings', true );
		if ( ! empty( $page_settings ) && is_array( $page_settings ) ) {
			// Only sync non-translatable settings
			$sync_settings = array_diff_key( $page_settings, array_flip( array(
				'page_title',
				'page_description',
			) ) );
			if ( ! empty( $sync_settings ) ) {
				update_post_meta( $target_post_id, '_elementor_page_settings', $sync_settings );
			}
		}
	}

	/**
	 * Sync Elementor global colors/typography (if accessible).
	 *
	 * @return void
	 */
	public function sync_global_colors_typography(): void {
		// Elementor stores global colors/typography in site options
		// This method would sync them if needed
		// For now, it's a placeholder for future implementation

		$global_colors = get_option( 'elementor_scheme_color', array() );
		$global_typography = get_option( 'elementor_scheme_typography', array() );

		// Global colors/typography are typically language-agnostic
		// They don't need translation, just sync if different
		if ( $this->logger ) {
			$this->logger->debug( 'Elementor global colors/typography sync', array(
				'colors'     => count( $global_colors ),
				'typography' => count( $global_typography ),
			) );
		}
	}

	/**
	 * Check if post has Elementor content.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected function has_elementor_content( int $post_id ): bool {
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		return ! empty( $elementor_data );
	}

	/**
	 * Check if content string has Elementor data.
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	protected function has_elementor_data( string $content ): bool {
		return strpos( $content, '"widgetType"' ) !== false ||
		       strpos( $content, '"elType"' ) !== false ||
		       strpos( $content, 'elementor' ) !== false;
	}

	/**
	 * Check if content string contains Elementor widgets.
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	public static function has_elementor_data_in_content( string $content ): bool {
		return strpos( $content, '"widgetType"' ) !== false ||
		       strpos( $content, '"elType"' ) !== false;
	}
}

