<?php
/**
 * Post Handlers Service - Manages post-related translation hooks.
 *
 * Extracted from Plugin.php to follow Single Responsibility Principle.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Logger;
use FP\Multilanguage\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles post-related translation hooks and events.
 *
 * @since 0.10.0
 */
class PostHandlers {
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
	 * @deprecated 1.0.0 Use dependency injection via container instead. Consider using PostHooks for new code.
	 *
	 * @return self
	 */
	public static function instance(): self {
		_doing_it_wrong( 
			'FP\Multilanguage\Core\PostHandlers::instance()', 
			'PostHandlers is deprecated. Use PostHooks via dependency injection instead.', 
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
	 * Get translatable post types.
	 *
	 * @return array<string> Post type slugs.
	 */
	public function get_translatable_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		if ( ! in_array( 'attachment', $post_types, true ) ) {
			$post_types[] = 'attachment';
		}

		$custom_post_types = get_option( '\FPML_custom_translatable_post_types', array() );
		if ( ! empty( $custom_post_types ) && is_array( $custom_post_types ) ) {
			$post_types = array_merge( $post_types, $custom_post_types );
		}

		/**
		 * Filter translatable post types.
		 *
		 * @param array<string> $post_types Post type slugs.
		 */
		$post_types = apply_filters( '\FPML_translatable_post_types', $post_types );

		return array_filter( array_map( 'sanitize_key', $post_types ) );
	}

	/**
	 * Sync post taxonomies between source and target posts.
	 *
	 * @param \WP_Post $source_post Source post.
	 * @param \WP_Post $target_post Target post.
	 *
	 * @return void
	 */
	public function sync_post_taxonomies( \WP_Post $source_post, \WP_Post $target_post ): void {
		if ( ! $this->translation_manager || ! $this->job_enqueuer ) {
			return;
		}

		// Determine target language from target post
		$target_lang = get_post_meta( $target_post->ID, '_fpml_target_language', true );
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();

		// Validate target_lang is enabled
		if ( empty( $target_lang ) || ! in_array( $target_lang, $enabled_languages, true ) ) {
			// Fallback to first enabled language
			$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
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

		if ( empty( $taxonomies ) ) {
			return;
		}

		// For each taxonomy, sync terms
		foreach ( $taxonomies as $taxonomy ) {
			$source_terms = wp_get_post_terms( $source_post->ID, $taxonomy, array( 'fields' => 'ids' ) );

			if ( empty( $source_terms ) || is_wp_error( $source_terms ) ) {
				continue;
			}

			$translated_term_ids = array();

			foreach ( $source_terms as $term_id ) {
				// Create or get term translation for specific target language
				$target_term_id = $this->translation_manager->ensure_term_translation( $term_id, $taxonomy, $target_lang );

				if ( $target_term_id ) {
					$target_term = get_term( $target_term_id, $taxonomy );
					if ( $target_term && ! is_wp_error( $target_term ) ) {
						$translated_term_ids[] = (int) $target_term->term_id;

						// Enqueue job to translate term name and description
						$source_term = get_term( $term_id, $taxonomy );
						if ( $source_term && ! is_wp_error( $source_term ) ) {
							$this->job_enqueuer->enqueue_term_jobs( $source_term, $target_term );
						}
					}
				}
			}

			// Assign translated terms to target post
			if ( ! empty( $translated_term_ids ) ) {
				wp_set_post_terms( $target_post->ID, $translated_term_ids, $taxonomy, false );
			}
		}
	}

	/**
	 * Handle post deletion - clean up translations.
	 *
	 * @param int $post_id Post ID being deleted.
	 *
	 * @return void
	 */
	public function handle_delete_post( int $post_id ): void {
		if ( $this->is_assisted_mode() || ! $this->translation_manager ) {
			return;
		}

		// If this is a translation, remove pair_id from source
		$source_id = get_post_meta( $post_id, '_fpml_pair_source_id', true );
		if ( $source_id ) {
			delete_post_meta( $source_id, '_fpml_pair_id' );
		}

		// If this is a source, optionally delete translations for all enabled languages
		$all_translations = $this->translation_manager->get_all_translations( $post_id );

		// Backward compatibility: also check legacy _fpml_pair_id
		$legacy_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
		if ( $legacy_id && empty( $all_translations ) ) {
			$all_translations['en'] = $legacy_id;
		}

		foreach ( $all_translations as $lang => $translation_id ) {
			// Remove pair reference from translation
			delete_post_meta( $translation_id, '_fpml_pair_source_id' );

			// Optionally trash the translation (configurable)
			$auto_delete = apply_filters( '\FPML_auto_delete_translation_on_source_delete', false );
			if ( $auto_delete ) {
				wp_trash_post( $translation_id );
			}
		}

		// Also delete legacy _fpml_pair_id if exists
		if ( $legacy_id ) {
			delete_post_meta( $post_id, '_fpml_pair_id' );
		}
	}
}

