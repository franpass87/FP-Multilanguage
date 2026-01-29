<?php
/**
 * Site Part Translator - Traduce menu, widget, opzioni tema e plugin.
 *
 * @package FP_Multilanguage
 * @since 0.9.4
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Processor;
use FP\Multilanguage\Admin\SitePartTranslators\TextTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\MenuTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\WidgetTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\ThemeOptionsTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\PluginTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\SiteSettingsTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\MediaTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\CommentTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\CustomizerTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\ArchiveTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\SearchTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\NotFoundTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\BreadcrumbTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\FormTranslator;
use FP\Multilanguage\Admin\SitePartTranslators\AuthorTranslator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe per tradurre parti del sito (menu, widget, opzioni tema, plugin).
 *
 * @since 0.9.4
 * @since 0.10.0 Refactored to use modular components.
 */
class SitePartTranslator {
	/**
	 * Text translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TextTranslator
	 */
	protected TextTranslator $text_translator;

	/**
	 * Menu translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MenuTranslator
	 */
	protected MenuTranslator $menu_translator;

	/**
	 * Widget translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var WidgetTranslator
	 */
	protected WidgetTranslator $widget_translator;

	/**
	 * Theme options translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ThemeOptionsTranslator
	 */
	protected ThemeOptionsTranslator $theme_options_translator;

	/**
	 * Plugin translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var PluginTranslator
	 */
	protected PluginTranslator $plugin_translator;

	/**
	 * Site settings translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var SiteSettingsTranslator
	 */
	protected SiteSettingsTranslator $site_settings_translator;

	/**
	 * Media translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MediaTranslator
	 */
	protected MediaTranslator $media_translator;

	/**
	 * Comment translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var CommentTranslator
	 */
	protected CommentTranslator $comment_translator;

	/**
	 * Customizer translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var CustomizerTranslator
	 */
	protected CustomizerTranslator $customizer_translator;

	/**
	 * Archive translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ArchiveTranslator
	 */
	protected ArchiveTranslator $archive_translator;

	/**
	 * Search translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var SearchTranslator
	 */
	protected SearchTranslator $search_translator;

	/**
	 * Not found translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var NotFoundTranslator
	 */
	protected NotFoundTranslator $not_found_translator;

	/**
	 * Breadcrumb translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var BreadcrumbTranslator
	 */
	protected BreadcrumbTranslator $breadcrumb_translator;

	/**
	 * Form translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var FormTranslator
	 */
	protected FormTranslator $form_translator;

	/**
	 * Author translator instance.
	 *
	 * @since 0.10.0
	 *
	 * @var AuthorTranslator
	 */
	protected AuthorTranslator $author_translator;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	public function __construct() {
		// Initialize text translator first (used by all others)
		$this->text_translator = new TextTranslator();

		// Initialize all translators
		$this->menu_translator = new MenuTranslator( $this->text_translator );
		$this->widget_translator = new WidgetTranslator( $this->text_translator );
		$this->theme_options_translator = new ThemeOptionsTranslator( $this->text_translator );
		$this->plugin_translator = new PluginTranslator( $this->text_translator );
		$this->site_settings_translator = new SiteSettingsTranslator( $this->text_translator );
		$this->media_translator = new MediaTranslator( $this->text_translator );
		$this->comment_translator = new CommentTranslator( $this->text_translator );
		$this->customizer_translator = new CustomizerTranslator( $this->text_translator );
		$this->archive_translator = new ArchiveTranslator( $this->text_translator );
		$this->search_translator = new SearchTranslator( $this->text_translator );
		$this->not_found_translator = new NotFoundTranslator( $this->text_translator );
		$this->breadcrumb_translator = new BreadcrumbTranslator( $this->text_translator );
		$this->form_translator = new FormTranslator( $this->text_translator );
		$this->author_translator = new AuthorTranslator( $this->text_translator );
	}
	
	/**
	 * Traduce una parte specifica del sito.
	 *
	 * @param string $part Tipo di parte da tradurre (menus, widgets, theme-options, plugins).
	 * @return array|WP_Error Risultato della traduzione.
	 */
	public function translate( $part ) {
		switch ( $part ) {
			case 'menus':
				return $this->menu_translator->translate();
			case 'widgets':
				return $this->widget_translator->translate();
			case 'theme-options':
				return $this->theme_options_translator->translate();
			case 'plugins':
				return $this->plugin_translator->translate();
			case 'site-settings':
				return $this->site_settings_translator->translate();
			case 'media':
				return $this->media_translator->translate();
			case 'comments':
				return $this->comment_translator->translate();
			case 'customizer':
				return $this->customizer_translator->translate();
			case 'archives':
				return $this->archive_translator->translate();
			case 'search':
				return $this->search_translator->translate();
			case '404':
				return $this->not_found_translator->translate();
			case 'breadcrumbs':
				return $this->breadcrumb_translator->translate();
			case 'forms':
				return $this->form_translator->translate();
			case 'authors':
				return $this->author_translator->translate();
			default:
				return new \WP_Error( 'invalid_part', __( 'Parte del sito non valida.', 'fp-multilanguage' ) );
		}
	}
}
