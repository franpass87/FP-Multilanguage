<?php
/**
 * Post resolver - Resolves posts from slugs/paths for language routing.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Routing;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resolves posts from slugs and paths for language routing.
 *
 * @since 0.10.0
 */
class PostResolver {
    /**
     * Get current language from request URI.
     *
     * @since 0.10.0
     *
     * @return string|false Language code or false if not a target language path.
     */
    public function get_current_language_from_path() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( empty( $request_uri ) ) {
            return false;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();

        foreach ( $enabled_languages as $lang ) {
            if ( ! isset( $available_languages[ $lang ] ) ) {
                continue;
            }

            $lang_info = $available_languages[ $lang ];
            if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
                continue;
            }
            $lang_slug = trim( $lang_info['slug'], '/' );
            
            if ( preg_match( '#^/' . preg_quote( $lang_slug, '#' ) . '(/|$)#', $request_uri ) ) {
                return $lang;
            }
        }

        return false;
    }

    /**
     * Resolve post from slug for language routing.
     *
     * @since 0.10.0
     *
     * @param string $slug Post slug.
     * @param string $lang Language code.
     * @return WP_Post|null Post object or null if not found.
     */
    public function resolve_post_from_slug( $slug, $lang ) {
        global $wpdb;

        // Try to find translation with exact slug
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_name = %s 
                AND post_type IN ('post','page') 
                AND post_status = 'publish' 
                LIMIT 1",
                $slug
            )
        );

        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post instanceof WP_Post && get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
                $target_lang = get_post_meta( $post_id, '_fpml_target_language', true );
                if ( $target_lang === $lang ) {
                    return $post;
                }
            }
        }

        // Try with en- prefix
        $en_slug = 'en-' . $slug;
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_name = %s 
                AND post_type IN ('post','page') 
                AND post_status = 'publish' 
                LIMIT 1",
                $en_slug
            )
        );

        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post instanceof WP_Post && get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
                $target_lang = get_post_meta( $post_id, '_fpml_target_language', true );
                if ( $target_lang === $lang ) {
                    return $post;
                }
            }
        }

        return null;
    }

    /**
     * Map path to query vars.
     *
     * @since 0.10.0
     *
     * @param string $path Path to map.
     * @param string $current_lang Current language code.
     * @return array Query vars.
     */
    public function map_path_to_query( $path, $current_lang = null ) {
        if ( empty( $path ) ) {
            return array();
        }

        $path = trim( $path, '/' );
        $segments = explode( '/', $path );

        if ( empty( $segments ) ) {
            return array();
        }

        $slug = array_pop( $segments );

        // Try to resolve as post/page
        if ( $current_lang ) {
            $post = $this->resolve_post_from_slug( $slug, $current_lang );
            if ( $post ) {
                return array( 'p' => $post->ID );
            }
        }

        // Try as page by path
        $page = get_page_by_path( $path );
        if ( $page && get_post_meta( $page->ID, '_fpml_is_translation', true ) ) {
            return array( 'page_id' => $page->ID );
        }

        // Try as post by name
        $post = get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) );
        if ( $post && get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            return array( 'p' => $post->ID );
        }

        return array( 'name' => $slug );
    }
}
















