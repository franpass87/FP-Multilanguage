<?php
/**
 * Bulk operations handler for translations.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Logger;
use FP\Multilanguage\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle bulk translation operations.
 *
 * @since 0.5.0
 */
class BulkOperations {
	/**
	 * Translate multiple posts.
	 *
	 * @param array<int> $post_ids Post IDs to translate.
	 *
	 * @return array{success: int, failed: int, errors: array<string>} Results.
	 */
	public static function translate_posts( array $post_ids ): array {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		$processor = \fpml_get_processor();
		$translation_manager = Container::get( 'translation_manager' );

		if ( ! $processor || ! $translation_manager ) {
			return array(
				'success' => 0,
				'failed'  => count( $post_ids ),
				'errors'  => array( 'Processor o TranslationManager non disponibili' ),
			);
		}

		foreach ( $post_ids as $post_id ) {
			try {
				$source_post = get_post( $post_id );
				if ( ! $source_post ) {
					$results['failed']++;
					$results['errors'][] = "Post #{$post_id} non trovato";
					continue;
				}

				// Create translation explicitly (bulk operation) for first enabled language
				$language_manager  = fpml_get_language_manager();
				if ( ! $language_manager ) {
					$results['failed']++;
					$results['errors'][] = "Language manager non disponibile per post #{$post_id}";
					continue;
				}
			$enabled_languages = $language_manager->get_enabled_languages();
			if ( empty( $enabled_languages ) ) {
				$results['failed']++;
				$results['errors'][] = "Nessuna lingua target abilitata per post #{$post_id}";
				continue;
			}
			$target_lang = $enabled_languages[0];

			$existing_id = $translation_manager->get_translation_id( $source_post->ID, $target_lang );
			// Backward compatibility: legacy _fpml_pair_id (no lang suffix).
			if ( ! $existing_id ) {
				$existing_id = (int) get_post_meta( $source_post->ID, '_fpml_pair_id', true );
			}
				
				if ( $existing_id ) {
					$target_post = get_post( $existing_id );
				} else {
					$target_post = $translation_manager->create_post_translation( $source_post, $target_lang, 'draft' );
				}
				
				if ( ! $target_post ) {
					$results['failed']++;
					$results['errors'][] = "Impossibile creare traduzione per post #{$post_id}";
					continue;
				}

				// Translate directly
				if ( method_exists( $processor, 'translate_post_directly' ) ) {
					$translation_result = $processor->translate_post_directly( $source_post, $target_post );
					if ( is_wp_error( $translation_result ) ) {
						$results['failed']++;
						$results['errors'][] = "Errore traduzione post #{$post_id}: " . $translation_result->get_error_message();
					} else {
						$results['success']++;
						Logger::info( 'Post tradotto con successo', array( 'post_id' => $post_id, 'target_id' => $target_post->ID ) );
					}
				} else {
					$results['failed']++;
					$results['errors'][] = "Metodo translate_post_directly non disponibile";
				}
			} catch ( \Exception $e ) {
				$results['failed']++;
				$results['errors'][] = "Eccezione post #{$post_id}: " . $e->getMessage();
				Logger::error( 'Errore traduzione bulk post', array( 'post_id' => $post_id, 'error' => $e->getMessage() ) );
			}
		}

		return $results;
	}

	/**
	 * Regenerate translations for multiple posts.
	 *
	 * @param array<int> $post_ids Post IDs to regenerate.
	 *
	 * @return array{success: int, failed: int, errors: array<string>} Results.
	 */
	public static function regenerate_translations( array $post_ids ): array {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		$processor           = \fpml_get_processor();
		$translation_manager = Container::get( 'translation_manager' );

		if ( ! $processor || ! $translation_manager ) {
			return array(
				'success' => 0,
				'failed'  => count( $post_ids ),
				'errors'  => array( 'Processor o TranslationManager non disponibili' ),
			);
		}

		$language_manager = fpml_get_language_manager();
		if ( ! $language_manager ) {
			return array(
				'success' => 0,
				'failed'  => count( $post_ids ),
				'errors'  => array( 'Language manager non disponibile' ),
			);
		}
		$enabled_languages = $language_manager->get_enabled_languages();

		foreach ( $post_ids as $post_id ) {
			try {
				$source_post = get_post( $post_id );
				if ( ! $source_post ) {
					$results['failed']++;
					$results['errors'][] = "Post #{$post_id} non trovato";
					continue;
				}

				// Itera su TUTTE le lingue abilitate, non solo la prima trovata.
				$any_success = false;
				foreach ( $enabled_languages as $lang_code ) {
			$target_post_id = (int) $translation_manager->get_translation_id( $post_id, $lang_code );
			// Backward compatibility: legacy _fpml_pair_id (no lang suffix).
			if ( ! $target_post_id ) {
				$target_post_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
			}
					if ( ! $target_post_id ) {
						continue;
					}
					$target_post = get_post( $target_post_id );
					if ( ! $target_post ) {
						continue;
					}

					if ( method_exists( $processor, 'translate_post_directly' ) ) {
						$translation_result = $processor->translate_post_directly( $source_post, $target_post );
						if ( is_wp_error( $translation_result ) ) {
							$results['errors'][] = "Errore rigenerazione post #{$post_id} [{$lang_code}]: " . $translation_result->get_error_message();
						} else {
							$any_success = true;
							Logger::info( 'Traduzione rigenerata con successo', array( 'post_id' => $post_id, 'lang' => $lang_code, 'target_id' => $target_post->ID ) );
						}
					}
				}

				if ( $any_success ) {
					$results['success']++;
				} else {
					$results['failed']++;
					$results['errors'][] = "Post #{$post_id}: nessuna traduzione rigenerata";
				}
			} catch ( \Exception $e ) {
				$results['failed']++;
				$results['errors'][] = "Eccezione rigenerazione post #{$post_id}: " . $e->getMessage();
				Logger::error( 'Errore rigenerazione bulk post', array( 'post_id' => $post_id, 'error' => $e->getMessage() ) );
			}
		}

		return $results;
	}

	/**
	 * Sync translations for multiple posts.
	 *
	 * @param array<int> $post_ids Post IDs to sync.
	 *
	 * @return array{success: int, failed: int, errors: array<string>} Results.
	 */
	public static function sync_translations( array $post_ids ): array {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		$job_enqueuer = Container::get( 'job_enqueuer' );
		$translation_manager = Container::get( 'translation_manager' );

		if ( ! $job_enqueuer || ! $translation_manager ) {
			return array(
				'success' => 0,
				'failed'  => count( $post_ids ),
				'errors'  => array( 'JobEnqueuer o TranslationManager non disponibili' ),
			);
		}

		$language_manager = fpml_get_language_manager();
		if ( ! $language_manager ) {
			return array(
				'success' => 0,
				'failed'  => count( $post_ids ),
				'errors'  => array( 'Language manager non disponibile' ),
			);
		}
		$enabled_languages = $language_manager->get_enabled_languages();

		foreach ( $post_ids as $post_id ) {
			try {
				$source_post = get_post( $post_id );
				if ( ! $source_post ) {
					$results['failed']++;
					$results['errors'][] = "Post #{$post_id} non trovato";
					continue;
				}

			// Itera su TUTTE le lingue abilitate, non solo la prima trovata.
			$any_enqueued = false;
			foreach ( $enabled_languages as $lang_code ) {
				$target_post_id = (int) $translation_manager->get_translation_id( $post_id, $lang_code );
				// Backward compatibility: legacy _fpml_pair_id (no lang suffix).
				if ( ! $target_post_id ) {
					$target_post_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
				}
				if ( ! $target_post_id ) {
					continue;
				}
				$target_post = get_post( $target_post_id );
				if ( ! $target_post ) {
					continue;
				}

				// Enqueue sync jobs
				$job_enqueuer->enqueue_post_jobs( $source_post, $target_post, true );
				$any_enqueued = true;
				Logger::info( 'Sincronizzazione accodata', array( 'post_id' => $post_id, 'lang' => $lang_code, 'target_id' => $target_post_id ) );
			}

			if ( $any_enqueued ) {
				$results['success']++;
			} else {
				$results['failed']++;
				$results['errors'][] = "Post #{$post_id} non ha traduzioni da sincronizzare";
			}
			} catch ( \Exception $e ) {
				$results['failed']++;
				$results['errors'][] = "Eccezione sincronizzazione post #{$post_id}: " . $e->getMessage();
				Logger::error( 'Errore sincronizzazione bulk post', array( 'post_id' => $post_id, 'error' => $e->getMessage() ) );
			}
		}

		return $results;
	}
}


