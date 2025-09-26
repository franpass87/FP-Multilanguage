<?php
namespace FPMultilanguage\Admin\Settings;

use FPMultilanguage\Services\TranslationService;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Admin\AdminNotices;

class Repository {
    public const OPTION_NAME = 'fp_multilanguage_options';
    public const MANUAL_STRINGS_OPTION = 'fp_multilanguage_manual_strings';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULTS = array(
        'source_language'   => 'en',
        'fallback_language' => 'en',
        'target_languages'  => array( 'it' ),
        'post_types'        => array( 'post', 'page' ),
        'taxonomies'        => array( 'category', 'post_tag' ),
        'custom_fields'     => array(),
        'providers'         => array(
            'google' => array(
                'enabled'              => false,
                'api_key'              => '',
                'timeout'              => 20,
                'glossary_id'          => '',
                'glossary_ignore_case' => false,
            ),
            'deepl'  => array(
                'enabled'     => false,
                'api_key'     => '',
                'endpoint'    => 'https://api.deepl.com/v2/translate',
                'glossary_id' => '',
                'formality'   => 'default',
            ),
        ),
        'auto_translate'    => true,
        'seo'               => array(
            'hreflang'   => true,
            'canonical'  => true,
            'open_graph' => true,
        ),
        'quote_tracking'    => array(),
    );

    private AdminNotices $notices;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedOptions = null;

    private bool $cacheHooksRegistered = false;

    public function __construct( AdminNotices $notices ) {
        $this->notices = $notices;
    }

    public function register_cache_hooks(): void {
        if ( $this->cacheHooksRegistered ) {
            return;
        }

        if ( function_exists( 'add_action' ) ) {
            add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'clear_cache' ), 10, 0 );
            add_action( 'add_option_' . self::OPTION_NAME, array( $this, 'clear_cache' ), 10, 0 );
            add_action( 'delete_option_' . self::OPTION_NAME, array( $this, 'clear_cache' ), 10, 0 );
        }

        $this->cacheHooksRegistered = true;
    }

    public function bootstrap_defaults(): void {
        if ( ! function_exists( 'get_option' ) ) {
            return;
        }

        $options = get_option( self::OPTION_NAME, array() );
        if ( ! is_array( $options ) || empty( $options ) ) {
            $options = self::DEFAULTS;
        } else {
            $options = wp_parse_args( $options, self::DEFAULTS );
        }

        update_option( self::OPTION_NAME, $options );
        $this->set_cached_options( $options );

        if ( false === get_option( self::MANUAL_STRINGS_OPTION, false ) ) {
            update_option( self::MANUAL_STRINGS_OPTION, array() );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function get_defaults(): array {
        return self::DEFAULTS;
    }

    /**
     * @param array<string, mixed>|mixed $input
     *
     * @return array<string, mixed>
     */
    public function sanitize_options( $input ): array {
        $defaults   = self::DEFAULTS;
        $sanitized = wp_parse_args( is_array( $input ) ? $input : array(), $defaults );

        $sanitized['source_language']   = $this->sanitize_language( (string) $sanitized['source_language'] );
        $sanitized['fallback_language'] = $this->sanitize_language( (string) $sanitized['fallback_language'] );

        if ( '' === $sanitized['fallback_language'] ) {
            $sanitized['fallback_language'] = $sanitized['source_language'];
        }

        $targets = $sanitized['target_languages'];
        if ( is_string( $targets ) ) {
            $targets = preg_split( '/[,\s]+/', $targets );
        }

        $targets = array_map( array( $this, 'sanitize_language' ), (array) $targets );
        $targets = array_filter( $targets );
        $targets = array_values( array_unique( $targets ) );
        $targets = array_values(
            array_filter(
                $targets,
                static function ( string $language ) use ( $sanitized ): bool {
                    return $language !== $sanitized['source_language'];
                }
            )
        );

        if ( $sanitized['fallback_language'] !== $sanitized['source_language'] && ! in_array( $sanitized['fallback_language'], $targets, true ) ) {
            $targets[] = $sanitized['fallback_language'];
        }

        if ( empty( $targets ) ) {
            $defaultTargets = array_map( array( $this, 'sanitize_language' ), (array) $defaults['target_languages'] );
            $defaultTargets = array_filter(
                $defaultTargets,
                static function ( string $language ) use ( $sanitized ): bool {
                    return $language !== $sanitized['source_language'];
                }
            );

            if ( empty( $defaultTargets ) && $sanitized['fallback_language'] !== $sanitized['source_language'] ) {
                $defaultTargets = array( $sanitized['fallback_language'] );
            }

            $targets = array_values( array_unique( $defaultTargets ) );
        }

        $sanitized['target_languages'] = array_values( array_unique( $targets ) );
        $sanitized['auto_translate']   = ! empty( $sanitized['auto_translate'] );

        $postTypes = $sanitized['post_types'] ?? array();
        if ( is_string( $postTypes ) ) {
            $postTypes = preg_split( '/[,\s]+/', $postTypes );
        }

        $postTypes = array_map( 'sanitize_key', (array) $postTypes );
        $postTypes = array_filter( $postTypes );
        $postTypes = array_values( array_unique( $postTypes ) );

        if ( function_exists( 'post_type_exists' ) ) {
            $postTypes = array_values(
                array_filter(
                    $postTypes,
                    static function ( string $postType ): bool {
                        return post_type_exists( $postType );
                    }
                )
            );
        }

        if ( empty( $postTypes ) ) {
            $postTypes = array_map( 'sanitize_key', (array) $defaults['post_types'] );
        }

        $sanitized['post_types'] = $postTypes;

        $taxonomies = $sanitized['taxonomies'] ?? array();
        if ( is_string( $taxonomies ) ) {
            $taxonomies = preg_split( '/[,\s]+/', $taxonomies );
        }

        $taxonomies = array_map( 'sanitize_key', (array) $taxonomies );
        $taxonomies = array_filter( $taxonomies );
        $taxonomies = array_values( array_unique( $taxonomies ) );

        if ( function_exists( 'taxonomy_exists' ) ) {
            $taxonomies = array_values(
                array_filter(
                    $taxonomies,
                    static function ( string $taxonomy ): bool {
                        return taxonomy_exists( $taxonomy );
                    }
                )
            );
        }

        if ( empty( $taxonomies ) ) {
            $taxonomies = array_map( 'sanitize_key', (array) $defaults['taxonomies'] );
        }

        $sanitized['taxonomies'] = $taxonomies;

        $customFields = $sanitized['custom_fields'] ?? array();
        if ( is_string( $customFields ) ) {
            $customFields = preg_split( '/[\r\n,]+/', $customFields );
        }

        $customFields = array_map( array( $this, 'sanitize_custom_field_key' ), (array) $customFields );
        $customFields = array_filter( $customFields );
        $sanitized['custom_fields'] = array_values( array_unique( $customFields ) );

        foreach ( array( 'google', 'deepl' ) as $provider ) {
            $providerOptions = $sanitized['providers'][ $provider ] ?? array();
            $providerOptions = wp_parse_args( $providerOptions, $defaults['providers'][ $provider ] );
            $providerOptions['enabled'] = ! empty( $providerOptions['enabled'] );
            $providerOptions['api_key'] = sanitize_text_field( (string) ( $providerOptions['api_key'] ?? '' ) );

            if ( isset( $providerOptions['timeout'] ) ) {
                $providerOptions['timeout'] = max( 5, (int) $providerOptions['timeout'] );
            }

            if ( isset( $providerOptions['endpoint'] ) ) {
                $providerOptions['endpoint'] = esc_url_raw( (string) $providerOptions['endpoint'] );
            }

            if ( isset( $providerOptions['glossary_id'] ) ) {
                $glossaryId = sanitize_text_field( (string) $providerOptions['glossary_id'] );
                $glossaryId = html_entity_decode( $glossaryId, ENT_QUOTES, 'UTF-8' );
                $glossaryId = preg_replace( '/[\r\n]+/', '', $glossaryId );
                if ( null === $glossaryId ) {
                    $glossaryId = '';
                }

                $providerOptions['glossary_id'] = trim( $glossaryId );
            }

            if ( 'google' === $provider ) {
                $providerOptions['glossary_ignore_case'] = ! empty( $providerOptions['glossary_ignore_case'] );
                if ( '' === $providerOptions['glossary_id'] ) {
                    $providerOptions['glossary_ignore_case'] = false;
                }
            }

            if ( 'deepl' === $provider ) {
                $formality = strtolower( sanitize_text_field( (string) ( $providerOptions['formality'] ?? '' ) ) );
                $allowed   = array( 'default', 'more', 'less' );
                if ( ! in_array( $formality, $allowed, true ) ) {
                    $formality = 'default';
                }

                $providerOptions['formality'] = $formality;
            }

            if ( $providerOptions['enabled'] && '' === $providerOptions['api_key'] ) {
                $providerOptions['enabled'] = false;
                $providerLabel              = 'deepl' === $provider ? 'DeepL' : ucfirst( $provider );
                $message                     = sprintf(
                    /* translators: %s is the provider name. */
                    __( 'Il provider %s è stato disabilitato perché manca la chiave API.', 'fp-multilanguage' ),
                    $providerLabel
                );
                $this->notices->add_notice( $message, 'warning', false );
            }

            $sanitized['providers'][ $provider ] = $providerOptions;
        }

        if ( ! isset( $sanitized['seo'] ) || ! is_array( $sanitized['seo'] ) ) {
            $sanitized['seo'] = $defaults['seo'];
        } else {
            foreach ( $defaults['seo'] as $key => $default ) {
                $sanitized['seo'][ $key ] = isset( $sanitized['seo'][ $key ] ) ? (bool) $sanitized['seo'][ $key ] : (bool) $default;
            }
        }

        $sanitized['quote_tracking'] = $this->get_quote_tracking();

        TranslationService::flush_cache();
        CurrentLanguage::clear_cache();
        $this->set_cached_options( $sanitized );

        return $sanitized;
    }

    public function update_options( array $options ): void {
        update_option( self::OPTION_NAME, $options );
        $this->set_cached_options( $options );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_options(): array {
        if ( null === $this->cachedOptions ) {
            if ( ! function_exists( 'get_option' ) ) {
                $this->cachedOptions = self::DEFAULTS;
            } else {
                $optionsRaw          = get_option( self::OPTION_NAME, array() );
                $this->cachedOptions = wp_parse_args( is_array( $optionsRaw ) ? $optionsRaw : array(), self::DEFAULTS );
            }
        }

        $options                   = $this->cachedOptions;
        $options['quote_tracking'] = $this->get_quote_tracking();

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_provider_defaults( string $provider ): array {
        return self::DEFAULTS['providers'][ $provider ] ?? array();
    }

    /**
     * @return array<string>
     */
    public function get_enabled_providers(): array {
        $options = $this->get_options();
        $enabled = array();
        foreach ( $options['providers'] as $name => $provider ) {
            if ( ! empty( $provider['enabled'] ) ) {
                $enabled[] = $name;
            }
        }

        return $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_provider_settings( string $provider ): array {
        $options = $this->get_options();

        return $options['providers'][ $provider ] ?? array();
    }

    public function get_source_language(): string {
        return $this->get_options()['source_language'];
    }

    public function get_fallback_language(): string {
        return $this->get_options()['fallback_language'];
    }

    /**
     * @return array<int, string>
     */
    public function get_target_languages(): array {
        return $this->get_options()['target_languages'];
    }

    /**
     * @return array<int, string>
     */
    public function get_translatable_post_types(): array {
        $postTypes = $this->get_options()['post_types'] ?? array();

        return array_values( array_unique( array_map( 'strval', (array) $postTypes ) ) );
    }

    /**
     * @return array<int, string>
     */
    public function get_translatable_taxonomies(): array {
        $taxonomies = $this->get_options()['taxonomies'] ?? array();

        return array_values( array_unique( array_map( 'strval', (array) $taxonomies ) ) );
    }

    /**
     * @return array<int, string>
     */
    public function get_translatable_meta_keys(): array {
        $customFields = $this->get_options()['custom_fields'] ?? array();

        return array_values( array_unique( array_map( 'strval', (array) $customFields ) ) );
    }

    public function is_auto_translate_enabled(): bool {
        return (bool) $this->get_options()['auto_translate'];
    }

    public function clear_cache(): void {
        $this->cachedOptions = null;
    }

    public function get_manual_strings(): array {
        $stored = get_option( self::MANUAL_STRINGS_OPTION, array() );

        return is_array( $stored ) ? $stored : array();
    }

    /**
     * @return array<string, array{key:string,original:string,context:string,translations:array<string, string>}>
     */
    public function get_manual_strings_catalog(): array {
        $catalog      = array();
        $translations = $this->get_manual_strings();
        $metadata     = $this->get_manual_string_metadata();

        foreach ( $metadata as $key => $meta ) {
            if ( ! is_string( $key ) || '' === $key ) {
                continue;
            }

            $catalog[ $key ] = array(
                'key'          => $key,
                'original'     => isset( $meta['original'] ) ? (string) $meta['original'] : '',
                'context'      => isset( $meta['context'] ) ? (string) $meta['context'] : '',
                'translations' => isset( $translations[ $key ] ) && is_array( $translations[ $key ] ) ? $translations[ $key ] : array(),
            );
        }

        foreach ( $translations as $key => $values ) {
            if ( ! is_string( $key ) || '' === $key ) {
                continue;
            }

            $catalog[ $key ] = array(
                'key'          => $key,
                'original'     => $catalog[ $key ]['original'] ?? '',
                'context'      => $catalog[ $key ]['context'] ?? '',
                'translations' => is_array( $values ) ? $values : array(),
            );
        }

        ksort( $catalog );

        return $catalog;
    }

    public function get_quote_tracking(): array {
        return TranslationService::get_usage_stats();
    }

    public function update_manual_string( string $key, string $language, string $value ): void {
        $key = $this->normalize_manual_string_key( $key );
        if ( '' === $key ) {
            return;
        }

        $language = $this->normalize_manual_string_language( $language );
        if ( '' === $language ) {
            return;
        }

        $value = $this->sanitize_manual_string_value( $value );

        $strings      = $this->get_manual_strings();
        $translations = isset( $strings[ $key ] ) && is_array( $strings[ $key ] ) ? $strings[ $key ] : array();
        $current      = $translations[ $language ] ?? null;

        if ( '' === $value ) {
            if ( ! isset( $translations[ $language ] ) ) {
                return;
            }

            unset( $translations[ $language ] );
            if ( empty( $translations ) ) {
                unset( $strings[ $key ] );
            } else {
                $strings[ $key ] = $translations;
            }
        } else {
            if ( $current === $value ) {
                return;
            }

            $translations[ $language ] = $value;
            $strings[ $key ]           = $translations;
        }

        update_option( self::MANUAL_STRINGS_OPTION, $strings );

        $this->sync_manual_string_storage( $key, $strings[ $key ] ?? array() );

        TranslationService::flush_cache();

        if ( function_exists( 'do_action' ) ) {
            do_action(
                'fp_multilanguage_manual_string_updated',
                $key,
                $language,
                $value,
                $strings[ $key ] ?? array()
            );
        }
    }

    private function sanitize_language( string $value ): string {
        $value = sanitize_text_field( strtolower( $value ) );
        $value = str_replace( array( ' ', '_' ), '-', $value );
        $value = preg_replace( '/[^a-z0-9-]/', '', $value );
        if ( null === $value ) {
            return '';
        }

        return trim( $value, '-' );
    }

    /**
     * @param mixed $value
     */
    private function sanitize_custom_field_key( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }

        $value = sanitize_text_field( $value );
        $value = preg_replace( '/[^A-Za-z0-9:_-]/', '', $value );
        if ( null === $value ) {
            return '';
        }

        return trim( $value );
    }

    private function set_cached_options( array $options ): void {
        $this->cachedOptions = $options;
    }

    private function normalize_manual_string_key( string $key ): string {
        return sanitize_key( $key );
    }

    private function normalize_manual_string_language( string $language ): string {
        return sanitize_key( $language );
    }

    private function sanitize_manual_string_value( string $value ): string {
        if ( function_exists( 'wp_kses_post' ) ) {
            $value = wp_kses_post( $value );
        } elseif ( function_exists( 'sanitize_text_field' ) ) {
            $value = sanitize_text_field( $value );
        }

        $value = preg_replace( '#<script\b[^>]*>(.*?)</script>#is', '', (string) $value );
        if ( null === $value ) {
            $value = '';
        }

        $value = trim( (string) $value );

        return $value;
    }

    private function sync_manual_string_storage( string $key, array $translations ): void {
        $this->sync_manual_string_table( $key, $translations );
        $this->sync_manual_string_fallback( $key, $translations );
    }

    private function sync_manual_string_table( string $key, array $translations ): void {
        global $wpdb;

        if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
            return;
        }

        if ( empty( $wpdb->prefix ) ) {
            return;
        }

        $table = $wpdb->prefix . 'fp_multilanguage_strings';

        if ( ! $this->manual_strings_table_exists( $table ) ) {
            return;
        }

        if ( empty( $translations ) ) {
            $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $table,
                array( 'string_key' => $key ),
                array( '%s' )
            );

            return;
        }

        $context  = '';
        $original = '';

        $table_name = esc_sql( $table );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
        $existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT context, original FROM {$table_name} WHERE string_key = %s",
                $key
            ),
            'ARRAY_A'
        );
        // phpcs:enable

        if ( is_array( $existing ) ) {
            $context  = isset( $existing['context'] ) ? (string) $existing['context'] : '';
            $original = isset( $existing['original'] ) ? (string) $existing['original'] : '';
        } else {
            $fallback = get_option( 'fp_multilanguage_strings', array() );
            if ( isset( $fallback[ $key ] ) && is_array( $fallback[ $key ] ) ) {
                $context  = isset( $fallback[ $key ]['context'] ) ? (string) $fallback[ $key ]['context'] : '';
                $original = isset( $fallback[ $key ]['original'] ) ? (string) $fallback[ $key ]['original'] : '';
            }
        }

        if ( function_exists( 'wp_json_encode' ) ) {
            $translationsJson = wp_json_encode( $translations );
            if ( false === $translationsJson ) {
                $translationsJson = wp_json_encode( array() );
            }
        } else {
            $translationsJson = json_encode( $translations );
            if ( false === $translationsJson ) {
                $translationsJson = json_encode( array() );
            }
        }

        if ( false === $translationsJson || null === $translationsJson ) {
            $translationsJson = '[]';
        }

        $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $table,
            array(
                'string_key'   => $key,
                'context'      => $context,
                'original'     => $original,
                'translations' => $translationsJson,
                'updated_at'   => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
    }

    private function manual_strings_table_exists( string $table ): bool {
        global $wpdb;

        if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
            return false;
        }

        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        return null !== $exists;
    }

    private function sync_manual_string_fallback( string $key, array $translations ): void {
        $option = get_option( 'fp_multilanguage_strings', array() );
        if ( ! is_array( $option ) ) {
            $option = array();
        }

        $entry = $option[ $key ] ?? array();
        if ( ! is_array( $entry ) ) {
            $entry = array();
        }

        if ( empty( $translations ) ) {
            unset( $entry['translations'] );

            $entryWithoutMeta = $entry;
            unset( $entryWithoutMeta['updated_at'] );

            if ( empty( $entryWithoutMeta ) ) {
                unset( $option[ $key ] );
            } else {
                $entry['updated_at'] = time();
                $option[ $key ]      = $entry;
            }
        } else {
            $entry['translations'] = $translations;
            $entry['updated_at']   = time();
            $option[ $key ]        = $entry;
        }

        update_option( 'fp_multilanguage_strings', $option );
    }

    /**
     * @return array<string, array{context:string,original:string,updated:string}>
     */
    private function get_manual_string_metadata(): array {
        $metadata = array();

        global $wpdb;

        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) && ! empty( $wpdb->prefix ) && class_exists( '\\wpdb' ) ) {
            $table = $wpdb->prefix . 'fp_multilanguage_strings';

            if ( $this->manual_strings_table_exists( $table ) ) {
                $tableName = esc_sql( $table );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results( "SELECT string_key, context, original, updated_at FROM {$tableName}", ARRAY_A );

                if ( is_array( $rows ) ) {
                    foreach ( $rows as $row ) {
                        if ( ! is_array( $row ) ) {
                            continue;
                        }

                        $key = isset( $row['string_key'] ) ? (string) $row['string_key'] : '';
                        if ( '' === $key ) {
                            continue;
                        }

                        $metadata[ $key ] = array(
                            'context'  => isset( $row['context'] ) ? (string) $row['context'] : '',
                            'original' => isset( $row['original'] ) ? (string) $row['original'] : '',
                            'updated'  => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
                        );
                    }
                }
            }
        }

        $fallback = get_option( 'fp_multilanguage_strings', array() );
        if ( is_array( $fallback ) ) {
            foreach ( $fallback as $key => $data ) {
                $key = is_string( $key ) ? $key : (string) $key;
                if ( '' === $key ) {
                    continue;
                }

                if ( ! isset( $metadata[ $key ] ) ) {
                    $metadata[ $key ] = array(
                        'context'  => isset( $data['context'] ) ? (string) $data['context'] : '',
                        'original' => isset( $data['original'] ) ? (string) $data['original'] : '',
                        'updated'  => isset( $data['updated_at'] ) ? (string) $data['updated_at'] : '',
                    );
                } else {
                    if ( '' === $metadata[ $key ]['context'] && isset( $data['context'] ) ) {
                        $metadata[ $key ]['context'] = (string) $data['context'];
                    }

                    if ( '' === $metadata[ $key ]['original'] && isset( $data['original'] ) ) {
                        $metadata[ $key ]['original'] = (string) $data['original'];
                    }
                }
            }
        }

        return $metadata;
    }
}
