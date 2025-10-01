<?php
/**
 * Manage manual overrides for scanned strings.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Provide storage utilities and runtime filters for string overrides.
 *
 * @since 0.2.0
 */
class FPML_Strings_Override {
        /**
         * Option storing overrides.
         */
        const OPTION_KEY = 'fpml_strings_overrides';

        /**
         * Singleton instance.
         *
         * @var FPML_Strings_Override|null
         */
        protected static $instance = null;

        /**
         * Cached overrides list.
         *
         * @var array
         */
        protected $overrides = array();

        /**
         * Constructor.
         */
        protected function __construct() {
                $this->overrides = $this->load_overrides();
                $this->register_filters();
        }

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Strings_Override
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Load overrides from storage.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function load_overrides() {
                $stored = get_option( self::OPTION_KEY, array() );

                if ( ! is_array( $stored ) ) {
                        $stored = array();
                }

                return $stored;
        }

        /**
         * Persist overrides list.
         *
         * @since 0.2.0
         *
         * @param array $overrides Overrides to persist.
         *
         * @return void
         */
        protected function save_overrides( $overrides ) {
                update_option( self::OPTION_KEY, $overrides, false );
                $this->overrides = $overrides;
        }

        /**
         * Register runtime filters so the overrides apply to gettext calls.
         *
         * @since 0.2.0
         *
         * @return void
         */
        protected function register_filters() {
                add_filter( 'gettext', array( $this, 'filter_gettext' ), 10, 3 );
                add_filter( 'ngettext', array( $this, 'filter_ngettext' ), 10, 5 );
        }

        /**
         * Retrieve overrides.
         *
         * @since 0.2.0
         *
         * @return array
         */
        public function get_overrides() {
                return $this->overrides;
        }

        /**
         * Update override translations.
         *
         * @since 0.2.0
         *
         * @param array $payload Overrides payload keyed by hash.
         *
         * @return void
         */
        public function update_overrides( $payload ) {
                $payload = is_array( $payload ) ? $payload : array();

                foreach ( $payload as $hash => $row ) {
                        if ( ! isset( $this->overrides[ $hash ] ) ) {
                                continue;
                        }

                        if ( isset( $row['delete'] ) && $row['delete'] ) {
                                unset( $this->overrides[ $hash ] );
                                continue;
                        }

                        $target = isset( $row['target'] ) ? wp_kses_post( $row['target'] ) : '';
                        $context = isset( $row['context'] ) ? sanitize_text_field( $row['context'] ) : $this->overrides[ $hash ]['context'];

                        $this->overrides[ $hash ]['target']     = $target;
                        $this->overrides[ $hash ]['context']    = $context;
                        $this->overrides[ $hash ]['updated_at'] = current_time( 'timestamp' );
                }

                $this->save_overrides( $this->overrides );
        }

        /**
         * Add a new override.
         *
         * @since 0.2.0
         *
         * @param string $source  Original string.
         * @param string $target  Override translation.
         * @param string $context Optional context.
         *
         * @return void
         */
        public function add_override( $source, $target, $context = '' ) {
                $source  = sanitize_text_field( $source );
                $target  = wp_kses_post( $target );
                $context = sanitize_text_field( $context );

                if ( '' === $source || '' === $target ) {
                        return;
                }

                $hash                        = md5( wp_json_encode( array( $source, $context ) ) );
                $this->overrides[ $hash ]     = array(
                        'source'     => $source,
                        'target'     => $target,
                        'context'    => $context,
                        'updated_at' => current_time( 'timestamp' ),
                );
                $this->save_overrides( $this->overrides );
        }

        /**
         * Delete overrides.
         *
         * @since 0.2.0
         *
         * @param array $hashes Hashes to remove.
         *
         * @return void
         */
        public function delete_overrides( $hashes ) {
                if ( empty( $hashes ) ) {
                        return;
                }

                foreach ( $hashes as $hash ) {
                        unset( $this->overrides[ $hash ] );
                }

                $this->save_overrides( $this->overrides );
        }

        /**
         * Export overrides as JSON string.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function export_json() {
                return wp_json_encode( array_values( $this->overrides ) );
        }

        /**
         * Export overrides as CSV string.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function export_csv() {
                $rows = array();
                $rows[] = array( 'source', 'target', 'context' );

                foreach ( $this->overrides as $row ) {
                        $rows[] = array( $row['source'], $row['target'], $row['context'] );
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
         * Import overrides from JSON payload.
         *
         * @since 0.2.0
         *
         * @param string $json JSON payload.
         *
         * @return int Number of imported rows.
         */
        public function import_json( $json ) {
                $decoded = json_decode( $json, true );

                if ( ! is_array( $decoded ) ) {
                        return 0;
                }

                $imported = 0;

                foreach ( $decoded as $row ) {
                        if ( empty( $row['source'] ) || empty( $row['target'] ) ) {
                                continue;
                        }

                        $this->add_override( $row['source'], $row['target'], isset( $row['context'] ) ? $row['context'] : '' );
                        $imported++;
                }

                return $imported;
        }

        /**
         * Import overrides from CSV payload.
         *
         * @since 0.2.0
         *
         * @param string $csv CSV payload.
         *
         * @return int Number of imported rows.
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

                        $this->add_override( $source, $target, $context );
                        $imported++;
                }

                return $imported;
        }

        /**
         * Apply overrides to gettext calls.
         *
         * @since 0.2.0
         *
         * @param string $translation Current translation.
         * @param string $text        Original text.
         * @param string $domain      Text domain.
         *
         * @return string
         */
        public function filter_gettext( $translation, $text, $domain ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( empty( $this->overrides ) ) {
                        return $translation;
                }

                $language = FPML_Language::instance();

                if ( $language && method_exists( $language, 'get_current_language' ) && FPML_Language::TARGET !== $language->get_current_language() ) {
                        return $translation;
                }

                foreach ( $this->overrides as $row ) {
                        if ( $row['source'] === $text ) {
                                return $row['target'];
                        }
                }

                return $translation;
        }

        /**
         * Apply overrides to plural gettext calls.
         *
         * @since 0.2.0
         *
         * @param string $translation Current translation.
         * @param string $single      Singular text.
         * @param string $plural      Plural text.
         * @param string $number      Current number.
         * @param string $domain      Text domain.
         *
         * @return string
         */
        public function filter_ngettext( $translation, $single, $plural, $number, $domain ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( empty( $this->overrides ) ) {
                        return $translation;
                }

                $language = FPML_Language::instance();

                if ( $language && method_exists( $language, 'get_current_language' ) && FPML_Language::TARGET !== $language->get_current_language() ) {
                        return $translation;
                }

                foreach ( $this->overrides as $row ) {
                        if ( 1 === (int) $number && $row['source'] === $single ) {
                                return $row['target'];
                        }

                        if ( 1 !== (int) $number && $row['source'] === $plural ) {
                                return $row['target'];
                        }
                }

                return $translation;
        }
}
