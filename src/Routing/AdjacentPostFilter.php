<?php
/**
 * Adjacent post filter - Handles filtering of next/previous post links.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Frontend\Routing;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filters adjacent post (next/previous) queries by language.
 *
 * @since 0.10.0
 */
class AdjacentPostFilter {
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
     * Filter adjacent post WHERE clause.
     *
     * @since 0.9.3
     *
     * @param string   $where WHERE clause.
     * @param bool     $in_same_term Whether post should be in same taxonomy term.
     * @param array    $excluded_terms Array of excluded term IDs.
     * @param string   $taxonomy Taxonomy to use if in same term.
     * @param WP_Post  $post Current post object.
     *
     * @return string
     */
    public function filter_adjacent_post_where( $where, $in_same_term, $excluded_terms, $taxonomy, $post ) {
        $current_lang = $this->post_resolver->get_current_language_from_path();
        $is_target_language_path = ! empty( $current_lang );
        
        if ( ! $is_target_language_path ) {
            global $wpdb;
            if ( false === strpos( $where, '_fpml_is_translation' ) ) {
                $where .= " AND p.ID NOT IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_fpml_is_translation'
                    AND meta_value = '1'
                )";
            }
            return $where;
        }
        
        global $wpdb;
        if ( false === strpos( $where, '_fpml_is_translation' ) ) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = '_fpml_is_translation'
                AND pm.meta_value = '1'
            )";
        }
        
        return $where;
    }

    /**
     * Filter adjacent post JOIN clause.
     *
     * @since 0.9.3
     *
     * @param string   $join The JOIN clause.
     * @param bool     $in_same_term Whether post should be in same taxonomy term.
     * @param array    $excluded_terms Array of excluded term IDs.
     * @param string   $taxonomy Taxonomy to use if in same term.
     * @param WP_Post  $post Current post object.
     *
     * @return string
     */
    public function filter_adjacent_post_join( $join, $in_same_term, $excluded_terms, $taxonomy, $post ) {
        // Currently no JOIN modifications needed
        return $join;
    }
}
















