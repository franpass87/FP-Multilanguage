<?php
/**
 * Core plugin bootstrap.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 0.2.0
 */
class FPML_Plugin {
        /**
         * Option tracking completed migrations.
         */
        const OPTION_AUTOLOAD_MIGRATED = 'fpml_options_autoload_migrated';

/**
 * Singleton instance.
 *
         * @var FPML_Plugin|null
         */
        protected static $instance = null;

        /**
         * Cached settings instance.
         *
         * @var FPML_Settings
         */
        protected $settings;

        /**
         * Cached queue handler.
         *
         * @var FPML_Queue
         */
        protected $queue;

        /**
         * Cached logger.
         *
         * @var FPML_Logger
         */
        protected $logger;

        /**
         * Flag to avoid recursion while creating translations.
         *
         * @var bool
         */
        protected $creating_translation = false;

        /**
         * Flag to avoid recursion while creating term translations.
         *
         * @var bool
         */
        protected $creating_term_translation = false;

        /**
         * Whether the plugin is running in assisted mode (WPML/Polylang active).
         *
         * @var bool
         */
        protected $assisted_mode = false;

        /**
         * Identifier of the multilingual plugin triggering assisted mode.
         *
         * @var string
         */
        protected $assisted_reason = '';

        /**
         * Plugin constructor.
         */
        protected function __construct() {
                $this->detect_assisted_mode();

                $this->settings = FPML_Settings::instance();
                $this->queue    = FPML_Queue::instance();

                if ( $this->queue && method_exists( $this->queue, 'maybe_upgrade' ) ) {
                        $this->queue->maybe_upgrade();
                }

                $this->logger = FPML_Logger::instance();

                $this->maybe_disable_autoloaded_options();

                $this->define_hooks();
        }

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.2.0
	 *
	 * @return FPML_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin activation callback.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
        public static function activate() {
                $reason = self::detect_external_multilingual();

                if ( ! $reason ) {
                        FPML_Rewrites::instance()->register_rewrites();
                }

                FPML_Queue::instance()->install();
                flush_rewrite_rules();
        }

	/**
	 * Plugin deactivation callback.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
        public static function deactivate() {
                wp_clear_scheduled_hook( 'fpml_cleanup_queue' );
                flush_rewrite_rules();
        }

        /**
         * Detect active multilingual plugins that require assisted mode.
         *
         * @since 0.2.0
         *
         * @return string Empty string when no external plugin is detected, otherwise the identifier.
         */
        protected static function detect_external_multilingual() {
                if ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) ) {
                        return 'wpml';
                }

                if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
                        return 'polylang';
                }

                return '';
        }

        /**
         * Detect whether the plugin should operate in assisted mode.
         *
         * @since 0.2.0
         *
         * @return void
         */
        protected function detect_assisted_mode() {
                $reason = self::detect_external_multilingual();

                if ( $reason ) {
                        $this->assisted_mode   = true;
                        $this->assisted_reason = $reason;
                }
        }

        /**
         * Check if assisted mode is active.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        public function is_assisted_mode() {
                return (bool) $this->assisted_mode;
        }

        /**
         * Retrieve the assisted mode reason identifier.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function get_assisted_reason() {
                return $this->assisted_reason;
        }

        /**
         * Get a human readable label for the assisted mode reason.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function get_assisted_reason_label() {
                switch ( $this->assisted_reason ) {
                        case 'wpml':
                                return 'WPML';
                        case 'polylang':
                                return 'Polylang';
                        default:
                                return '';
                }
        }

	/**
	 * Define hooks and bootstrap classes.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
protected function define_hooks() {
                load_plugin_textdomain( 'fp-multilanguage', false, dirname( plugin_basename( FPML_PLUGIN_FILE ) ) . '/languages' );

                FPML_Settings::instance();
                FPML_Logger::instance();
                FPML_Glossary::instance();
                FPML_Strings_Override::instance();
                FPML_Strings_Scanner::instance();
                FPML_Export_Import::instance();

                if ( ! $this->assisted_mode ) {
                        FPML_Rewrites::instance();
                        FPML_Language::instance();
                        FPML_Content_Diff::instance();
                        FPML_Processor::instance();
                        FPML_Menu_Sync::instance();
                        FPML_Media_Front::instance();
                        FPML_SEO::instance();
                }

                if ( class_exists( 'FPML_REST_Admin' ) ) {
                        FPML_REST_Admin::instance();
                }

                if ( is_admin() ) {
                        new FPML_Admin();
                }

                if ( ! $this->assisted_mode ) {
                        add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
                        add_action( 'created_term', array( $this, 'handle_created_term' ), 10, 3 );
                        add_action( 'edited_term', array( $this, 'handle_edited_term' ), 10, 3 );
                }
        }

        /**
         * Ensure heavy options are stored without autoload.
         *
         * @since 0.3.2
         *
         * @return void
         */
        protected function maybe_disable_autoloaded_options() {
                $migrated = get_option( self::OPTION_AUTOLOAD_MIGRATED );

                if ( $migrated ) {
                        return;
                }

                $options = array();

                if ( class_exists( 'FPML_Strings_Scanner' ) ) {
                        $options[] = FPML_Strings_Scanner::OPTION_KEY;
                }

                if ( class_exists( 'FPML_Strings_Override' ) ) {
                        $options[] = FPML_Strings_Override::OPTION_KEY;
                }

                if ( class_exists( 'FPML_Glossary' ) ) {
                        $options[] = FPML_Glossary::OPTION_KEY;
                }

                foreach ( array_filter( array_unique( $options ) ) as $option ) {
                        $value = get_option( $option, null );

                        if ( null === $value ) {
                                continue;
                        }

                        update_option( $option, $value, false );
                }

                update_option( self::OPTION_AUTOLOAD_MIGRATED, 1, false );
        }

        /**
         * Handle post save events to ensure translations and enqueue jobs.
         *
         * @since 0.2.0
         *
         * @param int     $post_id Post ID.
         * @param WP_Post $post    Post object.
         * @param bool    $update  Whether this is an existing post being updated.
         *
         * @return void
         */
        public function handle_save_post( $post_id, $post, $update ) {
                if ( $this->is_assisted_mode() ) {
                        return;
                }

                if ( $this->creating_translation ) {
                        return;
                }

                if ( ! $post instanceof WP_Post ) {
                        return;
                }

                if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                        return;
                }

                if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
                        return;
                }

                if ( 'auto-draft' === $post->post_status ) {
                        return;
                }

                if ( get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
                        return;
                }

                $post_types = $this->get_translatable_post_types();

                if ( empty( $post_types ) || ! in_array( $post->post_type, $post_types, true ) ) {
                        return;
                }

                $target_post = $this->ensure_post_translation( $post );

                if ( ! $target_post ) {
                        return;
                }

                $this->enqueue_post_jobs( $post, $target_post, $update );
        }

        /**
         * Ensure we react to created terms.
         *
         * @since 0.2.0
         *
         * @param int    $term_id  Term ID.
         * @param int    $tt_id    Term taxonomy ID.
         * @param string $taxonomy Taxonomy slug.
         *
         * @return void
         */
        public function handle_created_term( $term_id, $tt_id, $taxonomy ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( $this->is_assisted_mode() ) {
                        return;
                }

                $this->sync_term_translation( $term_id, $taxonomy );
        }

        /**
         * React to edited terms.
         *
         * @since 0.2.0
         *
         * @param int    $term_id  Term ID.
         * @param int    $tt_id    Term taxonomy ID.
         * @param string $taxonomy Taxonomy slug.
         *
         * @return void
         */
        public function handle_edited_term( $term_id, $tt_id, $taxonomy ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( $this->is_assisted_mode() ) {
                        return;
                }

                $this->sync_term_translation( $term_id, $taxonomy );
        }

        /**
         * Retrieve allowed post types for translation.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_translatable_post_types() {
                $post_types = get_post_types(
                        array(
                                'public' => true,
                        ),
                        'names'
                );

                if ( ! in_array( 'attachment', $post_types, true ) ) {
                        $post_types[] = 'attachment';
                }

                $post_types = apply_filters( 'fpml_translatable_post_types', $post_types );

                return array_filter( array_map( 'sanitize_key', $post_types ) );
        }

        /**
         * Ensure a translation post exists and return it.
         *
         * @since 0.2.0
         *
         * @param WP_Post $post Source post.
         *
         * @return WP_Post|false
         */
        protected function ensure_post_translation( $post ) {
                $target_id = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );

                if ( $target_id ) {
                        $target_post = get_post( $target_id );

                        if ( $target_post instanceof WP_Post ) {
                                update_post_meta( $target_post->ID, '_fpml_pair_source_id', $post->ID );
                                update_post_meta( $target_post->ID, '_fpml_is_translation', 1 );

                                return $target_post;
                        }
                }

                $this->creating_translation = true;

                $postarr = array(
                        'post_type'      => $post->post_type,
                        'post_status'    => $post->post_status,
                        'post_author'    => $post->post_author,
                        'post_parent'    => $post->post_parent,
                        'menu_order'     => $post->menu_order,
                        'post_password'  => $post->post_password,
                        'comment_status' => $post->comment_status,
                        'ping_status'    => $post->ping_status,
                        'post_title'     => '',
                        'post_content'   => '',
                        'post_excerpt'   => '',
                        'post_name'      => '',
                        'meta_input'     => array(
                                '_fpml_is_translation'  => 1,
                                '_fpml_pair_source_id' => $post->ID,
                        ),
                );

                $target_id = wp_insert_post( $postarr, true );

                $this->creating_translation = false;

                if ( is_wp_error( $target_id ) ) {
                        $this->logger->log(
                                'error',
                                sprintf( 'Impossibile creare la traduzione per il post #%d: %s', $post->ID, $target_id->get_error_message() ),
                                array(
                                        'post_id' => $post->ID,
                                )
                        );

                        return false;
                }

                update_post_meta( $post->ID, '_fpml_pair_id', $target_id );

                $target_post = get_post( $target_id );

                if ( $target_post instanceof WP_Post ) {
                        update_post_meta( $target_post->ID, '_fpml_pair_source_id', $post->ID );
                        update_post_meta( $target_post->ID, '_fpml_is_translation', 1 );

                        return $target_post;
                }

                return false;
        }

        /**
         * Enqueue translation jobs for a pair of posts.
         *
         * @since 0.2.0
         *
         * @param WP_Post $source_post Italian source post.
         * @param WP_Post $target_post English counterpart.
         * @param bool    $update      Whether this is an update.
         *
         * @return void
         */
        protected function enqueue_post_jobs( $source_post, $target_post, $update ) {
                $fields = array( 'post_title', 'post_excerpt', 'post_content' );

                foreach ( $fields as $field ) {
                        $hash = $this->hash_value( $this->get_post_field_value( $source_post, $field ) );

                        if ( ! $hash ) {
                                continue;
                        }

                        $this->queue->enqueue( 'post', $source_post->ID, $field, $hash );
                        $this->update_post_status_flag( $target_post->ID, $field, 'needs_update' );
                }

                $meta_keys = $this->get_meta_whitelist();

                foreach ( $meta_keys as $meta_key ) {
                        if ( '' === $meta_key ) {
                                continue;
                        }

                        $value = get_post_meta( $source_post->ID, $meta_key, true );
                        $hash  = $this->hash_value( $value );

                        if ( ! $hash ) {
                                continue;
                        }

                        $field_key = 'meta:' . $meta_key;

                        $this->queue->enqueue( 'post', $source_post->ID, $field_key, $hash );
                        $this->update_post_status_flag( $target_post->ID, $field_key, 'needs_update' );
                }

                /**
                 * Allow third parties to react when jobs are enqueued.
                 *
                 * @since 0.2.0
                 *
                 * @param WP_Post $source_post Source post.
                 * @param WP_Post $target_post Target post.
                 * @param bool    $update      Whether the post is being updated.
                 */
                do_action( 'fpml_post_jobs_enqueued', $source_post, $target_post, $update );
        }

        /**
         * Retrieve a value for hashing from a post field.
         *
         * @since 0.2.0
         *
         * @param WP_Post $post  Post object.
         * @param string  $field Field name.
         *
         * @return string
         */
        protected function get_post_field_value( $post, $field ) {
                switch ( $field ) {
                        case 'post_title':
                                return (string) $post->post_title;
                        case 'post_excerpt':
                                return (string) $post->post_excerpt;
                        case 'post_content':
                                return (string) $post->post_content;
                }

                return '';
        }

        /**
         * Parse whitelist meta keys.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_meta_whitelist() {
                $raw = $this->settings ? $this->settings->get( 'meta_whitelist', '' ) : '';

                if ( ! is_string( $raw ) ) {
                        return array();
                }

                $parts = preg_split( '/[\n,]+/', $raw );
                $parts = array_map( 'trim', $parts );
                $parts = array_filter( $parts );

                $sanitized = array();

                foreach ( $parts as $key ) {
                        $key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key );

                        if ( '' !== $key ) {
                                $sanitized[] = $key;
                        }
                }

                /**
                 * Allow other components to extend the meta whitelist.
                 *
                 * @since 0.2.0
                 *
                 * @param array         $sanitized Current whitelist.
                 * @param FPML_Plugin   $plugin    Plugin instance.
                 */
                $sanitized = apply_filters( 'fpml_meta_whitelist', array_unique( $sanitized ), $this );

                $required_keys = array(
                        '_wp_attachment_image_alt',
                        '_product_attributes',
                );

                foreach ( $required_keys as $required_key ) {
                        if ( '' === $required_key ) {
                                continue;
                        }

                        if ( ! in_array( $required_key, $sanitized, true ) ) {
                                $sanitized[] = $required_key;
                        }
                }

                return array_values( array_unique( array_filter( $sanitized ) ) );
        }

        /**
         * Generate a normalized hash for queue purposes.
         *
         * @since 0.2.0
         *
         * @param mixed $value Value to hash.
         *
         * @return string
         */
        protected function hash_value( $value ) {
                if ( is_array( $value ) || is_object( $value ) ) {
                        $value = wp_json_encode( $value );
                }

                $value = (string) $value;

                if ( '' === $value ) {
                        return md5( '' );
                }

                return md5( $value );
        }

        /**
         * Update translation status flag on the translated post.
         *
         * @since 0.2.0
         *
         * @param int    $post_id Target post ID.
         * @param string $field   Field identifier.
         * @param string $status  Status slug.
         *
         * @return void
         */
        protected function update_post_status_flag( $post_id, $field, $status ) {
                $meta_key = '_fpml_status_' . sanitize_key( str_replace( ':', '_', $field ) );

                update_post_meta( $post_id, $meta_key, sanitize_key( $status ) );
        }

        /**
         * Ensure taxonomy terms have an English counterpart.
         *
         * @since 0.2.0
         *
         * @param int    $term_id  Term ID.
         * @param string $taxonomy Taxonomy slug.
         *
         * @return void
         */
        protected function sync_term_translation( $term_id, $taxonomy ) {
                if ( $this->creating_term_translation ) {
                        return;
                }

                $taxonomies = get_taxonomies(
                        array(
                                'public' => true,
                        ),
                        'names'
                );

                $taxonomies = apply_filters( 'fpml_translatable_taxonomies', $taxonomies );

                if ( empty( $taxonomies ) || ! in_array( $taxonomy, $taxonomies, true ) ) {
                        return;
                }

                $term = get_term( $term_id, $taxonomy );

                if ( ! $term || is_wp_error( $term ) ) {
                        return;
                }

                if ( get_term_meta( $term_id, '_fpml_is_translation', true ) ) {
                        return;
                }

                $target_id = (int) get_term_meta( $term_id, '_fpml_pair_id', true );

                if ( $target_id ) {
                        $target_term = get_term( $target_id, $taxonomy );
                } else {
                        $target_term = $this->create_term_translation( $term );

                        if ( ! $target_term ) {
                                return;
                        }

                        $target_id = (int) $target_term->term_id;
                        update_term_meta( $term_id, '_fpml_pair_id', $target_id );
                }

                if ( ! $target_term ) {
                        return;
                }

		update_term_meta( $target_term->term_id, '_fpml_pair_source_id', $term->term_id );
		update_term_meta( $target_term->term_id, '_fpml_is_translation', 1 );

		$this->queue->enqueue_term( $term, 'name' );
		$this->queue->enqueue_term( $term, 'description' );

		update_term_meta( $target_term->term_id, '_fpml_status_name', 'needs_update' );
		update_term_meta( $target_term->term_id, '_fpml_status_description', 'needs_update' );
        }

        /**
         * Create a translated term shell.
         *
         * @since 0.2.0
         *
         * @param WP_Term $term Source term.
         *
         * @return WP_Term|false
         */
        protected function create_term_translation( $term ) {
                $this->creating_term_translation = true;

                $args = array(
                        'slug'       => $this->generate_translation_slug( $term->slug ),
                        'parent'     => $term->parent,
                        'description'=> '',
                        'meta_input' => array(
                                '_fpml_is_translation'  => 1,
                                '_fpml_pair_source_id' => $term->term_id,
                        ),
                );

                $result = wp_insert_term( $term->name, $term->taxonomy, $args );

                $this->creating_term_translation = false;

                if ( is_wp_error( $result ) ) {
                        $this->logger->log(
                                'error',
                                sprintf( 'Impossibile creare la traduzione del termine #%d: %s', $term->term_id, $result->get_error_message() ),
                                array(
                                        'term_id'  => $term->term_id,
                                        'taxonomy' => $term->taxonomy,
                                )
                        );

                        return false;
                }

                if ( empty( $result['term_id'] ) ) {
                        return false;
                }

                update_term_meta( $result['term_id'], '_fpml_pair_source_id', $term->term_id );
                update_term_meta( $result['term_id'], '_fpml_is_translation', 1 );

                return get_term( (int) $result['term_id'], $term->taxonomy );
        }

        /**
         * Generate a slug candidate for the translated entity.
         *
         * @since 0.2.0
         *
         * @param string $slug Source slug.
         *
         * @return string
         */
        protected function generate_translation_slug( $slug ) {
                $slug = sanitize_title( $slug );

                if ( '' === $slug ) {
                        $slug = uniqid( 'fpml-en-' );
                }

                if ( '-en' !== substr( $slug, -3 ) ) {
                        $slug .= '-en';
                }

                return $slug;
        }

        /**
         * Reindex existing content to ensure queue coverage and translations.
         *
         * @since 0.2.0
         *
         * @return array Summary data.
         */
        public function reindex_content() {
                if ( $this->is_assisted_mode() ) {
                        return new WP_Error(
                                'fpml_assisted_mode',
                                __( 'La modalità assistita è attiva: la duplicazione e il reindex automatico sono disabilitati.', 'fp-multilanguage' )
                        );
                }

                $summary = array(
                        'posts_scanned'        => 0,
                        'posts_enqueued'       => 0,
                        'translations_created' => 0,
                        'terms_scanned'        => 0,
                        'menus_synced'         => 0,
                );

                $post_types = $this->get_translatable_post_types();

                foreach ( $post_types as $post_type ) {
                        $paged = 1;

                        do {
                                $query = new WP_Query(
                                        array(
                                                'post_type'      => $post_type,
                                                'post_status'    => 'any',
                                                'posts_per_page' => 100,
                                                'paged'          => $paged,
                                                'fields'         => 'ids',
                                                'orderby'        => 'ID',
                                                'order'          => 'ASC',
                                        )
                                );

                                if ( ! $query->have_posts() ) {
                                        break;
                                }

                                // Pre-load all post meta to avoid N+1 queries
                                update_meta_cache( 'post', $query->posts );

                                foreach ( $query->posts as $post_id ) {
                                        $post = get_post( $post_id );

                                        if ( ! $post instanceof WP_Post ) {
                                                continue;
                                        }

                                        if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
                                                continue;
                                        }

                                        $summary['posts_scanned']++;

                                        $existing_target = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );
                                        $target_post     = $this->ensure_post_translation( $post );

                                        if ( ! $target_post ) {
                                                continue;
                                        }

                                        if ( ! $existing_target ) {
                                                $summary['translations_created']++;
                                        }

                                        $this->enqueue_post_jobs( $post, $target_post, true );
                                        $summary['posts_enqueued']++;
                                }

                                $paged++;
                        } while ( $paged <= $query->max_num_pages );

                        wp_reset_postdata();
                }

                $taxonomies = get_taxonomies(
                        array(
                                'public' => true,
                        ),
                        'names'
                );

                $taxonomies = apply_filters( 'fpml_translatable_taxonomies', $taxonomies );

                foreach ( $taxonomies as $taxonomy ) {
                        $terms = get_terms(
                                array(
                                        'taxonomy'   => $taxonomy,
                                        'hide_empty' => false,
                                        'fields'     => 'ids',
                                )
                        );

                        if ( is_wp_error( $terms ) ) {
                                continue;
                        }

                        // Pre-load term meta to avoid N+1 queries
                        if ( ! empty( $terms ) ) {
                                update_meta_cache( 'term', $terms );
                        }

                        foreach ( $terms as $term_id ) {
                                if ( get_term_meta( $term_id, '_fpml_is_translation', true ) ) {
                                        continue;
                                }

                                $summary['terms_scanned']++;
                                $this->sync_term_translation( $term_id, $taxonomy );
                        }
                }

                $menu_sync = FPML_Menu_Sync::instance();

                if ( $menu_sync instanceof FPML_Menu_Sync ) {
                        $summary['menus_synced'] = $menu_sync->resync_all();
                }

                /**
                 * Allow filtering of the reindex summary before returning.
                 *
                 * @since 0.2.0
                 *
                 * @param array         $summary Summary data.
                 * @param FPML_Plugin   $plugin  Plugin instance.
                 */
                return apply_filters( 'fpml_reindex_summary', $summary, $this );
        }

        /**
         * Build a diagnostics snapshot for the admin dashboard.
         *
         * @since 0.2.0
         *
         * @return array<string,mixed>
         */
        public function get_diagnostics_snapshot() {
                if ( $this->is_assisted_mode() ) {
                        return array(
                                'assisted_mode'   => true,
                                'assisted_reason' => $this->assisted_reason,
                                'message'         => __( 'WPML o Polylang sono attivi: la gestione della coda è disabilitata e le metriche non sono disponibili.', 'fp-multilanguage' ),
                        );
                }

                $queue      = FPML_Queue::instance();
                $processor  = FPML_Processor::instance();
                $logger     = FPML_Logger::instance();
                $settings   = $this->settings;
                $counts     = $queue->get_state_counts();
                $terms_done = $queue->count_completed_jobs( 'term' );
                $menu_done  = $queue->count_completed_jobs( 'menu', 'title' );
                $events     = array(
                        'fpml_run_queue'       => wp_next_scheduled( 'fpml_run_queue' ),
                        'fpml_retry_failed'    => wp_next_scheduled( 'fpml_retry_failed' ),
                        'fpml_resync_outdated' => wp_next_scheduled( 'fpml_resync_outdated' ),
                        'fpml_cleanup_queue'   => wp_next_scheduled( 'fpml_cleanup_queue' ),
                );
                $logs       = $logger->get_logs( 25 );
                $log_stats  = $logger->get_stats();
                $estimate   = $this->estimate_queue_cost();
                $estimate_error = '';

                if ( is_wp_error( $estimate ) ) {
                        $estimate_error = $estimate->get_error_message();
                        $estimate       = array(
                                'characters'     => 0,
                                'estimated_cost' => 0.0,
                                'jobs_scanned'   => 0,
                                'word_count'     => 0,
                        );
                }

                $translator = $processor->get_translator_instance();
                $translator_status = array(
                        'provider'   => $settings ? $settings->get( 'provider', '' ) : '',
                        'configured' => ! is_wp_error( $translator ) && $translator instanceof FPML_TranslatorInterface,
                        'error'      => is_wp_error( $translator ) ? $translator->get_error_message() : '',
                );

                $batch_durations = array();
                $batch_jobs      = array();
                $recent_errors   = array();

                foreach ( $logs as $entry ) {
                        if ( isset( $entry['level'] ) && 'error' === $entry['level'] ) {
                                $recent_errors[] = $entry;
                        }

                        if ( empty( $entry['message'] ) ) {
                                continue;
                        }

                        if ( false !== strpos( $entry['message'], 'Batch coda completato' ) ) {
                                if ( preg_match( '/([0-9]+(?:\.[0-9]+)?)s/', $entry['message'], $matches ) ) {
                                        $batch_durations[] = (float) $matches[1];
                                }

                                if ( isset( $entry['context']['jobs'] ) ) {
                                        $batch_jobs[] = (float) $entry['context']['jobs'];
                                }
                        }
                }

                $average_duration = ! empty( $batch_durations ) ? array_sum( $batch_durations ) / count( $batch_durations ) : 0.0;
                $average_jobs     = ! empty( $batch_jobs ) ? array_sum( $batch_jobs ) / count( $batch_jobs ) : 0.0;

                $queue_age = $this->get_queue_age_summary();

                return array(
                        'queue_counts'      => $counts,
                        'kpi'               => array(
                                'terms_translated'       => $terms_done,
                                'menu_labels_translated' => $menu_done,
                        ),
                        'events'            => $events,
                        'lock_active'       => $processor->is_locked(),
                        'cron_disabled'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
                        'logs'              => $logs,
                        'log_stats'         => $log_stats,
                        'estimate'          => $estimate,
                        'estimate_error'    => $estimate_error,
                        'translator_status' => $translator_status,
                        'batch_average'     => array(
                                'duration' => $average_duration,
                                'jobs'     => $average_jobs,
                        ),
                        'recent_errors'     => array_slice( $recent_errors, 0, 5 ),
                        'queue_age'         => $queue_age,
                );
        }

        /**
         * Estimate characters, words and cost for jobs in the queue.
         *
         * @since 0.2.0
         *
         * @param array<string>|null $states   Queue states to inspect.
         * @param int                $max_jobs Maximum number of jobs to analyse.
         *
         * @return array<string,float|int>|WP_Error
         */
        public function estimate_queue_cost( $states = null, $max_jobs = 500 ) {
                if ( $this->is_assisted_mode() ) {
                        return new WP_Error(
                                'fpml_assisted_mode',
                                __( 'La modalità assistita è attiva: la coda è gestita esternamente, nessuna stima disponibile.', 'fp-multilanguage' )
                        );
                }

                $processor  = FPML_Processor::instance();
                $translator = $processor->get_translator_instance();

                if ( is_wp_error( $translator ) ) {
                        return $translator;
                }

                if ( null === $states ) {
                        $states = array( 'pending', 'outdated', 'translating' );
                }

                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );

                if ( empty( $states ) ) {
                        return array(
                                'characters'     => 0,
                                'estimated_cost' => 0.0,
                                'jobs_scanned'   => 0,
                                'word_count'     => 0,
                        );
                }

                $max_jobs = max( 1, absint( $max_jobs ) );

                $queue       = FPML_Queue::instance();
                $batch_limit = (int) apply_filters( 'fpml_estimate_batch_size', 100 );
                $characters  = 0;
                $cost        = 0.0;
                $word_count  = 0;
                $offset      = 0;
                $scanned     = 0;

                while ( $scanned < $max_jobs ) {
                        $limit = min( $batch_limit, $max_jobs - $scanned );
                        $jobs  = $queue->get_jobs_for_states( $states, $limit, $offset );

                        if ( empty( $jobs ) ) {
                                break;
                        }

                        foreach ( $jobs as $job ) {
                                $text = $this->get_queue_job_text( $job );

                                if ( '' === trim( (string) $text ) ) {
                                        continue;
                                }

                                $length      = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
                                $characters += $length;
                                $cost       += (float) $translator->estimate_cost( $text );

                                $words = trim( wp_strip_all_tags( $text ) );
                                if ( '' !== $words ) {
                                        $word_count += count( preg_split( '/\s+/u', $words ) );
                                }
                        }

                        $count   = count( $jobs );
                        $scanned += $count;
                        $offset  += $count;

                        if ( $count < $limit ) {
                                break;
                        }
                }

                return array(
                        'characters'     => (int) $characters,
                        'estimated_cost' => (float) $cost,
                        'jobs_scanned'   => (int) $scanned,
                        'word_count'     => (int) $word_count,
                );
        }

        /**
         * Retrieve the raw text associated with a queue job.
         *
         * @since 0.2.0
         *
         * @param object $job Queue job entry.
         *
         * @return string
         */
        public function get_queue_job_text( $job ) {
                if ( ! is_object( $job ) || empty( $job->object_type ) ) {
                        return '';
                }

                $field = isset( $job->field ) ? (string) $job->field : '';

                switch ( $job->object_type ) {
                        case 'post':
                                $post = get_post( isset( $job->object_id ) ? (int) $job->object_id : 0 );

                                if ( ! $post instanceof WP_Post ) {
                                        return '';
                                }

                                if ( 0 === strpos( $field, 'meta:' ) ) {
                                        $meta_key = substr( $field, 5 );
                                        $value    = get_post_meta( $post->ID, $meta_key, true );

                                        if ( is_array( $value ) || is_object( $value ) ) {
                                                $value = wp_json_encode( $value );
                                        }

                                        return (string) $value;
                                }

                                switch ( $field ) {
                                        case 'post_title':
                                                return (string) $post->post_title;
                                        case 'post_excerpt':
                                                return (string) $post->post_excerpt;
                                        case 'post_content':
                                                return (string) $post->post_content;
                                }

                                break;

                        case 'term':
                                $object_id = isset( $job->object_id ) ? (int) $job->object_id : 0;
                                list( $taxonomy, $term_field ) = array_pad( explode( ':', $field, 2 ), 2, '' );
                                $taxonomy = sanitize_key( $taxonomy );

                                if ( '' === $taxonomy ) {
                                        break;
                                }

                                $term = get_term( $object_id, $taxonomy );

                                if ( $term instanceof WP_Term ) {
                                        switch ( $term_field ) {
                                                case 'name':
                                                        return (string) $term->name;
                                                case 'description':
                                                        return (string) $term->description;
                                        }
                                }

                                break;

                        case 'menu':
                                $item = get_post( isset( $job->object_id ) ? (int) $job->object_id : 0 );

                                if ( ! $item instanceof WP_Post ) {
                                        return '';
                                }

                                $label = get_post_meta( $item->ID, '_menu_item_title', true );

                                if ( '' === $label ) {
                                        $label = (string) $item->post_title;
                                }

                                return (string) $label;
                }

                return '';
        }

        /**
         * Retrieve the sanitized list of states targeted by queue cleanup.
         *
         * @since 0.3.1
         *
         * @return array
         */
        public function get_queue_cleanup_states() {
                $states = apply_filters( 'fpml_queue_cleanup_states', array( 'done', 'skipped', 'error' ) );
                $states = array_filter( array_map( 'sanitize_key', (array) $states ) );

                return array_values( array_unique( $states ) );
        }

        /**
         * Build queue age metrics useful for diagnostics.
         *
         * @since 0.3.1
         *
         * @return array
         */
        public function get_queue_age_summary() {
                if ( $this->is_assisted_mode() ) {
                        return array();
                }

                $queue = FPML_Queue::instance();

                $now              = current_time( 'timestamp', true );
                $date_format      = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );
                $pending_states   = array( 'pending', 'outdated', 'translating' );
                $cleanup_states   = $this->get_queue_cleanup_states();
                $retention        = $this->settings ? (int) $this->settings->get( 'queue_retention_days', 0 ) : 0;
                $oldest_pending   = $queue->get_oldest_job_for_states( $pending_states, 'created_at' );
                $oldest_completed = $queue->get_oldest_job_for_states( $cleanup_states, 'updated_at' );

                $summary = array(
                        'retention_days'  => $retention,
                        'cleanup_states'  => $cleanup_states,
                        'pending'         => $this->format_queue_age_entry( $oldest_pending, 'created_at', $now, $date_format ),
                        'completed'       => $this->format_queue_age_entry( $oldest_completed, 'updated_at', $now, $date_format ),
                );

                return $summary;
        }

        /**
         * Format queue age details for a specific job.
         *
         * @since 0.3.1
         *
         * @param object|null $job        Queue job instance.
         * @param string      $column     Date column to read.
         * @param int         $now        Current timestamp.
         * @param string      $date_format Date format for local output.
         *
         * @return array
         */
        protected function format_queue_age_entry( $job, $column, $now, $date_format ) {
                if ( ! $job || empty( $job->{$column} ) ) {
                        return array();
                }

                $timestamp = mysql2date( 'U', $job->{$column}, false );

                if ( ! $timestamp ) {
                        return array();
                }

                $local_datetime = function_exists( 'get_date_from_gmt' ) ? get_date_from_gmt( $job->{$column}, $date_format ) : $job->{$column};

                return array(
                        'job_id'          => isset( $job->id ) ? (int) $job->id : 0,
                        'state'           => isset( $job->state ) ? $job->state : '',
                        'timestamp'       => (int) $timestamp,
                        'age'             => human_time_diff( $timestamp, $now ),
                        'datetime_gmt'    => $job->{$column},
                        'datetime_local'  => $local_datetime,
                );
        }
}
