<?php
/**
 * Post Permalink Filter - Handles permalink filtering for posts and pages.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Permalink;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters permalinks for translated posts and pages.
 *
 * @since 0.10.0
 */
class PostPermalinkFilter {
	/**
	 * Cookie key for language preference.
	 */
	const COOKIE_NAME = '\FPML_lang_pref';

	/**
	 * Source language slug.
	 */
	const SOURCE = 'it';

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
	 * URL filter helper instance.
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
	 * Filter permalinks for translated pages to use /en/ prefix.
	 *
	 * @since 0.4.1
	 *
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post      The post object.
	 * @param bool    $force     Force filter even in admin.
	 *
	 * @return string
	 */
	public function filter_translation_permalink( $permalink, $post, $force = false ) {
		// Se $force è true, applica sempre il filtro (anche in admin)
		if ( ! $force && is_admin() ) {
			return $permalink;
		}
		
		if ( ! $post instanceof \WP_Post ) {
			return $permalink;
		}

		// Se WPML è attivo e il post usa WPML, non modificare il permalink (lascia gestire a WPML)
		$wpml_active = defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
		if ( $wpml_active && function_exists( 'icl_object_id' ) ) {
			$translation_provider = get_post_meta( $post->ID, '_fpml_translation_provider', true );
			// Se il post usa WPML o 'auto' (e WPML ha la traduzione), non modificare il permalink
			if ( $translation_provider === 'wpml' || ( $translation_provider === 'auto' && function_exists( 'icl_object_id' ) ) ) {
				// Lascia che WPML gestisca il permalink
				return $permalink;
			}
		}
		
		// Solo se il routing mode è 'segment'
		$routing = $this->settings->get( 'routing_mode', 'segment' );
		if ( 'segment' !== $routing ) {
			return $permalink;
		}

		// Get current language from path or cookie
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$available_languages = $language_manager->get_all_languages();
		
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$current_lang = self::SOURCE;
		$is_target_language_path = false;
		
		// Check if we're on a target language path
		foreach ( $enabled_languages as $lang_code ) {
			if ( ! isset( $available_languages[ $lang_code ] ) ) {
				continue;
			}
			$lang_info = $available_languages[ $lang_code ];
			if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
				continue;
			}
			$lang_slug = trim( $lang_info['slug'], '/' );
			
			if ( preg_match( '#^/' . preg_quote( $lang_slug, '#' ) . '(/|$)#', $request_uri ) ) {
				$current_lang = $lang_code;
				$is_target_language_path = true;
				break;
			}
		}
		
		$lang_cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] ) : '';
		$is_target_language_preference = ( in_array( $lang_cookie, $enabled_languages, true ) || $is_target_language_path );
		
		// Se $force è true, considera sempre che siamo in contesto di una lingua target (per admin)
		if ( $force && ! empty( $enabled_languages ) && is_array( $enabled_languages ) ) {
			$is_target_language_preference = true;
			$current_lang = $enabled_languages[0];
		}

		$is_translation = get_post_meta( $post->ID, '_fpml_is_translation', true );
		
		if ( $is_target_language_preference || ( $force && $is_translation ) ) {
			if ( $is_translation ) {
				// Continua con la logica esistente per le traduzioni
			} else {
				// Se il post NON è una traduzione ma siamo su una lingua target, cerca la traduzione
				$translation_id = fpml_get_translation_id( $post->ID, $current_lang );
				
				if ( $translation_id ) {
					$translation_post = get_post( $translation_id );
					if ( $translation_post instanceof \WP_Post ) {
						// Evita loop: rimuovi temporaneamente i filtri
						$this->filter_helper->remove_permalink_filters();
						
						try {
							$translation_permalink = get_permalink( $translation_id );
						} finally {
							$this->filter_helper->restore_permalink_filters();
						}
						
						// Usa il permalink della traduzione
						return $this->filter_translation_permalink( $translation_permalink, $translation_post, $force );
					}
				}
				// Se non c'è traduzione ma siamo su una lingua target, aggiungi il prefisso lingua
				if ( isset( $available_languages[ $current_lang ] ) ) {
					$lang_info = $available_languages[ $current_lang ];
					if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
						return $permalink;
					}
					$lang_slug = trim( $lang_info['slug'], '/' );
					
					if ( false === strpos( $permalink, '/' . $lang_slug . '/' ) ) {
						$this->filter_helper->remove_url_filters();
						
						try {
							$home_url_base = trailingslashit( home_url() );
						} finally {
							$this->filter_helper->restore_url_filters();
						}
						
						$rel_path = str_replace( $home_url_base, '', $permalink );
						$permalink = $home_url_base . $lang_slug . '/' . ltrim( $rel_path, '/' );
					}
				}
				return $permalink;
			}
		}

		// Solo per le pagine tradotte (logica originale)
		if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			return $permalink;
		}

		// Determina lo slug base
		$base_slug = $post->post_name;
		
		// Se lo slug inizia con 'en-', rimuovi il prefisso
		if ( 0 === strpos( $base_slug, 'en-' ) ) {
			$base_slug = substr( $base_slug, 3 );
		}
		
		if ( 0 === strpos( $base_slug, 'en_' ) ) {
			$base_slug = substr( $base_slug, 3 );
		}
		
		// Get current language slug
		$current_lang = $this->resolver->get_current_language();
		$language_manager = fpml_get_language_manager();
		$lang_info = $language_manager->get_language_info( $current_lang );
		$lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
		
		$lang_path = '/' . $lang_slug . '/';
		if ( false === strpos( $permalink, $lang_path ) ) {
			// Gestisci pagine gerarchiche (con parent)
			$parent_permalink = '';
			if ( $post->post_parent > 0 ) {
				$parent = get_post( $post->post_parent );
				if ( $parent instanceof \WP_Post ) {
					if ( get_post_meta( $parent->ID, '_fpml_is_translation', true ) ) {
						$parent_permalink = $this->filter_translation_permalink( get_permalink( $parent ), $parent, $force );
					} else {
						$parent_permalink = get_permalink( $parent );
					}
					$parent_permalink = str_replace( home_url( '/' ), '', trailingslashit( $parent_permalink ) );
				}
			}
			
			$this->filter_helper->remove_url_filters();
			
			try {
				$home_url = trailingslashit( home_url() );
			} finally {
				$this->filter_helper->restore_url_filters();
			}
			
			if ( $parent_permalink ) {
				$parent_permalink = str_replace( $lang_slug . '/', '', $parent_permalink );
				$parent_permalink = preg_replace( '#^' . preg_quote( $lang_slug, '#' ) . '/#', '', $parent_permalink );
				$permalink = $home_url . $lang_slug . '/' . trailingslashit( $parent_permalink ) . $base_slug . '/';
			} else {
				$permalink = $home_url . $lang_slug . '/' . $base_slug . '/';
			}
			
			$permalink = user_trailingslashit( $permalink );
		} else {
			// Se contiene già il path della lingua corrente, verifica che non ci sia doppio
			$pattern = '#(/' . preg_quote( $lang_slug, '#' ) . '/)+#';
			$permalink = preg_replace( $pattern, '/' . $lang_slug . '/', $permalink );
			
			if ( preg_last_error() !== PREG_NO_ERROR ) {
				\FP\Multilanguage\Logger::warning(
					'Regex error in filter_translation_permalink (duplicate language path)',
					array(
						'error'   => preg_last_error(),
						'pattern' => $pattern,
						'lang_slug' => $lang_slug,
					)
				);
				$double_path = '/' . $lang_slug . '/' . $lang_slug . '/';
				$permalink = str_replace( $double_path, '/' . $lang_slug . '/', $permalink );
			}
		}

		return $permalink;
	}

	/**
	 * Filter the sample permalink shown in admin edit screen.
	 *
	 * @since 0.9.5
	 *
	 * @param array  $permalink Array with 'permalink' and 'slug' keys.
	 * @param int    $post_id   Post ID.
	 * @param string $title     Post title.
	 * @param string $name      Post name (slug).
	 * @param object $post      Post object.
	 *
	 * @return array
	 */
	public function filter_sample_permalink( $permalink, $post_id, $title, $name, $post ) {
		if ( ! is_array( $permalink ) || ! isset( $permalink['permalink'] ) ) {
			return $permalink;
		}

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return $permalink;
		}

		if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			return $permalink;
		}

		$routing = $this->settings->get( 'routing_mode', 'segment' );
		if ( 'segment' !== $routing ) {
			return $permalink;
		}

		$filtered_permalink = $this->filter_translation_permalink( $permalink['permalink'], $post, true );

		if ( $filtered_permalink !== $permalink['permalink'] ) {
			$permalink['permalink'] = $filtered_permalink;
		}

		return $permalink;
	}
}















