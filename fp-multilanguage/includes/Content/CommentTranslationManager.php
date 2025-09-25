<?php
namespace FPMultilanguage\Content;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use WP_Comment;

class CommentTranslationManager {

        public const META_KEY = '_fp_multilanguage_comment_translations';

        private TranslationService $translationService;

        private Logger $logger;

        public function __construct( TranslationService $translationService, Settings $settings, AdminNotices $notices, Logger $logger ) {
                unset( $settings, $notices );

                $this->translationService = $translationService;
                $this->logger             = $logger;
        }

        public function register(): void {
                add_action( 'wp_insert_comment', array( $this, 'handle_comment_insert' ), 10, 2 );
                add_action( 'edit_comment', array( $this, 'handle_comment_update' ), 10, 2 );
                add_action( 'deleted_comment', array( $this, 'handle_comment_delete' ) );

                add_filter( 'get_comment_text', array( $this, 'filter_comment_text' ), 10, 3 );
                add_filter( 'comment_text', array( $this, 'filter_comment_text_legacy' ), 10, 2 );
                add_filter( 'rest_prepare_comment', array( $this, 'expose_translations' ), 10, 3 );
        }

        public function handle_comment_insert( int $comment_id, WP_Comment $comment ): void {
                if ( ! Settings::is_auto_translate_enabled() ) {
                        return;
                }

                $this->translate_comment( $comment_id, null, true, $comment );
        }

        public function handle_comment_update( int $comment_id, ?WP_Comment $comment = null ): void {
                unset( $comment );

                if ( ! Settings::is_auto_translate_enabled() ) {
                        return;
                }

                $this->translate_comment( $comment_id, null, true );
        }

        public function handle_comment_delete( int $comment_id ): void {
                delete_comment_meta( $comment_id, self::META_KEY );
        }

        public function translate_comment( int $comment_id, ?string $language = null, bool $force = false, ?WP_Comment $comment = null ): array {
                if ( null === $comment ) {
                        $comment = get_comment( $comment_id );
                }

                if ( ! $comment instanceof WP_Comment ) {
                        return array();
                }

                $source_language  = Settings::get_source_language();
                $target_languages = Settings::get_target_languages();

                if ( null !== $language ) {
                        $target_languages = array_intersect( $target_languages, array( $language ) );
                }

                $translations = $this->get_comment_translations( $comment_id );
                $has_changes  = false;

                foreach ( $target_languages as $target ) {
                        if ( $target === $source_language ) {
                                continue;
                        }

                        $existing            = $translations[ $target ] ?? array();
                        $existing_text       = isset( $existing['content'] ) ? (string) $existing['content'] : '';
                        $translated_content  = $this->translationService->translate_text( $comment->comment_content, $source_language, $target, array( 'format' => 'html' ) );
                        $language_has_change = $force;

                        if ( '' === $translated_content ) {
                                continue;
                        }

                        if ( $force || $existing_text !== $translated_content ) {
                                $translations[ $target ] = array(
                                        'content'    => $translated_content,
                                        'updated_at' => time(),
                                        'status'     => 'synced',
                                );
                                $language_has_change     = true;
                        }

                        if ( $language_has_change ) {
                                $has_changes = true;
                        }
                }

                if ( $has_changes ) {
                        $this->persist_translations( $comment_id, $translations );

                        $this->logger->debug(
                                'Comment translations updated.',
                                array(
                                        'comment_id' => $comment_id,
                                        'languages'  => array_keys( $translations ),
                                )
                        );
                }

                return $translations;
        }

        /**
         * @param WP_Comment|int|null $comment Comment instance or identifier.
         */
        public function filter_comment_text( string $text, WP_Comment|int|null $comment = null, array $args = array() ): string {
                unset( $args );

                $comment_object = $this->resolve_comment( $comment );
                if ( ! $comment_object instanceof WP_Comment ) {
                        return $text;
                }

                $language = CurrentLanguage::resolve();
                $source   = Settings::get_source_language();

                if ( '' === $language || $language === $source ) {
                        return $text;
                }

                $translations = $this->get_comment_translations( $comment_object->comment_ID );
                if ( isset( $translations[ $language ]['content'] ) && '' !== $translations[ $language ]['content'] ) {
                        return (string) $translations[ $language ]['content'];
                }

                $translated = $this->translationService->translate_text( $comment_object->comment_content, $source, $language, array( 'format' => 'html' ) );
                if ( '' === $translated ) {
                        return $text;
                }

                $translations[ $language ] = array(
                        'content'    => $translated,
                        'updated_at' => time(),
                        'status'     => 'generated',
                );

                $this->persist_translations( $comment_object->comment_ID, $translations );

                return $translated;
        }

        public function filter_comment_text_legacy( string $text, WP_Comment|int|null $comment = null ): string {
                return $this->filter_comment_text( $text, $comment );
        }

        /**
         * @param mixed                 $response Response object provided by WordPress REST infrastructure.
         * @param WP_Comment|int|null   $comment  Comment instance or identifier.
         * @param mixed                 $request  Original REST request.
         *
         * @return mixed
         */
        public function expose_translations( $response, WP_Comment|int|null $comment, $request ): mixed {
                unset( $request );

                $comment_object = $this->resolve_comment( $comment );
                if ( ! $comment_object instanceof WP_Comment ) {
                        return $response;
                }

                if ( isset( $response->data ) && is_array( $response->data ) ) {
                        $response->data['fp_multilanguage'] = array(
                                'language'     => CurrentLanguage::resolve(),
                                'translations' => $this->get_comment_translations( $comment_object->comment_ID ),
                        );
                }

                return $response;
        }

        public function get_comment_translations( int $comment_id ): array {
                $stored = get_comment_meta( $comment_id, self::META_KEY, true );
                if ( ! is_array( $stored ) ) {
                        return array();
                }

                return $stored;
        }

        private function resolve_comment( WP_Comment|int|null $comment ): ?WP_Comment {
                if ( $comment instanceof WP_Comment ) {
                        return $comment;
                }

                if ( is_numeric( $comment ) ) {
                        $comment = get_comment( (int) $comment );
                }

                return $comment instanceof WP_Comment ? $comment : null;
        }

        private function persist_translations( int $comment_id, array $translations ): void {
                update_comment_meta( $comment_id, self::META_KEY, $translations );
        }
}
