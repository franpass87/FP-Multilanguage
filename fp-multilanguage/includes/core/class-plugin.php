<?php
/**
 * Core plugin bootstrap - Refactored version.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class - Simplified with delegation to specialized services.
 *
 * @since 0.4.0
 */
class FPML_Plugin_Core {
	/**
	 * Option tracking completed migrations.
	 */
	const OPTION_AUTOLOAD_MIGRATED = 'fpml_options_autoload_migrated';

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Plugin_Core|null
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
	 * Translation manager instance.
	 *
	 * @var FPML_Translation_Manager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer instance.
	 *
	 * @var FPML_Job_Enqueuer
	 */
	protected $job_enqueuer;

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
	 * Plugin constructor - TEST 5C: + define_hooks [CRITICO].
	 */
	protected function __construct() {
		$this->detect_assisted_mode();
		
		$this->settings = FPML_Container::get( 'settings' ) ?: FPML_Settings::instance();
		$this->queue = FPML_Container::get( 'queue' ) ?: FPML_Queue::instance();
		$this->logger = FPML_Container::get( 'logger' ) ?: FPML_Logger::instance();
		$this->translation_manager = FPML_Container::get( 'translation_manager' ) ?: ( class_exists( 'FPML_Translation_Manager' ) ? FPML_Translation_Manager::instance() : null );
		$this->job_enqueuer = FPML_Container::get( 'job_enqueuer' ) ?: ( class_exists( 'FPML_Job_Enqueuer' ) ? FPML_Job_Enqueuer::instance() : null );
		
		if ( $this->queue && method_exists( $this->queue, 'maybe_upgrade' ) ) {
			$this->queue->maybe_upgrade();
		}
		
		$this->maybe_disable_autoloaded_options();
		
		// TEST 5C: Aggiungi define_hooks - QUESTA È SOSPETTA!
		$this->define_hooks();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.2.0
	 *
	 * @return FPML_Plugin_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run setup tasks if needed (safe - called after everything is loaded).
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function maybe_run_setup() {
		// Check if setup is needed
		if ( ! get_option( 'fpml_needs_setup' ) ) {
			return;
		}

		// Check if already completed
		if ( get_option( 'fpml_setup_completed' ) ) {
			delete_option( 'fpml_needs_setup' );
			return;
		}

		// Now it's safe to run setup tasks
		try {
			$reason = self::detect_external_multilingual();

			// Register rewrites if not in assisted mode
			if ( ! $reason && class_exists( 'FPML_Rewrites' ) ) {
				FPML_Rewrites::instance()->register_rewrites();
			}

			// Install queue tables
			if ( $this->queue && method_exists( $this->queue, 'install' ) ) {
				$this->queue->install();
			}

			// Flush rewrite rules
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules();
			}

			// Mark as completed
			update_option( 'fpml_setup_completed', '1', false );
			delete_option( 'fpml_needs_setup' );
		} catch ( Exception $e ) {
			// Log error but don't break the site
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FPML setup error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Plugin activation callback.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function activate() {
		// SAFE ACTIVATION: Just set a flag, do nothing else
		// Actual setup will happen on first use
		update_option( 'fpml_needs_setup', '1', false );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Clear all scheduled events
		$events = array(
			'fpml_run_queue',
			'fpml_retry_failed',
			'fpml_resync_outdated',
			'fpml_cleanup_queue',
			'fpml_daily_content_scan',
			'fpml_health_check',
		);
		
		foreach ( $events as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			while ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
		
		// Clear single events with args (WordPress 5.1+)
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( 'fpml_reindex_post_type' );
			wp_unschedule_hook( 'fpml_reindex_taxonomy' );
		}
		
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
	 * Define hooks and bootstrap classes - VERSIONE SICURA.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function define_hooks() {
		load_plugin_textdomain( 'fp-multilanguage', false, dirname( plugin_basename( FPML_PLUGIN_FILE ) ) . '/languages' );

		// Classi base (testate e funzionanti)
		FPML_Settings::instance();
		FPML_Logger::instance();
		FPML_Glossary::instance();
		FPML_Strings_Override::instance();
		FPML_Strings_Scanner::instance();
		FPML_Export_Import::instance();

		if ( class_exists( 'FPML_Webhooks' ) ) {
			FPML_Webhooks::instance();
		}

		// SKIP Health_Check - Causa errore 500
		
		// TEST: Solo Auto_Translate (senza Auto_Detection)
		if ( class_exists( 'FPML_Auto_Translate' ) ) {
			FPML_Auto_Translate::instance();
		}
		
		// STOP QUI per test
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
	 * Handle post save events - DELEGATES to Translation Manager and Job Enqueuer.
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

		if ( ! $this->translation_manager ) {
			return;
		}

		if ( $this->translation_manager->is_creating_translation() ) {
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

		$target_post = $this->translation_manager->ensure_post_translation( $post );

		if ( ! $target_post || ! $this->job_enqueuer ) {
			return;
		}

		$this->job_enqueuer->enqueue_post_jobs( $post, $target_post, $update );
	}

	/**
	 * Handle created terms - DELEGATES to Translation Manager.
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
		if ( $this->is_assisted_mode() || ! $this->translation_manager ) {
			return;
		}

		$this->sync_term_translation( $term_id, $taxonomy );
	}

	/**
	 * Handle edited terms - DELEGATES to Translation Manager.
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
		if ( $this->is_assisted_mode() || ! $this->translation_manager ) {
			return;
		}

		$this->sync_term_translation( $term_id, $taxonomy );
	}

	/**
	 * Sync term translation - DELEGATES to Translation Manager and Job Enqueuer.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	protected function sync_term_translation( $term_id, $taxonomy ) {
		if ( ! $this->translation_manager || ! $this->job_enqueuer ) {
			return;
		}

		$taxonomies = get_taxonomies(
			array(
				'public' => true,
			),
			'names'
		);

		$custom_taxonomies = get_option( 'fpml_custom_translatable_taxonomies', array() );
		if ( ! empty( $custom_taxonomies ) ) {
			$taxonomies = array_merge( $taxonomies, $custom_taxonomies );
		}

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

		$target_term = $this->translation_manager->sync_term_translation( $term_id, $taxonomy );

		if ( $target_term ) {
			$this->job_enqueuer->enqueue_term_jobs( $term, $target_term );
		}
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

		$custom_post_types = get_option( 'fpml_custom_translatable_post_types', array() );
		if ( ! empty( $custom_post_types ) ) {
			$post_types = array_merge( $post_types, $custom_post_types );
		}

		$post_types = apply_filters( 'fpml_translatable_post_types', $post_types );

		return array_filter( array_map( 'sanitize_key', $post_types ) );
	}

	/**
	 * Reindex existing content - DELEGATES to Content Indexer.
	 *
	 * @since 0.2.0
	 *
	 * @return array|WP_Error Summary data.
	 */
	public function reindex_content() {
		if ( $this->is_assisted_mode() ) {
			return new WP_Error(
				'fpml_assisted_mode',
				__( 'La modalità assistita è attiva: la duplicazione e il reindex automatico sono disabilitati.', 'fp-multilanguage' )
			);
		}

		$indexer = FPML_Container::get( 'content_indexer' );

		if ( ! $indexer ) {
			$indexer = FPML_Content_Indexer::instance();
		}

		return $indexer->reindex_content();
	}

	/**
	 * Reindex specific post type - DELEGATES to Content Indexer.
	 *
	 * @since 0.4.0
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_post_type( $post_type ) {
		if ( $this->is_assisted_mode() ) {
			return array();
		}

		$indexer = FPML_Container::get( 'content_indexer' );

		if ( ! $indexer ) {
			$indexer = FPML_Content_Indexer::instance();
		}

		return $indexer->reindex_post_type( $post_type );
	}

	/**
	 * Reindex specific taxonomy - DELEGATES to Content Indexer.
	 *
	 * @since 0.4.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_taxonomy( $taxonomy ) {
		if ( $this->is_assisted_mode() ) {
			return array();
		}

		$indexer = FPML_Container::get( 'content_indexer' );

		if ( ! $indexer ) {
			$indexer = FPML_Content_Indexer::instance();
		}

		return $indexer->reindex_taxonomy( $taxonomy );
	}

	/**
	 * Build diagnostics snapshot - DELEGATES to Diagnostics service.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string,mixed>
	 */
	public function get_diagnostics_snapshot() {
		$diagnostics = FPML_Container::get( 'diagnostics' );

		if ( ! $diagnostics ) {
			$diagnostics = FPML_Diagnostics::instance();
		}

		return $diagnostics->get_snapshot( $this->assisted_mode, $this->assisted_reason );
	}

	/**
	 * Estimate queue cost - DELEGATES to Cost Estimator service.
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

		$cost_estimator = FPML_Container::get( 'cost_estimator' );

		if ( ! $cost_estimator ) {
			$cost_estimator = FPML_Cost_Estimator::instance();
		}

		return $cost_estimator->estimate( $states, $max_jobs );
	}

	/**
	 * Get queue job text - DELEGATES to Cost Estimator.
	 *
	 * @since 0.2.0
	 *
	 * @param object $job Queue job entry.
	 *
	 * @return string
	 */
	public function get_queue_job_text( $job ) {
		$cost_estimator = FPML_Container::get( 'cost_estimator' );

		if ( ! $cost_estimator ) {
			$cost_estimator = FPML_Cost_Estimator::instance();
		}

		return $cost_estimator->get_queue_job_text( $job );
	}

	/**
	 * Get queue cleanup states - DELEGATES to Diagnostics.
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
	 * Get queue age summary - DELEGATES to Diagnostics.
	 *
	 * @since 0.3.1
	 *
	 * @return array
	 */
	public function get_queue_age_summary() {
		if ( $this->is_assisted_mode() ) {
			return array();
		}

		$diagnostics = FPML_Container::get( 'diagnostics' );

		if ( ! $diagnostics ) {
			$diagnostics = FPML_Diagnostics::instance();
		}

	return $diagnostics->get_queue_age_summary();
}

/**
 * Clean up orphaned pair references when a post is deleted.
 *
 * @since 0.4.1
 *
 * @param int $post_id Post ID being deleted.
 *
 * @return void
 */
public function handle_delete_post( $post_id ) {
	if ( $this->is_assisted_mode() ) {
		return;
	}

	// If this is a translation, remove pair_id from source
	$source_id = get_post_meta( $post_id, '_fpml_pair_source_id', true );
	if ( $source_id ) {
		delete_post_meta( $source_id, '_fpml_pair_id' );
	}

	// If this is a source, optionally delete translation too
	$translation_id = get_post_meta( $post_id, '_fpml_pair_id', true );
	if ( $translation_id ) {
		// Remove pair reference from translation
		delete_post_meta( $translation_id, '_fpml_pair_source_id' );
		
		// Optionally trash the translation (configurable)
		$auto_delete = apply_filters( 'fpml_auto_delete_translation_on_source_delete', false );
		if ( $auto_delete ) {
			wp_trash_post( $translation_id );
		}
	}
}

/**
 * Clean up orphaned pair references when a term is deleted.
 *
 * @since 0.4.1
 *
 * @param int    $term_id  Term ID being deleted.
 * @param int    $tt_id    Term taxonomy ID.
 * @param string $taxonomy Taxonomy slug.
 *
 * @return void
 */
public function handle_delete_term( $term_id, $tt_id, $taxonomy ) {
	if ( $this->is_assisted_mode() ) {
		return;
	}

	// If this is a translation, remove pair_id from source
	$source_id = get_term_meta( $term_id, '_fpml_pair_source_id', true );
	if ( $source_id ) {
		delete_term_meta( $source_id, '_fpml_pair_id' );
	}

	// If this is a source, remove pair reference from translation
	$translation_id = get_term_meta( $term_id, '_fpml_pair_id', true );
	if ( $translation_id ) {
		delete_term_meta( $translation_id, '_fpml_pair_source_id' );
		
		// Optionally delete the translation term
		$auto_delete = apply_filters( 'fpml_auto_delete_translation_term_on_source_delete', false );
		if ( $auto_delete ) {
			wp_delete_term( $translation_id, $taxonomy );
		}
	}
}
}
