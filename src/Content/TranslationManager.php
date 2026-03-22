<?php
/**
 * Translation Manager
 *
 * @package FP\Multilanguage
 * @since 0.1.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\Logger;
use FP\Multilanguage\Content\TranslationManager\PostTranslationManager;
use FP\Multilanguage\Content\TranslationManager\TermTranslationManager;
use FP\Multilanguage\Content\TranslationManager\TranslationCache;
use FP\Multilanguage\Content\TranslationManager\MetaManager;

/**
 * Manages the creation and synchronization of translated posts and terms.
 *
 * @since 0.1.0
 * @since 0.10.0 Refactored to use modular components.
 */
class TranslationManager {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Post translation manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var PostTranslationManager
	 */
	protected PostTranslationManager $post_manager;

	/**
	 * Term translation manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TermTranslationManager
	 */
	protected TermTranslationManager $term_manager;

	/**
	 * Translation cache instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TranslationCache
	 */
	protected TranslationCache $cache;

	/**
	 * Get singleton instance (for backward compatibility).
	 *
	 * @since 0.1.0
	 * @deprecated 1.0.0 Use dependency injection via container instead
	 *
	 * @return self
	 */
	public static function instance(): self {
		_doing_it_wrong( 
			'FP\Multilanguage\Content\TranslationManager::instance()', 
			'TranslationManager::instance() is deprecated. Use dependency injection via container instead.', 
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
	 * @since 1.0.0 Now public to support dependency injection
	 *
	 * @param Logger|null $logger Optional logger instance for DI.
	 */
	public function __construct( $logger = null ) {
		// Use injected logger or get from container/singleton
		if ( null === $logger ) {
			$container = $this->getContainer();
			if ( $container && $container->has( 'logger' ) ) {
				$this->logger = $container->get( 'logger' );
			} elseif ( class_exists( '\FP\Multilanguage\Logger' ) ) {
				$this->logger = fpml_get_logger();
			}
		} else {
			$this->logger = $logger;
		}

		// Initialize modules (avoid circular dependency)
		$meta_manager = new MetaManager();
		$this->cache = new TranslationCache( $meta_manager );
		$meta_manager->set_cache( $this->cache );
		
		$this->post_manager = new PostTranslationManager( $this->logger, $meta_manager, $this->cache );
		$this->term_manager = new TermTranslationManager( $this->logger );
	}

	/**
	 * Check if a translation is currently being created.
	 *
	 * @since 0.6.0
	 *
	 * @return bool
	 */
	public function is_creating_translation(): bool {
		return $this->post_manager->is_creating_translation();
	}

	/**
	 * Ensure a post has a translation, but ONLY create it when explicitly requested.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Post $post Post object.
	 * @param string    $target_lang Target language code. Default 'en'.
	 * @return \WP_Post|false Translated post object, or false on failure.
	 */
	public function ensure_post_translation( \WP_Post $post, string $target_lang = 'en' ): \WP_Post|false {
		return $this->post_manager->ensure_post_translation( $post, $target_lang );
	}

	/**
	 * Create translation explicitly when requested.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Post $post Post object.
	 * @param string    $target_lang Target language code. Default 'en'.
	 * @param string    $post_status Post status. Default 'draft'.
	 * @return \WP_Post|false Translated post object, or false on failure.
	 */
	public function create_post_translation( \WP_Post $post, string $target_lang = 'en', string $post_status = 'draft' ): \WP_Post|false {
		return $this->post_manager->create_post_translation( $post, $target_lang, $post_status );
	}

	/**
	 * Get translation ID for a specific language.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $post_id Source post ID.
	 * @param string $target_lang Target language code.
	 * @return int|false Translation post ID, or false if not found.
	 */
	public function get_translation_id( int $post_id, string $target_lang = 'en' ): int|false {
		$translation_id = $this->cache->get_translation_id( $post_id, $target_lang );
		if ( $translation_id ) {
			return $translation_id;
		}

		// WPML fallback: when an external translation already exists, reuse it
		// to avoid creating duplicated translations in coexistence scenarios.
		return $this->get_wpml_translation_id( $post_id, $target_lang );
	}

	/**
	 * Get all translations for a post.
	 *
	 * @since 0.10.0
	 *
	 * @param int $post_id Source post ID.
	 * @return array Array of [language_code => translation_id] pairs.
	 */
	public function get_all_translations( int $post_id ): array {
		$translations = $this->cache->get_all_translations( $post_id );

		if ( ! $this->is_wpml_active() ) {
			return $translations;
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof \WP_Post ) ) {
			return $translations;
		}

		$enabled_languages = function_exists( 'fpml_get_enabled_languages' ) ? fpml_get_enabled_languages() : array();
		foreach ( $enabled_languages as $lang_code ) {
			if ( isset( $translations[ $lang_code ] ) && (int) $translations[ $lang_code ] > 0 ) {
				continue;
			}

			$wpml_translation_id = $this->get_wpml_translation_id( $post_id, (string) $lang_code, $post->post_type );
			if ( $wpml_translation_id ) {
				$translations[ $lang_code ] = $wpml_translation_id;
			}
		}

		return $translations;
	}

	/**
	 * Resolve translation ID from WPML when available.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $post_id     Source post ID.
	 * @param string      $target_lang Target language code.
	 * @param string|null $post_type   Optional post type.
	 * @return int|false
	 */
	protected function get_wpml_translation_id( int $post_id, string $target_lang, ?string $post_type = null ): int|false {
		if ( ! $this->is_wpml_active() ) {
			return false;
		}

		if ( null === $post_type ) {
			$post = get_post( $post_id );
			if ( ! ( $post instanceof \WP_Post ) ) {
				return false;
			}
			$post_type = $post->post_type;
		}

		$wpml_translation_id = (int) icl_object_id( $post_id, $post_type, false, $target_lang );
		if ( $wpml_translation_id > 0 && $wpml_translation_id !== $post_id ) {
			return $wpml_translation_id;
		}

		return false;
	}

	/**
	 * Check whether WPML runtime is available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function is_wpml_active(): bool {
		return function_exists( 'icl_object_id' ) && ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) );
	}

	/**
	 * Ensure a term has a translation.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $target_lang Target language code. Default 'en'.
	 * @return \WP_Term|false Translated term object, or false on failure.
	 */
	public function ensure_term_translation( int $term_id, string $taxonomy, string $target_lang = 'en' ): \WP_Term|false {
		return $this->term_manager->ensure_term_translation( $term_id, $taxonomy, $target_lang );
	}
}
