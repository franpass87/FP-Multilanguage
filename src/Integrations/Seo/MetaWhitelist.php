<?php
/**
 * SEO Meta Whitelist - Manages FP-SEO meta keys in translation whitelist.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages FP-SEO meta keys in translation whitelist.
 *
 * @since 0.10.0
 */
class MetaWhitelist {
	/**
	 * FP SEO meta keys - Core SEO.
	 */
	const FP_SEO_TITLE            = '_fp_seo_title';
	const FP_SEO_META_DESCRIPTION = '_fp_seo_meta_description';
	const FP_SEO_META_CANONICAL   = '_fp_seo_meta_canonical';
	const FP_SEO_META_ROBOTS      = '_fp_seo_meta_robots';
	const FP_SEO_META_EXCLUDE     = '_fp_seo_performance_exclude';
	const FP_SEO_FOCUS_KEYWORD    = '_fp_seo_focus_keyword';
	const FP_SEO_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';
	const FP_SEO_MULTIPLE_KEYWORDS = '_fp_seo_multiple_keywords';
	
	/**
	 * FP SEO meta keys - GEO Claims.
	 */
	const FP_SEO_GEO_CLAIMS = '_fp_seo_geo_claims';
	
	/**
	 * FP SEO meta keys - Key Facts.
	 */
	const FP_SEO_KEY_FACTS = '_fp_seo_key_facts';
	
	/**
	 * FP SEO meta keys - Social.
	 */
	const FP_SEO_SOCIAL_META = '_fp_seo_social_meta';
	
	/**
	 * FP SEO meta keys - Schema.
	 */
	const FP_SEO_FAQ_QUESTIONS = '_fp_seo_faq_questions';
	const FP_SEO_HOWTO = '_fp_seo_howto';
	
	/**
	 * FP SEO meta keys - Changelog.
	 */
	const FP_SEO_CHANGELOG = '_fp_seo_changelog';

	/**
	 * Add FP-SEO meta keys to translatable whitelist.
	 *
	 * @since 0.10.0
	 *
	 * @param array $whitelist Current meta whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_fp_seo_meta_to_whitelist( array $whitelist ): array {
		$fp_seo_meta = array(
			// Core SEO
			self::FP_SEO_TITLE,
			self::FP_SEO_META_DESCRIPTION,
			self::FP_SEO_FOCUS_KEYWORD,
			self::FP_SEO_SECONDARY_KEYWORDS,
			self::FP_SEO_MULTIPLE_KEYWORDS,
			
			// GEO Claims (translatable)
			self::FP_SEO_GEO_CLAIMS,
			
			// Key Facts (translatable)
			self::FP_SEO_KEY_FACTS,
			
			// Social meta (will be handled specially in sync)
			self::FP_SEO_SOCIAL_META,
			
			// Schema (will be handled specially in sync)
			self::FP_SEO_FAQ_QUESTIONS,
			self::FP_SEO_HOWTO,
			
			// Additional translatable fields
			self::FP_SEO_CHANGELOG,
		);

		return array_merge( $whitelist, $fp_seo_meta );
	}
}
















