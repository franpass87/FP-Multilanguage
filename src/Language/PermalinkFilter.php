<?php
/**
 * Permalink filter - Handles permalink filtering for posts, pages, and terms.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Language;

use WP_Post;
use WP_Term;
use FP\Multilanguage\Language\Permalink\PostPermalinkFilter;
use FP\Multilanguage\Language\Permalink\TermPermalinkFilter;
use FP\Multilanguage\Language\Permalink\SamplePermalinkHtmlFilter;
use FP\Multilanguage\Language\Permalink\FilterHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filters permalinks for translated content.
 *
 * @since 0.10.0
 */
class PermalinkFilter {
    /**
     * Cookie key for language preference.
     */
    const COOKIE_NAME = '\FPML_lang_pref';

    /**
     * Source language slug.
     */
    const SOURCE = 'it';

    /**
     * Cached settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Language resolver instance.
     *
     * @var LanguageResolver
     */
    protected $resolver;

    /**
     * Post permalink filter instance.
     *
     * @var PostPermalinkFilter
     */
    protected $post_filter;

    /**
     * Term permalink filter instance.
     *
     * @var TermPermalinkFilter
     */
    protected $term_filter;

    /**
     * Sample permalink HTML filter instance.
     *
     * @var SamplePermalinkHtmlFilter
     */
    protected $sample_html_filter;

    /**
     * Filter helper instance.
     *
     * @var FilterHelper
     */
    protected $filter_helper;

    /**
     * Constructor.
     *
     * @param \FPML_Settings    $settings Settings instance.
     * @param LanguageResolver $resolver Language resolver instance.
     */
    public function __construct( $settings, LanguageResolver $resolver ) {
        $this->settings = $settings;
        $this->resolver = $resolver;
        
        // Initialize helper and filters
        $this->filter_helper = new FilterHelper();
        $this->post_filter = new PostPermalinkFilter( $settings, $resolver, $this->filter_helper );
        $this->term_filter = new TermPermalinkFilter( $settings, $resolver, $this->filter_helper );
        $this->sample_html_filter = new SamplePermalinkHtmlFilter( $settings, $resolver, $this->post_filter );
    }

    /**
     * Filter permalinks for translated pages to use /en/ prefix.
     *
     * @since 0.4.1
     *
     * @param string  $permalink The post's permalink.
     * @param WP_Post $post      The post object.
     * @param bool    $force     Force filter even in admin.
     *
     * @return string
     */
    public function filter_translation_permalink( $permalink, $post, $force = false ) {
        return $this->post_filter->filter_translation_permalink( $permalink, $post, $force );
    }

    /**
     * Filter permalinks for translated terms to use /en/ prefix.
     *
     * @since 0.9.3
     *
     * @param string  $permalink The term's permalink.
     * @param WP_Term $term      The term object.
     *
     * @return string
     */
    public function filter_term_permalink( $permalink, $term ) {
        return $this->term_filter->filter_term_permalink( $permalink, $term );
    }

    /**
     * Filter the sample permalink shown in admin edit screen.
     *
     * @since 0.9.5
     *
     * @param array  $permalink Array with 'permalink' and 'slug' keys.
     * @param int    $post_id   Post ID.
     * @param string $title     Post title.
     * @param string $name      Post name (slug).
     * @param object $post      Post object.
     *
     * @return array
     */
    public function filter_sample_permalink( $permalink, $post_id, $title, $name, $post ) {
        return $this->post_filter->filter_sample_permalink( $permalink, $post_id, $title, $name, $post );
    }

    /**
     * Filter the sample permalink HTML shown in admin edit screen.
     *
     * @since 0.9.5
     *
     * @param string  $html      The sample permalink HTML.
     * @param int     $post_id   Post ID.
     * @param string  $new_title New sample permalink title.
     * @param string  $new_slug  New sample permalink slug.
     * @param WP_Post $post      Post object.
     *
     * @return string
     */
    public function filter_sample_permalink_html( $html, $post_id, $new_title, $new_slug, $post ) {
        return $this->sample_html_filter->filter_sample_permalink_html( $html, $post_id, $new_title, $new_slug, $post );
    }

    /**
     * Remove permalink filters temporarily.
     *
     * @since 0.10.0
     * @return void
     */
    protected function remove_permalink_filters() {
        $this->filter_helper->remove_permalink_filters();
    }

    /**
     * Restore permalink filters.
     *
     * @since 0.10.0
     * @return void
     */
    protected function restore_permalink_filters() {
        $this->filter_helper->restore_permalink_filters();
    }

    /**
     * Remove URL filters temporarily.
     *
     * @since 0.10.0
     * @return void
     */
    protected function remove_url_filters() {
        $this->filter_helper->remove_url_filters();
    }

    /**
     * Restore URL filters.
     *
     * @since 0.10.0
     * @return void
     */
    protected function restore_url_filters() {
        $this->filter_helper->restore_url_filters();
    }
}
