<?php
/**
 * SEO Robots Manager - Handles robots directives.
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
 * Manages robots directives for SEO.
 *
 * @since 0.10.0
 */
class RobotsManager {
    /**
     * Settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Language helper instance.
     *
     * @var \FPML_Language
     */
    protected $language;

    /**
     * Constructor.
     *
     * @param \FPML_Settings $settings Settings instance.
     * @param \FPML_Language $language Language helper instance.
     */
    public function __construct( $settings, $language ) {
        $this->settings = $settings;
        $this->language = $language;
    }

    /**
     * Override robots directives when EN pages are marked as noindex.
     *
     * @since 0.10.0
     *
     * @param string $directives Robots directives string.
     * @return string
     */
    public function filter_robots_directive( $directives ) {
        if ( ! $this->should_noindex() ) {
            return $directives;
        }

        return 'noindex,nofollow';
    }

    /**
     * Adjust Rank Math robots directives.
     *
     * @since 0.10.0
     *
     * @param mixed $directives Current directives (array|string).
     * @return mixed
     */
    public function filter_rankmath_robots( $directives ) {
        if ( ! $this->should_noindex() ) {
            return $directives;
        }

        if ( is_array( $directives ) ) {
            return array( 'noindex', 'nofollow' );
        }

        return 'noindex,nofollow';
    }

    /**
     * Determine whether target-language pages should be noindex.
     *
     * @since 0.10.0
     *
     * @return bool
     */
    public function should_noindex() {
        $current_lang = $this->language->get_current_language();
        $is_target    = function_exists( 'fpml_is_target_language' ) ? fpml_is_target_language( $current_lang ) : false;
        $enabled = $this->settings->get( 'noindex_translations', false );
        // Backward compat: fall back to legacy keys if the canonical key is missing.
        if ( false === $enabled ) {
            $enabled = $this->settings->get( 'noindex_en', false );
        }
        return (bool) ( $enabled && $is_target );
    }
}
















