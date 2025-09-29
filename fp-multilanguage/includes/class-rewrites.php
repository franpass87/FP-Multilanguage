<?php
/**
 * Rewrite rules and routing utilities.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle custom rewrites for the English locale.
 *
 * @since 0.2.0
 */
class FPML_Rewrites {
    /**
     * Singleton instance.
     *
     * @var FPML_Rewrites|null
     */
    protected static $instance = null;

    /**
     * Retrieve singleton instance.
     *
     * @since 0.2.0
     *
     * @return FPML_Rewrites
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
        add_action( 'init', array( $this, 'register_rewrites' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_filter( 'request', array( $this, 'handle_request_overrides' ) );
    }

    /**
     * Register rewrite rules for the /en/ prefix when enabled.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function register_rewrites() {
        add_rewrite_rule( '^sitemap-en\.xml$', 'index.php?fpml_sitemap=en', 'top' );

        $settings = FPML_Settings::instance();

        if ( 'segment' !== $settings->get( 'routing_mode', 'segment' ) ) {
            return;
        }

        add_rewrite_tag( '%fpml_path%', '(.+)' );

        add_rewrite_rule( '^en/?$', 'index.php?fpml_lang=en', 'top' );
        add_rewrite_rule( '^en/(.+)/?$', 'index.php?fpml_lang=en&fpml_path=$matches[1]', 'top' );
    }

    /**
     * Register custom query vars.
     *
     * @since 0.2.0
     *
     * @param array $vars Query vars.
     *
     * @return array
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'fpml_lang';
        $vars[] = 'fpml_path';
        $vars[] = 'fpml_sitemap';

        return $vars;
    }

    /**
     * Normalize request variables for language routing.
     *
     * @since 0.2.0
     *
     * @param array $request Request vars.
     *
     * @return array
     */
    public function handle_request_overrides( $request ) {
        if ( is_admin() || defined( 'REST_REQUEST' ) ) {
            return $request;
        }

        if ( isset( $request['lang'] ) ) {
            if ( 'en' === strtolower( sanitize_text_field( $request['lang'] ) ) ) {
                $request['fpml_lang'] = 'en';
            }

            unset( $request['lang'] );
        }

        if ( empty( $request['fpml_lang'] ) || 'en' !== $request['fpml_lang'] ) {
            return $request;
        }

        if ( isset( $request['fpml_path'] ) ) {
            $mapped = $this->map_path_to_query( $request['fpml_path'] );

            unset( $request['fpml_path'] );

            if ( ! empty( $mapped ) ) {
                $request = array_merge( $request, $mapped );
            }
        }

        return $request;
    }

    /**
     * Attempt to map a rewritten path back to core query vars.
     *
     * @since 0.2.0
     *
     * @param string $path Original path.
     *
     * @return array
     */
    protected function map_path_to_query( $path ) {
        $path = trim( (string) $path );

        if ( '' === $path ) {
            return array();
        }

        $path = trim( $path, '/' );

        if ( '' === $path ) {
            return array();
        }

        $home_url = home_url( '/' . $path . '/' );
        $post_id  = url_to_postid( $home_url );

        if ( $post_id ) {
            return array(
                'page_id' => $post_id,
            );
        }

        $segments = explode( '/', $path );
        $slug     = end( $segments );

        $public_taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

        foreach ( $public_taxonomies as $taxonomy ) {
            $term = get_term_by( 'slug', $slug, $taxonomy );

            if ( $term && ! is_wp_error( $term ) ) {
                return array(
                    'taxonomy' => $taxonomy,
                    'term'     => $term->slug,
                );
            }
        }

        return array(
            'name'      => $slug,
            'post_type' => 'any',
        );
    }
}
