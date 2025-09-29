<?php
/**
 * Lightweight logger that stores events in an option.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Persist log entries without writing files.
 *
 * @since 0.2.0
 */
class FPML_Logger {
        /**
         * Option key for log storage.
         *
         * @var string
         */
        protected $option_key = 'fpml_logs';

        /**
         * Max entries retained.
         *
         * @var int
         */
        protected $max_entries = 200;

        /**
         * Singleton instance.
         *
         * @var FPML_Logger|null
         */
        protected static $instance = null;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Logger
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
                $this->max_entries = (int) apply_filters( 'fpml_logger_max_entries', $this->max_entries );
        }

        /**
         * Write a log entry.
         *
         * @since 0.2.0
         *
         * @param string $level   info|warn|error.
         * @param string $message Message to log.
         * @param array  $context Additional context data.
         *
         * @return void
         */
        public function log( $level, $message, $context = array() ) {
                $level   = $this->normalize_level( $level );
                $message = $this->sanitize_text( $message );
                $context = $this->sanitize_context( $context );

                $entry = array(
                        'timestamp' => current_time( 'mysql', true ),
                        'level'     => $level,
                        'message'   => $message,
                        'context'   => $context,
                );

                $logs = get_option( $this->option_key, array() );
                $logs = is_array( $logs ) ? $logs : array();

                array_unshift( $logs, $entry );

                if ( count( $logs ) > $this->max_entries ) {
                        $logs = array_slice( $logs, 0, $this->max_entries );
                }

                update_option( $this->option_key, $logs, false );
        }

        /**
         * Retrieve logs.
         *
         * @since 0.2.0
         *
         * @param int $limit Maximum entries to return.
         *
         * @return array
         */
        public function get_logs( $limit = 50 ) {
                $limit = max( 1, absint( $limit ) );
                $logs  = get_option( $this->option_key, array() );

                if ( ! is_array( $logs ) ) {
                        return array();
                }

                return array_slice( $logs, 0, $limit );
        }

        /**
         * Remove all stored logs.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function clear() {
                delete_option( $this->option_key );
        }

        /**
         * Import log entries keeping sanitation and anonymization rules.
         *
         * @since 0.2.0
         *
         * @param array $entries Entries to import.
         *
         * @return int
         */
        public function import_logs( $entries ) {
                if ( empty( $entries ) || ! is_array( $entries ) ) {
                        return 0;
                }

                $stored = get_option( $this->option_key, array() );
                $stored = is_array( $stored ) ? $stored : array();
                $imported = 0;

                foreach ( $entries as $entry ) {
                        if ( empty( $entry['message'] ) ) {
                                continue;
                        }

                        $record = array(
                                'timestamp' => isset( $entry['timestamp'] ) ? sanitize_text_field( $entry['timestamp'] ) : current_time( 'mysql', true ),
                                'level'     => $this->normalize_level( isset( $entry['level'] ) ? $entry['level'] : 'info' ),
                                'message'   => $this->sanitize_text( $entry['message'] ),
                                'context'   => $this->sanitize_context( isset( $entry['context'] ) ? $entry['context'] : array() ),
                        );

                        array_unshift( $stored, $record );
                        $imported++;
                }

                if ( $imported > 0 ) {
                        if ( count( $stored ) > $this->max_entries ) {
                                $stored = array_slice( $stored, 0, $this->max_entries );
                        }

                        update_option( $this->option_key, $stored, false );
                }

                return $imported;
        }

        /**
         * Count logs grouped by level.
         *
         * @since 0.2.0
         *
         * @return array
         */
        public function get_stats() {
                $logs  = get_option( $this->option_key, array() );
                $stats = array(
                        'info'  => 0,
                        'warn'  => 0,
                        'error' => 0,
                );

                if ( ! is_array( $logs ) ) {
                        return $stats;
                }

                foreach ( $logs as $log ) {
                        if ( empty( $log['level'] ) ) {
                                continue;
                        }

                        $level = $this->normalize_level( $log['level'] );

                        if ( isset( $stats[ $level ] ) ) {
                                $stats[ $level ]++;
                        }
                }

                return $stats;
        }

        /**
         * Normalize log level.
         *
         * @since 0.2.0
         *
         * @param string $level Incoming level.
         *
         * @return string
         */
        protected function normalize_level( $level ) {
                $level = strtolower( sanitize_text_field( $level ) );

                if ( ! in_array( $level, array( 'info', 'warn', 'error' ), true ) ) {
                        $level = 'info';
                }

                return $level;
        }

        /**
         * Sanitize log message and optionally anonymize sensitive data.
         *
         * @since 0.2.0
         *
         * @param string $text Raw message.
         *
         * @return string
         */
        protected function sanitize_text( $text ) {
                $text = sanitize_textarea_field( $text );

                if ( $this->should_anonymize() ) {
                        $text = $this->anonymize_text( $text );
                }

                return $text;
        }

        /**
         * Sanitize contextual data.
         *
         * @since 0.2.0
         *
         * @param array $context Raw context.
         *
         * @return array
         */
        protected function sanitize_context( $context ) {
                if ( empty( $context ) ) {
                        return array();
                }

                $clean = array();

                foreach ( (array) $context as $key => $value ) {
                        $key = sanitize_key( $key );

                        if ( is_scalar( $value ) ) {
                                $value = sanitize_textarea_field( (string) $value );
                        } else {
                                $value = wp_json_encode( $value );
                        }

                        if ( $this->should_anonymize() ) {
                                $value = $this->anonymize_text( $value );
                        }

                        $clean[ $key ] = $value;
                }

                return $clean;
        }

        /**
         * Check if anonymization is enabled.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        protected function should_anonymize() {
                $settings = FPML_Settings::instance();

                return (bool) $settings->get( 'anonymize_logs', false );
        }

        /**
         * Replace obvious personal data markers.
         *
         * @since 0.2.0
         *
         * @param string $text Text to scrub.
         *
         * @return string
         */
        protected function anonymize_text( $text ) {
                $text = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $text );
                $text = preg_replace( '/\b\d{4,}\b/', '[redacted-number]', $text );

                return $text;
        }
}
