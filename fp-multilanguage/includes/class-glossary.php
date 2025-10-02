<?php
/**
 * Glossary manager handling custom terminology enforcement.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Provide storage and helpers for glossary entries.
 *
 * @since 0.2.0
 */
class FPML_Glossary {
        /**
         * Option name storing glossary entries.
         */
        const OPTION_KEY = 'fpml_glossary_entries';

        /**
         * Singleton instance.
         *
         * @var FPML_Glossary|null
         */
        protected static $instance = null;

        /**
         * Cached glossary entries.
         *
         * @var array
         */
        protected $entries = array();

        /**
         * Constructor.
         */
        protected function __construct() {
                $this->entries = $this->load_entries();
                $this->register_filters();
        }

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Glossary
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Register glossary filters so providers can enforce terminology.
         *
         * @since 0.2.0
         *
         * @return void
         */
        protected function register_filters() {
                add_filter( 'fpml_glossary_pre_translate', array( $this, 'filter_pre_translate' ), 10, 4 );
                add_filter( 'fpml_glossary_post_translate', array( $this, 'filter_post_translate' ), 10, 4 );
        }

        /**
         * Load glossary entries from storage.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function load_entries() {
                $stored = get_option( self::OPTION_KEY, array() );

                if ( ! is_array( $stored ) ) {
                        $stored = array();
                }

                return $stored;
        }

        /**
         * Persist glossary entries.
         *
         * @since 0.2.0
         *
         * @param array $entries Entries to store.
         *
         * @return void
         */
        protected function save_entries( $entries ) {
                update_option( self::OPTION_KEY, $entries, false );
                $this->entries = $entries;
        }

        /**
         * Retrieve glossary entries.
         *
         * @since 0.2.0
         *
         * @return array
         */
        public function get_entries() {
                return $this->entries;
        }

        /**
         * Replace glossary entries with the provided list.
         *
         * @since 0.2.0
         *
         * @param array $entries Entries to persist.
         *
         * @return void
         */
        public function replace_entries( $entries ) {
                $sanitized = array();

                foreach ( $entries as $entry ) {
                        if ( empty( $entry['source'] ) || empty( $entry['target'] ) ) {
                                continue;
                        }

                        $key = md5( wp_json_encode( array( $entry['source'], $entry['context'] ?? '' ) ) );

                        $sanitized[ $key ] = array(
                                'source'     => sanitize_text_field( $entry['source'] ),
                                'target'     => sanitize_text_field( $entry['target'] ),
                                'context'    => isset( $entry['context'] ) ? sanitize_text_field( $entry['context'] ) : '',
                                'updated_at' => current_time( 'timestamp' ),
                        );
                }

                $this->save_entries( $sanitized );
        }

        /**
         * Add or update a single entry.
         *
         * @since 0.2.0
         *
         * @param string $source  Italian term.
         * @param string $target  English translation.
         * @param string $context Optional context label.
         *
         * @return void
         */
        public function upsert_entry( $source, $target, $context = '' ) {
                $source  = sanitize_text_field( $source );
                $target  = sanitize_text_field( $target );
                $context = sanitize_text_field( $context );

                if ( '' === $source || '' === $target ) {
                        return;
                }

                $key                       = md5( wp_json_encode( array( $source, $context ) ) );
                $this->entries[ $key ]     = array(
                        'source'     => $source,
                        'target'     => $target,
                        'context'    => $context,
                        'updated_at' => current_time( 'timestamp' ),
                );
                $this->save_entries( $this->entries );
        }

        /**
         * Delete glossary entries by keys.
         *
         * @since 0.2.0
         *
         * @param array $keys Keys to remove.
         *
         * @return void
         */
        public function delete_entries( $keys ) {
                if ( empty( $keys ) ) {
                        return;
                }

                foreach ( $keys as $key ) {
                        unset( $this->entries[ $key ] );
                }

                $this->save_entries( $this->entries );
        }

        /**
         * Export glossary to JSON.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function export_json() {
                return wp_json_encode( array_values( $this->entries ) );
        }

        /**
         * Export glossary to CSV.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function export_csv() {
                $rows = array();
                $rows[] = array( 'source', 'target', 'context' );

                foreach ( $this->entries as $entry ) {
                        $rows[] = array( $entry['source'], $entry['target'], $entry['context'] );
                }

                $buffer = fopen( 'php://temp', 'w+' );

                foreach ( $rows as $row ) {
                        fputcsv( $buffer, $row );
                }

                rewind( $buffer );
                $csv = stream_get_contents( $buffer );
                fclose( $buffer );

                return $csv;
        }

        /**
         * Import glossary from JSON payload.
         *
         * @since 0.2.0
         *
         * @param string $json JSON payload.
         *
         * @return int Number of imported entries.
         */
        public function import_json( $json ) {
                $decoded = json_decode( $json, true );

                if ( ! is_array( $decoded ) ) {
                        return 0;
                }

                $imported = 0;

                foreach ( $decoded as $entry ) {
                        if ( empty( $entry['source'] ) || empty( $entry['target'] ) ) {
                                continue;
                        }

                        $this->upsert_entry( $entry['source'], $entry['target'], isset( $entry['context'] ) ? $entry['context'] : '' );
                        $imported++;
                }

                return $imported;
        }

        /**
         * Import glossary from CSV payload.
         *
         * @since 0.2.0
         *
         * @param string $csv CSV payload.
         *
         * @return int Number of imported entries.
         */
        public function import_csv( $csv ) {
                $lines = explode( "\n", $csv );

                if ( empty( $lines ) ) {
                        return 0;
                }

                $imported = 0;
                $header   = true;

                foreach ( $lines as $line ) {
                        $line = trim( $line );

                        if ( '' === $line ) {
                                continue;
                        }

                        $columns = str_getcsv( $line );

                        if ( $header ) {
                                $header = false;
                                continue;
                        }

                        $source  = isset( $columns[0] ) ? $columns[0] : '';
                        $target  = isset( $columns[1] ) ? $columns[1] : '';
                        $context = isset( $columns[2] ) ? $columns[2] : '';

                        if ( '' === $source || '' === $target ) {
                                continue;
                        }

                        $this->upsert_entry( $source, $target, $context );
                        $imported++;
                }

                return $imported;
        }

        /**
         * Apply glossary replacements before sending text to providers.
         *
         * @since 0.2.0
         *
         * @param string              $text    Text to process.
         * @param string              $source  Source locale code.
         * @param string              $target  Target locale code.
         * @param string              $context Context domain.
         *
         * @return string
         */
        public function filter_pre_translate( $text, $source, $target, $context ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( empty( $this->entries ) || ! is_string( $text ) || '' === $text ) {
                        return $text;
                }

                $settings        = FPML_Settings::instance();
                $case_sensitive  = $settings ? $settings->get( 'glossary_case_sensitive', false ) : false;
                $ordered_entries = $this->get_entries_sorted();

                foreach ( $ordered_entries as $entry ) {
                        $text = $this->replace_glossary_term( $text, $entry, $case_sensitive );
                }

                return $text;
        }

        /**
         * Restore placeholders after translation.
         *
         * @since 0.2.0
         *
         * @param string              $text    Text processed by provider.
         * @param string              $source  Source locale code.
         * @param string              $target  Target locale code.
         * @param string              $context Context domain.
         *
         * @return string
         */
        public function filter_post_translate( $text, $source, $target, $context ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( ! is_string( $text ) || '' === $text ) {
                        return $text;
                }

                return preg_replace_callback(
                        '/\[\[FPML:([A-Za-z0-9+\/=\-_:]+)\]\]/',
                        static function( $matches ) {
                                $decoded = base64_decode( $matches[1], true );

                                if ( false === $decoded ) {
                                        return $matches[0];
                                }

                                return $decoded;
                        },
                        $text
                );
        }

        /**
         * Retrieve entries sorted by source length (DESC) to match longer phrases first.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_entries_sorted() {
                $entries = $this->entries;

                uasort(
                        $entries,
                        static function( $a, $b ) {
                                return mb_strlen( $b['source'], 'UTF-8' ) <=> mb_strlen( $a['source'], 'UTF-8' );
                        }
                );

                return $entries;
        }

        /**
         * Replace occurrences of a glossary term within the text.
         *
         * @since 0.2.0
         *
         * @param string $text           Original text.
         * @param array  $entry          Glossary entry.
         * @param bool   $case_sensitive Whether to match case-sensitive.
         *
         * @return string
         */
        protected function replace_glossary_term( $text, $entry, $case_sensitive ) {
                $source = $entry['source'];
                $target = $entry['target'];

                if ( '' === $source || '' === $target ) {
                        return $text;
                }

                $pattern = '/' . preg_quote( $source, '/' ) . '/u' . ( $case_sensitive ? '' : 'i' );

                return preg_replace_callback(
                        $pattern,
                        static function( $matches ) use ( $target ) {
                                $placeholder = '[[FPML:' . base64_encode( $target ) . ']]';
                                // Preserve spacing around the match if present.
                                if ( preg_match( '/^(\s*)(.*?)(\s*)$/u', $matches[0], $parts ) ) {
                                        return $parts[1] . $placeholder . $parts[3];
                                }

                                return $placeholder;
                        },
                        $text
                );
        }
}
