<?php
/**
 * Query filter - Handles filtering of WordPress queries by language.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Frontend\Routing;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filters WordPress queries to show only content in the correct language.
 *
 * @since 0.10.0
 */
class QueryFilter {
    /**
     * Post resolver instance.
     *
     * @var PostResolver
     */
    protected $post_resolver;

    /**
     * Constructor.
     *
     * @param PostResolver $post_resolver Post resolver instance.
     */
    public function __construct( PostResolver $post_resolver ) {
        $this->post_resolver = $post_resolver;
    }

    /**
     * Filter posts by language in pre_get_posts.
     *
     * @since 0.2.0
     *
     * @param WP_Query $query Query object.
     * @return void
     */
    public function filter_posts_by_language( $query ) {
        if ( is_admin() ) {
            return;
        }
        
        $current_lang = $this->post_resolver->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return;
        }
        
        if ( ! $query->is_main_query() && $query->is_singular() ) {
            return;
        }
        
        if ( ! $query->is_main_query() ) {
            $category_in = $query->get( 'category__in' );
            $post_type = $query->get( 'post_type' );
            $is_related_query = ! empty( $category_in ) || ( $post_type === 'post' && ! $query->is_singular() );
            
            if ( $is_related_query ) {
                $meta_query = $query->get( 'meta_query' );
                if ( ! is_array( $meta_query ) ) {
                    $meta_query = array();
                }
                
                $has_translation_meta = false;
                foreach ( $meta_query as $meta_condition ) {
                    if ( isset( $meta_condition['key'] ) && '_fpml_is_translation' === $meta_condition['key'] ) {
                        $has_translation_meta = true;
                        break;
                    }
                }
                
                if ( ! $has_translation_meta ) {
                    $meta_query[] = array(
                        'key'     => '_fpml_is_translation',
                        'value'   => '1',
                        'compare' => '=',
                    );
                    
                    $query->set( 'meta_query', $meta_query );
                }
            }
        }

        $lang = get_query_var( '\FPML_lang' );
        if ( empty( $lang ) ) {
            $lang = get_query_var( 'fpml_lang' );
        }

        if ( empty( $lang ) ) {
            $lang = $current_lang;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $is_target_lang = ! empty( $lang ) && in_array( $lang, $enabled_languages, true );

        if ( $is_target_lang ) {
            $meta_query = $query->get( 'meta_query' );
            if ( ! is_array( $meta_query ) ) {
                $meta_query = array();
            }

            $meta_query = array_filter( $meta_query, function( $mq ) {
                return ! isset( $mq['key'] ) || '_fpml_is_translation' !== $mq['key'];
            } );

            $meta_query[] = array(
                'key'   => '_fpml_is_translation',
                'value' => '1',
            );

            $query->set( 'meta_query', $meta_query );
            $query->set( 'title_filter', $lang );
        } else {
            if ( ! $query->is_singular() && ! $query->get( 'p' ) && ! $query->get( 'page_id' ) && ! $query->get( 'name' ) && ! $query->get( 'pagename' ) ) {
                $meta_query = $query->get( 'meta_query' );
                if ( ! is_array( $meta_query ) ) {
                    $meta_query = array();
                }

                $meta_query = array_filter( $meta_query, function( $mq ) {
                    return ! isset( $mq['key'] ) || '_fpml_is_translation' !== $mq['key'];
                } );

                $meta_query[] = array(
                    'key'     => '_fpml_is_translation',
                    'compare' => 'NOT EXISTS',
                );

                $query->set( 'meta_query', $meta_query );
            }
        }
    }

    /**
     * Filter posts SQL request before execution.
     *
     * @since 0.9.3
     *
     * @param string    $request The SQL query string.
     * @param WP_Query  $query   The WP_Query instance.
     * @return string Modified SQL query.
     */
    public function filter_posts_request_by_language( $request, $query ) {
        if ( is_admin() ) {
            return $request;
        }

        $current_lang = $this->post_resolver->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return $request;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $is_target_lang = in_array( $current_lang, $enabled_languages, true );

        if ( $is_target_lang ) {
            // Add JOIN and WHERE to filter translations
            if ( false === strpos( $request, 'INNER JOIN' ) && false === strpos( $request, 'LEFT JOIN' ) ) {
                global $wpdb;
                $request = str_replace(
                    "FROM {$wpdb->posts}",
                    "FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} AS fpml_meta ON ({$wpdb->posts}.ID = fpml_meta.post_id AND fpml_meta.meta_key = '_fpml_is_translation' AND fpml_meta.meta_value = '1')",
                    $request
                );
            }
        } else {
            // Exclude translations
            global $wpdb;
            if ( false === strpos( $request, 'NOT IN' ) && false === strpos( $request, '_fpml_is_translation' ) ) {
                $request = str_replace(
                    "WHERE 1=1",
                    "WHERE 1=1 AND {$wpdb->posts}.ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fpml_is_translation' AND meta_value = '1')",
                    $request
                );
            }
        }

        return $request;
    }

    /**
     * Filter posts WHERE clause.
     *
     * @since 0.9.3
     *
     * @param string   $where WHERE clause.
     * @param WP_Query $query Query object.
     * @return string
     */
    public function filter_posts_where_by_language( $where, $query ) {
        if ( is_admin() ) {
            return $where;
        }

        $current_lang = $this->post_resolver->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return $where;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $is_target_lang = in_array( $current_lang, $enabled_languages, true );

        global $wpdb;

        if ( $is_target_lang ) {
            if ( false === strpos( $where, '_fpml_is_translation' ) ) {
                $where .= " AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm
                    WHERE pm.post_id = {$wpdb->posts}.ID
                    AND pm.meta_key = '_fpml_is_translation'
                    AND pm.meta_value = '1'
                )";
            }
        } else {
            if ( false === strpos( $where, '_fpml_is_translation' ) ) {
                $where .= " AND {$wpdb->posts}.ID NOT IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_fpml_is_translation'
                    AND meta_value = '1'
                )";
            }
        }

        return $where;
    }

    /**
     * Filter posts results.
     *
     * @since 0.9.3
     *
     * @param array    $posts Posts array.
     * @param WP_Query $query Query object.
     * @return array
     */
    public function filter_posts_results_by_language( $posts, $query ) {
        if ( is_admin() || empty( $posts ) ) {
            return $posts;
        }

        $current_lang = $this->post_resolver->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return $posts;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $is_target_lang = in_array( $current_lang, $enabled_languages, true );

        if ( $is_target_lang ) {
            return array_filter( $posts, function( $post ) {
                return get_post_meta( $post->ID, '_fpml_is_translation', true );
            } );
        } else {
            return array_filter( $posts, function( $post ) {
                return ! get_post_meta( $post->ID, '_fpml_is_translation', true );
            } );
        }
    }

    /**
     * Filter the_posts.
     *
     * @since 0.9.3
     *
     * @param array    $posts Posts array.
     * @param WP_Query $query Query object.
     * @return array
     */
    public function filter_the_posts_by_language( $posts, $query ) {
        return $this->filter_posts_results_by_language( $posts, $query );
    }

    /**
     * Filter posts clauses.
     *
     * @since 0.9.3
     *
     * @param array    $clauses SQL clauses.
     * @param WP_Query $query   Query object.
     * @return array
     */
    public function filter_posts_clauses_by_language( $clauses, $query ) {
        if ( is_admin() ) {
            return $clauses;
        }

        $current_lang = $this->post_resolver->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return $clauses;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $is_target_lang = in_array( $current_lang, $enabled_languages, true );

        global $wpdb;

        if ( $is_target_lang ) {
            if ( false === strpos( $clauses['join'], '_fpml_is_translation' ) ) {
                $clauses['join'] .= " INNER JOIN {$wpdb->postmeta} AS fpml_meta ON ({$wpdb->posts}.ID = fpml_meta.post_id AND fpml_meta.meta_key = '_fpml_is_translation' AND fpml_meta.meta_value = '1')";
            }
        } else {
            if ( false === strpos( $clauses['where'], '_fpml_is_translation' ) ) {
                $clauses['where'] .= " AND {$wpdb->posts}.ID NOT IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_fpml_is_translation'
                    AND meta_value = '1'
                )";
            }
        }

        return $clauses;
    }
}
















