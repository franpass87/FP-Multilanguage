<?php
/**
 * Term Permalink Filter - Handles permalink filtering for taxonomy terms.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Permalink;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters permalinks for translated taxonomy terms.
 *
 * @since 0.10.0
 */
class TermPermalinkFilter {
	/**
	 * Cached settings instance.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Language resolver instance.
	 *
	 * @var \FP\Multilanguage\Language\LanguageResolver
	 */
	protected $resolver;

	/**
	 * Filter helper instance.
	 *
	 * @var FilterHelper
	 */
	protected $filter_helper;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Settings                              $settings     Settings instance.
	 * @param \FP\Multilanguage\Language\LanguageResolver $resolver     Language resolver instance.
	 * @param FilterHelper                                $filter_helper Filter helper instance.
	 */
	public function __construct( $settings, $resolver, FilterHelper $filter_helper ) {
		$this->settings      = $settings;
		$this->resolver      = $resolver;
		$this->filter_helper = $filter_helper;
	}

	/**
	 * Filter permalinks for translated terms to use /en/ prefix.
	 *
	 * @since 0.9.3
	 *
	 * @param string  $permalink The term's permalink.
	 * @param WP_Term $term      The term object.
	 *
	 * @return string
	 */
	public function filter_term_permalink( $permalink, $term ) {
		if ( is_admin() || ! $term instanceof \WP_Term ) {
			return $permalink;
		}

		$routing = $this->settings->get( 'routing_mode', 'segment' );
		if ( 'segment' !== $routing ) {
			return $permalink;
		}

		$request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$is_target_path = fpml_url_contains_target_language( $request_uri );
		$is_translation = get_term_meta( $term->term_id, '_fpml_is_translation', true );

		// Determine the actual language slug to use
		$language_manager = fpml_get_language_manager();
		$term_lang        = get_term_meta( $term->term_id, '_fpml_target_language', true );
		if ( empty( $term_lang ) ) {
			$term_lang = $this->resolver->get_current_language();
		}
		$lang_info = $language_manager->get_language_info( $term_lang );
		$lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : $term_lang;
		$lp        = preg_quote( $lang_slug, '#' );

		// Not on a target-language path: strip any stray lang prefix from the permalink
		if ( ! $is_target_path ) {
			if ( fpml_url_contains_target_language( $permalink ) ) {
				$permalink = preg_replace( '#/' . $lp . '/#', '/', $permalink );
			}
			// Remove legacy lang-prefixed slug (e.g. /en-slug/ → /slug/)
			foreach ( array( '-', '_' ) as $sep ) {
				$permalink = str_replace( '/' . $lang_slug . $sep, '/', $permalink );
			}
			return $permalink;
		}

		// On target-language path but term is not a translation: try to find the translation
		if ( ! $is_translation ) {
			$meta_key       = '_fpml_pair_id_' . $term_lang;
			$translation_id = (int) get_term_meta( $term->term_id, $meta_key, true );
			// Backward compat: legacy _fpml_pair_id (used for 'en' only)
			if ( ! $translation_id ) {
				$translation_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );
			}
			if ( $translation_id > 0 ) {
				$translation = get_term( $translation_id, $term->taxonomy );
				if ( $translation instanceof \WP_Term && get_term_meta( $translation_id, '_fpml_is_translation', true ) ) {
					$term           = $translation;
					$is_translation = true;
				} else {
					return $permalink;
				}
			} else {
				return $permalink;
			}
		}

		if ( ! $is_translation ) {
			return $permalink;
		}

		// Determine base slug: strip lang prefix (e.g. 'en-about' → 'about')
		$base_slug = $term->slug;
		foreach ( array( $lang_slug . '-', $lang_slug . '_' ) as $prefix ) {
			if ( 0 === strpos( $base_slug, $prefix ) ) {
				$base_slug = substr( $base_slug, strlen( $prefix ) );
				break;
			}
		}

		$this->filter_helper->remove_url_filters();
		try {
			$home_url = trailingslashit( home_url() );
		} finally {
			$this->filter_helper->restore_url_filters();
		}

		$parsed   = wp_parse_url( $permalink );
		$rel_path = ( $parsed && isset( $parsed['path'] ) ) ? $parsed['path'] : str_replace( $home_url, '', $permalink );

		// Strip any existing lang prefix from the relative path
		$rel_path = preg_replace( '#^(/' . $lp . '/)+#', '/', $rel_path );
		$rel_path = preg_replace( '#^' . $lp . '/#', '', ltrim( $rel_path, '/' ) );
		$rel_path = preg_replace( '#/' . $lp . '/#', '/', $rel_path );
		$rel_path = preg_replace( '#//+#', '/', $rel_path );
		$rel_path = ltrim( $rel_path, '/' );

		// Replace original slug with cleaned base slug in path
		if ( $term->slug !== $base_slug && false !== strpos( $rel_path, $term->slug ) ) {
			$rel_path = str_replace( $term->slug, $base_slug, $rel_path );
		}

		$permalink = $home_url . $lang_slug . '/' . $rel_path;

		return $permalink;
	}
}















