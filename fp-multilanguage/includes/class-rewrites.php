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
        $settings = FPML_Settings::instance();

        if ( $settings->get( 'sitemap_en', true ) ) {
            add_rewrite_rule( '^sitemap-en\.xml$', 'index.php?fpml_sitemap=en', 'top' );
        }

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
            $lang = sanitize_text_field( $request['lang'] );
            $lang = strtolower( $lang );

            if ( 'en' === $lang ) {
                $request['fpml_lang'] = 'en';
            }

            unset( $request['lang'] );
        }

        if ( isset( $request['fpml_lang'] ) ) {
            $request['fpml_lang'] = sanitize_key( $request['fpml_lang'] );
        }

        if ( empty( $request['fpml_lang'] ) || 'en' !== $request['fpml_lang'] ) {
            return $request;
        }

        $request['fpml_lang'] = 'en';

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

        $path = rawurldecode( $path );
        $path = trim( $path, '/' );

        if ( '' === $path ) {
            return array();
        }

        $pagination = 0;

        if ( preg_match( '#/(?:page)/([0-9]+)$#i', $path, $matches ) ) {
            $pagination = max( 1, (int) $matches[1] );
            $path       = trim( substr( $path, 0, -strlen( $matches[0] ) ), '/' );
        }

        $segments            = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
        $normalized_segments = array_values(
            array_filter(
                array_map( array( $this, 'normalize_request_segment' ), $segments ),
                'strlen'
            )
        );
        $normalized_path     = implode( '/', $normalized_segments );

        $result = array();

        if ( '' !== $normalized_path ) {
            $home_base = trailingslashit( home_url() );
            // Preserve subdirectory installs by appending the rewritten path to the actual home URL.
            $home_url  = $home_base . ltrim( $normalized_path, '/' );
            $home_url  = user_trailingslashit( $home_url );
            $post_id   = url_to_postid( $home_url );

            if ( $post_id ) {
                $result = array(
                    'p' => $post_id,
                );
            } else {
                $slug           = array_pop( $normalized_segments );
                $path_segments  = $normalized_segments;
                $slug           = is_string( $slug ) ? $slug : '';

                if ( '' !== $slug ) {
                    $public_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

                    if ( ! empty( $public_taxonomies ) ) {
                        foreach ( $public_taxonomies as $taxonomy ) {
                            if ( ! is_object( $taxonomy ) || empty( $taxonomy->name ) ) {
                                continue;
                            }

                            $term = get_term_by( 'slug', $slug, $taxonomy->name );

                            if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
                                continue;
                            }

                            $base_variants = $this->get_taxonomy_base_segments( $taxonomy );

                            foreach ( $base_variants as $base_segments ) {
                                if ( ! $this->segments_start_with( $path_segments, $base_segments ) ) {
                                    continue;
                                }

                                $remainder = array_slice( $path_segments, count( $base_segments ) );

                                if ( $this->term_matches_path_segments( $term, $remainder, $this->taxonomy_uses_hierarchical_paths( $taxonomy ) ) ) {
                                    $result = array(
                                        'taxonomy' => $taxonomy->name,
                                        'term'     => $term->slug,
                                    );
                                    break 2;
                                }
                            }
                        }
                    }

                    if ( empty( $result ) ) {
                        $author_query = $this->map_author_archive_query( $slug, $path_segments );

                        if ( ! empty( $author_query ) ) {
                            $result = $author_query;
                        }
                    }

                    if ( empty( $result ) ) {
                        $result = array(
                            'name'      => $slug,
                            'post_type' => 'any',
                        );
                    }
                }
            }
        }

        if ( $pagination > 0 ) {
            if ( isset( $result['p'] ) ) {
                $result['page'] = $pagination;
            } else {
                $result['paged'] = $pagination;
            }
        }

        return $result;
    }

    /**
     * Determine whether the request segments start with the provided prefix.
     *
     * @since 0.3.1
     *
     * @param array $segments Current request segments.
     * @param array $prefix   Prefix segments to validate.
     *
     * @return bool
     */
    protected function segments_start_with( $segments, $prefix ) {
        $segments = is_array( $segments )
            ? array_values(
                array_filter(
                    array_map( array( $this, 'normalize_request_segment' ), $segments ),
                    'strlen'
                )
            )
            : array();
        $prefix   = is_array( $prefix )
            ? array_values(
                array_filter(
                    array_map( array( $this, 'normalize_request_segment' ), $prefix ),
                    'strlen'
                )
            )
            : array();

        if ( empty( $prefix ) ) {
            return true;
        }

        if ( count( $segments ) < count( $prefix ) ) {
            return false;
        }

        return $prefix === array_slice( $segments, 0, count( $prefix ) );
    }

    /**
     * Check whether a term hierarchy matches the provided path remainder.
     *
     * @since 0.3.1
     *
     * @param WP_Term $term                Term instance.
     * @param array   $segments            Remaining request segments.
     * @param bool    $expects_hierarchy   Whether the taxonomy includes hierarchical slugs in the rewrite.
     *
     * @return bool
     */
    protected function term_matches_path_segments( WP_Term $term, $segments, $expects_hierarchy ) {
        $segments = is_array( $segments )
            ? array_values(
                array_filter(
                    array_map( array( $this, 'normalize_request_segment' ), $segments ),
                    'strlen'
                )
            )
            : array();

        if ( empty( $segments ) ) {
            if ( ! $expects_hierarchy ) {
                return true;
            }

            return 0 === (int) $term->parent;
        }

        if ( ! $expects_hierarchy ) {
            return false;
        }

        $ancestors = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );

        if ( empty( $ancestors ) ) {
            return false;
        }

        $ancestors     = array_reverse( $ancestors );
        $ancestor_slugs = array();

        foreach ( $ancestors as $ancestor_id ) {
            $ancestor = get_term( $ancestor_id, $term->taxonomy );

            if ( ! ( $ancestor instanceof WP_Term ) || is_wp_error( $ancestor ) ) {
                return false;
            }

            $ancestor_slugs[] = $this->normalize_request_segment( $ancestor->slug );
        }

        return $segments === $ancestor_slugs;
    }

    /**
     * Retrieve possible base segments for a taxonomy.
     *
     * @since 0.3.1
     *
     * @param WP_Taxonomy $taxonomy Taxonomy object.
     *
     * @return array[] Array of base segment arrays.
     */
    protected function get_taxonomy_base_segments( $taxonomy ) {
        $bases = array();

        if ( isset( $taxonomy->rewrite ) && is_array( $taxonomy->rewrite ) && ! empty( $taxonomy->rewrite['slug'] ) ) {
            $bases[] = trim( $taxonomy->rewrite['slug'], '/' );

            if ( ! empty( $taxonomy->rewrite['with_front'] ) ) {
                global $wp_rewrite;

                if ( isset( $wp_rewrite ) && is_object( $wp_rewrite ) && ! empty( $wp_rewrite->front ) ) {
                    $bases[] = trim( $wp_rewrite->front, '/' ) . '/' . trim( $taxonomy->rewrite['slug'], '/' );
                }
            }
        }

        if ( empty( $bases ) ) {
            $bases[] = $taxonomy->name;
        }

        $bases = array_values( array_unique( array_filter( $bases, 'strlen' ) ) );

        if ( empty( $bases ) ) {
            $bases[] = '';
        }

        return array_map(
            function ( $base ) {
                $base = trim( $base, '/' );

                if ( '' === $base ) {
                    return array();
                }

                $parts = array_values( array_filter( explode( '/', $base ), 'strlen' ) );

                if ( empty( $parts ) ) {
                    return array();
                }

                return array_values(
                    array_filter(
                        array_map( array( $this, 'normalize_request_segment' ), $parts ),
                        'strlen'
                    )
                );
            },
            $bases
        );
    }

    /**
     * Determine whether a taxonomy exposes hierarchical paths.
     *
     * @since 0.3.1
     *
     * @param WP_Taxonomy $taxonomy Taxonomy object.
     *
     * @return bool
     */
    protected function taxonomy_uses_hierarchical_paths( $taxonomy ) {
        if ( ! is_object( $taxonomy ) ) {
            return false;
        }

        if ( isset( $taxonomy->rewrite ) && is_array( $taxonomy->rewrite ) && array_key_exists( 'hierarchical', $taxonomy->rewrite ) ) {
            return (bool) $taxonomy->rewrite['hierarchical'];
        }

        return (bool) $taxonomy->hierarchical;
    }

    /**
     * Normalize a path segment for comparisons and lookups.
     *
     * @since 0.3.1
     *
     * @param string $segment Raw segment value.
     *
     * @return string
     */
    protected function normalize_request_segment( $segment ) {
        if ( is_string( $segment ) ) {
            $segment = rawurldecode( $segment );
        }

        $segment = trim( (string) $segment );

        if ( '' === $segment ) {
            return '';
        }

        $normalized = sanitize_title( $segment );

        if ( '' === $normalized && is_numeric( $segment ) ) {
            $normalized = (string) $segment;
        }

        return $normalized;
    }

    /**
     * Build a query var map for author archives.
     *
     * @since 0.3.1
     *
     * @param string $slug          Detected author slug.
     * @param array  $path_segments Path segments preceding the slug.
     *
     * @return array
     */
    protected function map_author_archive_query( $slug, $path_segments ) {
        $slug = $this->normalize_request_segment( $slug );

        if ( '' === $slug ) {
            return array();
        }

        $segments = is_array( $path_segments )
            ? array_values(
                array_filter(
                    array_map( array( $this, 'normalize_request_segment' ), $path_segments ),
                    'strlen'
                )
            )
            : array();

        if ( empty( $segments ) ) {
            return array();
        }

        $bases = $this->get_author_base_segments();

        foreach ( $bases as $base_segments ) {
            if ( ! $this->segments_start_with( $segments, $base_segments ) ) {
                continue;
            }

            $remainder = array_slice( $segments, count( $base_segments ) );

            if ( empty( $remainder ) ) {
                return array(
                    'author_name' => $slug,
                );
            }
        }

        return array();
    }

    /**
     * Retrieve normalized author base segments.
     *
     * @since 0.3.1
     *
     * @return array[]
     */
    protected function get_author_base_segments() {
        $bases = array();

        global $wp_rewrite;

        if ( isset( $wp_rewrite ) && is_object( $wp_rewrite ) ) {
            $structure = $wp_rewrite->get_author_permastruct();

            if ( is_string( $structure ) && '' !== $structure ) {
                if ( false !== strpos( $structure, '%author%' ) ) {
                    $base = trim( str_replace( '%author%', '', $structure ), '/' );

                    if ( '' !== $base ) {
                        $bases[] = $base;
                    }
                }
            }

            if ( ! empty( $wp_rewrite->author_base ) ) {
                $author_base = trim( (string) $wp_rewrite->author_base, '/' );

                if ( '' !== $author_base ) {
                    $bases[] = $author_base;

                    if ( ! empty( $wp_rewrite->front ) ) {
                        $bases[] = trim( $wp_rewrite->front, '/' ) . '/' . $author_base;
                    }
                }
            }
        }

        if ( empty( $bases ) ) {
            $bases[] = 'author';
        }

        $bases = array_values( array_unique( array_filter( $bases, 'strlen' ) ) );

        return array_values(
            array_filter(
                array_map(
                    function ( $base ) {
                        $parts = array_values( array_filter( explode( '/', trim( $base, '/' ) ), 'strlen' ) );

                        if ( empty( $parts ) ) {
                            return array();
                        }

                        return array_values(
                            array_filter(
                                array_map( array( $this, 'normalize_request_segment' ), $parts ),
                                'strlen'
                            )
                        );
                    },
                    $bases
                ),
                function ( $segments ) {
                    return ! empty( $segments );
                }
            )
        );
    }
}
