<?php
/**
 * Rewrite rules and routing utilities.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use WP_Post;
use FP\Multilanguage\Routing\RewriteRules;
use FP\Multilanguage\Routing\QueryFilter;
use FP\Multilanguage\Routing\PostResolver;
use FP\Multilanguage\Routing\RequestHandler;
use FP\Multilanguage\Routing\AdjacentPostFilter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle custom rewrites for the English locale.
 *
 * @since 0.2.0
 */
class Rewrites {
	use ContainerAwareTrait;
    /**
     * Singleton instance.
     *
     * @var \FPML_Rewrites|null
     */
    protected static $instance = null;

    /**
     * Rewrite rules instance.
     *
     * @since 0.10.0
     *
     * @var RewriteRules
     */
    protected $rewrite_rules;

    /**
     * Query filter instance.
     *
     * @since 0.10.0
     *
     * @var QueryFilter
     */
    protected $query_filter;

    /**
     * Post resolver instance.
     *
     * @since 0.10.0
     *
     * @var PostResolver
     */
    protected $post_resolver;

    /**
     * Request handler instance.
     *
     * @since 0.10.0
     *
     * @var RequestHandler
     */
    protected $request_handler;

    /**
     * Adjacent post filter instance.
     *
     * @since 0.10.0
     *
     * @var AdjacentPostFilter
     */
    protected $adjacent_post_filter;

    /**
     * Retrieve singleton instance.
     *
     * @since 0.2.0
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        $container = $this->getContainer();
        $settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
        
        // Initialize modules
        $this->rewrite_rules = new RewriteRules( $settings );
        $this->post_resolver = new PostResolver();
        $this->query_filter = new QueryFilter( $this->post_resolver );
        $this->request_handler = new RequestHandler( $this->post_resolver );
        $this->adjacent_post_filter = new AdjacentPostFilter( $this->post_resolver );

        // Register hooks - delegate to modules
        add_action( 'init', array( $this->rewrite_rules, 'register_rewrites' ) );
        add_filter( 'query_vars', array( $this->rewrite_rules, 'register_query_vars' ) );
        add_filter( 'request', array( $this->request_handler, 'handle_request_overrides' ) );
        add_action( 'pre_get_posts', array( $this->request_handler, 'handle_english_queries' ), 1 );
        add_action( 'pre_get_posts', array( $this->query_filter, 'filter_posts_by_language' ), 5 );
        add_filter( 'posts_request', array( $this->query_filter, 'filter_posts_request_by_language' ), 10, 2 );
        add_filter( 'posts_clauses', array( $this->query_filter, 'filter_posts_clauses_by_language' ), 10, 2 );
        add_filter( 'posts_where', array( $this->query_filter, 'filter_posts_where_by_language' ), 5, 2 );
        add_filter( 'posts_results', array( $this->query_filter, 'filter_posts_results_by_language' ), 5, 2 );
        add_filter( 'the_posts', array( $this->query_filter, 'filter_the_posts_by_language' ), 10, 2 );
        add_action( 'template_redirect', array( $this, 'force_single_post' ), 1 );
        add_action( 'template_redirect', array( $this, 'redirect_untranslated_to_home' ), 2 );
        add_action( 'wp_footer', array( $this, 'filter_output_html_by_language' ), 999 );
        add_filter( 'get_previous_post_where', array( $this->adjacent_post_filter, 'filter_adjacent_post_where' ), 10, 5 );
        add_filter( 'get_next_post_where', array( $this->adjacent_post_filter, 'filter_adjacent_post_where' ), 10, 5 );
        add_filter( 'get_previous_post_join', array( $this->adjacent_post_filter, 'filter_adjacent_post_join' ), 10, 5 );
        add_filter( 'get_next_post_join', array( $this->adjacent_post_filter, 'filter_adjacent_post_join' ), 10, 5 );
    }

    /**
     * Get current language from request URI.
     *
     * @since 0.10.0
     *
     * @return string|false Language code or false if not a target language path.
     */
    protected function get_current_language_from_path() {
        return $this->post_resolver->get_current_language_from_path();
    }

    /**
     * Check if current path is a target language path.
     *
     * @since 0.10.0
     *
     * @param string|null $lang Optional specific language to check.
     * @return bool True if current path is a target language path.
     */
    protected function is_target_language_path( $lang = null ) {
        $current_lang = $this->get_current_language_from_path();
        
        if ( null === $lang ) {
            return false !== $current_lang;
        }
        
        return $current_lang === $lang;
    }

    /**
     * Register rewrite rules for enabled languages.
     *
     * @since 0.2.0
     * @since 0.10.0 Updated to support multiple enabled languages dynamically.
     *
     * @return void
     */
    public function register_rewrites(): void {
        $this->rewrite_rules->register_rewrites();
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
    /**
     * Register query variables.
     *
     * @param array<string> $vars Existing query vars.
     *
     * @return array<string>
     */
    public function register_query_vars( array $vars ): array {
        return $this->rewrite_rules->register_query_vars( $vars );
    }

    /**
     * Normalize request variables for language routing.
     *
     * @since 0.2.0
     *
     * @param array<string, mixed> $request Request vars.
     *
     * @return array<string, mixed>
     */
    public function handle_request_overrides( array $request ): array {
        return $this->request_handler->handle_request_overrides( $request );
    }


    /**
     * Attempt to map a rewritten path back to core query vars.
     *
     * @since 0.2.0
     * @since 0.10.0 Updated to support multiple languages.
     *
     * @param string $path Original path.
     * @param string $current_lang Current language code.
     *
     * @return array
     */
    protected function map_path_to_query( $path, $current_lang = null ) {
        // If no current_lang provided, try to detect from path
        if ( empty( $current_lang ) ) {
            $current_lang = $this->get_current_language_from_path();
        }
        
        // Fallback to first enabled language if still empty
        if ( empty( $current_lang ) ) {
            $language_manager = fpml_get_language_manager();
            if ( ! $language_manager ) {
                return array();
            }
            $enabled_languages = $language_manager->get_enabled_languages();
            $current_lang      = ! empty( $enabled_languages ) ? $enabled_languages[0] : '';
        }
        $path = trim( (string) $path );

        if ( '' === $path ) {
            return array();
        }

        $path = rawurldecode( $path );
        $path = trim( $path, '/' );

        if ( '' === $path ) {
            return array();
        }

        // Get language info to extract slug
        $language_manager = fpml_get_language_manager();
        $available_languages = $language_manager->get_all_languages();
        $is_target_language_path = false;
        $detected_lang = $current_lang;

        // Check if path starts with any enabled language slug
        if ( isset( $available_languages[ $current_lang ] ) ) {
            $lang_info = $available_languages[ $current_lang ];
            if ( is_array( $lang_info ) && ! empty( $lang_info['slug'] ) ) {
                $lang_slug = trim( $lang_info['slug'], '/' );
                
                if ( preg_match( '#^' . preg_quote( $lang_slug, '#' ) . '(/|$)#', $path ) ) {
                    $is_target_language_path = true;
                    $detected_lang = $current_lang;
                    // Remove language prefix from path
                    $path = preg_replace( '#^' . preg_quote( $lang_slug, '#' ) . '/#', '', $path );
                    $path = trim( $path, '/' );
                }
            }
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
            // Se siamo su un path di una lingua target, cerca direttamente la traduzione senza usare url_to_postid
            // perché url_to_postid potrebbe non funzionare correttamente con i prefissi lingua
            if ( $is_target_language_path ) {
                // Estrai lo slug dall'ultimo segmento del path normalizzato
                $slug = end( $normalized_segments );
                $slug = is_string( $slug ) ? $slug : '';
                
                if ( '' !== $slug ) {
                    // Cerca direttamente la traduzione con questo slug per la lingua corrente
                    global $wpdb;
                    
                    // Prima prova con slug esatto e lingua target
                    $translation_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT p.ID FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
                        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
                        WHERE p.post_name = %s
                        AND p.post_type IN ('post', 'page')
                        AND p.post_status = 'publish'
                        AND pm1.meta_key = '_fpml_is_translation'
                        AND pm1.meta_value = '1'
                        AND (pm2.meta_value = %s OR pm2.meta_id IS NULL)
                        LIMIT 1",
                        '_fpml_target_language',
                        $slug,
                        $detected_lang
                    ) );
                    
                    // Se non trovato, cerca il post originale e poi la sua traduzione per questa lingua
                    if ( ! $translation_id ) {
                        $original_id = $wpdb->get_var( $wpdb->prepare(
                            "SELECT ID FROM {$wpdb->posts}
                            WHERE post_name = %s
                            AND post_type IN ('post', 'page')
                            AND post_status = 'publish'
                            LIMIT 1",
                            $slug
                        ) );
                        
                        if ( $original_id ) {
                            // Use helper function to get translation for specific language
                            $translation_id = fpml_get_translation_id( $original_id, $detected_lang );
                        }
                    }
                    
                    if ( $translation_id ) {
                        $result = array(
                            'p' => (int) $translation_id,
                        );
                        return $result;
                    }
                }
            }
            
            $home_base = trailingslashit( home_url() );
            // Preserve subdirectory installs by appending the rewritten path to the actual home URL.
            $home_url  = $home_base . ltrim( $normalized_path, '/' );
            $home_url  = user_trailingslashit( $home_url );
            $post_id   = url_to_postid( $home_url );

            if ( $post_id ) {
                // If the resolved post is already a translation, serve it directly.
                if ( get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
                    $result = array(
                        'p' => $post_id,
                    );
                } else {
                    // Otherwise, try to resolve its translated counterpart for the current language.
                    $target_id = fpml_get_translation_id( $post_id, $detected_lang );

                    if ( $target_id > 0 ) {
                        $target_post = get_post( $target_id );

                        if ( $target_post instanceof WP_Post && 'trash' !== $target_post->post_status ) {
                            $result = array(
                                'p' => $target_id,
                            );
                        }
                    }

                    // Fall back to the original post if no translation is available.
                    if ( empty( $result ) ) {
                        $result = array(
                            'p' => $post_id,
                        );
                    }
                }
            } else {
                $slug           = array_pop( $normalized_segments );
                $path_segments  = $normalized_segments;
                $slug           = is_string( $slug ) ? $slug : '';

                if ( '' !== $slug ) {
                    // Se si accede tramite /en/, cerca solo pagine tradotte
                    if ( $is_target_language_path ) {
                        // Prova prima con slug esatto (senza prefisso en-)
                        $translation_post = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );

                        if ( $translation_post && get_post_meta( $translation_post->ID, '_fpml_is_translation', true ) ) {
                            $result = array(
                                'p' => $translation_post->ID,
                            );
                        } else {
                            // Prova con prefisso "<lang>-*" (dinamico)
                            $translation_slug = $current_lang . '-' . $slug;
                            $translation_post = get_page_by_path( $translation_slug, OBJECT, array( 'page', 'post' ) );

                            if ( $translation_post && get_post_meta( $translation_post->ID, '_fpml_is_translation', true ) ) {
                                $result = array(
                                    'p' => $translation_post->ID,
                                );
                            } else {
                                // Cerca nel database
                                global $wpdb;

                                if ( isset( $wpdb ) ) {
                                    // Cerca prima senza prefisso
                                    $maybe_id = $wpdb->get_var(
                                        $wpdb->prepare(
                                            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('post','page') AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
                                            $slug
                                        )
                                    );

                                    if ( $maybe_id ) {
                                        $maybe_post = get_post( (int) $maybe_id );
                                        if ( $maybe_post instanceof WP_Post && get_post_meta( $maybe_post->ID, '_fpml_is_translation', true ) ) {
                                            $result = array(
                                                'p' => $maybe_post->ID,
                                            );
                                        }
                                    }

                                    // Se non trovato, prova con prefisso "<lang>-*" (dinamico)
                                    if ( empty( $result ) ) {
                                        $like_slug = $wpdb->esc_like( $translation_slug ) . '%';
                                        $maybe_id  = $wpdb->get_var(
                                            $wpdb->prepare(
                                                "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE %s AND post_type IN ('post','page') AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
                                                $like_slug
                                            )
                                        );

                                        if ( $maybe_id ) {
                                            $maybe_post = get_post( (int) $maybe_id );
                                            if ( $maybe_post instanceof WP_Post && get_post_meta( $maybe_post->ID, '_fpml_is_translation', true ) ) {
                                                $result = array(
                                                    'p' => $maybe_post->ID,
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // Comportamento normale: prova prima a cercare una pagina tradotta con slug esatto
                        $translation_post = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );

                        if ( $translation_post && get_post_meta( $translation_post->ID, '_fpml_is_translation', true ) ) {
                            $result = array(
                                'p' => $translation_post->ID,
                            );
                        } else {
                            // Prova con prefisso "<lang>-*" (dinamico)
                            $translation_slug = $current_lang . '-' . $slug;
                            $translation_post = get_page_by_path( $translation_slug, OBJECT, array( 'page', 'post' ) );

                            if ( $translation_post && get_post_meta( $translation_post->ID, '_fpml_is_translation', true ) ) {
                                $result = array(
                                    'p' => $translation_post->ID,
                                );
                            } else {
                                global $wpdb;

                                if ( isset( $wpdb ) ) {
                                    // Cerca prima senza prefisso
                                    $maybe_id = $wpdb->get_var(
                                        $wpdb->prepare(
                                            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('post','page') AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
                                            $slug
                                        )
                                    );

                                    if ( $maybe_id ) {
                                        $maybe_post = get_post( (int) $maybe_id );
                                        if ( $maybe_post instanceof WP_Post && get_post_meta( $maybe_post->ID, '_fpml_is_translation', true ) ) {
                                            $result = array(
                                                'p' => $maybe_post->ID,
                                            );
                                        }
                                    }

                                    // Se non trovato, prova con prefisso "en-*"
                                    if ( empty( $result ) ) {
                                        $like_slug = $wpdb->esc_like( $translation_slug ) . '%';
                                        $maybe_id  = $wpdb->get_var(
                                            $wpdb->prepare(
                                                "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE %s AND post_type IN ('post','page') AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
                                                $like_slug
                                            )
                                        );

                                        if ( $maybe_id ) {
                                            $maybe_post = get_post( (int) $maybe_id );
                                            if ( $maybe_post instanceof WP_Post && get_post_meta( $maybe_post->ID, '_fpml_is_translation', true ) ) {
                                                $result = array(
                                                    'p' => $maybe_post->ID,
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $public_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

                    if ( ! empty( $public_taxonomies ) ) {
                        foreach ( $public_taxonomies as $taxonomy ) {
                            if ( ! is_object( $taxonomy ) || empty( $taxonomy->name ) ) {
                                continue;
                            }

                            $term = get_term_by( 'slug', $slug, $taxonomy->name );

                            // Se non trovato e siamo in path lingua target, prova con prefisso dinamico
                            if ( ! ( $term instanceof \WP_Term ) && $is_target_language_path ) {
                                $term = get_term_by( 'slug', $current_lang . '-' . $slug, $taxonomy->name );
                            }

                            if ( ! ( $term instanceof \WP_Term ) || is_wp_error( $term ) ) {
                                continue;
                            }

                            // Se trovato e siamo in path inglese, assicuriamoci di usare la traduzione
                            if ( $is_target_language_path ) {
                                if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
                                    // È già una traduzione, perfetto
                                } else {
                                    // È il termine originale, cerchiamo la sua traduzione.
                                    $term_lang_mgr  = fpml_get_language_manager();
                                    $term_lang      = '';
                                    if ( $term_lang_mgr ) {
                                        $term_enabled = $term_lang_mgr->get_enabled_languages();
                                        $term_lang    = ! empty( $term_enabled ) ? $term_enabled[0] : '';
                                    }
                                    if ( empty( $term_lang ) ) {
                                        $term_lang = function_exists( 'fpml_get_primary_target_language' ) ? fpml_get_primary_target_language() : '';
                                    }
                                    $meta_key = '_fpml_pair_id_' . $term_lang;
                                    $trans_id = $term_lang ? (int) get_term_meta( $term->term_id, $meta_key, true ) : 0;
                                    // Backward compatibility: legacy _fpml_pair_id (no lang suffix).
                                    if ( ! $trans_id ) {
                                            $trans_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );
                                    }
                                    if ( $trans_id ) {
                                        $trans_term = get_term( $trans_id, $taxonomy->name );
                                        if ( $trans_term instanceof \WP_Term && ! is_wp_error( $trans_term ) ) {
                                            $term = $trans_term;
                                        }
                                    }
                                }
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

    /**
     * Filter posts by language in main query.
     *
     * @since 0.9.3
     *
     * @param \WP_Query $query The WP_Query instance.
     *
     * @return void
     */
    public function filter_posts_by_language( $query ) {
        $this->query_filter->filter_posts_by_language( $query );
    }


    /**
     * Filter posts SQL request before execution to intercept secondary queries.
     * This is the most aggressive filter that modifies SQL directly.
     *
     * @since 0.9.3
     *
     * @param string    $request The SQL query string.
     * @param WP_Query  $query   The WP_Query instance.
     * @return string Modified SQL query.
     */
    public function filter_posts_request_by_language( $request, $query ) {
        return $this->query_filter->filter_posts_request_by_language( $request, $query );
    }

    /**
     * Filter posts clauses (WHERE, JOIN, etc.) to exclude non-translated posts on /en/ paths.
     * This is a more aggressive filter that intercepts SQL at the clause level.
     *
     * @since 0.9.3
     *
     * @param array    $clauses Array of SQL clauses (where, join, orderby, etc.).
     * @param WP_Query $query   The WP_Query instance.
     * @return array Modified clauses.
     */
    public function filter_posts_clauses_by_language( $clauses, $query ) {
        return $this->query_filter->filter_posts_clauses_by_language( $clauses, $query );
    }

    /**
     * Filter posts WHERE clause by language for more aggressive filtering.
     * Applicato a tutte le query frontend (main + widget/sidebar) per filtrare completamente.
     *
     * @since 0.9.3
     *
     * @param string    $where The WHERE clause.
     * @param \WP_Query $query The WP_Query instance.
     *
     * @return string
     */
    public function filter_posts_where_by_language( $where, $query ) {
        return $this->query_filter->filter_posts_where_by_language( $where, $query );
    }

    /**
     * Filter posts results to exclude incomplete translations.
     * This acts as a final safety net after SQL filtering.
     *
     * @since 0.9.3
     *
     * @param array    $posts Array of post objects.
     * @param \WP_Query $query The WP_Query instance.
     * @return array Filtered array of post objects.
     */
    public function filter_posts_results_by_language( $posts, $query ) {
        return $this->query_filter->filter_posts_results_by_language( $posts, $query );
    }

    /**
     * Filter the_posts hook for additional safety (acts on final output).
     *
     * @since 0.9.3
     *
     * @param array    $posts Array of post objects.
     * @param \WP_Query $query The WP_Query instance.
     * @return array Filtered array of post objects.
     */
    public function filter_the_posts_by_language( $posts, $query ) {
        return $this->query_filter->filter_the_posts_by_language( $posts, $query );
    }

    /**
     * Filter output HTML to remove problematic links as final fallback.
     * This removes links to non-translated posts from the HTML output.
     *
     * @since 0.9.3
     */
    public function filter_output_html_by_language() {
        // Applica solo al frontend, non in admin
        if ( is_admin() ) {
            return;
        }

        // Check if we're on a target language path
        $current_lang = $this->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return;
        }

        // Minimal JS fallback: fix any /lang/https?:// duplicate URLs that PHP missed.
        $lang_mgr_js2  = fpml_get_language_manager();
        $lang_info_js2 = $lang_mgr_js2 ? $lang_mgr_js2->get_language_info( $current_lang ) : null;
        $lang_slug_js2 = $lang_info_js2 ? trim( $lang_info_js2['slug'], '/' ) : $current_lang;
        ?>
        <script type="text/javascript">
        (function() {
            window.fpmlDebug = window.fpmlDebug || <?php echo ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'true' : 'false'; ?>;
            var fpmlLangSlug = '<?php echo esc_js( $lang_slug_js2 ); ?>';
            function fixDuplicateUrls() {
                var links = document.querySelectorAll('a[href]');
                var fixedCount = 0;
                var currentHost = window.location.hostname;
                
                for (var i = 0; i < links.length; i++) {
                    var link = links[i];
                    var href = link.getAttribute('href');
                    
                    if (!href) continue;
                    
                    // Verifica se l'URL contiene duplicati (pattern: /LANG/http:// o http://domain/LANG/http://)
                    var langPrefix = '/' + fpmlLangSlug + '/';
                    if (href.indexOf(langPrefix + 'http://') !== -1 || href.indexOf(langPrefix + 'https://') !== -1 || 
                        (href.indexOf('http://' + currentHost + langPrefix + 'http://') !== -1) ||
                        (href.indexOf('https://' + currentHost + langPrefix + 'https://') !== -1)) {
                        
                        // Estrai solo l'ultimo URL completo (dopo l'ultimo http:// o https://)
                        var lastHttpPos = Math.max(
                            href.lastIndexOf('http://'),
                            href.lastIndexOf('https://')
                        );
                        
                        if (lastHttpPos !== -1) {
                            var fixedHref = href.substring(lastHttpPos);
                            
                            // Verifica che l'URL corretto sia valido
                            try {
                                var testUrl = new URL(fixedHref);
                                if (testUrl.hostname === currentHost) {
                                    link.setAttribute('href', fixedHref);
                                    fixedCount++;
                                }
                            } catch (e) {
                                // Se l'URL non è valido, prova un approccio alternativo
                                // Pattern: http://domain/LANG/http://domain/path -> http://domain/LANG/path
                                var langEscaped2 = fpmlLangSlug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                                var match = fixedHref.match(new RegExp('http[s]?:\\/\\/([^\\/]+)\\/' + langEscaped2 + '\\/http[s]?:\\/\\/[^\\/]+\\/(.+)$'));
                                if (match) {
                                    var scheme = fixedHref.indexOf('https://') === 0 ? 'https://' : 'http://';
                                    var correctedHref = scheme + match[1] + langPrefix + match[2];
                                    link.setAttribute('href', correctedHref);
                                    fixedCount++;
                                } else {
                                    // Ultimo tentativo: estrai solo la parte dopo l'ultimo http://
                                    var parts = fixedHref.split('http://');
                                    if (parts.length > 1) {
                                        link.setAttribute('href', 'http://' + parts[parts.length - 1]);
                                        fixedCount++;
                                    } else {
                                        parts = fixedHref.split('https://');
                                        if (parts.length > 1) {
                                            link.setAttribute('href', 'https://' + parts[parts.length - 1]);
                                            fixedCount++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (fixedCount > 0 && window.fpmlDebug && window.console && window.console.log) {
                    console.log('[FPML] Corretti ' + fixedCount + ' URL duplicati nel DOM');
                }
                
                return fixedCount;
            }
            
            function removeNonTranslatedLinks() {
                // Funzione che funziona anche senza jQuery
                var relatedSelectors = [
                    '.related-post-wrap',
                    '[class*="related"]',
                    '[class*="Related"]',
                    '.nectar-related-posts',
                    '.related-posts',
                    '.post-related',
                    'section[class*="related"]'
                ];
                
                var relatedSections = [];
                relatedSelectors.forEach(function(selector) {
                    var elements = document.querySelectorAll(selector);
                    for (var i = 0; i < elements.length; i++) {
                        relatedSections.push(elements[i]);
                    }
                });
                
                if (relatedSections.length === 0) {
                    // Se non troviamo sezioni specifiche, cerca in tutto il body
                    relatedSections = [document.body];
                }
                
                var removedCount = 0;
                var currentHost = window.location.hostname;
                
                relatedSections.forEach(function(section) {
                    var links = section.querySelectorAll('a[href]');
                    
                    for (var i = 0; i < links.length; i++) {
                        var link = links[i];
                        var href = link.getAttribute('href');
                        
                        if (!href) continue;
                        
                        // Verifica se il link punta al nostro dominio
                        var linkUrl = new URL(href, window.location.origin);
                        if (linkUrl.hostname !== currentHost) continue;
                        
                        var pathname = linkUrl.pathname;
                        
                    }
                });
                
                // Log per debug (solo se console è disponibile e debug attivo)
                if (removedCount > 0 && window.fpmlDebug && window.console && window.console.log) {
                    console.log('[FPML] Rimossi ' + removedCount + ' link problematici dal DOM');
                }
            }
            
            function run() { fixDuplicateUrls(); removeNonTranslatedLinks(); }

            // Run once on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }

            // MutationObserver: debounced re-run when new nodes are added (e.g. AJAX content)
            if (typeof MutationObserver !== 'undefined') {
                var timer = null;
                var observer = new MutationObserver(function(mutations) {
                    var hasNew = mutations.some(function(m) { return m.addedNodes.length > 0; });
                    if (hasNew) {
                        clearTimeout(timer);
                        timer = setTimeout(run, 150);
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
    }

    /**
     * Handle English queries to ensure single posts are shown correctly.
     *
     * @since 0.9.3
     *
     * @param WP_Query $query The WP_Query instance.
     *
     * @return void
     */
    public function handle_english_queries( $query ) {
        $this->request_handler->handle_english_queries( $query );
    }

    /**
     * Force single post display when accessing /en/articolo-slug.
     *
     * @since 0.9.3
     *
     * @return void
     */
    public function force_single_post() {
        if ( is_admin() ) {
            return;
        }

        // Check if we're on a target language path
        $lang = get_query_var( 'fpml_lang' );
        
        if ( empty( $lang ) ) {
            $lang = $this->get_current_language_from_path();
        }

        // Check if lang is a target language
        $language_manager = fpml_get_language_manager();
        if ( ! $language_manager ) {
            return;
        }
        $enabled_languages = $language_manager->get_enabled_languages();
        $is_target_lang = ! empty( $lang ) && in_array( $lang, $enabled_languages, true );

        if ( ! $is_target_lang ) {
            return;
        }

        global $wp_query;

        // Se abbiamo un name ma non è singular, cerca la traduzione
        $name = $wp_query->get( 'name' );
        $post_id = $wp_query->get( 'p' );

        // Se abbiamo già un post ID, non fare nulla
        if ( ! empty( $post_id ) ) {
            return;
        }

        // Resolve current target language before any slug-prefix queries.
        $current_lang = ! empty( $lang ) ? $lang : (
            $language_manager ? ( ( $enabled_languages[0] ?? '' ) ?: '' ) : ''
        );

        if ( ! empty( $name ) && ! $wp_query->is_singular() && ! $wp_query->is_404() ) {
            global $wpdb;

            // First try exact slug match
            $found_post_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_name = %s 
                AND post_type IN ('post', 'page') 
                AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_fpml_is_translation' 
                    AND meta_value = '1'
                )
                LIMIT 1",
                $name
            ) );

            // If not found, try with lang- prefix (only if we know the target language).
            if ( ! $found_post_id && ! empty( $current_lang ) ) {
                $found_post_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                    WHERE post_name LIKE %s 
                    AND post_type IN ('post', 'page') 
                    AND post_status = 'publish'
                    AND ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = '_fpml_is_translation' 
                        AND meta_value = '1'
                    )
                    LIMIT 1",
                    $current_lang . '-' . $wpdb->esc_like( $name ) . '%'
                ) );
            }

            // Se ancora non trovato, cerca anche cercando il post originale e poi la sua traduzione
            if ( ! $found_post_id ) {
                // Cerca il post originale con questo slug
                $original_post_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                    WHERE post_name = %s 
                    AND post_type IN ('post', 'page') 
                    AND post_status = 'publish'
                    LIMIT 1",
                    $name
                ) );

                if ( $original_post_id ) {
                    // $current_lang is already resolved above.
                    $found_post_id = fpml_get_translation_id( $original_post_id, $current_lang );
                    // Backward compatibility: legacy _fpml_pair_id was only stored for 'en'.
                    if ( ! $found_post_id ) {
                        $legacy = (int) get_post_meta( $original_post_id, '_fpml_pair_id', true );
                        if ( $legacy ) {
                            $found_post_id = $legacy;
                        }
                    }
                }
            }

            if ( $found_post_id ) {
                // Force single post query
                $wp_query->set( 'p', (int) $found_post_id );
                $wp_query->set( 'name', '' );
                $wp_query->is_singular = true;
                $wp_query->is_single = true;
                $wp_query->is_page = false;
                $wp_query->is_archive = false;
                $wp_query->is_home = false;
                
                // Re-run the query
                $wp_query->query( $wp_query->query_vars );
            } else {
                // If no translation found, show 404
                $wp_query->set_404();
                status_header( 404 );
            }
        }
    }

    /**
     * Filter adjacent post WHERE clause to include only translations when on /en/ path.
     *
     * @since 0.9.3
     *
     * @param string $where The WHERE clause.
     * @param bool   $in_same_term Whether post should be in same taxonomy term.
     * @param array  $excluded_terms Array of excluded term IDs.
     * @param string $taxonomy Taxonomy to use if in same term.
     * @param WP_Post $post Current post object.
     *
     * @return string
     */
    public function filter_adjacent_post_where( $where, $in_same_term, $excluded_terms, $taxonomy, $post ) {
        return $this->adjacent_post_filter->filter_adjacent_post_where( $where, $in_same_term, $excluded_terms, $taxonomy, $post );
    }

    /**
     * Filter adjacent post JOIN clause (can be used for additional filtering if needed).
     *
     * @since 0.9.3
     *
     * @param string $join The JOIN clause.
     * @param bool   $in_same_term Whether post should be in same taxonomy term.
     * @param array  $excluded_terms Array of excluded term IDs.
     * @param string $taxonomy Taxonomy to use if in same term.
     * @param WP_Post $post Current post object.
     *
     * @return string
     */
    public function filter_adjacent_post_join( $join, $in_same_term, $excluded_terms, $taxonomy, $post ) {
        return $this->adjacent_post_filter->filter_adjacent_post_join( $join, $in_same_term, $excluded_terms, $taxonomy, $post );
    }

    /**
     * Redirect untranslated content with English fallback.
     *
     * @since 0.9.4
     *
     * @return void
     */
    public function redirect_untranslated_to_home() {
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        // Verifica se siamo su un path di lingua target
        $current_lang = $this->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return;
        }

        // Solo se il routing mode è 'segment'
        $container = $this->getContainer();
        $settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
        $routing_mode = $settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing_mode ) {
            return;
        }

        global $wp_query, $post;

        // Se è la homepage /en/, non fare redirect
        if ( $wp_query->is_home() || $wp_query->is_front_page() ) {
            return;
        }

        // Get current language for redirects
        $current_lang     = $this->get_current_language_from_path();
        $language_manager = fpml_get_language_manager();

        if ( ! $language_manager ) {
            return;
        }

        if ( empty( $current_lang ) ) {
            $enabled_languages = $language_manager->get_enabled_languages();
            $current_lang      = ! empty( $enabled_languages ) ? $enabled_languages[0] : '';
        }

        if ( empty( $current_lang ) ) {
            return;
        }

        $lang_info         = $language_manager->get_language_info( $current_lang );
        $lang_slug         = $lang_info ? trim( $lang_info['slug'], '/' ) : $current_lang;
        $home_url          = home_url( '/' . $lang_slug . '/' );
        $fallback_lang     = $this->get_english_fallback_language( $language_manager );
        $fallback_home_url = '' !== $fallback_lang ? $this->get_language_home_url( $language_manager, $fallback_lang ) : '';
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

        // Se è una pagina singolare, verifica se è tradotta
        if ( $wp_query->is_singular() && isset( $post ) && $post instanceof \WP_Post ) {
            $is_translation = get_post_meta( $post->ID, '_fpml_is_translation', true );
            
            // If this is an untranslated source post, try EN translation first.
            if ( ! $is_translation ) {
                $fallback_translation_url = $this->resolve_fallback_translation_url( $post, $fallback_lang );
                if ( '' !== $fallback_translation_url ) {
                    wp_safe_redirect( $fallback_translation_url, 302 );
                    exit;
                }

                if ( '' !== $fallback_home_url ) {
                    wp_safe_redirect( $fallback_home_url, 302 );
                    exit;
                }

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                    error_log( sprintf( 
                        '[FPML] Redirect pagina non tradotta a home %s: post_id=%d, slug=%s, url=%s', 
                        $lang_slug,
                        $post->ID,
                        $post->post_name,
                        $request_uri
                    ) );
                }
                
                wp_safe_redirect( $home_url, 302 );
                exit;
            }
        }

        // Se è una categoria/archivio e non ci sono post tradotti, redirect alla home
        if ( ( $wp_query->is_category() || $wp_query->is_archive() || $wp_query->is_tag() ) && ! $wp_query->have_posts() ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( sprintf( 
                    '[FPML] Redirect archivio vuoto a home %s: query_type=%s, url=%s', 
                    $lang_slug,
                    $wp_query->is_category() ? 'category' : ( $wp_query->is_tag() ? 'tag' : 'archive' ),
                    $request_uri
                ) );
            }
            
            if ( '' !== $fallback_home_url ) {
                wp_safe_redirect( $fallback_home_url, 302 );
                exit;
            }

            wp_safe_redirect( $home_url, 302 );
            exit;
        }

        // Se è un 404, prova prima il fallback EN, poi la home EN.
        // MA solo se NON siamo già sulla homepage (per evitare loop)
        if ( $wp_query->is_404() ) {
            $is_homepage_path = ( rtrim( $request_uri, '/' ) === '/' . $lang_slug || rtrim( $request_uri, '/' ) === '/' . $lang_slug . '/' );

            if ( $is_homepage_path ) {
                return;
            }

            $fallback_translation_url = $this->resolve_fallback_url_from_request_path( $request_uri, $lang_slug, $fallback_lang );
            if ( '' !== $fallback_translation_url ) {
                wp_safe_redirect( $fallback_translation_url, 302 );
                exit;
            }

            if ( '' !== $fallback_home_url ) {
                wp_safe_redirect( $fallback_home_url, 302 );
                exit;
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( sprintf( 
                    '[FPML] Redirect 404 a home %s: url=%s', 
                    $lang_slug,
                    $request_uri
                ) );
            }
            
            wp_safe_redirect( $home_url, 302 );
            exit;
        }
    }

    /**
     * Resolve the preferred English fallback language code.
     *
     * @param object $language_manager Language manager instance.
     * @return string
     */
    protected function get_english_fallback_language( $language_manager ): string {
        $enabled_languages = $language_manager->get_enabled_languages();

        if ( in_array( 'en', $enabled_languages, true ) ) {
            return 'en';
        }

        foreach ( $enabled_languages as $lang_code ) {
            $lang_info = $language_manager->get_language_info( $lang_code );
            $lang_slug = $lang_info && ! empty( $lang_info['slug'] ) ? strtolower( trim( (string) $lang_info['slug'], '/' ) ) : '';

            if ( 'en' === $lang_slug ) {
                return (string) $lang_code;
            }
        }

        return '';
    }

    /**
     * Build home URL for a specific language.
     *
     * @param object $language_manager Language manager instance.
     * @param string $lang             Language code.
     * @return string
     */
    protected function get_language_home_url( $language_manager, string $lang ): string {
        if ( '' === $lang ) {
            return '';
        }

        $lang_info = $language_manager->get_language_info( $lang );
        $lang_slug = $lang_info && ! empty( $lang_info['slug'] ) ? trim( (string) $lang_info['slug'], '/' ) : $lang;

        return (string) home_url( '/' . $lang_slug . '/' );
    }

    /**
     * Resolve translation URL for a source or translated post.
     *
     * @param \WP_Post $post          Current post.
     * @param string   $fallback_lang Fallback language code.
     * @return string
     */
    protected function resolve_fallback_translation_url( \WP_Post $post, string $fallback_lang ): string {
        if ( '' === $fallback_lang || ! function_exists( 'fpml_get_translation_id' ) ) {
            return '';
        }

        $source_post_id = (int) $post->ID;

        if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            $paired_source_id = (int) get_post_meta( $post->ID, '_fpml_pair_source_id', true );
            if ( $paired_source_id > 0 ) {
                $source_post_id = $paired_source_id;
            }
        }

        $fallback_post_id = (int) fpml_get_translation_id( $source_post_id, $fallback_lang );
        if ( $fallback_post_id <= 0 ) {
            return '';
        }

        $fallback_post = get_post( $fallback_post_id );
        if ( ! $fallback_post instanceof \WP_Post || 'publish' !== $fallback_post->post_status ) {
            return '';
        }

        $fallback_url = get_permalink( $fallback_post_id );

        return is_string( $fallback_url ) ? esc_url_raw( $fallback_url ) : '';
    }

    /**
     * Resolve fallback URL from the current request path.
     *
     * @param string $request_uri   Current request URI.
     * @param string $current_slug  Current language slug in path.
     * @param string $fallback_lang Fallback language code.
     * @return string
     */
    protected function resolve_fallback_url_from_request_path( string $request_uri, string $current_slug, string $fallback_lang ): string {
        if ( '' === $request_uri || '' === $current_slug || '' === $fallback_lang ) {
            return '';
        }

        $request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
        if ( '' === $request_path ) {
            return '';
        }

        $home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
        if ( '' !== $home_path && '/' !== $home_path ) {
            $request_path = preg_replace( '#^' . preg_quote( rtrim( $home_path, '/' ), '#' ) . '#', '', $request_path );
            $request_path = is_string( $request_path ) ? $request_path : '';
        }

        $request_path = '/' . ltrim( $request_path, '/' );
        $lang_prefix  = '/' . trim( $current_slug, '/' );

        if ( str_starts_with( $request_path, $lang_prefix . '/' ) ) {
            $relative_path = substr( $request_path, strlen( $lang_prefix . '/' ) );
        } elseif ( rtrim( $request_path, '/' ) === $lang_prefix ) {
            $relative_path = '';
        } else {
            return '';
        }

        $relative_path = trim( (string) $relative_path, '/' );
        if ( '' === $relative_path ) {
            return '';
        }

        $source_post = get_page_by_path( $relative_path, OBJECT, array( 'page', 'post' ) );

        if ( ! $source_post instanceof \WP_Post ) {
            $segments     = explode( '/', $relative_path );
            $last_segment = end( $segments );
            $slug         = is_string( $last_segment ) ? $last_segment : '';

            if ( '' === $slug ) {
                return '';
            }

            $source_post = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
        }

        if ( ! $source_post instanceof \WP_Post ) {
            return '';
        }

        return $this->resolve_fallback_translation_url( $source_post, $fallback_lang );
    }
}

