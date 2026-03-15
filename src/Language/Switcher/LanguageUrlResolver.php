<?php
/**
 * Language URL Resolver - Resolves URLs for language switching.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Switcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves URLs for language switching.
 *
 * @since 0.10.0
 */
class LanguageUrlResolver {
	/**
	 * Language instance.
	 *
	 * @var \FPML_Language|null
	 */
	protected $language_instance;

	/**
	 * Available languages.
	 *
	 * @var array
	 */
	protected $available_languages;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Language|null $language_instance Language instance.
	 * @param array               $available_languages Available languages.
	 */
	public function __construct( $language_instance, $available_languages ) {
		$this->language_instance   = $language_instance;
		$this->available_languages = $available_languages;
	}

	/**
	 * Get URL for Italian (source language).
	 *
	 * @return string
	 */
	public function get_italian_url() {
		$source_lang = function_exists( 'fpml_get_source_language' ) ? (string) fpml_get_source_language() : 'it';
		$it_url      = '';
		if ( $this->language_instance && method_exists( $this->language_instance, 'get_url_for_language' ) ) {
			$it_url = (string) $this->language_instance->get_url_for_language( $source_lang );
		}

		// Safety guard: if source URL resolves to current target-language home (e.g. /de/ on /de/),
		// force root path to avoid sticky language loop.
		if ( $this->is_same_as_current_target_root( $it_url ) ) {
			$it_url = '/';
		}

		// Use relative root fallback to preserve current host/port.
		if ( empty( $it_url ) ) {
			$it_url = '/';
		}
		return $it_url;
	}

	/**
	 * Get URL for a specific language.
	 *
	 * @param string $lang_code Language code.
	 * @return string
	 */
	public function get_language_url( $lang_code ) {
		if ( ! isset( $this->available_languages[ $lang_code ] ) ) {
			return home_url( '/' );
		}

		$lang_info = $this->available_languages[ $lang_code ];
		if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
			return home_url( '/' );
		}

		// Se WPML è attivo, verifica se il post corrente usa WPML
		$wpml_active = defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
		if ( $wpml_active && function_exists( 'icl_object_id' ) ) {
			// Verifica se siamo su un singolo post/pagina
			global $post;
			if ( $post && isset( $post->ID ) ) {
				$translation_provider = get_post_meta( $post->ID, '_fpml_translation_provider', true );
				// Se il post usa WPML o 'auto' (e WPML ha la traduzione), usa WPML
				if ( $translation_provider === 'wpml' || ( $translation_provider === 'auto' && function_exists( 'icl_object_id' ) ) ) {
					// Usa WPML per generare l'URL
					$wpml_lang_code = $lang_code === 'it' ? 'it' : $lang_code; // WPML potrebbe usare codici diversi
					if ( function_exists( 'apply_filters' ) ) {
						// Usa il filtro WPML per ottenere l'URL tradotto
						$wpml_url = apply_filters( 'wpml_permalink', get_permalink( $post->ID ), $wpml_lang_code );
						if ( $wpml_url && $wpml_url !== get_permalink( $post->ID ) ) {
							return esc_url_raw( $wpml_url );
						}
					}
				}
			}
		}

		// Use get_url_for_language to maintain current page context
		$lang_url = '';
		if ( $this->language_instance && method_exists( $this->language_instance, 'get_url_for_language' ) ) {
			$lang_url = (string) $this->language_instance->get_url_for_language( $lang_code );
		}
		// Fallback to home_url if get_url_for_language returns empty or false
		if ( empty( $lang_url ) ) {
			$lang_slug = trim( (string) $lang_info['slug'], '/' );
			$lang_url  = '/' . $lang_slug . '/';
		}
		// Ensure URL is never empty
		if ( empty( $lang_url ) ) {
			$lang_url = '/';
		}

		return $lang_url;
	}

	/**
	 * Check whether a generated source URL points to the current target-language root.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	protected function is_same_as_current_target_root( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$url_path     = (string) wp_parse_url( $url, PHP_URL_PATH );

		$request_path = '/' . trim( $request_path, '/' );
		$url_path     = '/' . trim( $url_path, '/' );

		foreach ( $this->available_languages as $lang_info ) {
			if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
				continue;
			}

			$lang_slug = trim( (string) $lang_info['slug'], '/' );
			if ( '' === $lang_slug ) {
				continue;
			}

			$target_root = '/' . $lang_slug;
			$is_on_lang  = ( $request_path === $target_root || str_starts_with( $request_path, $target_root . '/' ) );

			if ( ! $is_on_lang ) {
				continue;
			}

			if ( $url_path === $target_root || $url_path === $target_root . '/' ) {
				return true;
			}
		}

		return false;
	}
}















