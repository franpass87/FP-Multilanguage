<?php
/**
 * Translation Manager Post Translation Manager - Manages post translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Content\TranslationManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages post translations.
 *
 * @since 0.10.0
 */
class PostTranslationManager {
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger
	 */
	protected $logger;

	/**
	 * Meta manager instance.
	 *
	 * @var MetaManager
	 */
	protected MetaManager $meta_manager;

	/**
	 * Translation cache instance.
	 *
	 * @var TranslationCache
	 */
	protected TranslationCache $cache;

	/**
	 * Creating translation flag.
	 *
	 * @var bool
	 */
	protected bool $creating_translation = false;

	/**
	 * Constructor.
	 *
	 * @param \FP\Multilanguage\Logger $logger      Logger instance.
	 * @param MetaManager               $meta_manager Meta manager instance.
	 * @param TranslationCache          $cache       Cache instance.
	 */
	public function __construct( $logger, MetaManager $meta_manager, TranslationCache $cache ) {
		$this->logger = $logger;
		$this->meta_manager = $meta_manager;
		$this->cache = $cache;
	}

	/**
	 * Check if a translation is currently being created.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function is_creating_translation(): bool {
		return $this->creating_translation;
	}

	/**
	 * Ensure a post has a translation, but ONLY create it when explicitly requested.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post object.
	 * @param string    $target_lang Target language code. Default 'en'.
	 * @return \WP_Post|false Translated post object, or false on failure.
	 */
	public function ensure_post_translation( \WP_Post $post, string $target_lang = 'en' ): \WP_Post|false {
		// Check if translation already exists for this language
		$target_id = $this->cache->get_translation_id( $post->ID, $target_lang );

		if ( $target_id ) {
			$target_post = get_post( $target_id );

			if ( $target_post instanceof \WP_Post ) {
				// Update meta to ensure consistency (using direct DB to avoid hooks)
				$this->meta_manager->update_meta_directly( $target_post->ID, '_fpml_pair_source_id', (string) $post->ID );
				$this->meta_manager->update_meta_directly( $target_post->ID, '_fpml_is_translation', '1' );
				$this->meta_manager->update_meta_directly( $target_post->ID, '_fpml_target_language', $target_lang );

				return $target_post;
			}
		}

		// Mark that translation is needed but not yet created
		update_post_meta( $post->ID, '_fpml_translation_needed_' . $target_lang, '1' );
		
		// Return false to indicate translation doesn't exist yet
		return false;
	}

	/**
	 * Create translation explicitly when requested.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post object.
	 * @param string    $target_lang Target language code. Default 'en'.
	 * @param string    $post_status Post status. Default 'draft'.
	 * @return \WP_Post|false Translated post object, or false on failure.
	 */
	public function create_post_translation( \WP_Post $post, string $target_lang = 'en', string $post_status = 'draft' ): \WP_Post|false {
		// Validate target language
		$language_manager = fpml_get_language_manager();
		$available_languages = array_keys( $language_manager->get_all_languages() );
		if ( ! in_array( $target_lang, $available_languages, true ) ) {
			if ( $this->logger ) {
				$this->logger->log( 'error', sprintf( 'Invalid target language: %s', $target_lang ), array( 'post_id' => $post->ID ) );
			}
			return false;
		}

		// Check if translation already exists for this specific language
		$existing_id = $this->cache->get_translation_id( $post->ID, $target_lang );
		if ( $existing_id ) {
			$existing = get_post( $existing_id );
			if ( $existing instanceof \WP_Post ) {
				return $existing;
			}
		}

		// Backward compatibility: Check old _fpml_pair_id if target_lang is 'en'
		if ( 'en' === $target_lang ) {
			$legacy_id = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );
			if ( $legacy_id && ! $existing_id ) {
				// Migrate legacy _fpml_pair_id to _fpml_pair_id_en
				$this->meta_manager->update_meta_directly( $post->ID, '_fpml_pair_id_en', (string) $legacy_id );
				$this->meta_manager->update_meta_directly( $legacy_id, '_fpml_target_language', 'en' );
				$existing = get_post( $legacy_id );
				if ( $existing instanceof \WP_Post ) {
					return $existing;
				}
			}
		}

		// Set flags to prevent loops
		$this->creating_translation = true;
		$GLOBALS['fpml_creating_translation'] = true;

		try {
			// Map parent to its translation if exists
			$translated_parent = 0;
			if ( $post->post_parent > 0 ) {
				$parent_translation_id = $this->cache->get_translation_id( $post->post_parent, $target_lang );
				// Backward compatibility: check legacy _fpml_pair_id for 'en'
				if ( ! $parent_translation_id && 'en' === $target_lang ) {
					$parent_translation_id = (int) get_post_meta( $post->post_parent, '_fpml_pair_id', true );
				}
				if ( $parent_translation_id ) {
					$translated_parent = (int) $parent_translation_id;
				}
			}

			// Generate title and slug
			$translation_title = $post->post_title;
			$base_slug = $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
			$base_slug = preg_replace( '/^(it|en)[-_]/i', '', $base_slug );
			$temp_slug = $base_slug;

			// Create placeholder content
			$placeholder_content = '';

			// Direct database insertion with NO WordPress hooks
			global $wpdb;
			
			$now = current_time( 'mysql' );
			$now_gmt = current_time( 'mysql', 1 );
			
			// Determine post status
			$new_status = 'draft';
			if ( 'publish' === $post_status || ( 'draft' === $post_status && 'publish' === $post->post_status ) ) {
				$new_status = 'publish';
			}

			$post_data = array(
				'post_author' => $post->post_author,
				'post_date' => $now,
				'post_date_gmt' => $now_gmt,
				'post_content' => $placeholder_content,
				'post_title' => $translation_title,
				'post_excerpt' => '',
				'post_status' => $new_status,
				'comment_status' => $post->comment_status,
				'ping_status' => $post->ping_status,
				'post_password' => $post->post_password,
				'post_name' => $temp_slug,
				'to_ping' => '',
				'pinged' => '',
				'post_modified' => $now,
				'post_modified_gmt' => $now_gmt,
				'post_content_filtered' => '',
				'post_parent' => $translated_parent,
				'guid' => '',
				'menu_order' => $post->menu_order,
				'post_type' => $post->post_type,
				'post_mime_type' => '',
				'comment_count' => 0,
			);
			
			// Insert directly into database
			$insert_result = $wpdb->insert(
				$wpdb->posts,
				$post_data,
				array(
					'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
					'%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d'
				)
			);
			
			if ( false === $insert_result ) {
				throw new \Exception( sprintf( 'Database insert failed: %s', $wpdb->last_error ) );
			}
			
			$target_id = (int) $wpdb->insert_id;
			
			// Update GUID
			$guid = home_url( '/?p=' . $target_id );
			$wpdb->update(
				$wpdb->posts,
				array( 'guid' => $guid ),
				array( 'ID' => $target_id ),
				array( '%s' ),
				array( '%d' )
			);
			
			// Add meta fields directly (no hooks)
			$this->meta_manager->update_meta_directly( $target_id, '_fpml_is_translation', '1' );
			$this->meta_manager->update_meta_directly( $target_id, '_fpml_pair_source_id', (string) $post->ID );
			$this->meta_manager->update_meta_directly( $target_id, '_fpml_target_language', $target_lang );
			$this->meta_manager->update_meta_directly( $target_id, '_fpml_translation_status', 'pending' );
			
			// Store translation ID with language-specific meta key
			$meta_key = '_fpml_pair_id_' . $target_lang;
			$this->meta_manager->update_meta_directly( $post->ID, $meta_key, (string) $target_id );
			
			// Backward compatibility: also set _fpml_pair_id for 'en'
			if ( 'en' === $target_lang ) {
				$this->meta_manager->update_meta_directly( $post->ID, '_fpml_pair_id', (string) $target_id );
			}
			
			// Clear translation ID cache
			$this->cache->clear_translation_id_cache( $post->ID, $target_lang );
			
			// Clear post cache
			clean_post_cache( $target_id );
			wp_cache_delete( $target_id, 'posts' );
			wp_cache_delete( $post->ID, 'posts' );
			
			// Clear dashboard stats cache
			delete_transient( 'fpml_dashboard_stats' );
			
			$target_post = get_post( $target_id );
			
			if ( ! $target_post instanceof \WP_Post ) {
				throw new \Exception( 'Failed to retrieve created post' );
			}

			// Fire action with flag still active to prevent loops
			$GLOBALS['fpml_creating_translation'] = true;
			do_action( 'fpml_after_translation_saved', $target_post->ID, $post->ID );
			$GLOBALS['fpml_creating_translation'] = false;

			$this->creating_translation = false;
			
			if ( class_exists( '\FP\Multilanguage\Logger' ) ) {
				\FP\Multilanguage\Logger::debug( 'Post translation created successfully', array( 
					'target_id' => $target_id,
					'source_id' => $post->ID 
				) );
			}

			return $target_post;

		} catch ( \Exception $e ) {
			$this->creating_translation = false;
			$GLOBALS['fpml_creating_translation'] = false;
			
			if ( $this->logger ) {
				$this->logger->log(
					'error',
					sprintf( 'Failed to create translation for post #%d: %s', $post->ID, $e->getMessage() ),
					array( 'post_id' => $post->ID )
				);
			} else {
				error_log( sprintf( 'FPML: Failed to create translation for post #%d: %s', $post->ID, $e->getMessage() ) );
			}

			return false;
		}
	}
}
















