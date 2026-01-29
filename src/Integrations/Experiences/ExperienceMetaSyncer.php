<?php
/**
 * Experience Meta Syncer - Syncs experience meta fields to translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Integrations\Experiences;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs experience meta fields from original to translated posts.
 *
 * @since 0.10.0
 */
class ExperienceMetaSyncer {
	/**
	 * FP Experiences meta keys - TRANSLATABLE.
	 */
	const FP_EXP_SHORT_DESC      = '_fp_short_desc';
	const FP_EXP_HIGHLIGHTS      = '_fp_highlights';
	const FP_EXP_MEETING_POINT   = '_fp_meeting_point';
	const FP_EXP_INCLUSIONS      = '_fp_inclusions';
	const FP_EXP_EXCLUSIONS      = '_fp_exclusions';
	const FP_EXP_WHAT_TO_BRING   = '_fp_what_to_bring';
	const FP_EXP_NOTES           = '_fp_notes';
	const FP_EXP_FAQ             = '_fp_faq';
	const FP_EXP_RULES_CHILDREN  = '_fp_rules_children';
	const FP_EXP_TICKET_TYPES    = '_fp_ticket_types';
	const FP_EXP_ADDONS          = '_fp_addons';
	const FP_EXP_POLICY_CANCEL   = '_fp_policy_cancel';
	const FP_EXP_META_TITLE      = '_fp_meta_title';
	const FP_EXP_META_DESCRIPTION = '_fp_meta_description';
	const FP_EXP_SCHEMA_MANUAL   = '_fp_schema_manual';

	/**
	 * FP Experiences meta keys - COPY (non-translatable).
	 */
	const FP_EXP_MEETING_POINT_ID    = '_fp_meeting_point_id';
	const FP_EXP_MEETING_POINT_ALT   = '_fp_meeting_point_alt';
	const FP_EXP_AGE_MIN             = '_fp_age_min';
	const FP_EXP_AGE_MAX             = '_fp_age_max';
	const FP_EXP_MIN_PARTY           = '_fp_min_party';
	const FP_EXP_CAPACITY_SLOT       = '_fp_capacity_slot';
	const FP_EXP_RESOURCES           = '_fp_resources';
	const FP_EXP_SCHEDULE_RULES      = '_fp_schedule_rules';
	const FP_EXP_SCHEDULE_EXCEPTIONS = '_fp_schedule_exceptions';
	const FP_EXP_LEAD_TIME_HOURS     = '_fp_lead_time_hours';
	const FP_EXP_BUFFER_BEFORE       = '_fp_buffer_before_minutes';
	const FP_EXP_BUFFER_AFTER        = '_fp_buffer_after_minutes';
	const FP_EXP_PAGE_ID             = '_fp_exp_page_id';
	const FP_EXP_PRICING             = '_fp_exp_pricing';
	const FP_EXP_AVAILABILITY        = '_fp_exp_availability';
	const FP_EXP_RECURRENCE          = '_fp_exp_recurrence';
	const FP_EXP_BASE_PRICE          = '_fp_base_price';
	const FP_EXP_PRICING_RULES       = '_fp_pricing_rules';
	const FP_EXP_DURATION_MINUTES    = '_fp_duration_minutes';
	const FP_EXP_LANGUAGES           = '_fp_languages';
	const FP_EXP_COGNITIVE_BIASES    = '_fp_cognitive_biases';
	const FP_EXP_GALLERY_IDS         = '_fp_gallery_ids';
	const FP_EXP_GALLERY_VIDEO_URL   = '_fp_gallery_video_url';
	const FP_EXP_HERO_IMAGE_ID       = '_fp_hero_image_id';
	const FP_EXP_USE_RTB             = '_fp_use_rtb';

	/**
	 * Experiences enqueuer instance.
	 *
	 * @var ExperiencesEnqueuer
	 */
	protected $enqueuer;

	/**
	 * Experiences logger instance.
	 *
	 * @var ExperiencesLogger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param ExperiencesEnqueuer $enqueuer Enqueuer instance.
	 * @param ExperiencesLogger   $logger   Logger instance.
	 */
	public function __construct( ExperiencesEnqueuer $enqueuer, ExperiencesLogger $logger ) {
		$this->enqueuer = $enqueuer;
		$this->logger   = $logger;
	}

	/**
	 * Sync experience meta from original to translated post.
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 */
	public function sync_experience_meta_to_translation( $translated_id, $original_id ) {
		if ( ! $translated_id || ! $original_id ) {
			return;
		}

		// Only sync for fp_experience post type
		$original_post = get_post( $original_id );
		if ( ! $original_post || 'fp_experience' !== $original_post->post_type ) {
			return;
		}

		$synced_count = 0;

		// TRANSLATABLE META FIELDS - Copy and enqueue for translation
		$translatable_meta = array(
			self::FP_EXP_SHORT_DESC,
			self::FP_EXP_HIGHLIGHTS,
			self::FP_EXP_MEETING_POINT,
			self::FP_EXP_INCLUSIONS,
			self::FP_EXP_EXCLUSIONS,
			self::FP_EXP_WHAT_TO_BRING,
			self::FP_EXP_NOTES,
			self::FP_EXP_FAQ,
			self::FP_EXP_RULES_CHILDREN,
			self::FP_EXP_TICKET_TYPES,
			self::FP_EXP_ADDONS,
			self::FP_EXP_POLICY_CANCEL,
			self::FP_EXP_META_TITLE,
			self::FP_EXP_META_DESCRIPTION,
			self::FP_EXP_SCHEMA_MANUAL,
		);

		foreach ( $translatable_meta as $meta_key ) {
			$original_value = get_post_meta( $original_id, $meta_key, true );
			$translated_value = get_post_meta( $translated_id, $meta_key, true );
			
			if ( empty( $translated_value ) && ! empty( $original_value ) ) {
				// Copy original value first
				update_post_meta( $translated_id, $meta_key, $original_value );
				// Enqueue for translation
				$this->enqueuer->enqueue_experiences_meta_translation( $translated_id, $meta_key, $original_value, $original_id );
				$synced_count++;
			}
		}

		// COPY META FIELDS - Copy directly without translation
		$copy_meta = array(
			self::FP_EXP_MEETING_POINT_ID,
			self::FP_EXP_MEETING_POINT_ALT,
			self::FP_EXP_AGE_MIN,
			self::FP_EXP_AGE_MAX,
			self::FP_EXP_MIN_PARTY,
			self::FP_EXP_CAPACITY_SLOT,
			self::FP_EXP_RESOURCES,
			self::FP_EXP_SCHEDULE_RULES,
			self::FP_EXP_SCHEDULE_EXCEPTIONS,
			self::FP_EXP_LEAD_TIME_HOURS,
			self::FP_EXP_BUFFER_BEFORE,
			self::FP_EXP_BUFFER_AFTER,
			self::FP_EXP_PAGE_ID,
			self::FP_EXP_PRICING,
			self::FP_EXP_AVAILABILITY,
			self::FP_EXP_RECURRENCE,
			self::FP_EXP_BASE_PRICE,
			self::FP_EXP_PRICING_RULES,
			self::FP_EXP_DURATION_MINUTES,
			self::FP_EXP_LANGUAGES,
			self::FP_EXP_COGNITIVE_BIASES,
			self::FP_EXP_GALLERY_IDS,
			self::FP_EXP_GALLERY_VIDEO_URL,
			self::FP_EXP_HERO_IMAGE_ID,
			self::FP_EXP_USE_RTB,
		);

		foreach ( $copy_meta as $meta_key ) {
			$value = get_post_meta( $original_id, $meta_key, true );
			if ( ! empty( $value ) || ( is_numeric( $value ) && 0 === (int) $value ) ) {
				update_post_meta( $translated_id, $meta_key, $value );
				$synced_count++;
			}
		}

		/**
		 * Fires after experience meta sync.
		 *
		 * @param int $translated_id Translated post ID.
		 * @param int $original_id   Original post ID.
		 * @param int $synced_count  Number of meta fields synced.
		 */
		do_action( 'fpml_experiences_meta_synced', $translated_id, $original_id, $synced_count );

		$this->logger->log_sync( $translated_id, "Experience meta sync completed: {$synced_count} meta fields" );
	}
}















