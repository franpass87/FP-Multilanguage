<?php
/**
 * Site Translations - Mostra traduzioni di menu, widget, opzioni tema e plugin su /en/.
 *
 * @package FP_Multilanguage
 * @since 0.9.4
 */

namespace FP\Multilanguage\Frontend\Content;

use FP\Multilanguage\SiteTranslations\MenuFilter;
use FP\Multilanguage\SiteTranslations\WidgetFilter;
use FP\Multilanguage\SiteTranslations\ThemeOptionsFilter;
use FP\Multilanguage\SiteTranslations\OptionsFilter;
use FP\Multilanguage\SiteTranslations\MediaFilter;
use FP\Multilanguage\SiteTranslations\CommentFilter;
use FP\Multilanguage\SiteTranslations\ArchiveFilter;
use FP\Multilanguage\SiteTranslations\SearchFilter;
use FP\Multilanguage\SiteTranslations\NotFoundFilter;
use FP\Multilanguage\SiteTranslations\BreadcrumbFilter;
use FP\Multilanguage\SiteTranslations\AuthorFilter;
use FP\Multilanguage\SiteTranslations\FormFilter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe per mostrare traduzioni di parti del sito su /en/.
 *
 * @since 0.9.4
 * @since 0.10.0 Refactored to use modular filters.
 */
class SiteTranslations {
	
	/**
	 * Singleton instance.
	 *
	 * @var SiteTranslations|null
	 */
	protected static $instance = null;
	
	/**
	 * Current language code.
	 *
	 * @var string|null
	 */
	protected $current_language = null;

	/**
	 * Menu filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MenuFilter
	 */
	protected $menu_filter;

	/**
	 * Widget filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var WidgetFilter
	 */
	protected $widget_filter;

	/**
	 * Theme options filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ThemeOptionsFilter
	 */
	protected $theme_options_filter;

	/**
	 * Options filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var OptionsFilter
	 */
	protected $options_filter;

	/**
	 * Media filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MediaFilter
	 */
	protected $media_filter;

	/**
	 * Comment filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var CommentFilter
	 */
	protected $comment_filter;

	/**
	 * Archive filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ArchiveFilter
	 */
	protected $archive_filter;

	/**
	 * Search filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var SearchFilter
	 */
	protected $search_filter;

	/**
	 * Not found filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var NotFoundFilter
	 */
	protected $not_found_filter;

	/**
	 * Breadcrumb filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var BreadcrumbFilter
	 */
	protected $breadcrumb_filter;

	/**
	 * Author filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var AuthorFilter
	 */
	protected $author_filter;

	/**
	 * Form filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var FormFilter
	 */
	protected $form_filter;
	
	/**
	 * Get singleton instance.
	 *
	 * @return SiteTranslations
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Non applicare filtri in admin, AJAX, REST API o WP-CLI
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		
		// Get enabled languages and check if we're on any target language path
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$available_languages = $language_manager->get_all_languages();
		
		// Ensure arrays are valid
		if ( ! is_array( $enabled_languages ) ) {
			$enabled_languages = array();
		}
		if ( ! is_array( $available_languages ) ) {
			$available_languages = array();
		}
		
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$is_target_language_path = false;
		$current_lang = null;
		
		// Check if we're on any enabled language path
		foreach ( $enabled_languages as $lang_code ) {
			if ( ! isset( $available_languages[ $lang_code ] ) ) {
				continue;
			}
			
			$lang_info = $available_languages[ $lang_code ];
			if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
				continue;
			}
			$lang_slug = trim( $lang_info['slug'], '/' );
			$pattern = '#^/' . preg_quote( $lang_slug, '#' ) . '(/|$)#';
			
			if ( preg_match( $pattern, $request_uri ) ) {
				$is_target_language_path = true;
				$current_lang = $lang_code;
				break;
			}
			
			// Check for regex errors
			if ( preg_last_error() !== PREG_NO_ERROR ) {
				\FP\Multilanguage\Logger::warning(
					'Regex error in SiteTranslations constructor',
					array(
						'error'   => preg_last_error(),
						'pattern' => $pattern,
						'uri'     => $request_uri,
						'lang'    => $lang_code,
					)
				);
			}
		}
		
		// Store current language for use in filter methods
		$this->current_language = $current_lang;

		// Initialize filter modules
		$this->menu_filter          = new MenuFilter();
		$this->widget_filter        = new WidgetFilter();
		$this->theme_options_filter = new ThemeOptionsFilter();
		$this->options_filter       = new OptionsFilter();
		$this->media_filter         = new MediaFilter();
		$this->comment_filter       = new CommentFilter();
		$this->archive_filter       = new ArchiveFilter();
		$this->search_filter        = new SearchFilter();
		$this->not_found_filter     = new NotFoundFilter();
		$this->breadcrumb_filter    = new BreadcrumbFilter();
		$this->author_filter        = new AuthorFilter();
		$this->form_filter          = new FormFilter();
		
		if ( $is_target_language_path ) {
			// Menu filters
			add_filter( 'wp_nav_menu_objects', array( $this->menu_filter, 'filter_menu_items' ), 10, 2 );
			add_filter( 'nav_menu_item_title', array( $this->menu_filter, 'filter_menu_item_title' ), 10, 4 );
			
			// Widget filters
			add_filter( 'widget_title', array( $this->widget_filter, 'filter_widget_title' ), 10, 3 );
			add_filter( 'widget_text', array( $this->widget_filter, 'filter_widget_text' ), 10, 3 );
			
			// Theme options filters
			add_filter( 'option_salient', array( $this->theme_options_filter, 'filter_theme_options' ), 10, 2 );
			add_filter( 'theme_mod', array( $this->theme_options_filter, 'filter_theme_mod' ), 10, 2 );
			
			// Options filters
			add_filter( 'option_woocommerce_shop_page_title', array( $this->options_filter, 'filter_option' ), 10, 2 );
			add_filter( 'option_woocommerce_cart_page_title', array( $this->options_filter, 'filter_option' ), 10, 2 );
			add_filter( 'option_woocommerce_checkout_page_title', array( $this->options_filter, 'filter_option' ), 10, 2 );
			add_filter( 'option_blogname', array( $this->options_filter, 'filter_blogname' ), 10, 2 );
			add_filter( 'option_blogdescription', array( $this->options_filter, 'filter_blogdescription' ), 10, 2 );
			add_filter( 'option', array( $this->options_filter, 'filter_generic_option' ), 10, 2 );
			
			// Media filters
			add_filter( 'wp_get_attachment_image_attributes', array( $this->media_filter, 'filter_image_alt' ), 10, 3 );
			add_filter( 'wp_get_attachment_caption', array( $this->media_filter, 'filter_image_caption' ), 10, 2 );
			add_filter( 'get_attachment_metadata', array( $this->media_filter, 'filter_image_description' ), 10, 2 );
			add_filter( 'the_content', array( $this->media_filter, 'filter_attachment_content' ), 10, 1 );
			add_filter( 'get_the_excerpt', array( $this->media_filter, 'filter_attachment_excerpt' ), 10, 2 );
			
			// Comment filters
			add_filter( 'get_comment_text', array( $this->comment_filter, 'filter_comment_text' ), 10, 3 );
			add_filter( 'comment_text', array( $this->comment_filter, 'filter_comment_text' ), 10, 3 );
			
			// Archive filters
			add_filter( 'get_the_archive_title', array( $this->archive_filter, 'filter_archive_title' ), 10, 1 );
			add_filter( 'get_the_archive_description', array( $this->archive_filter, 'filter_archive_description' ), 10, 1 );
			
			// Search filters
			add_filter( 'get_search_query', array( $this->search_filter, 'filter_search_query' ), 10, 1 );
			add_filter( 'document_title_parts', array( $this->search_filter, 'filter_search_title' ), 10, 1 );
			
			// 404 filters
			add_filter( 'wp_title', array( $this->not_found_filter, 'filter_404_title' ), 10, 2 );
			add_filter( 'document_title_parts', array( $this->not_found_filter, 'filter_404_document_title' ), 10, 1 );
			
			// Breadcrumb filters
			add_filter( 'wpseo_breadcrumb_links', array( $this->breadcrumb_filter, 'filter_yoast_breadcrumbs' ), 10, 1 );
			add_filter( 'rank_math/frontend/breadcrumb/items', array( $this->breadcrumb_filter, 'filter_rankmath_breadcrumbs' ), 10, 2 );
			add_filter( 'aioseo_breadcrumbs_trail', array( $this->breadcrumb_filter, 'filter_aioseo_breadcrumbs' ), 10, 1 );
			
			// Author filters
			add_filter( 'get_the_author_description', array( $this->author_filter, 'filter_author_bio' ), 10, 1 );
			add_filter( 'the_author_description', array( $this->author_filter, 'filter_author_bio' ), 10, 1 );
			
			// Form filters
			add_filter( 'wpcf7_form_elements', array( $this->form_filter, 'filter_cf7_form' ), 10, 1 );
			if ( class_exists( 'WPForms' ) ) {
				add_filter( 'wpforms_field_properties', array( $this->form_filter, 'filter_wpforms_fields' ), 10, 3 );
			}
		}
	}
	
	/**
	 * Check if we're on a target language path.
	 *
	 * @since 0.10.0 Updated to support multiple languages.
	 *
	 * @return bool True if on a target language path.
	 */
	protected function is_target_language() {
		return ! empty( $this->current_language );
	}
	
	/**
	 * Check if current language is English (backward compatibility).
	 *
	 * @deprecated Use is_target_language() for multi-language support.
	 * @return bool True if English.
	 */
	protected function is_english() {
		return 'en' === $this->current_language;
	}
}


