<?php
/**
 * Post Translation Service.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Domain\Services;

use FP\Multilanguage\Domain\Repositories\TranslationRepositoryInterface;
use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for translating posts.
 *
 * @since 1.0.0
 */
class PostTranslationService {
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
	 * Translate a post directly (immediate translation).
	 *
	 * @param WP_Post $source_post Source post.
	 * @param WP_Post $target_post Target post.
	 * @param array   $fields      Fields to translate.
	 * @return array Result with statistics: { 'translated' => int, 'skipped' => int, 'errors' => int }
	 */
	public function translatePost( WP_Post $source_post, WP_Post $target_post, array $fields = array() ): array {
		$result = array(
			'translated' => 0,
			'skipped'    => 0,
			'errors'     => 0,
		);

		// Default fields if not provided
		if ( empty( $fields ) ) {
			$fields = array( 'post_title', 'post_excerpt', 'post_content', 'slug' );
		}

		foreach ( $fields as $field ) {
			try {
				$translation_result = $this->translatePostField( $source_post, $target_post, $field );

				if ( is_wp_error( $translation_result ) ) {
					$result['errors']++;
					if ( $this->logger ) {
						$this->logger->error(
							'Post field translation failed',
							array(
								'source_post_id' => $source_post->ID,
								'target_post_id' => $target_post->ID,
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
						'Post field translation exception',
						array(
							'source_post_id' => $source_post->ID,
							'target_post_id' => $target_post->ID,
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
	 * Translate a specific post field.
	 *
	 * @param WP_Post $source_post Source post.
	 * @param WP_Post $target_post Target post.
	 * @param string  $field       Field to translate.
	 * @return bool|string|WP_Error True on success, 'skipped' if skipped, WP_Error on failure.
	 */
	protected function translatePostField( WP_Post $source_post, WP_Post $target_post, string $field ) {
		// Get source value
		$source_value = $this->getPostFieldValue( $source_post, $field );

		if ( empty( $source_value ) ) {
			return 'skipped';
		}

		// Get target language
		$target_language = get_post_meta( $target_post->ID, '_fpml_target_language', true );
		if ( empty( $target_language ) ) {
			$target_language = 'en'; // Default
		}

		// Translate content
		$translated_value = $this->translation_service->translate(
			$source_value,
			'it', // Source language (default)
			$target_language,
			array(
				'object_type' => 'post',
				'object_id'   => $source_post->ID,
				'field'       => $field,
			)
		);

		// Save translated value
		$this->savePostFieldValue( $target_post, $field, $translated_value );

		return true;
	}

	/**
	 * Get post field value.
	 *
	 * @param WP_Post $post  Post object.
	 * @param string  $field Field name.
	 * @return string
	 */
	protected function getPostFieldValue( WP_Post $post, string $field ): string {
		if ( 'slug' === $field ) {
			return $post->post_name;
		}

		if ( strpos( $field, 'meta:' ) === 0 ) {
			$meta_key = substr( $field, 5 );
			return (string) get_post_meta( $post->ID, $meta_key, true );
		}

		// Standard post fields
		if ( isset( $post->$field ) ) {
			return (string) $post->$field;
		}

		return '';
	}

	/**
	 * Save post field value.
	 *
	 * @param WP_Post $post  Post object.
	 * @param string  $field Field name.
	 * @param string  $value Value to save.
	 * @return void
	 */
	protected function savePostFieldValue( WP_Post $post, string $field, string $value ): void {
		if ( 'slug' === $field ) {
			wp_update_post(
				array(
					'ID'        => $post->ID,
					'post_name' => sanitize_title( $value ),
				)
			);
			return;
		}

		if ( strpos( $field, 'meta:' ) === 0 ) {
			$meta_key = substr( $field, 5 );
			update_post_meta( $post->ID, $meta_key, $value );
			return;
		}

		// Standard post fields
		wp_update_post(
			array(
				'ID'   => $post->ID,
				$field => $value,
			)
		);
	}
}














