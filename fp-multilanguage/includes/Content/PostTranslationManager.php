<?php
namespace FPMultilanguage\Content;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use WP_Post;

class PostTranslationManager {

	public const META_KEY = '_fp_multilanguage_translations';

	public const RELATION_META_KEY = '_fp_multilanguage_relations';

	private TranslationService $translationService;

	private Settings $settings;

	private AdminNotices $notices;

	private Logger $logger;

	public function __construct( TranslationService $translationService, Settings $settings, AdminNotices $notices, Logger $logger ) {
		$this->translationService = $translationService;
		$this->settings           = $settings;
		$this->notices            = $notices;
		$this->logger             = $logger;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_query_var' ) );
		add_action( 'save_post', array( $this, 'handle_post_save' ), 20, 3 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_filter( 'the_content', array( $this, 'filter_content' ) );
		add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_attachment_attributes' ), 10, 2 );
		add_filter( 'rest_prepare_post', array( $this, 'expose_translations' ), 10, 3 );
		add_filter( 'rest_prepare_page', array( $this, 'expose_translations' ), 10, 3 );
		add_filter( 'rest_prepare_attachment', array( $this, 'expose_translations' ), 10, 3 );

		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
	}

	public function register_query_var(): void {
		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				if ( ! in_array( 'fp_lang', $vars, true ) ) {
					$vars[] = 'fp_lang';
				}

				return $vars;
			}
		);
	}

	public function handle_post_save( int $postId, WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $postId ) ) {
			return;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $postId ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
				return;
		}

		if ( ! Settings::is_auto_translate_enabled() ) {
				return;
		}

				$this->translate_post( $postId );
	}

	public function translate_post( int $postId, ?string $language = null, bool $force = false ): array {
		$post = get_post( $postId );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$sourceLanguage  = Settings::get_source_language();
		$targetLanguages = Settings::get_target_languages();
		if ( $language !== null ) {
			$targetLanguages = array_intersect( $targetLanguages, array( $language ) );
		}

				$translations  = $this->get_post_translations( $postId );
				$customFields  = $this->get_custom_fields( $post );
				$hasChanges    = false;
				$titleHash     = $this->hash_value( $post->post_title );
				$contentHash   = $this->hash_value( $post->post_content );
				$excerptSource = $post->post_excerpt !== '' ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 );
				$excerptHash   = $this->hash_value( $excerptSource );
				$metaHashes    = array();
		foreach ( $customFields as $metaKey => $value ) {
				$metaHashes[ $metaKey ] = $this->hash_value( (string) $value );
		}

		foreach ( $targetLanguages as $target ) {
			if ( $target === $sourceLanguage ) {
						continue;
			}

				$existing           = $translations[ $target ] ?? array();
				$updated            = $existing;
				$languageHasChanges = $force;

				$existingSource = array();
			if ( isset( $existing['source'] ) && is_array( $existing['source'] ) ) {
							$existingSource = $existing['source'];
			}

							$updatedSource = array(
								'title'   => $existingSource['title'] ?? null,
								'content' => $existingSource['content'] ?? null,
								'excerpt' => $existingSource['excerpt'] ?? null,
								'meta'    => is_array( $existingSource['meta'] ?? null ) ? $existingSource['meta'] : array(),
							);

							$languageHasChanges = $this->sync_field_translation(
								$updated,
								$existing,
								$updatedSource,
								'title',
								$post->post_title,
								$titleHash,
								$sourceLanguage,
								$target,
								array(),
								$force
							) || $languageHasChanges;

				$languageHasChanges = $this->sync_field_translation(
					$updated,
					$existing,
					$updatedSource,
					'content',
					$post->post_content,
					$contentHash,
					$sourceLanguage,
					$target,
					array( 'format' => 'html' ),
					$force
				) || $languageHasChanges;

				$languageHasChanges = $this->sync_field_translation(
					$updated,
					$existing,
					$updatedSource,
					'excerpt',
					$excerptSource,
					$excerptHash,
					$sourceLanguage,
					$target,
					array( 'format' => 'html' ),
					$force
				) || $languageHasChanges;

				list( $metaTranslations, $metaSource, $metaChanged ) = $this->sync_meta_translations(
					$existing,
					$updatedSource['meta'],
					$customFields,
					$metaHashes,
					$sourceLanguage,
					$target,
					$force
				);

			if ( ! empty( $metaTranslations ) ) {
				$updated['meta'] = $metaTranslations;
			} elseif ( isset( $updated['meta'] ) ) {
						unset( $updated['meta'] );
			}

				$updatedSource['meta'] = $metaSource;
				$languageHasChanges    = $languageHasChanges || $metaChanged;
				$existingMetaKeys      = array_keys( is_array( $existing['meta'] ?? null ) ? $existing['meta'] : array() );
				$currentMetaKeys       = array_keys( $customFields );
				$removedMetaKeys       = array_diff( $existingMetaKeys, $currentMetaKeys );
			if ( ! empty( $removedMetaKeys ) ) {
							$languageHasChanges = true;
			}

			if ( $languageHasChanges ) {
				$updated['source']       = $updatedSource;
				$updated['updated_at']   = time();
				$updated['status']       = 'synced';
				$translations[ $target ] = $updated;
				$hasChanges              = true;
			}
		}

		if ( $hasChanges ) {
				update_post_meta( $postId, self::META_KEY, $translations );
		}

		$this->persist_relations( $postId, $translations, $sourceLanguage );

		return $translations;
	}

	public function filter_content( string $content ): string {
		if ( is_admin() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return $content;
		}

		return $this->get_translated_value( $post, 'content', $content, array( 'format' => 'html' ) );
	}

	public function filter_title( string $title, $post = null ): string {
		if ( is_admin() ) {
			return $title;
		}

		$postObject = get_post( $post );
		if ( ! $postObject instanceof WP_Post ) {
			return $title;
		}

		return $this->get_translated_value( $postObject, 'title', $title );
	}

	public function filter_excerpt( $excerpt, $post = null ) {
		if ( is_admin() ) {
			return $excerpt;
		}

		$postObject = get_post( $post );
		if ( ! $postObject instanceof WP_Post ) {
			return $excerpt;
		}

		return $this->get_translated_value( $postObject, 'excerpt', (string) $excerpt, array( 'format' => 'html' ) );
	}

	public function filter_attachment_attributes( array $attributes, $attachment ): array {
		$post = get_post( $attachment );
		if ( ! $post instanceof WP_Post ) {
			return $attributes;
		}

		if ( isset( $attributes['alt'] ) ) {
			$attributes['alt'] = $this->get_translated_value( $post, 'title', $attributes['alt'] );
		}

		return $attributes;
	}

	public function filter_body_class( array $classes ): array {
		$language = CurrentLanguage::resolve();
		if ( $language !== '' ) {
			$classes[] = 'fp-lang-' . sanitize_html_class( $language );
		}

		return $classes;
	}

	public function expose_translations( $response, $post, $request ) {
		unset( $request );

		if ( ! $post instanceof WP_Post ) {
			return $response;
		}

		if ( isset( $response->data ) && is_array( $response->data ) ) {
			$response->data['fp_multilanguage'] = array(
				'language'     => CurrentLanguage::resolve(),
				'translations' => $this->get_post_translations( $post->ID ),
			);
		}

		return $response;
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'fp-multilanguage/v1',
			'/posts/(?P<id>\\d+)/translate',
			array(
				'methods'             => array( 'POST' ),
				'callback'            => array( $this, 'rest_translate_post' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public function rest_translate_post( $request ) {
			$postId   = (int) $request['id'];
			$language = $request->get_param( 'language' );
			$language = is_string( $language ) ? sanitize_key( $language ) : null;

			$translations = $this->translate_post( $postId, $language, true );

		return rest_ensure_response(
			array(
				'translations' => $translations,
			)
		);
	}

	public function get_post_translations( int $postId ): array {
		$stored = get_post_meta( $postId, self::META_KEY, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		return $stored;
	}

	private function persist_relations( int $postId, array $translations, string $sourceLanguage ): void {
		$relation = array(
			'source'    => $sourceLanguage,
			'languages' => array_keys( $translations ),
		);

		update_post_meta( $postId, self::RELATION_META_KEY, $relation );
	}

	private function get_translated_value( WP_Post $post, string $field, string $default_value, array $options = array() ): string {
			$language = CurrentLanguage::resolve();
			$source   = Settings::get_source_language();

		if ( $language === '' || $language === $source ) {
				return $default_value;
		}

		$translations = $this->get_post_translations( $post->ID );
		if ( isset( $translations[ $language ][ $field ] ) && $translations[ $language ][ $field ] !== '' ) {
				return $translations[ $language ][ $field ];
		}

		$translated = $this->translationService->translate_text( $default_value, $source, $language, $options );
		if ( $translated !== '' ) {
				return $translated;
		}

		$fallback = Settings::get_fallback_language();
		if ( $fallback !== $language && isset( $translations[ $fallback ][ $field ] ) ) {
				return $translations[ $fallback ][ $field ];
		}

			return $default_value;
	}

	private function hash_value( string $value ): string {
			return hash( 'sha1', $value );
	}

	private function sync_field_translation(
		array &$updated,
		array $existing,
		array &$updatedSource,
		string $field,
		string $value,
		string $hash,
		string $sourceLanguage,
		string $targetLanguage,
		array $options,
		bool $force
	): bool {
			$hasChanges       = false;
			$existingValue    = $existing[ $field ] ?? null;
			$existingSource   = $updatedSource[ $field ] ?? null;
			$needsTranslation = $force || ! is_string( $existingSource ) || $existingSource !== $hash || ! is_string( $existingValue );

		if ( ! $needsTranslation ) {
				return false;
		}

			$translated = $this->translationService->translate_text( $value, $sourceLanguage, $targetLanguage, $options );
		if ( $translated === '' ) {
				$translated = is_string( $existingValue ) ? $existingValue : $value;
		}

		if ( ! is_string( $existingValue ) || $existingValue !== $translated ) {
				$updated[ $field ] = $translated;
				$hasChanges        = true;
		}

		if ( $existingSource !== $hash ) {
				$hasChanges = true;
		}

			$updatedSource[ $field ] = $hash;

			return $hasChanges;
	}

	private function sync_meta_translations(
		array $existing,
		array $existingSource,
		array $customFields,
		array $metaHashes,
		string $sourceLanguage,
		string $targetLanguage,
		bool $force
	): array {
			$updatedMeta       = array();
			$updatedMetaSource = array();
			$hasChanges        = false;
			$existingMeta      = is_array( $existing['meta'] ?? null ) ? $existing['meta'] : array();

		foreach ( $customFields as $metaKey => $value ) {
				$hash             = $metaHashes[ $metaKey ] ?? $this->hash_value( (string) $value );
				$existingValue    = $existingMeta[ $metaKey ] ?? null;
				$existingMetaHash = $existingSource[ $metaKey ] ?? null;
				$needsTranslation = $force || ! is_string( $existingMetaHash ) || $existingMetaHash !== $hash || ! is_string( $existingValue );

			if ( $needsTranslation ) {
				$translated = $this->translationService->translate_text( (string) $value, $sourceLanguage, $targetLanguage );
				if ( $translated === '' ) {
						$translated = is_string( $existingValue ) ? $existingValue : (string) $value;
				}

				if ( ! is_string( $existingValue ) || $existingValue !== $translated ) {
						$hasChanges = true;
				}

				if ( $existingMetaHash !== $hash ) {
						$hasChanges = true;
				}

				$updatedMeta[ $metaKey ] = $translated;
			} elseif ( is_string( $existingValue ) ) {
					$updatedMeta[ $metaKey ] = $existingValue;
			}

				$updatedMetaSource[ $metaKey ] = $hash;
		}

			return array( $updatedMeta, $updatedMetaSource, $hasChanges );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_custom_fields( WP_Post $post ): array {
		$fields   = array();
		$metaKeys = apply_filters( 'fp_multilanguage_custom_fields', array() );
		foreach ( $metaKeys as $metaKey ) {
			$value = get_post_meta( $post->ID, $metaKey, true );
			if ( is_string( $value ) && $value !== '' ) {
				$fields[ $metaKey ] = $value;
			}
		}

		return $fields;
	}
}
