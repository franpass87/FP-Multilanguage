<?php
/**
 * Translation Repository Implementation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Domain\Repositories;

use FP\Multilanguage\Domain\Models\Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-based translation repository.
 *
 * @since 1.0.0
 */
class TranslationRepository implements TranslationRepositoryInterface {
	/**
	 * Find translation by source ID and target language.
	 *
	 * @param int    $source_id      Source object ID.
	 * @param string $target_language Target language code.
	 * @param string $object_type    Object type.
	 * @return Translation|null
	 */
	public function findBySourceAndLanguage( int $source_id, string $target_language, string $object_type ): ?Translation {
		// Use existing TranslationManager logic
		if ( class_exists( '\FP\Multilanguage\Content\TranslationManager' ) ) {
			$translation_manager = fpml_get_translation_manager();
			
		if ( ! $translation_manager ) {
			return null;
		}

		if ( 'post' === $object_type ) {
			$target_id = $translation_manager->get_translation_id( $source_id, $target_language );
		} elseif ( 'term' === $object_type ) {
			// Use new meta key format
			$meta_key = '_fpml_pair_id_' . $target_language;
			$target_id = (int) get_term_meta( $source_id, $meta_key, true );
			// Backward compatibility: check legacy _fpml_pair_id if lang is 'en'
			if ( ! $target_id && 'en' === $target_language ) {
				$target_id = (int) get_term_meta( $source_id, '_fpml_pair_id', true );
			}
		} else {
			return null;
		}

			if ( $target_id > 0 ) {
				return new Translation(
					$source_id,
					$target_id,
					$object_type,
					$target_language,
					'completed'
				);
			}
		}

		return null;
	}

	/**
	 * Find translation by target ID.
	 *
	 * @param int $target_id Target object ID.
	 * @return Translation|null
	 */
	public function findByTarget( int $target_id ): ?Translation {
		// Check if it's a translation
		$is_translation = get_post_meta( $target_id, '_fpml_is_translation', true );
		if ( ! $is_translation ) {
			return null;
		}

		// Get source ID using new meta key
		$source_id = (int) get_post_meta( $target_id, '_fpml_pair_source_id', true );
		// Backward compatibility: check legacy _fpml_pair_id
		if ( ! $source_id ) {
			$source_id = (int) get_post_meta( $target_id, '_fpml_pair_id', true );
		}
		if ( ! $source_id ) {
			return null;
		}

		// Get target language
		$target_language = get_post_meta( $target_id, '_fpml_target_language', true );
		if ( empty( $target_language ) ) {
			$target_language = 'en';
		}

		return new Translation(
			$source_id,
			$target_id,
			'post',
			$target_language,
			'completed'
		);
	}

	/**
	 * Save translation.
	 *
	 * @param Translation $translation Translation model.
	 * @return bool
	 */
	public function save( Translation $translation ): bool {
		// Use existing meta storage with new meta keys
		if ( 'post' === $translation->getObjectType() ) {
			$target_lang = $translation->getTargetLanguage();
			update_post_meta( $translation->getTargetId(), '_fpml_is_translation', '1' );
			update_post_meta( $translation->getTargetId(), '_fpml_pair_source_id', $translation->getSourceId() );
			update_post_meta( $translation->getTargetId(), '_fpml_target_language', $target_lang );
			// Use new language-specific meta key
			update_post_meta( $translation->getSourceId(), '_fpml_pair_id_' . $target_lang, $translation->getTargetId() );
			// Backward compatibility: also update legacy _fpml_pair_id for 'en'
			if ( 'en' === $target_lang ) {
				update_post_meta( $translation->getSourceId(), '_fpml_pair_id', $translation->getTargetId() );
			}
			return true;
		}

		if ( 'term' === $translation->getObjectType() ) {
			$target_lang = $translation->getTargetLanguage();
			update_term_meta( $translation->getTargetId(), '_fpml_is_translation', '1' );
			update_term_meta( $translation->getTargetId(), '_fpml_pair_source_id', $translation->getSourceId() );
			update_term_meta( $translation->getTargetId(), '_fpml_target_language', $target_lang );
			// Use new language-specific meta key
			update_term_meta( $translation->getSourceId(), '_fpml_pair_id_' . $target_lang, $translation->getTargetId() );
			// Backward compatibility: also update legacy _fpml_pair_id for 'en'
			if ( 'en' === $target_lang ) {
				update_term_meta( $translation->getSourceId(), '_fpml_pair_id', $translation->getTargetId() );
			}
			return true;
		}

		return false;
	}

	/**
	 * Delete translation.
	 *
	 * @param Translation $translation Translation model.
	 * @return bool
	 */
	public function delete( Translation $translation ): bool {
		$target_lang = $translation->getTargetLanguage();
		
		if ( 'post' === $translation->getObjectType() ) {
			delete_post_meta( $translation->getTargetId(), '_fpml_is_translation' );
			delete_post_meta( $translation->getTargetId(), '_fpml_pair_source_id' );
			delete_post_meta( $translation->getTargetId(), '_fpml_target_language' );
			// Delete new language-specific meta key
			delete_post_meta( $translation->getSourceId(), '_fpml_pair_id_' . $target_lang );
			// Backward compatibility: also delete legacy _fpml_pair_id for 'en'
			if ( 'en' === $target_lang ) {
				delete_post_meta( $translation->getSourceId(), '_fpml_pair_id' );
			}
			return true;
		}

		if ( 'term' === $translation->getObjectType() ) {
			delete_term_meta( $translation->getTargetId(), '_fpml_is_translation' );
			delete_term_meta( $translation->getTargetId(), '_fpml_pair_source_id' );
			delete_term_meta( $translation->getTargetId(), '_fpml_target_language' );
			// Delete new language-specific meta key
			delete_term_meta( $translation->getSourceId(), '_fpml_pair_id_' . $target_lang );
			// Backward compatibility: also delete legacy _fpml_pair_id for 'en'
			if ( 'en' === $target_lang ) {
				delete_term_meta( $translation->getSourceId(), '_fpml_pair_id' );
			}
			return true;
		}

		return false;
	}

	/**
	 * Get all translations for a source object.
	 *
	 * @param int    $source_id   Source object ID.
	 * @param string $object_type Object type.
	 * @return Translation[]
	 */
	public function findAllBySource( int $source_id, string $object_type ): array {
		$translations = array();

		if ( 'post' === $object_type ) {
			// Get all enabled languages
			if ( class_exists( '\FP\Multilanguage\MultiLanguage\LanguageManager' ) ) {
				$language_manager = fpml_get_language_manager();
				$enabled_languages = $language_manager->get_enabled_languages();

				foreach ( $enabled_languages as $lang ) {
					$translation = $this->findBySourceAndLanguage( $source_id, $lang, $object_type );
					if ( $translation ) {
						$translations[] = $translation;
					}
				}
			}
		}

		return $translations;
	}
}














