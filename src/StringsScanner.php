<?php
/**
 * Scanner for theme and plugin strings used in translation functions.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Build and expose a catalog of strings detected across active code.
 *
 * @since 0.2.0
 */
class StringsScanner {
        /**
         * Option storing catalog data.
         */
        const OPTION_KEY = '\FPML_strings_catalog';

        /**
         * Option storing last scan timestamp.
         */
        const OPTION_LAST_SCAN = '\FPML_strings_last_scan';

        /**
         * Singleton instance.
         *
         * @var \FPML_Strings_Scanner|null
         */
        protected static $instance = null;

        /**
         * Cached catalog.
         *
         * @var array
         */
        protected $catalog = array();

        /**
         * Constructor.
         */
        protected function __construct() {
                $this->catalog = $this->load_catalog();
        }

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return \FPML_Strings_Scanner
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Load catalog from storage.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function load_catalog() {
                $stored = get_option( self::OPTION_KEY, array() );

                if ( ! is_array( $stored ) ) {
                        $stored = array();
                }

                return $stored;
        }

        /**
         * Persist catalog data.
         *
         * @since 0.2.0
         *
         * @param array $catalog Catalog entries.
         *
         * @return void
         */
        protected function save_catalog( $catalog ) {
                update_option( self::OPTION_KEY, $catalog, false );
                $this->catalog = $catalog;
        }

        /**
         * Retrieve catalog entries.
         *
         * @since 0.2.0
         *
         * @return array
         */
        public function get_catalog() {
                return $this->catalog;
        }

        /**
         * Retrieve last scan timestamp.
         *
         * @since 0.2.0
         *
         * @return int
         */
        public function get_last_scan_time() {
                return (int) get_option( self::OPTION_LAST_SCAN, 0 );
        }

        /**
         * Run a scan of active theme and plugins.
         *
         * @since 0.2.0
         *
         * @return int Number of unique strings detected.
         */
        public function scan() {
                $targets = $this->get_scan_targets();

                if ( empty( $targets ) ) {
                        $this->save_catalog( array() );
                        update_option( self::OPTION_LAST_SCAN, time() );
                        return 0;
                }

                $catalog = array();

                foreach ( $targets as $path ) {
                        $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
                                RecursiveIteratorIterator::SELF_FIRST
                        );

                        foreach ( $iterator as $file ) {
                                if ( $file->isDir() ) {
                                        continue;
                                }

                                if ( 'php' !== strtolower( $file->getExtension() ) ) {
                                        continue;
                                }

                                $this->scan_file( $file->getPathname(), $catalog );
                        }
                }

                $this->save_catalog( $catalog );
                update_option( self::OPTION_LAST_SCAN, time() );

                return count( $catalog );
        }

        /**
         * Build list of directories to scan.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_scan_targets() {
                $targets = array();

                $theme = get_stylesheet_directory();
                if ( $theme && is_dir( $theme ) ) {
                        $targets[] = $theme;
                }

                $parent_theme = get_template_directory();
                if ( $parent_theme && is_dir( $parent_theme ) ) {
                        $targets[] = $parent_theme;
                }

                $plugins = (array) get_option( 'active_plugins', array() );

                foreach ( $plugins as $plugin_file ) {
                        $plugin_path = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );

                        if ( is_dir( $plugin_path ) ) {
                                $targets[] = $plugin_path;
                        }
                }

                $targets = array_unique( array_filter( $targets ) );

                /**
                 * Allow filtering the list of scanned directories.
                 *
                 * @since 0.2.0
                 *
                 * @param array $targets Directories to scan.
                 */
                return apply_filters( '\FPML_strings_scan_targets', $targets );
        }

        /**
         * Scan a single PHP file for translation calls.
         *
         * @since 0.2.0
         *
         * @param string $file_path File path.
         * @param array  $catalog   Reference to catalog.
         *
         * @return void
         */
        protected function scan_file( $file_path, array &$catalog ) {
                $content = file_get_contents( $file_path );

                if ( false === $content ) {
                        return;
                }

                $pattern = "/(?P<function>__|_e|esc_html__|esc_attr__|_x|_ex)\\s*\\(\\s*(?P<quote>['\"])" .
                        "(?P<string>(?:\\\\.|(?!\\1).)+)\\1\\s*(?:,\\s*(?P<context_quote>['\"])" .
                        "(?P<context>(?:\\\\.|(?!\\4).)+)\\4)?/Uu";

                if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
                        return;
                }

                $rel_path = $this->make_relative_path( $file_path );

                foreach ( $matches as $match ) {
                        $original = stripcslashes( $match['string'][0] );
                        $context  = isset( $match['context'][0] ) ? stripcslashes( $match['context'][0] ) : '';
                        $domain   = $this->extract_text_domain( $content, $match[0][1] + strlen( $match[0][0] ) );
                        $hash     = md5( wp_json_encode( array( $original, $domain, $context ) ) );

                        if ( ! isset( $catalog[ $hash ] ) ) {
                                $catalog[ $hash ] = array(
                                        'original'    => $original,
                                        'domain'      => $domain,
                                        'context'     => $context,
                                        'occurrences' => 0,
                                        'files'       => array(),
                                        'last_found'  => current_time( 'timestamp' ),
                                );
                        }

                        $line = $this->detect_line_number( $content, $match[0][1] );

                        $catalog[ $hash ]['occurrences']++;
                        $catalog[ $hash ]['files'][] = array(
                                'file' => $rel_path,
                                'line' => $line,
                        );
                }
        }

        /**
         * Attempt to detect the text domain argument.
         *
         * @since 0.2.0
         *
         * @param string $content File content.
         * @param int    $offset  Offset to start searching.
         *
         * @return string
         */
        protected function extract_text_domain( $content, $offset ) {
                $slice = substr( $content, $offset );

                if ( ! $slice ) {
                        return '';
                }

                if ( ! preg_match( '/,\s*(?:array\s*\(|(?P<quote>[\'"]))(?P<domain>[a-zA-Z0-9_-]+)/', $slice, $matches ) ) {
                        return '';
                }

                return isset( $matches['domain'] ) ? sanitize_key( $matches['domain'] ) : '';
        }

        /**
         * Detect the line number of a match.
         *
         * @since 0.2.0
         *
         * @param string $content File content.
         * @param int    $offset  Match offset.
         *
         * @return int
         */
        protected function detect_line_number( $content, $offset ) {
                $before = substr( $content, 0, $offset );

                if ( false === $before ) {
                        return 0;
                }

                return substr_count( $before, "\n" ) + 1;
        }

        /**
         * Turn an absolute path into a path relative to ABSPATH.
         *
         * @since 0.2.0
         *
         * @param string $path Absolute path.
         *
         * @return string
         */
        protected function make_relative_path( $path ) {
                $path = wp_normalize_path( $path );
                $root = wp_normalize_path( ABSPATH );

                if ( strpos( $path, $root ) === 0 ) {
                        return ltrim( substr( $path, strlen( $root ) ), '/' );
                }

                return $path;
        }
}

