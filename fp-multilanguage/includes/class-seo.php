<?php
/**
 * SEO orchestration: meta synchronization, hreflang, canonical and slug utilities.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Handle SEO related responsibilities for translated content.
 *
 * @since 0.2.0
 */
class FPML_SEO {
        /**
         * Singleton instance.
         *
         * @var FPML_SEO|null
         */
        protected static $instance = null;

        /**
         * Cached settings handler.
         *
         * @var FPML_Settings
         */
        protected $settings;

        /**
         * Cached language helper.
         *
         * @var FPML_Language
         */
        protected $language;

        /**
         * Cached queue handler.
         *
         * @var FPML_Queue
         */
        protected $queue;

        /**
         * Cached logger instance.
         *
         * @var FPML_Logger
         */
        protected $logger;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_SEO
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
                $this->settings = FPML_Settings::instance();
                $this->language = FPML_Language::instance();
                $this->queue    = FPML_Queue::instance();
                $this->logger   = FPML_Logger::instance();

                add_filter( 'fpml_meta_whitelist', array( $this, 'register_seo_meta_keys' ) );
                add_action( 'fpml_post_jobs_enqueued', array( $this, 'maybe_enqueue_slug_job' ), 20, 3 );
                add_action( 'wp_head', array( $this, 'render_head_tags' ), 5 );
                add_action( 'template_redirect', array( $this, 'maybe_render_sitemap' ), 0 );
                add_action( 'template_redirect', array( $this, 'handle_legacy_slug_redirects' ), 1 );

                add_filter( 'wpseo_canonical', array( $this, 'filter_canonical_url' ) );
                add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_canonical_url' ) );
                add_filter( 'aioseo_canonical_url', array( $this, 'filter_canonical_url' ) );

                add_filter( 'wpseo_robots', array( $this, 'filter_robots_directive' ) );
                add_filter( 'rank_math/frontend/robots', array( $this, 'filter_rankmath_robots' ) );

                add_filter( 'wpseo_sitemap_index', array( $this, 'inject_wpseo_sitemap_entry' ) );
                add_filter( 'rank_math/sitemap/index', array( $this, 'inject_rankmath_sitemap_entry' ) );
                add_filter( 'aioseo_sitemap_indexes', array( $this, 'inject_aioseo_sitemap_entry' ) );

                add_action( 'save_post', array( $this, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'delete_post', array( $this, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'created_term', array( $this, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'edited_term', array( $this, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'delete_term', array( $this, 'invalidate_sitemap_cache' ), 20, 0 );
                add_action( 'fpml_post_jobs_enqueued', array( $this, 'invalidate_sitemap_cache' ), 90, 0 );
        }

        /**
         * Ensure key SEO meta fields are always part of the translation whitelist.
         *
         * @since 0.2.0
         *
         * @param array $keys Current whitelist.
         *
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

                $canonical = $this->get_canonical_url();

                if ( $canonical && ! $this->is_seo_plugin_active() ) {
                        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                $hreflangs = $this->get_hreflang_links();

                foreach ( $hreflangs as $link ) {
                        echo '<link rel="alternate" hreflang="' . esc_attr( $link['lang'] ) . '" href="' . esc_url( $link['url'] ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                if ( $this->should_noindex() && ! $this->is_seo_plugin_active() ) {
                        echo '<meta name="robots" content="noindex,nofollow" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
        }

        /**
         * Filter canonical URL emitted by compatible SEO plugins.
         *
         * @since 0.2.0
         *
         * @param string $canonical Current canonical URL.
         *
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
         * Override robots directives when EN pages are marked as noindex.
         *
         * @since 0.2.0
         *
         * @param string $directives Robots directives string.
         *
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
         * @since 0.2.0
         *
         * @param mixed $directives Current directives (array|string).
         *
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
         * Handle slug translation saving logic.
         *
         * @since 0.2.0
         *
         * @param WP_Post $post        English post object.
         * @param string  $new_value   Raw translated phrase.
         *
         * @return void
         */
        public function handle_slug_translation( $post, $new_value ) {
                if ( ! ( $post instanceof WP_Post ) ) {
                        return;
                }

                $translated = sanitize_text_field( (string) $new_value );

                if ( '' === $translated ) {
                        return;
                }

                $slug_candidate = sanitize_title( $translated );

                if ( '' === $slug_candidate ) {
                        return;
                }

                $old_slug = (string) $post->post_name;

                if ( $old_slug === $slug_candidate ) {
                        update_post_meta( $post->ID, '_fpml_status_slug', 'automatic' );
                        return;
                }

                $result = wp_update_post(
                        array(
                                'ID'        => $post->ID,
                                'post_name' => $slug_candidate,
                        ),
                        true
                );

                if ( is_wp_error( $result ) ) {
                        $this->logger->log(
                                'error',
                                sprintf( 'Impossibile aggiornare lo slug inglese per il post #%d: %s', $post->ID, $result->get_error_message() ),
                                array(
                                        'post_id' => $post->ID,
                                        'context' => 'slug',
                                )
                        );

                        return;
                }

                update_post_meta( $post->ID, '_fpml_status_slug', 'automatic' );

                if ( $this->settings->get( 'slug_redirect', false ) && '' !== $old_slug ) {
                        $this->register_slug_redirect( $post->ID, $old_slug, $slug_candidate );
                }
        }

        /**
         * Persist redirect mapping for legacy English slugs.
         *
         * @since 0.2.0
         *
         * @param int    $post_id   Post ID.
         * @param string $old_slug  Previous slug.
         * @param string $new_slug  New slug.
         *
         * @return void
         */
        protected function register_slug_redirect( $post_id, $old_slug, $new_slug ) {
                $post_id  = absint( $post_id );
                $old_slug = sanitize_title( $old_slug );
                $new_slug = sanitize_title( $new_slug );

                if ( $post_id <= 0 || '' === $old_slug || '' === $new_slug || $old_slug === $new_slug ) {
                        return;
                }

                $redirects = get_option( 'fpml_slug_redirects', array() );

                if ( ! is_array( $redirects ) ) {
                        $redirects = array();
                }

                $redirects = array_values( array_filter( $redirects, function( $entry ) use ( $post_id, $old_slug ) {
                        if ( ! is_array( $entry ) || ! isset( $entry['post_id'], $entry['old_slug'] ) ) {
                                return false;
                        }

                        if ( (int) $entry['post_id'] === $post_id && sanitize_title( $entry['old_slug'] ) === $old_slug ) {
                                return false;
                        }

                        return true;
                } ) );

                $redirects[] = array(
                        'post_id'   => $post_id,
                        'old_slug'  => $old_slug,
                        'new_slug'  => $new_slug,
                        'lang'      => 'en',
                        'timestamp' => time(),
                );

                if ( count( $redirects ) > 50 ) {
                        $redirects = array_slice( $redirects, -50 );
                }

                update_option( 'fpml_slug_redirects', $redirects, false );

                $this->logger->log(
                        'info',
                        sprintf( 'Registrato redirect 301 da %1$s a %2$s per il post #%3$d.', $old_slug, $new_slug, $post_id ),
                        array(
                                'post_id' => $post_id,
                                'context' => 'slug_redirect',
                        )
                );
        }

        /**
         * Redirect legacy English slugs when requested.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function handle_legacy_slug_redirects() {
                if ( is_admin() || ! is_404() || ! $this->settings->get( 'slug_redirect', false ) ) {
                        return;
                }

                $slug = $this->get_request_slug();

                if ( '' === $slug ) {
                        return;
                }

                $redirects = get_option( 'fpml_slug_redirects', array() );

                if ( empty( $redirects ) || ! is_array( $redirects ) ) {
                        return;
                }

                foreach ( $redirects as $entry ) {
                        if ( ! is_array( $entry ) || empty( $entry['old_slug'] ) || $entry['old_slug'] !== $slug ) {
                                continue;
                        }

                        if ( ! empty( $entry['lang'] ) && 'en' !== $entry['lang'] ) {
                                continue;
                        }

                        $post_id = isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;

                        if ( $post_id <= 0 ) {
                                continue;
                        }

                        $post = get_post( $post_id );

                        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
                                continue;
                        }

                        $target = get_permalink( $post );

                        if ( empty( $target ) ) {
                                continue;
                        }

                        wp_safe_redirect( $target, 301 );
                        exit;
                }
        }

        /**
         * Determine whether EN pages should be noindex.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        protected function should_noindex() {
                return (bool) ( $this->settings->get( 'noindex_en', false ) && FPML_Language::TARGET === $this->language->get_current_language() );
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
         * Compute canonical URL for the current request.
         *
         * @since 0.2.0
         *
         * @return string
         */
        protected function get_canonical_url() {
                if ( is_front_page() || is_home() ) {
                        if ( FPML_Language::TARGET === $this->language->get_current_language() ) {
                                return $this->language->get_url_for_language( FPML_Language::TARGET );
                        }

                        return home_url( '/' );
                }

                if ( is_singular() ) {
                        $object = get_queried_object();

                        if ( $object instanceof WP_Post ) {
                                return get_permalink( $object );
                        }
                }

                if ( is_tax() || is_category() || is_tag() ) {
                        $term = get_queried_object();

                        if ( $term instanceof WP_Term ) {
                                $link = get_term_link( $term );

                                if ( ! is_wp_error( $link ) ) {
                                        return $link;
                                }
                        }
                }

                return '';
        }

        /**
         * Build hreflang associations for the current object.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_hreflang_links() {
                $links = array();

                if ( is_front_page() || is_home() ) {
                        $italian = home_url( '/' );
                        $english = $this->language->get_url_for_language( FPML_Language::TARGET );

                        if ( $italian ) {
                                $links[] = array(
                                        'lang' => 'it-IT',
                                        'url'  => $italian,
                                );
                        }

                        if ( $english ) {
                                $links[] = array(
                                        'lang' => 'en-US',
                                        'url'  => $english,
                                );
                        }

                        if ( ! empty( $links ) && apply_filters( 'fpml_output_xdefault', true ) ) {
                                $links[] = array(
                                        'lang' => 'x-default',
                                        'url'  => $english ? $english : $italian,
                                );
                        }

                        return $links;
                }

                if ( is_singular() ) {
                        $object = get_queried_object();

                        if ( ! $object instanceof WP_Post ) {
                                return $links;
                        }

                        if ( get_post_meta( $object->ID, '_fpml_is_translation', true ) ) {
                                $source_id = (int) get_post_meta( $object->ID, '_fpml_pair_source_id', true );
                                $italian   = $source_id ? get_permalink( $source_id ) : '';
                                $english   = get_permalink( $object );
                        } else {
                                $target_id = (int) get_post_meta( $object->ID, '_fpml_pair_id', true );
                                $italian   = get_permalink( $object );
                                $english   = $target_id ? get_permalink( $target_id ) : '';
                        }

                        if ( $italian ) {
                                $links[] = array(
                                        'lang' => 'it-IT',
                                        'url'  => $italian,
                                );
                        }

                        if ( $english ) {
                                $links[] = array(
                                        'lang' => 'en-US',
                                        'url'  => $english,
                                );
                        }

                        return $links;
                }

                if ( is_tax() || is_category() || is_tag() ) {
                        $term = get_queried_object();

                        if ( ! $term instanceof WP_Term ) {
                                return $links;
                        }

                        if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
                                $source_id = (int) get_term_meta( $term->term_id, '_fpml_pair_source_id', true );
                                $source    = $source_id ? get_term( $source_id ) : null;
                                $target    = $term;
                        } else {
                                $target_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );
                                $source    = $term;
                                $target    = $target_id ? get_term( $target_id ) : null;
                        }

                        if ( $source instanceof WP_Term ) {
                                $italian_link = get_term_link( $source );

                                if ( ! is_wp_error( $italian_link ) ) {
                                        $links[] = array(
                                                'lang' => 'it-IT',
                                                'url'  => $italian_link,
                                        );
                                }
                        }

                        if ( $target instanceof WP_Term ) {
                                $english_link = get_term_link( $target );

                                if ( ! is_wp_error( $english_link ) ) {
                                        $links[] = array(
                                                'lang' => 'en-US',
                                                'url'  => $english_link,
                                        );
                                }
                        }
                }

                return $links;
        }

        /**
         * Maybe render the English sitemap when requested.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function maybe_render_sitemap() {
                if ( is_admin() ) {
                        return;
                }

                if ( ! $this->settings->get( 'sitemap_en', true ) ) {
                        return;
                }

                $requested = get_query_var( 'fpml_sitemap' );

                if ( empty( $requested ) && isset( $_GET['fpml_sitemap'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $requested = sanitize_key( wp_unslash( $_GET['fpml_sitemap'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                } else {
                        $requested = sanitize_key( (string) $requested );
                }

                if ( 'en' !== $requested ) {
                        return;
                }

                $charset = get_bloginfo( 'charset' );

                if ( ! $charset ) {
                        $charset = 'UTF-8';
                }

                $charset = preg_replace( '/[^a-zA-Z0-9\-]/', '', $charset );
                
                // Handle PCRE error
                if ( null === $charset ) {
                        $charset = 'UTF-8';
                }

                if ( '' === $charset ) {
                        $charset = 'UTF-8';
                }

		$cache_key = $this->get_sitemap_cache_key();
		$xml       = get_transient( $cache_key );

		if ( ! is_string( $xml ) || '' === $xml ) {
			// Prevent cache stampede: use temporary lock
			$lock_key = $cache_key . '_lock';
			$lock     = get_transient( $lock_key );
			
			if ( false === $lock ) {
				// Acquire lock for 30 seconds
				set_transient( $lock_key, 1, 30 );
				
				$xml = $this->build_sitemap_xml();
				set_transient( $cache_key, $xml, HOUR_IN_SECONDS );
				
				// Release lock
				delete_transient( $lock_key );
			} else {
				// Lock exists, wait briefly and retry cache
				usleep( 100000 ); // 100ms
				$xml = get_transient( $cache_key );
				
				// If still empty, build without lock (failsafe)
				if ( ! is_string( $xml ) || '' === $xml ) {
					$xml = $this->build_sitemap_xml();
				}
			}
		}

                nocache_headers();
                status_header( 200 );
                header( 'Content-Type: application/xml; charset=' . $charset );
                echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                exit;
        }

        /**
         * Build sitemap XML markup for English content.
         *
         * @since 0.2.0
         *
         * @return string
         */
        protected function build_sitemap_xml() {
                $entries = $this->collect_sitemap_entries();
                $charset = get_bloginfo( 'charset' );

                if ( ! $charset ) {
                        $charset = 'UTF-8';
                }

		$charset = preg_replace( '/[^a-zA-Z0-9\-]/', '', $charset );
		
		// Handle PCRE error
		if ( null === $charset ) {
			$charset = 'UTF-8';
		}

		if ( '' === $charset ) {
			$charset = 'UTF-8';
		}

		$lines   = array();
		$lines[] = '<?xml version="1.0" encoding="' . esc_html( $charset ) . '"?>';
                $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

                foreach ( $entries as $entry ) {
                        if ( empty( $entry['loc'] ) ) {
                                continue;
                        }

                        $lines[] = '\t<url>';
                        $lines[] = '\t\t<loc>' . esc_url( $entry['loc'] ) . '</loc>';

                        if ( ! empty( $entry['lastmod'] ) ) {
                                $lines[] = '\t\t<lastmod>' . esc_html( gmdate( 'c', (int) $entry['lastmod'] ) ) . '</lastmod>';
                        }

                        $lines[] = '\t</url>';
                }

                $lines[] = '</urlset>';

                return implode( "\n", $lines );
        }

        /**
         * Gather sitemap entries for English content.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function collect_sitemap_entries() {
                $entries = array();

                $home = $this->language->get_url_for_language( FPML_Language::TARGET );

                if ( $home ) {
                        $entries[] = array(
                                'loc'     => $home,
                                'lastmod' => $this->get_front_page_lastmod(),
                        );
                }

                $post_types  = $this->get_sitemap_post_types();
                $post_status = array( 'publish' );

                if ( in_array( 'attachment', $post_types, true ) ) {
                        $post_status[] = 'inherit';
                }

                if ( ! empty( $post_types ) ) {
                        $args = array(
                                'post_type'      => $post_types,
                                'post_status'    => $post_status,
                                'posts_per_page' => 200,
                                'paged'          => 1,
                                'orderby'        => 'ID',
                                'order'          => 'ASC',
                                'no_found_rows'  => true,
                                'fields'         => 'ids',
                                'meta_query'     => array(
                                        array(
                                                'key'   => '_fpml_is_translation',
                                                'value' => '1',
                                        ),
                                ),
                        );

                        do {
                                $query = new WP_Query( $args );

                                if ( empty( $query->posts ) ) {
                                        break;
                                }

                                foreach ( $query->posts as $post_id ) {
                                        $post = get_post( (int) $post_id );

                                        if ( ! $post instanceof WP_Post ) {
                                                continue;
                                        }

                                        $url = get_permalink( $post );

                                        if ( ! $url ) {
                                                continue;
                                        }

                                        $entries[] = array(
                                                'loc'     => $url,
                                                'lastmod' => (int) get_post_modified_time( 'U', true, $post ),
                                        );
                                }

                                $args['paged']++;
                        } while ( count( $query->posts ) === $args['posts_per_page'] );
                }

                $taxonomies = $this->get_sitemap_taxonomies();

                if ( ! empty( $taxonomies ) ) {
                        $term_query = new WP_Term_Query(
                                array(
                                        'taxonomy'   => $taxonomies,
                                        'hide_empty' => false,
                                        'meta_query' => array(
                                                array(
                                                        'key'   => '_fpml_is_translation',
                                                        'value' => '1',
                                                ),
                                        ),
                                )
                        );

                        if ( ! is_wp_error( $term_query ) && ! empty( $term_query->terms ) ) {
                                foreach ( $term_query->terms as $term ) {
                                        if ( ! $term instanceof WP_Term ) {
                                                continue;
                                        }

                                        $link = get_term_link( $term );

                                        if ( is_wp_error( $link ) || ! $link ) {
                                                continue;
                                        }

                                        $entries[] = array(
                                                'loc'     => $link,
                                                'lastmod' => 0,
                                        );
                                }
                        }
                }

                /**
                 * Allow customization of sitemap entries.
                 *
                 * @since 0.2.0
                 *
                 * @param array $entries Sitemap entries array.
                 */
                return apply_filters( 'fpml_sitemap_entries', $entries );
        }

        /**
         * Retrieve the cache key for the sitemap payload.
         *
         * @since 0.2.0
         *
         * @return string
         */
        protected function get_sitemap_cache_key() {
                return 'fpml_sitemap_en_cache';
        }

        /**
         * Resolve post types included in the English sitemap.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_sitemap_post_types() {
                $post_types = get_post_types(
                        array(
                                'public' => true,
                        ),
                        'names'
                );

                if ( ! in_array( 'attachment', $post_types, true ) ) {
                        $post_types[] = 'attachment';
                }

                $post_types = apply_filters( 'fpml_sitemap_post_types', $post_types );

                return array_filter( array_map( 'sanitize_key', (array) $post_types ) );
        }

        /**
         * Resolve taxonomies included in the English sitemap.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_sitemap_taxonomies() {
                $taxonomies = get_taxonomies(
                        array(
                                'public' => true,
                        ),
                        'names'
                );

                $taxonomies = apply_filters( 'fpml_sitemap_taxonomies', $taxonomies );

                return array_filter( array_map( 'sanitize_key', (array) $taxonomies ) );
        }

        /**
         * Retrieve the last modification timestamp for sitemap indexing.
         *
         * @since 0.2.0
         *
         * @return int
         */
        protected function get_sitemap_lastmod_timestamp() {
                $post_types  = $this->get_sitemap_post_types();
                $post_status = array( 'publish' );

                if ( in_array( 'attachment', $post_types, true ) ) {
                        $post_status[] = 'inherit';
                }

                $timestamp = 0;

                if ( ! empty( $post_types ) ) {
                        $latest = new WP_Query(
                                array(
                                        'post_type'      => $post_types,
                                        'post_status'    => $post_status,
                                        'posts_per_page' => 1,
                                        'orderby'        => 'modified',
                                        'order'          => 'DESC',
                                        'no_found_rows'  => true,
                                        'fields'         => 'ids',
                                        'meta_query'     => array(
                                                array(
                                                        'key'   => '_fpml_is_translation',
                                                        'value' => '1',
                                                ),
                                        ),
                                )
                        );

                        if ( ! empty( $latest->posts ) ) {
                                $post      = get_post( (int) $latest->posts[0] );
                                $timestamp = $post instanceof WP_Post ? (int) get_post_modified_time( 'U', true, $post ) : 0;
                        }
                }

                $front_timestamp = $this->get_front_page_lastmod();

                if ( $front_timestamp > $timestamp ) {
                        $timestamp = $front_timestamp;
                }

                if ( $timestamp <= 0 ) {
                        $timestamp = time();
                }

                return $timestamp;
        }

        /**
         * Attempt to retrieve the English front page last modification.
         *
         * @since 0.2.0
         *
         * @return int
         */
        protected function get_front_page_lastmod() {
                $front_id = (int) get_option( 'page_on_front' );

                if ( $front_id <= 0 ) {
                        return 0;
                }

                $english_id = (int) get_post_meta( $front_id, '_fpml_pair_id', true );

                if ( $english_id > 0 ) {
                        $front_id = $english_id;
                }

                $post = get_post( $front_id );

                if ( ! $post instanceof WP_Post ) {
                        return 0;
                }

                return (int) get_post_modified_time( 'U', true, $post );
        }

        /**
         * Return sitemap absolute URL.
         *
         * @since 0.2.0
         *
         * @return string
         */
        protected function get_sitemap_url() {
                if ( ! $this->settings->get( 'sitemap_en', true ) ) {
                        return '';
                }

                return home_url( '/sitemap-en.xml' );
        }

        /**
         * Inject English sitemap entry into Yoast SEO sitemap index.
         *
         * @since 0.2.0
         *
         * @param string $content Sitemap XML.
         *
         * @return string
         */
        public function inject_wpseo_sitemap_entry( $content ) {
                $content = (string) $content;
                $url     = $this->get_sitemap_url();

                if ( '' === $url || false === strpos( $content, '<sitemapindex' ) ) {
                        return $content;
                }

                if ( false !== strpos( $content, $url ) ) {
                        return $content;
                }

                $lastmod = esc_html( gmdate( 'c', $this->get_sitemap_lastmod_timestamp() ) );
                $entry   = '<sitemap><loc>' . esc_url( $url ) . '</loc><lastmod>' . $lastmod . '</lastmod></sitemap>';

                return str_replace( '</sitemapindex>', $entry . '</sitemapindex>', $content );
        }

        /**
         * Inject English sitemap entry into Rank Math sitemap index.
         *
         * @since 0.2.0
         *
         * @param array $entries Sitemap entries.
         *
         * @return array
         */
        public function inject_rankmath_sitemap_entry( $entries ) {
                if ( ! is_array( $entries ) ) {
                        $entries = array();
                }

                $url = $this->get_sitemap_url();

                if ( '' === $url ) {
                        return $entries;
                }

                foreach ( $entries as $entry ) {
                        if ( ! is_array( $entry ) ) {
                                continue;
                        }

                        if ( isset( $entry['loc'] ) && $url === $entry['loc'] ) {
                                return $entries;
                        }
                }

                $entries[] = array(
                        'loc'     => $url,
                        'lastmod' => gmdate( 'c', $this->get_sitemap_lastmod_timestamp() ),
                );

                return $entries;
        }

        /**
         * Inject English sitemap entry into AIOSEO sitemap index.
         *
         * @since 0.2.0
         *
         * @param array $indexes Sitemap index entries.
         *
         * @return array
         */
        public function inject_aioseo_sitemap_entry( $indexes ) {
                if ( ! is_array( $indexes ) ) {
                        $indexes = array();
                }

                $url = $this->get_sitemap_url();

                if ( '' === $url ) {
                        return $indexes;
                }

                foreach ( $indexes as $index ) {
                        if ( ! is_array( $index ) ) {
                                continue;
                        }

                        if ( isset( $index['loc'] ) && $url === $index['loc'] ) {
                                return $indexes;
                        }
                }

                $indexes[] = array(
                        'loc'     => $url,
                        'lastmod' => gmdate( 'c', $this->get_sitemap_lastmod_timestamp() ),
                );

                return $indexes;
        }

        /**
         * Clear cached sitemap payload when content changes.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function invalidate_sitemap_cache() {
                delete_transient( $this->get_sitemap_cache_key() );
        }

        /**
         * Extract the current request slug from the URL.
         *
         * @since 0.2.0
         *
         * @return string
         */
        protected function get_request_slug() {
                $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER

                if ( '' === $uri ) {
                        return '';
                }

                $path = (string) parse_url( $uri, PHP_URL_PATH );

                if ( '' === $path ) {
                        return '';
                }

                $home_path = (string) parse_url( home_url( '/' ), PHP_URL_PATH );

                if ( $home_path && 0 === strpos( $path, $home_path ) ) {
                        $path = substr( $path, strlen( $home_path ) );
                }

                $path = trim( $path, '/' );

                if ( '' === $path ) {
                        return '';
                }

                $parts = explode( '/', $path );
                $slug  = end( $parts );

                return sanitize_title( $slug );
        }
}
