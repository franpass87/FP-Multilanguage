<?php
/**
 * SEO Canonical Manager - Handles canonical URL generation and filtering.
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
 * Manages canonical URL generation and filtering for SEO plugins.
 *
 * @since 0.10.0
 */
class CanonicalManager {
    /**
     * Language helper instance.
     *
     * @var \FPML_Language
     */
    protected $language;

    /**
     * Constructor.
     *
     * @param \FPML_Language $language Language helper instance.
     */
    public function __construct( $language ) {
        $this->language = $language;
    }

    /**
     * Filter canonical URL emitted by compatible SEO plugins.
     *
     * @since 0.10.0
     *
     * @param string $canonical Current canonical URL.
     * @return string
     */
    public function filter_canonical_url( $canonical ) {
        $custom = $this->get_canonical_url();

        if ( $custom ) {
            return $custom;
        }

        return $canonical;
    }

    /**
     * Compute canonical URL for the current request.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_canonical_url() {
        if ( is_front_page() || is_home() ) {
            if ( \FPML_Language::TARGET === $this->language->get_current_language() ) {
                return $this->language->get_url_for_language( \FPML_Language::TARGET );
            }

            return home_url( '/' );
        }

        if ( is_singular() ) {
            $object = get_queried_object();

            if ( $object instanceof \WP_Post ) {
                // Use get_permalink which will be filtered by PostPermalinkFilter
                // to include the /en/ prefix when on a target language path
                return get_permalink( $object );
            }
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();

            if ( $term instanceof \WP_Term ) {
                $link = get_term_link( $term );

                if ( ! is_wp_error( $link ) ) {
                    return $link;
                }
            }
        }

        return '';
    }
}
















