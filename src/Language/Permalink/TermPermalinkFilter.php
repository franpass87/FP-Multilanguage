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

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$is_english_path = fpml_url_contains_target_language( $request_uri );
		
		$is_translation = get_term_meta( $term->term_id, '_fpml_is_translation', true );

		$language_manager = fpml_get_language_manager();
		$current_lang = $this->resolver->get_current_language();
		$lang_info = $language_manager->get_language_info( $current_lang );
		$lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
		$lang_path = '/' . $lang_slug . '/';
		
		if ( ! $is_english_path && ! $is_translation ) {
			if ( fpml_url_contains_target_language( $permalink ) ) {
				$permalink = preg_replace( '#/' . preg_quote( $lang_slug, '#' ) . '/#', '/', $permalink );
			}
			return $permalink;
		}

		if ( ! $is_english_path && $is_translation ) {
			if ( fpml_url_contains_target_language( $permalink ) ) {
				$permalink = preg_replace( '#/' . preg_quote( $lang_slug, '#' ) . '/#', '/', $permalink );
			}
			if ( false !== strpos( $permalink, '/en-' ) ) {
				$permalink = str_replace( '/en-', '/', $permalink );
			}
			return $permalink;
		}

		if ( $is_english_path && ! $is_translation ) {
			// Backward compatibility: check legacy _fpml_pair_id for 'en'
			$meta_key = '_fpml_pair_id_en';
			$translation_id = (int) get_term_meta( $term->term_id, $meta_key, true );
			if ( ! $translation_id ) {
				$translation_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );
			}
			if ( $translation_id > 0 ) {
				$translation = get_term( $translation_id, $term->taxonomy );
				if ( $translation instanceof \WP_Term && get_term_meta( $translation_id, '_fpml_is_translation', true ) ) {
					$term = $translation;
					$is_translation = true;
				} else {
					return $permalink;
				}
			} else {
				return $permalink;
			}
		}

		if ( ! $is_translation || ! $is_english_path ) {
			return $permalink;
		}

		$base_slug = $term->slug;
		if ( 0 === strpos( $base_slug, 'en-' ) ) {
			$base_slug = substr( $base_slug, 3 );
		}

		$this->filter_helper->remove_url_filters();
		
		try {
			$home_url = trailingslashit( home_url() );
		} finally {
			$this->filter_helper->restore_url_filters();
		}
		
		$parsed = wp_parse_url( $permalink );
		if ( $parsed && isset( $parsed['path'] ) ) {
			$rel_path = $parsed['path'];
		} else {
			$rel_path = str_replace( $home_url, '', $permalink );
		}
		
		$rel_path = preg_replace( '#^(/en/)+#', '/', $rel_path );
		if ( preg_last_error() !== PREG_NO_ERROR ) {
			\FP\Multilanguage\Logger::warning(
				'Regex error in filter_term_permalink (remove /en/)',
				array( 'error' => preg_last_error() )
			);
		}
		
		$rel_path = preg_replace( '#^en/#', '', $rel_path );
		$rel_path = preg_replace( '#/en/#', '/', $rel_path );
		$rel_path = ltrim( $rel_path, '/' );
		
		if ( preg_match( '#^[^/]+\.(local|com|net|org|it|eu)/(.+)$#', $rel_path, $matches ) ) {
			if ( preg_last_error() === PREG_NO_ERROR ) {
				$rel_path = $matches[2];
			}
		}
		
		$rel_path = preg_replace( '#//+#', '/', $rel_path );
		$rel_path = ltrim( $rel_path, '/' );
		
		if ( false !== strpos( $rel_path, $term->slug ) ) {
			$rel_path = str_replace( $term->slug, $base_slug, $rel_path );
		}
		
		$permalink = $home_url . 'en/' . $rel_path;

		return $permalink;
	}
}















