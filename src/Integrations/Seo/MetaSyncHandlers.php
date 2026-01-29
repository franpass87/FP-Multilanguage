<?php
/**
 * SEO Meta Sync Handlers - Handles synchronization of different SEO meta types.
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
 * Handles synchronization of different SEO meta types.
 *
 * @since 0.10.0
 */
class MetaSyncHandlers {
	/**
	 * Translation enqueuer instance.
	 *
	 * @var TranslationEnqueuer
	 */
	protected TranslationEnqueuer $enqueuer;

	/**
	 * Constructor.
	 *
	 * @param TranslationEnqueuer $enqueuer Translation enqueuer instance.
	 */
	public function __construct( TranslationEnqueuer $enqueuer ) {
		$this->enqueuer = $enqueuer;
	}

	/**
	 * Sync core SEO meta (description, canonical, robots).
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 * @return int Number of fields synced.
	 */
	public function sync_core_seo_meta( int $translated_id, int $original_id ): int {
		$count = 0;

		// SEO Title - TRANSLATE
		$original_title = get_post_meta( $original_id, MetaWhitelist::FP_SEO_TITLE, true );
		$translated_title = get_post_meta( $translated_id, MetaWhitelist::FP_SEO_TITLE, true );
		
		if ( empty( $translated_title ) && ! empty( $original_title ) ) {
			// Copy original value first
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_TITLE, $original_title );
			// Enqueue for translation
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_TITLE, $original_title, $original_id );
			$count++;
		}

		// Meta Description - TRANSLATE
		$original_description = get_post_meta( $original_id, MetaWhitelist::FP_SEO_META_DESCRIPTION, true );
		$translated_description = get_post_meta( $translated_id, MetaWhitelist::FP_SEO_META_DESCRIPTION, true );
		
		if ( empty( $translated_description ) && ! empty( $original_description ) ) {
			// Copy original value first
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_META_DESCRIPTION, $original_description );
			// Enqueue for translation
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_META_DESCRIPTION, $original_description, $original_id );
			$count++;
		}

		// Robots - COPY (same for all languages)
		$original_robots = get_post_meta( $original_id, MetaWhitelist::FP_SEO_META_ROBOTS, true );
		if ( ! empty( $original_robots ) ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_META_ROBOTS, $original_robots );
			$count++;
		}

		// Canonical - UPDATE to EN URL
		$translated_url = get_permalink( $translated_id );
		if ( $translated_url ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_META_CANONICAL, $translated_url );
			$count++;
		}

		// Exclude flag - COPY
		$exclude = get_post_meta( $original_id, MetaWhitelist::FP_SEO_META_EXCLUDE, true );
		if ( ! empty( $exclude ) ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_META_EXCLUDE, $exclude );
			$count++;
		}

		return $count;
	}

	/**
	 * Sync keywords meta.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 * @return int Number of fields synced.
	 */
	public function sync_keywords_meta( int $translated_id, int $original_id ): int {
		$count = 0;

		// Focus keyword - TRANSLATE
		$focus_keyword = get_post_meta( $original_id, MetaWhitelist::FP_SEO_FOCUS_KEYWORD, true );
		if ( ! empty( $focus_keyword ) ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_FOCUS_KEYWORD, $focus_keyword );
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_FOCUS_KEYWORD, $focus_keyword, $original_id );
			$count++;
		}

		// Secondary keywords - TRANSLATE
		$secondary = get_post_meta( $original_id, MetaWhitelist::FP_SEO_SECONDARY_KEYWORDS, true );
		if ( ! empty( $secondary ) ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_SECONDARY_KEYWORDS, $secondary );
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_SECONDARY_KEYWORDS, $secondary, $original_id );
			$count++;
		}

		// Multiple keywords - TRANSLATE (JSON array)
		$multiple = get_post_meta( $original_id, MetaWhitelist::FP_SEO_MULTIPLE_KEYWORDS, true );
		if ( ! empty( $multiple ) ) {
			// Copy structure first
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_MULTIPLE_KEYWORDS, $multiple );
			// Enqueue for translation (will handle JSON structure)
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_MULTIPLE_KEYWORDS, $multiple, $original_id );
			$count++;
		}

		return $count;
	}

	/**
	 * Sync AI features meta (QA, entities, embeddings).
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 * @return int Number of fields synced.
	 */
	public function sync_ai_features_meta( int $translated_id, int $original_id ): int {
		$count = 0;

		// QA Pairs - DON'T copy (needs re-generation for EN)
		// Conversational Variants - DON'T copy
		// Embeddings - DON'T copy (language-specific)
		
		// Entities - COPY (names are often international)
		$entities = get_post_meta( $original_id, '_fp_seo_entities', true );
		if ( ! empty( $entities ) ) {
			update_post_meta( $translated_id, '_fp_seo_entities', $entities );
			$count++;
		}

		// Relationships - COPY structure
		$relationships = get_post_meta( $original_id, '_fp_seo_relationships', true );
		if ( ! empty( $relationships ) ) {
			update_post_meta( $translated_id, '_fp_seo_relationships', $relationships );
			$count++;
		}

		return $count;
	}

	/**
	 * Sync GEO and freshness meta.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 * @return int Number of fields synced.
	 */
	public function sync_geo_freshness_meta( int $translated_id, int $original_id ): int {
		$count = 0;

		// Update frequency - COPY
		$frequency = get_post_meta( $original_id, '_fp_seo_update_frequency', true );
		if ( ! empty( $frequency ) ) {
			update_post_meta( $translated_id, '_fp_seo_update_frequency', $frequency );
			$count++;
		}

		// Next review - COPY
		$next_review = get_post_meta( $original_id, '_fp_seo_next_review', true );
		if ( ! empty( $next_review ) ) {
			update_post_meta( $translated_id, '_fp_seo_next_review', $next_review );
			$count++;
		}

		// Fact checked - COPY
		$fact_checked = get_post_meta( $original_id, '_fp_seo_fact_checked', true );
		if ( ! empty( $fact_checked ) ) {
			update_post_meta( $translated_id, '_fp_seo_fact_checked', $fact_checked );
			$count++;
		}

		// Sources - COPY (URLs are language-agnostic)
		$sources = get_post_meta( $original_id, '_fp_seo_sources', true );
		if ( ! empty( $sources ) ) {
			update_post_meta( $translated_id, '_fp_seo_sources', $sources );
			$count++;
		}

		// GEO Claims - TRANSLATE
		$claims = get_post_meta( $original_id, MetaWhitelist::FP_SEO_GEO_CLAIMS, true );
		if ( ! empty( $claims ) ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_GEO_CLAIMS, $claims );
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_GEO_CLAIMS, $claims, $original_id );
			$count++;
		}

		// GEO No AI Reuse - COPY
		$no_ai_reuse = get_post_meta( $original_id, '_fp_seo_geo_no_ai_reuse', true );
		if ( ! empty( $no_ai_reuse ) ) {
			update_post_meta( $translated_id, '_fp_seo_geo_no_ai_reuse', $no_ai_reuse );
			$count++;
		}

		// GEO Expose - COPY
		$expose = get_post_meta( $original_id, '_fp_seo_geo_expose', true );
		if ( ! empty( $expose ) ) {
			update_post_meta( $translated_id, '_fp_seo_geo_expose', $expose );
			$count++;
		}

		// Key Facts - TRANSLATE
		$key_facts = get_post_meta( $original_id, MetaWhitelist::FP_SEO_KEY_FACTS, true );
		if ( ! empty( $key_facts ) ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_KEY_FACTS, $key_facts );
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_KEY_FACTS, $key_facts, $original_id );
			$count++;
		}

		// Content Version - COPY (version number)
		$content_version = get_post_meta( $original_id, '_fp_seo_content_version', true );
		if ( ! empty( $content_version ) ) {
			update_post_meta( $translated_id, '_fp_seo_content_version', $content_version );
			$count++;
		}

		// Changelog - TRANSLATE (contains text)
		$changelog = get_post_meta( $original_id, MetaWhitelist::FP_SEO_CHANGELOG, true );
		if ( ! empty( $changelog ) ) {
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_CHANGELOG, $changelog );
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_CHANGELOG, $changelog, $original_id );
			$count++;
		}

		return $count;
	}

	/**
	 * Sync social media meta.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 * @return int Number of fields synced.
	 */
	public function sync_social_meta( int $translated_id, int $original_id ): int {
		$count = 0;

		// Social meta (OG, Twitter) - TRANSLATE
		$social_meta = get_post_meta( $original_id, MetaWhitelist::FP_SEO_SOCIAL_META, true );
		
		if ( ! empty( $social_meta ) && is_array( $social_meta ) ) {
			// Copy structure first
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_SOCIAL_META, $social_meta );
			// Enqueue entire array for translation (Processor will handle nested fields recursively)
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_SOCIAL_META, $social_meta, $original_id );
			$count++;
		}

		return $count;
	}

	/**
	 * Sync schema meta (FAQ, HowTo).
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 * @return int Number of fields synced.
	 */
	public function sync_schema_meta( int $translated_id, int $original_id ): int {
		$count = 0;

		// FAQ questions - TRANSLATE
		$faq = get_post_meta( $original_id, MetaWhitelist::FP_SEO_FAQ_QUESTIONS, true );
		if ( ! empty( $faq ) && is_array( $faq ) ) {
			// Copy structure first
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_FAQ_QUESTIONS, $faq );
			// Enqueue entire FAQ structure for translation
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_FAQ_QUESTIONS, $faq, $original_id );
			$count++;
		}

		// HowTo - TRANSLATE
		$howto = get_post_meta( $original_id, MetaWhitelist::FP_SEO_HOWTO, true );
		if ( ! empty( $howto ) && is_array( $howto ) ) {
			// Copy structure first
			update_post_meta( $translated_id, MetaWhitelist::FP_SEO_HOWTO, $howto );
			// Enqueue entire HowTo structure for translation
			$this->enqueuer->enqueue_seo_meta_translation( $translated_id, MetaWhitelist::FP_SEO_HOWTO, $howto, $original_id );
			$count++;
		}

		return $count;
	}
}
















