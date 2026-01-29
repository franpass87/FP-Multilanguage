<?php
/**
 * SEO orchestration: meta synchronization, hreflang, canonical and slug utilities.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\SEO\MetaKeysManager;
use FP\Multilanguage\SEO\SlugManager;
use FP\Multilanguage\SEO\CanonicalManager;
use FP\Multilanguage\SEO\HreflangManager;
use FP\Multilanguage\SEO\RobotsManager;
use FP\Multilanguage\SEO\SitemapManager;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Handle SEO related responsibilities for translated content.
 *
 * @since 0.2.0
 */
class SEO {
	use ContainerAwareTrait;
        /**
         * Singleton instance.
         *
         * @var \FPML_SEO|null
         */
        protected static $instance = null;

        /**
         * Cached settings handler.
         *
         * @var \FPML_Settings
         */
        protected $settings;

        /**
         * Cached language helper.
         *
         * @var \FPML_Language
         */
        protected $language;

        /**
         * Cached queue handler.
         *
         * @var \FPML_Queue
         */
        protected $queue;

        /**
         * Cached logger instance.
         *
         * @var \FPML_Logger
         */
        protected $logger;

        /**
         * Meta keys manager instance.
         *
         * @since 0.10.0
         *
         * @var MetaKeysManager
         */
        protected $meta_keys_manager;

        /**
         * Slug manager instance.
         *
         * @since 0.10.0
         *
         * @var SlugManager
         */
        protected $slug_manager;

        /**
         * Canonical manager instance.
         *
         * @since 0.10.0
         *
         * @var CanonicalManager
         */
        protected $canonical_manager;

        /**
         * Hreflang manager instance.
         *
         * @since 0.10.0
         *
         * @var HreflangManager
         */
        protected $hreflang_manager;

        /**
         * Robots manager instance.
         *
         * @since 0.10.0
         *
         * @var RobotsManager
         */
        protected $robots_manager;

        /**
         * Sitemap manager instance.
         *
         * @since 0.10.0
         *
         * @var SitemapManager
         */
        protected $sitemap_manager;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return \FPML_SEO
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
                $container = $this->getContainer();
                $this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
                $this->language = ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() );
                $this->queue = $container && $container->has( 'queue' ) ? $container->get( 'queue' ) : fpml_get_queue();
                $this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : \FPML_fpml_get_logger();

                // Initialize modules
                $this->meta_keys_manager = new MetaKeysManager();
                $this->slug_manager      = new SlugManager( $this->settings, $this->logger );
                $this->canonical_manager = new CanonicalManager( $this->language );
                $this->hreflang_manager  = new HreflangManager( $this->language );
                $this->robots_manager    = new RobotsManager( $this->settings, $this->language );
                $this->sitemap_manager   = new SitemapManager( $this->settings, $this->language );

                add_filter( '\FPML_meta_whitelist', array( $this->meta_keys_manager, 'register_seo_meta_keys' ) );
                add_action( '\FPML_post_jobs_enqueued', array( $this, 'maybe_enqueue_slug_job' ), 20, 3 );
                add_action( 'wp_head', array( $this, 'render_head_tags' ), 5 );
                add_action( 'template_redirect', array( $this->sitemap_manager, 'maybe_render_sitemap' ), 0 );
                add_action( 'template_redirect', array( $this->slug_manager, 'handle_legacy_slug_redirects' ), 1 );

                add_filter( 'wpseo_canonical', array( $this->canonical_manager, 'filter_canonical_url' ) );
                add_filter( 'rank_math/frontend/canonical', array( $this->canonical_manager, 'filter_canonical_url' ) );
                add_filter( 'aioseo_canonical_url', array( $this->canonical_manager, 'filter_canonical_url' ) );

                add_filter( 'wpseo_robots', array( $this->robots_manager, 'filter_robots_directive' ) );
                add_filter( 'rank_math/frontend/robots', array( $this->robots_manager, 'filter_rankmath_robots' ) );

                add_filter( 'wpseo_sitemap_index', array( $this->sitemap_manager, 'inject_wpseo_sitemap_entry' ) );
                add_filter( 'rank_math/sitemap/index', array( $this->sitemap_manager, 'inject_rankmath_sitemap_entry' ) );
                add_filter( 'aioseo_sitemap_indexes', array( $this->sitemap_manager, 'inject_aioseo_sitemap_entry' ) );

                add_action( 'save_post', array( $this->sitemap_manager, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'delete_post', array( $this->sitemap_manager, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'created_term', array( $this->sitemap_manager, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'edited_term', array( $this->sitemap_manager, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'delete_term', array( $this->sitemap_manager, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( '\FPML_post_jobs_enqueued', array( $this->sitemap_manager, 'invalidate_sitemap_cache' ), 90, 0 );
        }

        /**
         * Ensure key SEO meta fields are always part of the translation whitelist.
         *
         * @since 0.2.0
         *
         * @param array $keys Current whitelist.
         * @return array
         */
        public function register_seo_meta_keys( $keys ) {
                return $this->meta_keys_manager->register_seo_meta_keys( $keys );
        }

        /**
         * Enqueue slug translation jobs when requested.
         *
         * @since 0.2.0
         *
         * @param WP_Post $source_post Italian post.
         * @param WP_Post $target_post English post.
         * @param bool    $update      Whether this is an update operation.
         *
         * @return void
         */
        public function maybe_enqueue_slug_job( $source_post, $target_post, $update ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( ! $this->settings->get( 'translate_slugs', false ) ) {
                        return;
                }

                if ( ! ( $source_post instanceof WP_Post ) || ! ( $target_post instanceof WP_Post ) ) {
                        return;
                }

                if ( 'attachment' === $source_post->post_type ) {
                        return;
                }

                $slug = (string) $source_post->post_name;

                if ( '' === $slug ) {
                        $slug = sanitize_title( $source_post->post_title );
                }

                if ( '' === $slug ) {
                        return;
                }

                $hash = md5( $slug );

                $this->queue->enqueue( 'post', $source_post->ID, 'slug', $hash );
                update_post_meta( $target_post->ID, '_fpml_status_slug', 'needs_update' );
        }

        /**
         * Render canonical, hreflang and robots hints into the head.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function render_head_tags() {
                if ( is_admin() ) {
                        return;
                }

                $canonical = $this->canonical_manager->get_canonical_url();

                if ( $canonical && ! $this->is_seo_plugin_active() ) {
                        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                $hreflangs = $this->hreflang_manager->get_hreflang_links();

                foreach ( $hreflangs as $link ) {
                        echo '<link rel="alternate" hreflang="' . esc_attr( $link['lang'] ) . '" href="' . esc_url( $link['url'] ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                if ( $this->robots_manager->should_noindex() && ! $this->is_seo_plugin_active() ) {
                        echo '<meta name="robots" content="noindex,nofollow" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
        }

        /**
         * Filter canonical URL emitted by compatible SEO plugins.
         *
         * @since 0.2.0
         *
         * @param string $canonical Current canonical URL.
         * @return string
         */
        public function filter_canonical_url( $canonical ) {
                return $this->canonical_manager->filter_canonical_url( $canonical );
        }

        /**
         * Override robots directives when EN pages are marked as noindex.
         *
         * @since 0.2.0
         *
         * @param string $directives Robots directives string.
         * @return string
         */
        public function filter_robots_directive( $directives ) {
                return $this->robots_manager->filter_robots_directive( $directives );
        }

        /**
         * Adjust Rank Math robots directives.
         *
         * @since 0.2.0
         *
         * @param mixed $directives Current directives (array|string).
         * @return mixed
         */
        public function filter_rankmath_robots( $directives ) {
                return $this->robots_manager->filter_rankmath_robots( $directives );
        }

        /**
         * Handle slug translation saving logic.
         *
         * @since 0.2.0
         *
         * @param \WP_Post $post      English post object.
         * @param string   $new_value Raw translated phrase.
         * @return void
         */
        public function handle_slug_translation( $post, $new_value ) {
                $this->slug_manager->handle_slug_translation( $post, $new_value );
        }

        /**
         * Redirect legacy English slugs when requested.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function handle_legacy_slug_redirects() {
                $this->slug_manager->handle_legacy_slug_redirects();
        }

        /**
         * Determine whether EN pages should be noindex.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        protected function should_noindex() {
                return $this->robots_manager->should_noindex();
        }

        /**
         * Check if a major SEO plugin is active to avoid duplicate tags.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        protected function is_seo_plugin_active() {
                return ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || class_exists( '\\AIOSEO\\Plugin\\Common\\Main' ) );
        }

        /**
         * Maybe render the English sitemap when requested.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function maybe_render_sitemap() {
                $this->sitemap_manager->maybe_render_sitemap();
        }

        /**
         * Inject English sitemap entry into Yoast SEO sitemap index.
         *
         * @since 0.2.0
         *
         * @param string $content Sitemap XML.
         * @return string
         */
        public function inject_wpseo_sitemap_entry( $content ) {
                return $this->sitemap_manager->inject_wpseo_sitemap_entry( $content );
        }

        /**
         * Inject English sitemap entry into Rank Math sitemap index.
         *
         * @since 0.2.0
         *
         * @param array $entries Sitemap entries.
         * @return array
         */
        public function inject_rankmath_sitemap_entry( $entries ) {
                return $this->sitemap_manager->inject_rankmath_sitemap_entry( $entries );
        }

        /**
         * Inject English sitemap entry into AIOSEO sitemap index.
         *
         * @since 0.2.0
         *
         * @param array $indexes Sitemap index entries.
         * @return array
         */
        public function inject_aioseo_sitemap_entry( $indexes ) {
                return $this->sitemap_manager->inject_aioseo_sitemap_entry( $indexes );
        }

        /**
         * Clear cached sitemap payload when content changes.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function invalidate_sitemap_cache() {
                $this->sitemap_manager->invalidate_sitemap_cache();
        }
}

