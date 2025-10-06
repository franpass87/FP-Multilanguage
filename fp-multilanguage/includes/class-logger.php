<?php
/**
 * Lightweight logger that stores events in an option.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
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

        /**
         * Log translation start event.
         *
         * @since 0.3.2
         *
         * @param int    $job_id   Queue job ID.
         * @param string $provider Provider slug.
         * @param int    $chars    Character count.
         *
         * @return void
         */
        public function log_translation_start( $job_id, $provider, $chars ) {
                $this->log(
                        'info',
                        sprintf( 'Translation started for job #%d', $job_id ),
                        array(
                                'event'      => 'translation.start',
                                'job_id'     => $job_id,
                                'provider'   => $provider,
                                'characters' => $chars,
                        )
                );
        }

        /**
         * Log translation complete event.
         *
         * @since 0.3.2
         *
         * @param int   $job_id      Queue job ID.
         * @param int   $duration_ms Duration in milliseconds.
         * @param float $cost        Estimated cost.
         *
         * @return void
         */
        public function log_translation_complete( $job_id, $duration_ms, $cost ) {
                $this->log(
                        'info',
                        sprintf( 'Translation completed for job #%d', $job_id ),
                        array(
                                'event'    => 'translation.complete',
                                'job_id'   => $job_id,
                                'duration' => $duration_ms,
                                'cost'     => $cost,
                        )
                );
        }

        /**
         * Log API error event.
         *
         * @since 0.3.2
         *
         * @param string      $provider     Provider slug.
         * @param string      $code         Error code.
         * @param string      $message      Error message.
         * @param int|null    $http_status  HTTP status code if available.
         *
         * @return void
         */
        public function log_api_error( $provider, $code, $message, $http_status = null ) {
                $context = array(
                        'event'         => 'api.error',
                        'provider'      => $provider,
                        'error_code'    => $code,
                        'error_message' => $message,
                );

                if ( null !== $http_status ) {
                        $context['http_status'] = $http_status;
                }

                $this->log(
                        'error',
                        sprintf( 'API error from %s: %s', $provider, $message ),
                        $context
                );
        }

        /**
         * Retrieve logs filtered by event type.
         *
         * @since 0.3.2
         *
         * @param string $event_type Event type to filter (e.g. 'translation.start').
         * @param int    $limit      Maximum entries to return.
         *
         * @return array
         */
        public function get_logs_by_event( $event_type, $limit = 100 ) {
                $all_logs = $this->get_logs( $limit * 2 );
                $filtered = array();

                foreach ( $all_logs as $log ) {
                        if ( isset( $log['context']['event'] ) && $log['context']['event'] === $event_type ) {
                                $filtered[] = $log;

                                if ( count( $filtered ) >= $limit ) {
                                        break;
                                }
                        }
                }

                return $filtered;
        }
}
