<?php
namespace FPMultilanguage\Content;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

class TermTranslationManager {

	public const META_KEY = '_fp_multilanguage_term_translations';

	private TranslationService $translationService;

	private Logger $logger;

	public function __construct( TranslationService $translationService, Settings $settings, AdminNotices $notices, Logger $logger ) {
			unset( $settings, $notices );

			$this->translationService = $translationService;
			$this->logger             = $logger;
	}

	public function register(): void {
			add_action( 'created_term', array( $this, 'handle_term_change' ), 10, 3 );
			add_action( 'edited_term', array( $this, 'handle_term_change' ), 10, 3 );
			add_action( 'delete_term', array( $this, 'handle_term_delete' ), 10, 5 );

			add_filter( 'get_term', array( $this, 'filter_term' ), 10, 2 );
			add_filter( 'get_terms', array( $this, 'filter_terms' ), 10, 3 );

		foreach ( Settings::get_translatable_taxonomies() as $taxonomy ) {
			if ( '' === $taxonomy ) {
				continue;
			}

				add_filter( 'rest_prepare_' . $taxonomy, array( $this, 'expose_translations' ), 10, 3 );
		}
	}

	public function handle_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
			unset( $tt_id );

		if ( ! Settings::is_auto_translate_enabled() ) {
				return;
		}

		if ( ! $this->is_taxonomy_translatable( $taxonomy ) ) {
				return;
		}

			$this->translate_term( $term_id, $taxonomy, null, true );
	}

	public function handle_term_delete( int $term_id, int $tt_id, string $taxonomy, ?WP_Term $deleted_term = null, array $object_ids = array() ): void {
		unset( $tt_id, $taxonomy, $deleted_term, $object_ids );

			delete_term_meta( $term_id, self::META_KEY );
	}

	/**
	 * @param WP_Term|WP_Error|false $term
	 * @return WP_Term|WP_Error|false
	 */
	public function filter_term( $term, string $taxonomy ) {
		if ( ! $term instanceof WP_Term ) {
				return $term;
		}

		if ( ! $this->is_taxonomy_translatable( $taxonomy ) ) {
				return $term;
		}

			return $this->apply_translations_to_term( $term );
	}

		/**
		 * @param array<int, mixed>|mixed $terms
		 * @param array<int, string>      $taxonomies
		 * @param array<string, mixed>    $args
		 *
		 * @return array<int, mixed>|mixed
		 */
	/**
	 * @param array<int, mixed>|WP_Error $terms
	 * @param array<int, string>         $taxonomies
	 * @param array<string, mixed>       $args
	 * @return array<int, mixed>|WP_Error
	 */
	public function filter_terms( $terms, array $taxonomies, array $args ) {
		unset( $taxonomies, $args );

		if ( ! is_array( $terms ) || empty( $terms ) ) {
				return $terms;
		}

		foreach ( $terms as $index => $term ) {
			if ( ! $term instanceof WP_Term ) {
					continue;
			}

			if ( ! $this->is_taxonomy_translatable( $term->taxonomy ) ) {
					continue;
			}

				$terms[ $index ] = $this->apply_translations_to_term( $term );
		}

			return $terms;
	}

	public function translate_term( int $term_id, string $taxonomy, ?string $language = null, bool $force = false ): array {
			$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
				return array();
		}

		if ( ! $this->is_taxonomy_translatable( $term->taxonomy ) ) {
				return array();
		}

			$sourceLanguage  = Settings::get_source_language();
			$targetLanguages = Settings::get_target_languages();

		if ( null !== $language ) {
				$targetLanguages = array_intersect( $targetLanguages, array( $language ) );
		}

			$translations = $this->get_term_translations( $term_id );
			$hasChanges   = false;

		foreach ( $targetLanguages as $target ) {
			if ( $target === $sourceLanguage ) {
					continue;
			}

				$existing           = $translations[ $target ] ?? array();
				$updated            = $existing;
				$languageHasChanges = $force;

				$translatedName = $this->translationService->translate_text( $term->name, $sourceLanguage, $target );
			if ( '' !== $translatedName && ( ! isset( $existing['name'] ) || $existing['name'] !== $translatedName ) ) {
					$updated['name']    = $translatedName;
					$languageHasChanges = true;
			}

			if ( '' !== $term->description ) {
					$translatedDescription = $this->translationService->translate_text( $term->description, $sourceLanguage, $target, array( 'format' => 'html' ) );
				if ( '' !== $translatedDescription && ( ! isset( $existing['description'] ) || $existing['description'] !== $translatedDescription ) ) {
						$updated['description'] = $translatedDescription;
						$languageHasChanges     = true;
				}
			}

			if ( $languageHasChanges ) {
					$updated['updated_at']   = time();
					$updated['status']       = 'synced';
					$translations[ $target ] = $updated;
					$hasChanges              = true;
			}
		}

		if ( $hasChanges ) {
				update_term_meta( $term_id, self::META_KEY, $translations );

				$this->logger->debug(
					'Term translations updated.',
					array(
						'term_id'   => $term_id,
						'taxonomy'  => $taxonomy,
						'languages' => array_keys( $translations ),
					)
				);
		}

			return $translations;
	}

	/**
	 * @param WP_Term|WP_Error|false             $term
	 * @param WP_REST_Request<array<string, mixed>> $request
	 */
	public function expose_translations( WP_REST_Response $response, $term, WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		if ( ! $term instanceof WP_Term ) {
				return $response;
		}

		if ( ! isset( $response->data ) || ! is_array( $response->data ) ) {
				return $response;
		}

			$response->data['fp_multilanguage'] = array(
				'language'     => CurrentLanguage::resolve(),
				'translations' => $this->get_term_translations( $term->term_id ),
			);

			return $response;
	}

	public function get_term_translations( int $term_id ): array {
			$stored = get_term_meta( $term_id, self::META_KEY, true );
		if ( ! is_array( $stored ) ) {
				return array();
		}

			return $stored;
	}

	private function apply_translations_to_term( WP_Term $term ): WP_Term {
			$language = CurrentLanguage::resolve();
			$source   = Settings::get_source_language();

		if ( '' === $language || $language === $source ) {
				return $term;
		}

			$translations = $this->get_term_translations( $term->term_id );

			$nameApplied = false;
		if ( isset( $translations[ $language ]['name'] ) && '' !== $translations[ $language ]['name'] ) {
				$term->name  = (string) $translations[ $language ]['name'];
				$nameApplied = true;
		} else {
				$translatedName = $this->translationService->translate_text( $term->name, $source, $language );
			if ( '' !== $translatedName ) {
					$term->name  = $translatedName;
					$nameApplied = true;
			}
		}

			$descriptionApplied = false;
		if ( isset( $translations[ $language ]['description'] ) && '' !== $translations[ $language ]['description'] ) {
				$term->description  = (string) $translations[ $language ]['description'];
				$descriptionApplied = true;
		} elseif ( '' !== $term->description ) {
				$translatedDescription = $this->translationService->translate_text( $term->description, $source, $language, array( 'format' => 'html' ) );
			if ( '' !== $translatedDescription ) {
					$term->description  = $translatedDescription;
					$descriptionApplied = true;
			}
		}

		if ( ! $nameApplied ) {
				$fallback = Settings::get_fallback_language();
			if ( $fallback !== $language && isset( $translations[ $fallback ]['name'] ) && '' !== $translations[ $fallback ]['name'] ) {
					$term->name = (string) $translations[ $fallback ]['name'];
			}
		}

		if ( ! $descriptionApplied && '' !== $term->description ) {
				$fallback = Settings::get_fallback_language();
			if ( $fallback !== $language && isset( $translations[ $fallback ]['description'] ) && '' !== $translations[ $fallback ]['description'] ) {
					$term->description = (string) $translations[ $fallback ]['description'];
			}
		}

			return $term;
	}

	private function is_taxonomy_translatable( string $taxonomy ): bool {
		if ( '' === $taxonomy ) {
				return false;
		}

			return in_array( $taxonomy, Settings::get_translatable_taxonomies(), true );
	}
}
