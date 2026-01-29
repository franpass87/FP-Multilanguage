<?php
/**
 * SEO Meta Keys Manager - Handles registration of SEO meta keys for translation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages SEO meta keys registration for translation whitelist.
 *
 * @since 0.10.0
 */
class MetaKeysManager {
    /**
     * Ensure key SEO meta fields are always part of the translation whitelist.
     *
     * @since 0.10.0
     *
     * @param array $keys Current whitelist.
     * @return array
     */
    public function register_seo_meta_keys( $keys ) {
        $keys = is_array( $keys ) ? $keys : array();

        $defaults = array(
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
            '_yoast_wpseo_twitter-title',
            '_yoast_wpseo_twitter-description',
            '_yoast_wpseo_canonical',
            'rank_math_title',
            'rank_math_description',
            'rank_math_facebook_title',
            'rank_math_facebook_description',
            'rank_math_twitter_title',
            'rank_math_twitter_description',
            'rank_math_canonical_url',
            '_aioseo_title',
            '_aioseo_description',
            '_aioseo_og_title',
            '_aioseo_og_description',
            '_aioseo_twitter_title',
            '_aioseo_twitter_description',
            '_aioseo_canonical_url',
        );

        $merged = array_merge( $keys, $defaults );
        $merged = array_map( 'sanitize_key', $merged );

        return array_values( array_unique( array_filter( $merged ) ) );
    }
}
















