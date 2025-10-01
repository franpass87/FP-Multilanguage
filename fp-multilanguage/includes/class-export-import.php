<?php
/**
 * Export and import helpers for FP Multilanguage.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Handle structured export/import routines and sandbox previews.
 *
 * @since 0.2.0
 */
class FPML_Export_Import {
        /**
         * Option key for sandbox previews storage.
         */
        const SANDBOX_OPTION = 'fpml_sandbox_previews';

        /**
         * Singleton instance.
         *
         * @var FPML_Export_Import|null
         */
        protected static $instance = null;

        /**
         * Queue reference.
         *
         * @var FPML_Queue
         */
        protected $queue;

        /**
         * Logger reference.
         *
         * @var FPML_Logger
         */
        protected $logger;

        /**
         * Settings reference.
         *
         * @var FPML_Settings
         */
        protected $settings;

        /**
         * Glossary manager reference.
         *
         * @var FPML_Glossary
         */
        protected $glossary;

        /**
         * Overrides manager reference.
         *
         * @var FPML_Strings_Override
         */
        protected $overrides;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Export_Import
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        protected function __construct() {
                $this->queue     = FPML_Queue::instance();
                $this->logger    = FPML_Logger::instance();
                $this->settings  = FPML_Settings::instance();
                $this->glossary  = FPML_Glossary::instance();
                $this->overrides = FPML_Strings_Override::instance();
        }

        /**
         * Export translation status state.
         *
         * @since 0.2.0
         *
         * @param string $format Format: json|csv.
         *
         * @return string
         */
        public function export_translation_state( $format = 'json' ) {
                $entries = $this->get_translation_state_entries();
                $format  = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

                if ( 'csv' === $format ) {
                        return $this->convert_entries_to_csv( $entries );
                }

                return wp_json_encode( $entries );
        }

        /**
         * Import translation state entries.
         *
         * @since 0.2.0
         *
         * @param string $payload Raw payload.
         * @param string $format  json|csv.
         *
         * @return int Number of imported rows.
         */
        public function import_translation_state( $payload, $format = 'json' ) {
                $format  = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';
                $payload = is_string( $payload ) ? trim( $payload ) : '';

                if ( '' === $payload ) {
                        return 0;
                }

                if ( 'csv' === $format ) {
                        $rows = $this->parse_csv_rows( $payload );
                } else {
                        $rows = json_decode( $payload, true );
                }

                if ( ! is_array( $rows ) ) {
                        return 0;
                }

                $imported = 0;

                foreach ( $rows as $row ) {
                        if ( empty( $row['object_type'] ) || empty( $row['field'] ) || empty( $row['status'] ) ) {
                                continue;
                        }

                        $object_type = sanitize_key( $row['object_type'] );
                        $field       = sanitize_key( $row['field'] );
                        $status      = sanitize_key( $row['status'] );
                        $target_id   = isset( $row['translation_id'] ) ? absint( $row['translation_id'] ) : 0;

                        if ( ! $target_id ) {
                                continue;
                        }

                        $meta_key = '_fpml_status_' . $field;

                        switch ( $object_type ) {
                                case 'post':
                                case 'menu':
                                        update_post_meta( $target_id, $meta_key, $status );
                                        $imported++;
                                        break;
                                case 'term':
                                        update_term_meta( $target_id, $meta_key, $status );
                                        $imported++;
                                        break;
                        }
                }

                return $imported;
        }

        /**
         * Export logger entries.
         *
         * @since 0.2.0
         *
         * @param string $format json|csv.
         *
         * @return string
         */
        public function export_logs( $format = 'json' ) {
                $format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';
                $logs   = $this->logger->get_logs( 200 );

                if ( 'csv' === $format ) {
                        $rows   = array();
                        $rows[] = array( 'timestamp', 'level', 'message', 'context' );

                        foreach ( $logs as $log ) {
                                $rows[] = array(
                                        isset( $log['timestamp'] ) ? $log['timestamp'] : '',
                                        isset( $log['level'] ) ? $log['level'] : 'info',
                                        isset( $log['message'] ) ? $log['message'] : '',
                                        isset( $log['context'] ) ? wp_json_encode( $log['context'] ) : '',
                                );
                        }

                        return $this->convert_rows_to_csv( $rows );
                }

                return wp_json_encode( $logs );
        }

        /**
         * Import log entries.
         *
         * @since 0.2.0
         *
         * @param string $payload Payload body.
         * @param string $format  json|csv.
         *
         * @return int Imported count.
         */
        public function import_logs( $payload, $format = 'json' ) {
                $format  = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';
                $payload = is_string( $payload ) ? trim( $payload ) : '';

                if ( '' === $payload ) {
                        return 0;
                }

                if ( 'csv' === $format ) {
                        $rows = $this->parse_csv_rows( $payload );
                } else {
                        $rows = json_decode( $payload, true );
                }

                if ( ! is_array( $rows ) ) {
                        return 0;
                }

                $normalized = array();

                foreach ( $rows as $row ) {
                        if ( empty( $row['message'] ) ) {
                                continue;
                        }

                        $normalized[] = array(
                                'timestamp' => isset( $row['timestamp'] ) ? sanitize_text_field( $row['timestamp'] ) : current_time( 'mysql', true ),
                                'level'     => isset( $row['level'] ) ? sanitize_key( $row['level'] ) : 'info',
                                'message'   => sanitize_textarea_field( $row['message'] ),
                                'context'   => isset( $row['context'] ) ? $this->normalize_context_column( $row['context'] ) : array(),
                        );
                }

                if ( empty( $normalized ) ) {
                        return 0;
                }

                return $this->logger->import_logs( $normalized );
        }

        /**
         * Retrieve stored sandbox previews.
         *
         * @since 0.2.0
         *
         * @return array
         */
        public function get_sandbox_previews() {
                $previews = get_option( self::SANDBOX_OPTION, array() );

                if ( ! is_array( $previews ) ) {
                        return array();
                }

                return $previews;
        }

        /**
         * Record a sandbox preview entry.
         *
         * @since 0.2.0
         *
         * @param array $data Preview data.
         *
         * @return void
         */
        public function record_sandbox_preview( $data ) {
                $entry = array(
                        'timestamp'         => current_time( 'mysql', true ),
                        'object_type'       => isset( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : 'post',
                        'object_id'         => isset( $data['object_id'] ) ? absint( $data['object_id'] ) : 0,
                        'field'             => isset( $data['field'] ) ? sanitize_text_field( $data['field'] ) : '',
                        'characters'        => isset( $data['characters'] ) ? absint( $data['characters'] ) : 0,
                        'word_count'        => isset( $data['word_count'] ) ? absint( $data['word_count'] ) : 0,
                        'estimated_cost'    => isset( $data['estimated_cost'] ) ? (float) $data['estimated_cost'] : 0.0,
                        'source_excerpt'    => isset( $data['source_excerpt'] ) ? $this->clean_preview_text( $data['source_excerpt'] ) : '',
                        'translated_excerpt'=> isset( $data['translated_excerpt'] ) ? $this->clean_preview_text( $data['translated_excerpt'] ) : '',
                        'job_id'            => isset( $data['job_id'] ) ? absint( $data['job_id'] ) : 0,
                        'provider'          => isset( $data['provider'] ) ? sanitize_text_field( $data['provider'] ) : '',
                        'source_url'        => isset( $data['source_url'] ) ? esc_url_raw( $data['source_url'] ) : '',
                        'translation_url'   => isset( $data['translation_url'] ) ? esc_url_raw( $data['translation_url'] ) : '',
                );

                $previews = $this->get_sandbox_previews();
                array_unshift( $previews, $entry );

                if ( count( $previews ) > 20 ) {
                        $previews = array_slice( $previews, 0, 20 );
                }

                update_option( self::SANDBOX_OPTION, $previews, false );
        }

        /**
         * Clear sandbox previews.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function clear_sandbox_previews() {
                delete_option( self::SANDBOX_OPTION );
        }

        /**
         * Normalize context column from CSV imports.
         *
         * @since 0.2.0
         *
         * @param mixed $context Raw context.
         *
         * @return array
         */
        protected function normalize_context_column( $context ) {
                if ( empty( $context ) ) {
                        return array();
                }

                if ( is_array( $context ) ) {
                        return $context;
                }

                $decoded = json_decode( (string) $context, true );

                if ( is_array( $decoded ) ) {
                        return $decoded;
                }

                return array();
        }

        /**
         * Clean preview text to avoid leaking HTML.
         *
         * @since 0.2.0
         *
         * @param string $text Raw text.
         *
         * @return string
         */
        protected function clean_preview_text( $text ) {
                $text = wp_strip_all_tags( (string) $text );
                $text = preg_replace( '/\s+/u', ' ', $text );

                return trim( wp_trim_words( $text, 40, '…' ) );
        }

        /**
         * Retrieve translation state entries for posts, terms and menus.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_translation_state_entries() {
                $entries = array();

                $entries = array_merge( $entries, $this->collect_post_status_rows() );
                $entries = array_merge( $entries, $this->collect_term_status_rows() );
                $entries = array_merge( $entries, $this->collect_menu_status_rows() );

                return $entries;
        }

        /**
         * Collect post status rows.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function collect_post_status_rows() {
                $rows = array();

                $args = array(
                        'post_type'      => 'any',
                        'posts_per_page' => -1,
                        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
                        'meta_key'       => '_fpml_is_translation',
                        'meta_value'     => 1,
                        'fields'         => 'ids',
                );

                $ids = get_posts( $args );

                foreach ( $ids as $translation_id ) {
                        $translation_id = (int) $translation_id;
                        $post           = get_post( $translation_id );

                        if ( ! $post ) {
                                continue;
                        }

                        $meta      = get_post_meta( $translation_id );
                        $source_id = (int) get_post_meta( $translation_id, '_fpml_pair_source_id', true );
                        $source    = $source_id ? get_permalink( $source_id ) : '';
                        $target    = get_permalink( $translation_id );

                        foreach ( $meta as $meta_key => $values ) {
                                if ( 0 !== strpos( $meta_key, '_fpml_status_' ) ) {
                                        continue;
                                }

                                $status = end( $values );
                                $status = sanitize_key( $status );
                                $field  = substr( $meta_key, 13 );

                                $rows[] = array(
                                        'object_type'      => 'post',
                                        'object_subtype'   => $post->post_type,
                                        'source_id'        => $source_id,
                                        'translation_id'   => $translation_id,
                                        'field'            => $field,
                                        'status'           => $status,
                                        'source_url'       => $source ? esc_url_raw( $source ) : '',
                                        'translation_url'  => $target ? esc_url_raw( $target ) : '',
                                        'status_date'      => $this->get_status_timestamp( $meta, $meta_key, get_post_modified_gmt( $translation_id ) ),
                                        'title'            => $this->clean_preview_text( get_the_title( $translation_id ) ),
                                );
                        }
                }

                return $rows;
        }

        /**
         * Collect term status rows.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function collect_term_status_rows() {
                $rows       = array();
                $taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

                foreach ( $taxonomies as $taxonomy ) {
                        $terms = get_terms(
                                array(
                                        'taxonomy'   => $taxonomy,
                                        'hide_empty' => false,
                                        'meta_query' => array(
                                                array(
                                                        'key'   => '_fpml_is_translation',
                                                        'value' => 1,
                                                ),
                                        ),
                                )
                        );

                        if ( is_wp_error( $terms ) ) {
                                continue;
                        }

                        foreach ( $terms as $term ) {
                                $meta      = get_term_meta( $term->term_id );
                                $source_id = (int) get_term_meta( $term->term_id, '_fpml_pair_source_id', true );
                                $source    = $source_id ? get_term_link( (int) $source_id, $taxonomy ) : '';
                                $target    = get_term_link( $term, $taxonomy );

                                foreach ( $meta as $meta_key => $values ) {
                                        if ( 0 !== strpos( $meta_key, '_fpml_status_' ) ) {
                                                continue;
                                        }

                                        $status = end( $values );
                                        $status = sanitize_key( $status );
                                        $field  = substr( $meta_key, 13 );

                                        $rows[] = array(
                                                'object_type'      => 'term',
                                                'object_subtype'   => $taxonomy,
                                                'source_id'        => $source_id,
                                                'translation_id'   => (int) $term->term_id,
                                                'field'            => $field,
                                                'status'           => $status,
                                                'source_url'       => ! is_wp_error( $source ) ? esc_url_raw( $source ) : '',
                                                'translation_url'  => ! is_wp_error( $target ) ? esc_url_raw( $target ) : '',
                                                'status_date'      => $this->get_status_timestamp( $meta, $meta_key, gmdate( 'Y-m-d H:i:s' ) ),
                                                'title'            => $this->clean_preview_text( $term->name ),
                                        );
                                }
                        }
                }

                return $rows;
        }

        /**
         * Collect menu status rows.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function collect_menu_status_rows() {
                $rows = array();

                $items = get_posts(
                        array(
                                'post_type'      => 'nav_menu_item',
                                'posts_per_page' => -1,
                                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                                'meta_key'       => '_fpml_is_translation',
                                'meta_value'     => 1,
                                'fields'         => 'ids',
                        )
                );

                foreach ( $items as $item_id ) {
                        $item_id    = (int) $item_id;
                        $meta       = get_post_meta( $item_id );
                        $source_id  = (int) get_post_meta( $item_id, '_fpml_pair_source_id', true );
                        $source_url = $source_id ? get_post_meta( $source_id, '_menu_item_url', true ) : '';
                        $target_url = get_post_meta( $item_id, '_menu_item_url', true );
                        $title      = get_post_meta( $item_id, '_menu_item_title', true );

                        foreach ( $meta as $meta_key => $values ) {
                                if ( 0 !== strpos( $meta_key, '_fpml_status_' ) ) {
                                        continue;
                                }

                                $status = end( $values );
                                $status = sanitize_key( $status );
                                $field  = substr( $meta_key, 13 );

                                $rows[] = array(
                                        'object_type'      => 'menu',
                                        'object_subtype'   => 'nav_menu_item',
                                        'source_id'        => $source_id,
                                        'translation_id'   => $item_id,
                                        'field'            => $field,
                                        'status'           => $status,
                                        'source_url'       => $source_url ? esc_url_raw( $source_url ) : '',
                                        'translation_url'  => $target_url ? esc_url_raw( $target_url ) : '',
                                        'status_date'      => get_post_modified_gmt( $item_id ),
                                        'title'            => $this->clean_preview_text( $title ? $title : get_the_title( $item_id ) ),
                                );
                        }
                }

                return $rows;
        }

        /**
         * Extract timestamp from meta or fallback.
         *
         * @since 0.2.0
         *
         * @param array  $meta       Meta array.
         * @param string $meta_key   Status meta key.
         * @param string $fallback   Fallback timestamp.
         *
         * @return string
         */
        protected function get_status_timestamp( $meta, $meta_key, $fallback ) {
                $suffix = $meta_key . '_updated_at';

                if ( isset( $meta[ $suffix ] ) ) {
                        $value = end( $meta[ $suffix ] );
                        $value = sanitize_text_field( $value );

                        if ( '' !== $value ) {
                                return $value;
                        }
                }

                return $fallback;
        }

        /**
         * Convert entries to CSV string.
         *
         * @since 0.2.0
         *
         * @param array $entries Rows to export.
         *
         * @return string
         */
        protected function convert_entries_to_csv( $entries ) {
                $rows   = array();
                $header = array( 'object_type', 'object_subtype', 'source_id', 'translation_id', 'field', 'status', 'source_url', 'translation_url', 'status_date', 'title' );
                $rows[] = $header;

                foreach ( $entries as $entry ) {
                        $rows[] = array(
                                isset( $entry['object_type'] ) ? $entry['object_type'] : '',
                                isset( $entry['object_subtype'] ) ? $entry['object_subtype'] : '',
                                isset( $entry['source_id'] ) ? $entry['source_id'] : '',
                                isset( $entry['translation_id'] ) ? $entry['translation_id'] : '',
                                isset( $entry['field'] ) ? $entry['field'] : '',
                                isset( $entry['status'] ) ? $entry['status'] : '',
                                isset( $entry['source_url'] ) ? $entry['source_url'] : '',
                                isset( $entry['translation_url'] ) ? $entry['translation_url'] : '',
                                isset( $entry['status_date'] ) ? $entry['status_date'] : '',
                                isset( $entry['title'] ) ? $entry['title'] : '',
                        );
                }

                return $this->convert_rows_to_csv( $rows );
        }

        /**
         * Convert rows to CSV output.
         *
         * @since 0.2.0
         *
         * @param array $rows Rows of data.
         *
         * @return string
         */
        protected function convert_rows_to_csv( $rows ) {
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
         * Parse CSV rows into associative arrays.
         *
         * @since 0.2.0
         *
         * @param string $csv CSV payload.
         *
         * @return array
         */
        protected function parse_csv_rows( $csv ) {
                $csv    = (string) $csv;
                $buffer = fopen( 'php://temp', 'w+' );

                if ( false === $buffer ) {
                        return array();
                }

                fwrite( $buffer, $csv );
                rewind( $buffer );

                $header = array();
                $rows   = array();

                while ( ( $columns = fgetcsv( $buffer ) ) !== false ) {
                        if ( empty( $columns ) || ( 1 === count( $columns ) && '' === trim( (string) $columns[0] ) ) ) {
                                continue;
                        }

                        if ( empty( $header ) ) {
                                $header = array_map( 'sanitize_key', $columns );
                                continue;
                        }

                        $row = array();

                        foreach ( $header as $position => $key ) {
                                $row[ $key ] = isset( $columns[ $position ] ) ? $columns[ $position ] : '';
                        }

                        $rows[] = $row;
                }

                fclose( $buffer );

                return $rows;
        }
}
