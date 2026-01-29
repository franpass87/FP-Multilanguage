<?php
/**
 * Experiences Whitelist - Manages whitelist for FP Experiences post types, taxonomies, and meta.
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
 * Manages whitelist for FP Experiences integration.
 *
 * @since 0.10.0
 */
class ExperiencesWhitelist {
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
	 * FP Meeting Point meta keys - TRANSLATABLE.
	 */
	const FP_MP_ADDRESS      = '_fp_mp_address';
	const FP_MP_NOTES        = '_fp_mp_notes';
	const FP_MP_OPENING_HOURS = '_fp_mp_opening_hours';

	/**
	 * Add FP Experiences post types to translatable post types.
	 *
	 * @param array $post_types Current translatable post types.
	 * @return array Extended post types.
	 */
	public function add_fp_experiences_post_types( $post_types ) {
		// Add fp_experience
		if ( ! in_array( 'fp_experience', $post_types, true ) ) {
			$post_types[] = 'fp_experience';
		}

		// Add fp_meeting_point
		if ( ! in_array( 'fp_meeting_point', $post_types, true ) ) {
			$post_types[] = 'fp_meeting_point';
		}

		return $post_types;
	}

	/**
	 * Add fp_exp_language taxonomy to translatable taxonomies.
	 *
	 * @param array $taxonomies Current translatable taxonomies.
	 * @return array Extended taxonomies.
	 */
	public function add_fp_exp_language_taxonomy( $taxonomies ) {
		if ( ! in_array( 'fp_exp_language', $taxonomies, true ) ) {
			$taxonomies[] = 'fp_exp_language';
		}
		return $taxonomies;
	}

	/**
	 * Add FP-Experiences meta keys to translatable whitelist.
	 *
	 * @param array $whitelist Current meta whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_fp_experiences_meta_to_whitelist( $whitelist ) {
		$fp_exp_meta = array(
			// Experience translatable meta
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
			// Meeting Point translatable meta
			self::FP_MP_ADDRESS,
			self::FP_MP_NOTES,
			self::FP_MP_OPENING_HOURS,
		);

		return array_merge( $whitelist, $fp_exp_meta );
	}
}















