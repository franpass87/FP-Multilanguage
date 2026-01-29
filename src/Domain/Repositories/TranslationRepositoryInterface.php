<?php
/**
 * Translation Repository Interface.
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
 * Interface for translation repositories.
 *
 * @since 1.0.0
 */
interface TranslationRepositoryInterface {
	/**
	 * Find translation by source ID and target language.
	 *
	 * @param int    $source_id      Source object ID.
	 * @param string $target_language Target language code.
	 * @param string $object_type    Object type.
	 * @return Translation|null
	 */
	public function findBySourceAndLanguage( int $source_id, string $target_language, string $object_type ): ?Translation;

	/**
	 * Find translation by target ID.
	 *
	 * @param int $target_id Target object ID.
	 * @return Translation|null
	 */
	public function findByTarget( int $target_id ): ?Translation;

	/**
	 * Save translation.
	 *
	 * @param Translation $translation Translation model.
	 * @return bool
	 */
	public function save( Translation $translation ): bool;

	/**
	 * Delete translation.
	 *
	 * @param Translation $translation Translation model.
	 * @return bool
	 */
	public function delete( Translation $translation ): bool;

	/**
	 * Get all translations for a source object.
	 *
	 * @param int    $source_id   Source object ID.
	 * @param string $object_type Object type.
	 * @return Translation[]
	 */
	public function findAllBySource( int $source_id, string $object_type ): array;
}














