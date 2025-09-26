<?php
namespace FPMultilanguage\Content;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use ArrayAccess;
use WP_Error;
use WP_Post;
use WP_REST_Request;

class PostTranslationManager {

        public const META_KEY = '_fp_multilanguage_translations';

        public const RELATION_META_KEY = '_fp_multilanguage_relations';

        private const TRANSLATION_EVENT = 'fp_multilanguage_process_translation';

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
                add_action( self::TRANSLATION_EVENT, array( $this, 'process_translation_job' ), 10, 4 );

                add_filter( 'the_content', array( $this, 'filter_content' ) );
		add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_attachment_attributes' ), 10, 2 );
		add_filter( 'wp_get_attachment_caption', array( $this, 'filter_attachment_caption' ), 10, 2 );
		add_filter( 'get_post_metadata', array( $this, 'filter_attachment_meta' ), 10, 4 );
                foreach ( Settings::get_translatable_post_types() as $postType ) {
                        add_filter( 'rest_prepare_' . $postType, array( $this, 'expose_translations' ), 10, 3 );
                }
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

		if ( ! Settings::is_auto_translate_enabled() ) {
			return;
		}

                if ( ! in_array( $post->post_type, Settings::get_translatable_post_types(), true ) && 'attachment' !== $post->post_type ) {
                        return;
                }

                $this->queue_translation_job( $postId, $post->post_type );
        }

        public function queue_translation_job( int $postId, string $postType, ?string $language = null, bool $force = false ): void {
                if ( 'attachment' !== $postType && ! in_array( $postType, Settings::get_translatable_post_types(), true ) ) {
                        return;
                }

                if ( ! function_exists( 'wp_schedule_single_event' ) ) {
                        $this->run_translation_immediately( $postId, $postType, $language, $force );

                        return;
                }

                $args = array( $postId, $postType, $language, $force );

                if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
                        wp_clear_scheduled_hook( self::TRANSLATION_EVENT, $args );
                }

                $timestamp = time() + 5;
                $scheduled = wp_schedule_single_event( $timestamp, self::TRANSLATION_EVENT, $args );

                if ( false === $scheduled ) {
                        $this->logger->warning(
                                'Unable to schedule translation job for post {post_id}. Executing synchronously.',
                                array( 'post_id' => $postId )
                        );
                        $this->run_translation_immediately( $postId, $postType, $language, $force );

                        return;
                }

                $this->logger->debug(
                        'Scheduled translation job for post {post_id} ({post_type}).',
                        array(
                                'post_id'   => $postId,
                                'post_type' => $postType,
                                'language'  => $language,
                                'force'     => $force ? '1' : '0',
                        )
                );
        }

        public function process_translation_job( int $postId, string $postType, ?string $language = null, bool $force = false ): void {
                $post = get_post( $postId );
                if ( ! $post instanceof WP_Post ) {
                        $this->logger->debug(
                                'Skipping translation job for missing post {post_id}.',
                                array( 'post_id' => $postId )
                        );

                        return;
                }

                if ( 'attachment' === $postType ) {
                        $this->translate_attachment( $postId, $language, $force );

                        return;
                }

                if ( ! in_array( $postType, Settings::get_translatable_post_types(), true ) ) {
                        $this->logger->debug(
                                'Skipping translation job for non-translatable post type {post_type}.',
                                array( 'post_type' => $postType )
                        );

                        return;
                }

                $this->translate_post( $postId, $language, $force );
        }

        private function run_translation_immediately( int $postId, string $postType, ?string $language = null, bool $force = false ): void {
                if ( 'attachment' === $postType ) {
                        $this->translate_attachment( $postId, $language, $force );

                        return;
                }

                $this->translate_post( $postId, $language, $force );
        }

	public function translate_attachment( int $postId, ?string $language = null, bool $force = false ): array {
		$post = get_post( $postId );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$sourceLanguage  = Settings::get_source_language();
		$targetLanguages = Settings::get_target_languages();
		if ( null !== $language ) {
			$targetLanguages = array_intersect( $targetLanguages, array( $language ) );
		}

		$translations = $this->get_post_translations( $postId );
		$altText      = (string) get_post_meta( $postId, '_wp_attachment_image_alt', true );
		$hasChanges   = false;

		foreach ( $targetLanguages as $target ) {
			if ( $target === $sourceLanguage ) {
				continue;
			}

			$existing             = $translations[ $target ] ?? array();
			$updated              = $existing;
			$languageHasChanges = $force;

			$title = $this->translationService->translate_text( $post->post_title, $sourceLanguage, $target );
			if ( ! isset( $existing['title'] ) || $existing['title'] !== $title ) {
				$updated['title']    = $title;
				$languageHasChanges = true;
			}

			$description = $this->translationService->translate_text( $post->post_content, $sourceLanguage, $target, array( 'format' => 'html' ) );
			if ( ! isset( $existing['content'] ) || $existing['content'] !== $description ) {
				$updated['content']  = $description;
				$languageHasChanges = true;
			}

			$caption_source = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_title;
			$caption        = $this->translationService->translate_text( $caption_source, $sourceLanguage, $target, array( 'format' => 'html' ) );
			if ( ! isset( $existing['excerpt'] ) || $existing['excerpt'] !== $caption ) {
				$updated['excerpt']  = $caption;
				$languageHasChanges = true;
			}

			if ( ! isset( $updated['meta'] ) || ! is_array( $updated['meta'] ) ) {
				$updated['meta'] = array();
			}

			if ( '' !== $altText ) {
				$translatedAlt = $this->translationService->translate_text( $altText, $sourceLanguage, $target );
				if ( ! isset( $updated['meta']['_wp_attachment_image_alt'] ) || $updated['meta']['_wp_attachment_image_alt'] !== $translatedAlt ) {
					$updated['meta']['_wp_attachment_image_alt'] = $translatedAlt;
					$languageHasChanges                          = true;
				}
			}

			if ( $languageHasChanges ) {
				$updated['updated_at']   = time();
				$updated['status']       = 'synced';
				$translations[ $target ] = $updated;
				$hasChanges             = true;
			}
		}

		if ( $hasChanges ) {
			update_post_meta( $postId, self::META_KEY, $translations );
		}

		$this->persist_relations( $postId, $translations, $sourceLanguage );

		return $translations;
	}

        public function translate_post( int $postId, ?string $language = null, bool $force = false ): array {
                $post = get_post( $postId );
                if ( ! $post instanceof WP_Post ) {
                        return array();
                }

                if ( ! in_array( $post->post_type, Settings::get_translatable_post_types(), true ) ) {
                        return array();
                }

                $sourceLanguage  = Settings::get_source_language();
                $targetLanguages = Settings::get_target_languages();
		if ( $language !== null ) {
			$targetLanguages = array_intersect( $targetLanguages, array( $language ) );
		}

		$translations = $this->get_post_translations( $postId );
		$customFields = $this->get_custom_fields( $post );
		$hasChanges   = false;

		foreach ( $targetLanguages as $target ) {
			if ( $target === $sourceLanguage ) {
				continue;
			}

			$existing           = $translations[ $target ] ?? array();
			$updated            = $existing;
			$languageHasChanges = $force;

			$title = $this->translationService->translate_text( $post->post_title, $sourceLanguage, $target );
			if ( ! isset( $existing['title'] ) || $existing['title'] !== $title ) {
				$updated['title']   = $title;
				$languageHasChanges = true;
			}

			$content = $this->translationService->translate_text( $post->post_content, $sourceLanguage, $target, array( 'format' => 'html' ) );
			if ( ! isset( $existing['content'] ) || $existing['content'] !== $content ) {
				$updated['content'] = $content;
				$languageHasChanges = true;
			}

			$excerptSource = $post->post_excerpt !== '' ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 );
			$excerpt       = $this->translationService->translate_text( $excerptSource, $sourceLanguage, $target, array( 'format' => 'html' ) );
			if ( ! isset( $existing['excerpt'] ) || $existing['excerpt'] !== $excerpt ) {
				$updated['excerpt'] = $excerpt;
				$languageHasChanges = true;
			}

			$updated['meta'] = $existing['meta'] ?? array();
			foreach ( $customFields as $metaKey => $value ) {
				$translatedMeta = $this->translationService->translate_text( (string) $value, $sourceLanguage, $target );
				if ( ! isset( $updated['meta'][ $metaKey ] ) || $updated['meta'][ $metaKey ] !== $translatedMeta ) {
					$updated['meta'][ $metaKey ] = $translatedMeta;
					$languageHasChanges          = true;
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
			$attributes['alt'] = $this->get_translated_meta_value( $post, '_wp_attachment_image_alt', $attributes['alt'] );
		}

		return $attributes;
	}

	/**
	 * @param string|null $caption
	 */
	public function filter_attachment_caption( ?string $caption, int $postId ): string {
		$post = get_post( $postId );
		if ( ! $post instanceof WP_Post ) {
			return is_string( $caption ) ? $caption : '';
		}

		return $this->get_translated_value( $post, 'excerpt', (string) $caption, array( 'format' => 'html' ) );
	}

	/**
	 * @param mixed $value
	 * @return array<int, string>|string
	 */
	public function filter_attachment_meta( $value, int $objectId, string $metaKey, bool $single ): array|string {
		if ( '_wp_attachment_image_alt' !== $metaKey ) {
			return $value;
		}

		$post = get_post( $objectId );
		if ( ! $post instanceof WP_Post ) {
			return $value;
		}

		$translated = $this->get_translated_meta_value( $post, $metaKey, '' );
		if ( '' === $translated ) {
			return $value;
		}

		if ( $single ) {
			return $translated;
		}

		return array( $translated );
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
                $routeArgs = array(
                        'methods'             => array( 'POST' ),
                        'callback'            => array( $this, 'rest_translate_post' ),
                        'permission_callback' => static function ( WP_REST_Request $request ): bool {
                                $postId = (int) $request->get_param( 'id' );
                                if ( $postId <= 0 ) {
                                        return false;
                                }

                                return current_user_can( 'edit_post', $postId );
                        },
                );

                register_rest_route(
                        'fp-multilanguage/v1',
                        '/posts/(?P<id>\\d+)/translate',
                        $routeArgs
                );

                register_rest_route(
                        'fp-multilanguage/v1',
                        '/attachments/(?P<id>\\d+)/translate',
                        $routeArgs
                );

                $contentRouteArgs           = $routeArgs;
                $contentRouteArgs['args'] = array(
                        'type' => array(
                                'sanitize_callback' => 'sanitize_key',
                        ),
                );

                register_rest_route(
                        'fp-multilanguage/v1',
                        '/content/(?P<type>[a-z0-9_-]+)/(?P<id>\\d+)/translate',
                        $contentRouteArgs
                );
        }

        public function rest_translate_post( $request ) {
                $postId = (int) $this->get_request_param( $request, 'id', 0 );
                if ( $postId <= 0 ) {
                        return new WP_Error(
                                'rest_post_invalid_id',
                                __( 'Contenuto non trovato.', 'fp-multilanguage' ),
                                array( 'status' => 404 )
                        );
                }

                $languageParam = $this->get_request_param( $request, 'language' );
                $language      = is_string( $languageParam ) ? sanitize_key( $languageParam ) : null;

                $forceParam = $this->get_request_param( $request, 'force', true );
                if ( is_string( $forceParam ) || is_int( $forceParam ) ) {
                        $force = filter_var( $forceParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                        if ( null === $force ) {
                                $force = true;
                        }
                } else {
                        $force = (bool) $forceParam;
                }

                $typeParam = $this->get_request_param( $request, 'type' );
                $requestedType = is_string( $typeParam ) ? sanitize_key( $typeParam ) : null;

                $post = get_post( $postId );
                if ( ! $post instanceof WP_Post ) {
                        return new WP_Error(
                                'rest_post_invalid_id',
                                __( 'Contenuto non trovato.', 'fp-multilanguage' ),
                                array( 'status' => 404 )
                        );
                }

                if ( ! current_user_can( 'edit_post', $postId ) ) {
                        return new WP_Error(
                                'rest_forbidden',
                                __( 'Non hai il permesso di tradurre questo contenuto.', 'fp-multilanguage' ),
                                array( 'status' => 403 )
                        );
                }

                if ( null !== $requestedType && $post->post_type !== $requestedType ) {
                        return new WP_Error(
                                'rest_post_invalid_type',
                                __( 'Il tipo di contenuto richiesto non corrisponde alla risorsa selezionata.', 'fp-multilanguage' ),
                                array( 'status' => 400 )
                        );
                }

                if ( 'attachment' !== $post->post_type && ! in_array( $post->post_type, Settings::get_translatable_post_types(), true ) ) {
                        return new WP_Error(
                                'rest_post_unsupported_type',
                                __( 'Questo tipo di contenuto non supporta la traduzione automatica.', 'fp-multilanguage' ),
                                array( 'status' => 400 )
                        );
                }

                if ( 'attachment' === $post->post_type ) {
                        $translations = $this->translate_attachment( $postId, $language, $force );
                } else {
                        $translations = $this->translate_post( $postId, $language, $force );
                }

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

	private function get_translated_meta_value( WP_Post $post, string $metaKey, string $default_value ): string {
		$language = CurrentLanguage::resolve();
		$source   = Settings::get_source_language();

		if ( $language === '' || $language === $source ) {
			return $default_value;
		}

		$translations = $this->get_post_translations( $post->ID );
		if ( isset( $translations[ $language ]['meta'][ $metaKey ] ) && '' !== $translations[ $language ]['meta'][ $metaKey ] ) {
			return $translations[ $language ]['meta'][ $metaKey ];
		}

		if ( '' !== $default_value ) {
			$translated = $this->translationService->translate_text( $default_value, $source, $language );
			if ( '' !== $translated ) {
				return $translated;
			}
		}

		$fallback = Settings::get_fallback_language();
		if ( $fallback !== $language && isset( $translations[ $fallback ]['meta'][ $metaKey ] ) && '' !== $translations[ $fallback ]['meta'][ $metaKey ] ) {
			return $translations[ $fallback ]['meta'][ $metaKey ];
		}

		return $default_value;
	}

	/**
	 * @return array<string, string>
	 */
        private function get_custom_fields( WP_Post $post ): array {
                $fields   = array();
                $metaKeys = array_merge(
                        Settings::get_translatable_meta_keys(),
                        apply_filters( 'fp_multilanguage_custom_fields', array() )
                );

                $metaKeys = array_values( array_unique( array_filter( array_map( 'strval', $metaKeys ) ) ) );
                foreach ( $metaKeys as $metaKey ) {
                        $value = get_post_meta( $post->ID, $metaKey, true );
                        if ( is_string( $value ) && $value !== '' ) {
                                $fields[ $metaKey ] = $value;
                        }
                }

                return $fields;
        }

        /**
         * @param mixed $request
         * @param mixed $default
         * @return mixed
         */
        private function get_request_param( $request, string $key, $default = null ) {
                if ( is_array( $request ) ) {
                        return $request[ $key ] ?? $default;
                }

                if ( $request instanceof ArrayAccess && $request->offsetExists( $key ) ) {
                        return $request[ $key ];
                }

                if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
                        return $request->get_param( $key );
                }

                return $default;
        }
}
