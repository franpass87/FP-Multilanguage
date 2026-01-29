<?php
/**
 * Term Handlers Service - Manages term-related translation hooks.
 *
 * Extracted from Plugin.php to follow Single Responsibility Principle.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles term-related translation hooks and events.
 *
 * @since 0.10.0
 */
class TermHandlers {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Translation manager instance.
	 *
	 * @var TranslationManager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer instance.
	 *
	 * @var JobEnqueuer
	 */
	protected $job_enqueuer;

	/**
	 * Plugin instance (for assisted mode check).
	 *
	 * @var PluginCore|null
	 */
	protected $plugin;

	/**
	 * Get singleton instance (for backward compatibility).
	 *
	 * @since 0.10.0
	 * @deprecated 1.0.0 Use dependency injection via container instead. Consider using TermHooks for new code.
	 *
	 * @return self
	 */
	public static function instance(): self {
		_doing_it_wrong( 
			'FP\Multilanguage\Core\TermHandlers::instance()', 
			'TermHandlers is deprecated. Use TermHooks via dependency injection instead.', 
			'1.0.0' 
		);
		
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 * @since 1.0.0 Now public to support dependency injection (but class is deprecated)
	 *
	 * @param TranslationManager|null $translation_manager Optional translation manager for DI.
	 * @param JobEnqueuer|null        $job_enqueuer        Optional job enqueuer for DI.
	 */
	public function __construct( $translation_manager = null, $job_enqueuer = null ) {
		// Use injected dependencies or get from container/singleton
		if ( null === $translation_manager ) {
			$this->translation_manager = Container::get( 'translation_manager' ) 
				?: fpml_get_translation_manager();
		} else {
			$this->translation_manager = $translation_manager;
		}
		
		if ( null === $job_enqueuer ) {
			$this->job_enqueuer = Container::get( 'job_enqueuer' ) 
				?: ( class_exists( \FP\Multilanguage\Translation\JobEnqueuer::class ) 
					? fpml_get_job_enqueuer() 
					: null );
		} else {
			$this->job_enqueuer = $job_enqueuer;
		}
		
		// Plugin instance will be set via set_plugin() to avoid circular dependencies
		$this->plugin = null;
	}

	/**
	 * Set plugin instance.
	 *
	 * @param PluginCore $plugin Plugin instance.
	 *
	 * @return void
	 */
	public function set_plugin( PluginCore $plugin ): void {
		$this->plugin = $plugin;
	}

	/**
	 * Check if in assisted mode.
	 *
	 * @return bool
	 */
	protected function is_assisted_mode(): bool {
		if ( ! $this->plugin ) {
			// Try to get plugin instance if not set (lazy loading to avoid circular dependencies)
			if ( class_exists( '\FPML_Plugin' ) ) {
				$this->plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
			}
		}
		return $this->plugin ? $this->plugin->is_assisted_mode() : false;
	}

	/**
	 * Handle created terms - sync translation.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_created_term( int $term_id, int $tt_id, string $taxonomy ): void {
		// Check if we're creating a translation
		if ( isset( $GLOBALS['fpml_creating_term_translation'] ) && $GLOBALS['fpml_creating_term_translation'] ) {
			return;
		}

		if ( $this->is_assisted_mode() || ! $this->translation_manager ) {
			return;
		}

		$this->sync_term_translation( $term_id, $taxonomy );
	}

	/**
	 * Handle edited terms - sync translation.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_edited_term( int $term_id, int $tt_id, string $taxonomy ): void {
		// Check if we're creating a translation
		if ( isset( $GLOBALS['fpml_creating_term_translation'] ) && $GLOBALS['fpml_creating_term_translation'] ) {
			return;
		}

		if ( $this->is_assisted_mode() || ! $this->translation_manager ) {
			return;
		}

		$this->sync_term_translation( $term_id, $taxonomy );
	}

	/**
	 * Sync term translation for all enabled languages.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	protected function sync_term_translation( int $term_id, string $taxonomy ): void {
		if ( ! $this->translation_manager || ! $this->job_enqueuer ) {
			return;
		}

		// Get translatable taxonomies
		$taxonomies = get_taxonomies(
			array(
				'public' => true,
			),
			'names'
		);

		$custom_taxonomies = get_option( '\FPML_custom_translatable_taxonomies', array() );
		if ( ! empty( $custom_taxonomies ) && is_array( $custom_taxonomies ) ) {
			$taxonomies = array_merge( $taxonomies, $custom_taxonomies );
		}

		/**
		 * Filter translatable taxonomies.
		 *
		 * @param array<string> $taxonomies Taxonomy slugs.
		 */
		$taxonomies = apply_filters( '\FPML_translatable_taxonomies', $taxonomies );

		if ( empty( $taxonomies ) || ! in_array( $taxonomy, $taxonomies, true ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		if ( get_term_meta( $term_id, '_fpml_is_translation', true ) ) {
			return;
		}

		// Get enabled languages and sync for all of them
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();

		// Sync term translation for each enabled language
		foreach ( $enabled_languages as $target_lang ) {
			$target_term_id = $this->translation_manager->ensure_term_translation( $term_id, $taxonomy, $target_lang );

			if ( $target_term_id ) {
				$target_term = get_term( $target_term_id, $taxonomy );
				if ( $target_term && ! is_wp_error( $target_term ) ) {
					$this->job_enqueuer->enqueue_term_jobs( $term, $target_term );
				}
			}
		}
	}

	/**
	 * Handle term deletion - clean up translations.
	 *
	 * @param int    $term_id  Term ID being deleted.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_delete_term( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( $this->is_assisted_mode() ) {
			return;
		}

		// If this is a translation, remove pair_id from source
		$source_id = get_term_meta( $term_id, '_fpml_pair_source_id', true );
		if ( $source_id ) {
			delete_term_meta( $source_id, '_fpml_pair_id' );
		}

		// If this is a source, remove pair reference from translations for all enabled languages
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();

		// Check all enabled languages
		foreach ( $enabled_languages as $lang ) {
			$meta_key = '_fpml_pair_id_' . $lang;
			$translation_id = get_term_meta( $term_id, $meta_key, true );

			if ( $translation_id ) {
				delete_term_meta( $translation_id, '_fpml_pair_source_id' );

				// Optionally delete the translation term
				$auto_delete = apply_filters( '\FPML_auto_delete_translation_term_on_source_delete', false );
				if ( $auto_delete ) {
					wp_delete_term( $translation_id, $taxonomy );
				}
			}
		}

		// Also handle legacy _fpml_pair_id
		$legacy_id = get_term_meta( $term_id, '_fpml_pair_id', true );
		if ( $legacy_id ) {
			delete_term_meta( $legacy_id, '_fpml_pair_source_id' );
			$auto_delete = apply_filters( '\FPML_auto_delete_translation_term_on_source_delete', false );
			if ( $auto_delete ) {
				wp_delete_term( $legacy_id, $taxonomy );
			}
			delete_term_meta( $term_id, '_fpml_pair_id' );
		}
	}
}

