<?php
/**
 * Term Translation Service.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Domain\Services;

use FP\Multilanguage\Domain\Repositories\TranslationRepositoryInterface;
use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for translating terms.
 *
 * @since 1.0.0
 */
class TermTranslationService {
	/**
	 * Translation repository.
	 *
	 * @var TranslationRepositoryInterface
	 */
	protected $repository;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
	 */
	protected $logger;

	/**
	 * Translation service.
	 *
	 * @var TranslationServiceInterface
	 */
	protected $translation_service;

	/**
	 * Constructor.
	 *
	 * @param TranslationRepositoryInterface $repository          Translation repository.
	 * @param TranslationServiceInterface    $translation_service Translation service.
	 * @param LoggerInterface|null           $logger              Logger instance.
	 */
	public function __construct(
		TranslationRepositoryInterface $repository,
		TranslationServiceInterface $translation_service,
		?LoggerInterface $logger = null
	) {
		$this->repository          = $repository;
		$this->translation_service = $translation_service;
		$this->logger              = $logger;
	}

	/**
	 * Translate a term.
	 *
	 * @param WP_Term $source_term Source term.
	 * @param WP_Term $target_term Target term.
	 * @param array   $fields     Fields to translate (name, description).
	 * @return array Result with statistics.
	 */
	public function translateTerm( WP_Term $source_term, WP_Term $target_term, array $fields = array() ): array {
		$result = array(
			'translated' => 0,
			'skipped'    => 0,
			'errors'     => 0,
		);

		// Default fields if not provided
		if ( empty( $fields ) ) {
			$fields = array( 'name', 'description' );
		}

		foreach ( $fields as $field ) {
			try {
				$translation_result = $this->translateTermField( $source_term, $target_term, $field );

				if ( is_wp_error( $translation_result ) ) {
					$result['errors']++;
					if ( $this->logger ) {
						$this->logger->error(
							'Term field translation failed',
							array(
								'source_term_id' => $source_term->term_id,
								'target_term_id' => $target_term->term_id,
								'field'          => $field,
								'error'          => $translation_result->get_error_message(),
							)
						);
					}
				} elseif ( 'skipped' === $translation_result ) {
					$result['skipped']++;
				} else {
					$result['translated']++;
				}
			} catch ( \Exception $e ) {
				$result['errors']++;
				if ( $this->logger ) {
					$this->logger->error(
						'Term field translation exception',
						array(
							'source_term_id' => $source_term->term_id,
							'target_term_id' => $target_term->term_id,
							'field'          => $field,
							'exception'      => $e->getMessage(),
						)
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Translate a specific term field.
	 *
	 * @param WP_Term $source_term Source term.
	 * @param WP_Term $target_term Target term.
	 * @param string  $field       Field to translate.
	 * @return bool|string|WP_Error True on success, 'skipped' if skipped, WP_Error on failure.
	 */
	protected function translateTermField( WP_Term $source_term, WP_Term $target_term, string $field ) {
		// Get source value
		$source_value = $this->getTermFieldValue( $source_term, $field );

		if ( empty( $source_value ) ) {
			return 'skipped';
		}

		// Get target language
		$target_language = get_term_meta( $target_term->term_id, '_fpml_target_language', true );
		if ( empty( $target_language ) ) {
			$target_language = 'en'; // Default
		}

		// Translate content
		$translated_value = $this->translation_service->translate(
			$source_value,
			'it', // Source language (default)
			$target_language,
			array(
				'object_type' => 'term',
				'object_id'   => $source_term->term_id,
				'field'       => $field,
			)
		);

		// Save translated value
		$this->saveTermFieldValue( $target_term, $field, $translated_value );

		return true;
	}

	/**
	 * Get term field value.
	 *
	 * @param WP_Term $term  Term object.
	 * @param string  $field Field name.
	 * @return string
	 */
	protected function getTermFieldValue( WP_Term $term, string $field ): string {
		if ( 'name' === $field ) {
			return $term->name;
		}

		if ( 'description' === $field ) {
			return $term->description;
		}

		return '';
	}

	/**
	 * Save term field value.
	 *
	 * @param WP_Term $term  Term object.
	 * @param string  $field Field name.
	 * @param string  $value Value to save.
	 * @return void
	 */
	protected function saveTermFieldValue( WP_Term $term, string $field, string $value ): void {
		$args = array(
			'name'        => $term->name,
			'description' => $term->description,
		);

		if ( 'name' === $field ) {
			$args['name'] = sanitize_text_field( $value );
		}

		if ( 'description' === $field ) {
			$args['description'] = wp_kses_post( $value );
		}

		wp_update_term( $term->term_id, $term->taxonomy, $args );
	}
}














