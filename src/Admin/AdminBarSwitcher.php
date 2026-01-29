<?php
/**
 * Admin Bar Language Switcher (WPML-style).
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Language;
use FP\Multilanguage\MultiLanguage\LanguageManager;
use FP\Multilanguage\Content\TranslationManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add language switcher to admin bar.
 *
 * @since 0.5.0
 */
class AdminBarSwitcher {
	protected static $instance = null;

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

	protected function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add menu to admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 *
	 * @return void
	 */
	public function add_admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$current_lang = ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : Language::instance() )->get_current_language();
		$is_italian = ( 'it' === $current_lang );
		
		// Get language manager
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$all_languages = $language_manager->get_all_languages();

		// Ensure arrays are valid
		if ( ! is_array( $enabled_languages ) ) {
			$enabled_languages = array();
		}
		if ( ! is_array( $all_languages ) ) {
			$all_languages = array();
		}

		// Get current language info for title
		$current_lang_info = $language_manager->get_language_info( $current_lang );
		$current_lang_name = ( $current_lang_info && isset( $current_lang_info['name'] ) ) ? $current_lang_info['name'] : ucfirst( $current_lang );
		// Fallback per bandierina: se non trovata, usa emoji hardcoded
		$current_lang_flag = ( $current_lang_info && isset( $current_lang_info['flag'] ) ) ? $current_lang_info['flag'] : '';
		if ( empty( $current_lang_flag ) ) {
			// Fallback per lingue comuni
			$flag_fallback = array(
				'it' => '🇮🇹',
				'en' => '🇬🇧',
				'de' => '🇩🇪',
				'fr' => '🇫🇷',
				'es' => '🇪🇸',
			);
			$current_lang_flag = isset( $flag_fallback[ $current_lang ] ) ? $flag_fallback[ $current_lang ] : '';
		}

		// Parent menu - assicurati che la bandierina sia sempre presente
		$menu_title = trim( $current_lang_flag . ' ' . $current_lang_name );
		$wp_admin_bar->add_node(
			array(
				'id'    => 'fpml-lang-switcher',
				'title' => $menu_title,
				'href'  => '#',
				'meta'  => array(
					'class' => 'fpml-admin-bar-lang',
					'title' => __( 'Seleziona lingua', 'fp-multilanguage' ),
					'aria-label' => sprintf(
						/* translators: %s: current language name */
						__( 'Selettore lingua - Lingua corrente: %s', 'fp-multilanguage' ),
						$current_lang_name
					),
				),
			)
		);

		// Get current post/page if in singular view
		$current_post_id = 0;
		$translation_ids = array();

		if ( is_singular() ) {
			$current_post_id = get_queried_object_id();
			
			// Get all translations for current post
			$translation_manager = fpml_get_translation_manager();
			$translation_ids = $translation_manager->get_all_translations( $current_post_id );
		}

		// Submenu: Italiano (always shown)
		if ( $is_italian ) {
			$it_url = '';
			if ( $current_post_id ) {
				$it_url = get_permalink( $current_post_id );
			}
			if ( empty( $it_url ) ) {
				$it_url = home_url( '/' );
			}
			
			$wp_admin_bar->add_node(
				array(
					'parent' => 'fpml-lang-switcher',
					'id'     => 'fpml-lang-it',
					'title'  => '✓ 🇮🇹 Italiano (corrente)',
					'href'   => $it_url,
					'meta'   => array(
						'class' => 'fpml-current-lang',
						'aria-label' => __( 'Italiano - Lingua corrente', 'fp-multilanguage' ),
						'aria-current' => 'true',
						'lang' => 'it',
					),
				)
			);
		} else {
			// Find Italian translation
			$it_translation_id = isset( $translation_ids['it'] ) ? $translation_ids['it'] : 0;
			if ( ! $it_translation_id && $current_post_id ) {
				// Try legacy _fpml_pair_source_id for backward compatibility
				$it_translation_id = get_post_meta( $current_post_id, '_fpml_pair_source_id', true );
			}
			
			$it_url = '';
			if ( $it_translation_id ) {
				$it_url = get_permalink( $it_translation_id );
			}
			if ( empty( $it_url ) ) {
				$it_url = home_url( '/' );
			}
			
			$wp_admin_bar->add_node(
				array(
					'parent' => 'fpml-lang-switcher',
					'id'     => 'fpml-lang-it',
					'title'  => '🇮🇹 Italiano' . ( $it_translation_id ? '' : ' (non tradotto)' ),
					'href'   => $it_url,
					'meta'   => array(
						'aria-label' => __( 'Passa alla versione italiana', 'fp-multilanguage' ),
						'lang' => 'it',
					),
				)
			);
		}

		// Submenu: Enabled target languages
		foreach ( $enabled_languages as $lang_code ) {
			if ( 'it' === $lang_code ) {
				continue; // Already handled above
			}
			
			if ( ! isset( $all_languages[ $lang_code ] ) ) {
				continue;
			}
			
			$lang_info = $all_languages[ $lang_code ];
			if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
				continue;
			}
			
			$lang_slug = trim( $lang_info['slug'], '/' );
			$is_current = ( $lang_code === $current_lang );
			
			$translation_id = isset( $translation_ids[ $lang_code ] ) ? $translation_ids[ $lang_code ] : 0;
			if ( ! $translation_id && $current_post_id ) {
				// Try legacy _fpml_pair_id for backward compatibility (English)
				if ( 'en' === $lang_code ) {
					$translation_id = get_post_meta( $current_post_id, '_fpml_pair_id', true );
				}
			}
			
			// Ensure URL is never empty
			$lang_url = '';
			if ( $translation_id ) {
				$lang_url = get_permalink( $translation_id );
			}
			if ( empty( $lang_url ) ) {
				$lang_url = home_url( '/' . $lang_slug . '/' );
			}
			// Final fallback
			if ( empty( $lang_url ) ) {
				$lang_url = home_url( '/' );
			}
			
			// Assicurati che la bandierina sia sempre presente
			$lang_flag = isset( $lang_info['flag'] ) ? $lang_info['flag'] : '';
			if ( empty( $lang_flag ) ) {
				// Fallback per lingue comuni
				$flag_fallback = array(
					'it' => '🇮🇹',
					'en' => '🇬🇧',
					'de' => '🇩🇪',
					'fr' => '🇫🇷',
					'es' => '🇪🇸',
				);
				$lang_flag = isset( $flag_fallback[ $lang_code ] ) ? $flag_fallback[ $lang_code ] : '';
			}
			
			$lang_name = isset( $lang_info['name'] ) ? $lang_info['name'] : ucfirst( $lang_code );
			$lang_title = trim( $lang_flag . ' ' . $lang_name );
			
			if ( $is_current ) {
				$lang_title .= ' (corrente)';
			} elseif ( ! $translation_id ) {
				$lang_title .= ' (non tradotto)';
			}
			
			$aria_label = sprintf(
				/* translators: %1$s: language name, %2$s: translation status */
				__( 'Passa alla versione %1$s%2$s', 'fp-multilanguage' ),
				strtolower( $lang_name ),
				! $translation_id ? ' ' . __( '(non tradotto)', 'fp-multilanguage' ) : ''
			);
			
			$wp_admin_bar->add_node(
				array(
					'parent' => 'fpml-lang-switcher',
					'id'     => 'fpml-lang-' . $lang_code,
					'title'  => ( $is_current ? '✓ ' : '' ) . $lang_title,
					'href'   => $lang_url,
					'meta'   => array_merge(
						$is_current ? array(
							'class' => 'fpml-current-lang',
							'aria-current' => 'true',
						) : array(),
						array(
							'aria-label' => $aria_label,
							'lang' => $lang_code,
						)
					),
				)
			);
		}

		// Edit links if in admin
		if ( is_admin() && $current_post_id ) {
			global $pagenow;
			
			if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
				$edit_post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
				
				if ( $edit_post_id ) {
					$pair_id = get_post_meta( $edit_post_id, '_fpml_pair_id', true );
					
					if ( $pair_id ) {
						$wp_admin_bar->add_node(
							array(
								'parent' => 'fpml-lang-switcher',
								'id'     => 'fpml-edit-translation',
								'title'  => '✏️ Modifica Traduzione EN',
								'href'   => admin_url( 'post.php?post=' . $pair_id . '&action=edit' ),
								'meta'   => array(
									'aria-label' => __( 'Modifica la traduzione inglese di questo contenuto', 'fp-multilanguage' ),
								),
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Enqueue styles.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		wp_add_inline_style(
			'admin-bar',
			'
			#wp-admin-bar-fpml-lang-switcher .ab-item {
				font-weight: 600;
			}
			#wp-admin-bar-fpml-lang-switcher .fpml-current-lang a {
				background: rgba(255,255,255,0.1);
				font-weight: bold;
			}
			'
		);
	}
}

