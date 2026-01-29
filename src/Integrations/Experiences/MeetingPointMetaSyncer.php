<?php
/**
 * Meeting Point Meta Syncer - Syncs meeting point meta fields to translations.
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
 * Syncs meeting point meta fields from original to translated posts.
 *
 * @since 0.10.0
 */
class MeetingPointMetaSyncer {
	/**
	 * FP Meeting Point meta keys - TRANSLATABLE.
	 */
	const FP_MP_ADDRESS      = '_fp_mp_address';
	const FP_MP_NOTES        = '_fp_mp_notes';
	const FP_MP_OPENING_HOURS = '_fp_mp_opening_hours';

	/**
	 * FP Meeting Point meta keys - COPY (non-translatable).
	 */
	const FP_MP_LAT   = '_fp_mp_lat';
	const FP_MP_LNG   = '_fp_mp_lng';
	const FP_MP_PHONE = '_fp_mp_phone';
	const FP_MP_EMAIL = '_fp_mp_email';

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
	 * Sync meeting point meta from original to translated post.
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 */
	public function sync_meeting_point_meta_to_translation( $translated_id, $original_id ) {
		if ( ! $translated_id || ! $original_id ) {
			return;
		}

		// Only sync for fp_meeting_point post type
		$original_post = get_post( $original_id );
		if ( ! $original_post || 'fp_meeting_point' !== $original_post->post_type ) {
			return;
		}

		$synced_count = 0;

		// TRANSLATABLE META FIELDS - Copy and enqueue for translation
		$translatable_meta = array(
			self::FP_MP_ADDRESS,
			self::FP_MP_NOTES,
			self::FP_MP_OPENING_HOURS,
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
			self::FP_MP_LAT,
			self::FP_MP_LNG,
			self::FP_MP_PHONE,
			self::FP_MP_EMAIL,
		);

		foreach ( $copy_meta as $meta_key ) {
			$value = get_post_meta( $original_id, $meta_key, true );
			if ( ! empty( $value ) || ( is_numeric( $value ) && 0 === (int) $value ) ) {
				update_post_meta( $translated_id, $meta_key, $value );
				$synced_count++;
			}
		}

		$this->logger->log_sync( $translated_id, "Meeting point meta sync completed: {$synced_count} meta fields" );
	}
}















