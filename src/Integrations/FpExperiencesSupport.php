<?php
/**
 * FP Experiences Integration.
 *
 * Provides bidirectional integration between FP-Multilanguage and FP-Experiences:
 * - Translate fp_experience post type (title, content, excerpt)
 * - Translate fp_meeting_point post type (title, address, notes, opening hours)
 * - Translate fp_exp_language taxonomy (terms names and descriptions)
 * - Sync Experience meta fields (descriptions, highlights, inclusions, etc. are translatable)
 * - Sync Meeting Point meta fields (address, notes, opening hours are translatable)
 * - Ensure fp_experience and fp_meeting_point post types are translatable
 *
 * @package FP_Multilanguage
 * @since 0.9.1
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Integrations;

use FP\Multilanguage\Integrations\Experiences\ExperiencesWhitelist;
use FP\Multilanguage\Integrations\Experiences\ExperienceMetaSyncer;
use FP\Multilanguage\Integrations\Experiences\MeetingPointMetaSyncer;
use FP\Multilanguage\Integrations\Experiences\ExperiencesEnqueuer;
use FP\Multilanguage\Integrations\Experiences\ExperiencesLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FP Experiences integration class.
 *
 * @since 0.9.1
 * @since 0.10.0 Refactored to use modular components.
 */
class FpExperiencesSupport {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Whitelist manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ExperiencesWhitelist
	 */
	protected $whitelist;

	/**
	 * Experience meta syncer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ExperienceMetaSyncer
	 */
	protected $experience_syncer;

	/**
	 * Meeting point meta syncer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MeetingPointMetaSyncer
	 */
	protected $meeting_point_syncer;

	/**
	 * Get singleton instance.
	 *
	 * @return self
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
		// Initialize logger and enqueuer first (they have no dependencies)
		$logger   = new ExperiencesLogger();
		$enqueuer = new ExperiencesEnqueuer( $logger );

		// Initialize syncer modules
		$this->experience_syncer    = new ExperienceMetaSyncer( $enqueuer, $logger );
		$this->meeting_point_syncer = new MeetingPointMetaSyncer( $enqueuer, $logger );

		// Initialize whitelist
		$this->whitelist = new ExperiencesWhitelist();
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		// Only if FP-Experiences is active
		if ( ! $this->is_fp_experiences_active() ) {
			return;
		}

		// Add post types to translatable post types
		add_filter( '\FPML_translatable_post_types', array( $this->whitelist, 'add_fp_experiences_post_types' ) );

		// Add taxonomy to translatable taxonomies
		add_filter( '\FPML_translatable_taxonomies', array( $this->whitelist, 'add_fp_exp_language_taxonomy' ) );

		// Add FP-Experiences meta to translatable whitelist
		add_filter( '\FPML_meta_whitelist', array( $this->whitelist, 'add_fp_experiences_meta_to_whitelist' ) );

		// Sync experience meta after translation
		add_action( 'fpml_after_translation_saved', array( $this->experience_syncer, 'sync_experience_meta_to_translation' ), 10, 2 );

		// Sync meeting point meta after translation
		add_action( 'fpml_after_translation_saved', array( $this->meeting_point_syncer, 'sync_meeting_point_meta_to_translation' ), 10, 2 );
	}

	/**
	 * Add FP Experiences post types to translatable post types.
	 *
	 * @param array $post_types Current translatable post types.
	 * @return array Extended post types.
	 */
	public function add_fp_experiences_post_types( $post_types ) {
		return $this->whitelist->add_fp_experiences_post_types( $post_types );
	}

	/**
	 * Add fp_exp_language taxonomy to translatable taxonomies.
	 *
	 * @param array $taxonomies Current translatable taxonomies.
	 * @return array Extended taxonomies.
	 */
	public function add_fp_exp_language_taxonomy( $taxonomies ) {
		return $this->whitelist->add_fp_exp_language_taxonomy( $taxonomies );
	}

	/**
	 * Add FP-Experiences meta keys to translatable whitelist.
	 *
	 * @param array $whitelist Current meta whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_fp_experiences_meta_to_whitelist( $whitelist ) {
		return $this->whitelist->add_fp_experiences_meta_to_whitelist( $whitelist );
	}

	/**
	 * Check if FP-Experiences is active.
	 *
	 * @return bool
	 */
	protected function is_fp_experiences_active() {
		return class_exists( '\FP_Exp\Plugin' ) || 
		       defined( 'FP_EXP_VERSION' ) ||
		       defined( 'FP_EXP_PLUGIN_FILE' );
	}

	/**
	 * Sync experience meta from original to translated post.
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 */
	public function sync_experience_meta_to_translation( $translated_id, $original_id ) {
		$this->experience_syncer->sync_experience_meta_to_translation( $translated_id, $original_id );
	}

	/**
	 * Sync meeting point meta from original to translated post.
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 */
	public function sync_meeting_point_meta_to_translation( $translated_id, $original_id ) {
		$this->meeting_point_syncer->sync_meeting_point_meta_to_translation( $translated_id, $original_id );
	}
}
