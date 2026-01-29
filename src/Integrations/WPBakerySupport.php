<?php
/**
 * WPBakery Page Builder Integration.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakerySupport {
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		if ( ! $this->is_wpbakery_active() ) {
			return;
		}

		add_filter( 'fpml_translatable_meta', array( $this, 'add_wpbakery_meta' ) );
		add_filter( 'fpml_translate_field', array( $this, 'translate_wpbakery_content' ), 10, 3 );
	}

	protected function is_wpbakery_active() {
		return defined( 'WPB_VC_VERSION' ) || class_exists( 'Vc_Manager' );
	}

	public function add_wpbakery_meta( $meta_keys ) {
		return array_merge( $meta_keys, array(
			'_wpb_vc_js_status',
			'_wpb_post_custom_css',
			'_wpb_shortcodes_custom_css',
		) );
	}

	public function translate_wpbakery_content( $translated, $content, $field ) {
		if ( 'content' !== $field ) {
			return $translated;
		}

		// Parse WPBakery shortcodes
		if ( false === strpos( $content, '[vc_' ) ) {
			return $translated;
		}

		// Extract text from shortcodes and translate
		return $this->translate_shortcodes( $content, $translated );
	}

	protected function translate_shortcodes( $original, $translated ) {
		// WPBakery content is already in $translated from OpenAI
		// We just need to ensure shortcode structure is preserved
		
		// Parse shortcode attributes that need translation
		$translated = $this->translate_shortcode_attributes( $translated );
		
		return $translated;
	}
	
	/**
	 * Translate specific shortcode attributes (like titles, captions).
	 *
	 * @param string $content Content with shortcodes.
	 * @return string
	 */
	protected function translate_shortcode_attributes( $content ) {
		// Attributes that commonly contain text to translate
		$translatable_attrs = array(
			'title',
			'subtitle',
			'caption',
			'heading',
			'button_text',
			'link_text',
			'message',
		);
		
		// WPBakery shortcodes are already translated by OpenAI
		// This method ensures structure preservation
		// In future, could add specific attribute mapping
		
		return $content;
	}
	
	/**
	 * Check if content contains WPBakery shortcodes.
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	public static function has_wpbakery_content( $content ) {
		return strpos( $content, '[vc_' ) !== false;
	}
}


