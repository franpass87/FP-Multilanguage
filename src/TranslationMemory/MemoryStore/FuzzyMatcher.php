<?php
/**
 * Translation Memory Store Fuzzy Matcher - Finds similar translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\TranslationMemory\MemoryStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds similar translations using fuzzy matching.
 *
 * @since 0.10.0
 */
class FuzzyMatcher {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Text normalizer instance.
	 *
	 * @var TextNormalizer
	 */
	protected TextNormalizer $normalizer;

	/**
	 * Constructor.
	 *
	 * @param string          $table     Table name.
	 * @param TextNormalizer  $normalizer Text normalizer instance.
	 */
	public function __construct( string $table, TextNormalizer $normalizer ) {
		$this->table = $table;
		$this->normalizer = $normalizer;
	}

	/**
	 * Find similar translations using fuzzy matching.
	 *
	 * @since 0.10.0
	 *
	 * @param string $source      Source text.
	 * @param float  $threshold   Similarity threshold (0.0-1.0), default 0.75.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param int    $limit       Maximum results to return, default 5.
	 * @return array Array of matches with confidence scores.
	 */
	public function find_similar( string $source, float $threshold = 0.75, string $source_lang = 'it', string $target_lang = 'en', int $limit = 5 ): array {
		global $wpdb;

		// Normalize source text for comparison
		$normalized_source = $this->normalizer->normalize( $source );

		// Get candidates using FULLTEXT search first (fast)
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, source_text, target_text, use_count, quality_score,
					MATCH(source_text) AGAINST(%s) as relevance
				FROM {$this->table} 
				WHERE source_lang = %s AND target_lang = %s
					AND MATCH(source_text) AGAINST(%s IN NATURAL LANGUAGE MODE)
				ORDER BY relevance DESC
				LIMIT 20",
				$source,
				$source_lang,
				$target_lang,
				$source
			),
			ARRAY_A
		);

		if ( empty( $candidates ) ) {
			return array();
		}

		// Calculate fuzzy match scores for each candidate
		$matches = array();
		foreach ( $candidates as $candidate ) {
			$similarity = $this->calculate_similarity( $normalized_source, $this->normalizer->normalize( $candidate['source_text'] ) );
			
			// Apply confidence scoring
			$confidence = $this->calculate_confidence( $similarity, $candidate );

			if ( $confidence >= $threshold ) {
				$matches[] = array(
					'source_text'  => $candidate['source_text'],
					'target_text'  => $candidate['target_text'],
					'similarity'   => $similarity,
					'confidence'   => $confidence,
					'use_count'    => (int) ( $candidate['use_count'] ?? 1 ),
					'quality_score' => isset( $candidate['quality_score'] ) ? (int) $candidate['quality_score'] : null,
				);
			}
		}

		// Sort by confidence (highest first)
		usort( $matches, function( $a, $b ) {
			return $b['confidence'] <=> $a['confidence'];
		} );

		// Return top results
		return array_slice( $matches, 0, $limit );
	}

	/**
	 * Calculate text similarity using multiple algorithms.
	 *
	 * @since 0.10.0
	 *
	 * @param string $text1 First text.
	 * @param string $text2 Second text.
	 * @return float Similarity score (0.0-1.0).
	 */
	protected function calculate_similarity( string $text1, string $text2 ): float {
		// If texts are identical, return 1.0
		if ( $text1 === $text2 ) {
			return 1.0;
		}

		// Use similar_text for word-level similarity
		$percent = 0.0;
		similar_text( $text1, $text2, $percent );
		$similar_text_score = $percent / 100.0;

		// Use Levenshtein distance for character-level similarity
		$max_len = max( mb_strlen( $text1, 'UTF-8' ), mb_strlen( $text2, 'UTF-8' ) );
		if ( $max_len === 0 ) {
			return 1.0;
		}

		$levenshtein_distance = levenshtein( $text1, $text2 );
		$levenshtein_score = 1.0 - ( $levenshtein_distance / $max_len );

		// Combine both scores (weighted average)
		$combined_score = ( $similar_text_score * 0.6 ) + ( $levenshtein_score * 0.4 );

		return max( 0.0, min( 1.0, $combined_score ) );
	}

	/**
	 * Calculate confidence score based on similarity and metadata.
	 *
	 * @since 0.10.0
	 *
	 * @param float $similarity Similarity score (0.0-1.0).
	 * @param array $candidate  Candidate translation data.
	 * @return float Confidence score (0.0-1.0).
	 */
	protected function calculate_confidence( float $similarity, array $candidate ): float {
		$confidence = $similarity;

		// Boost confidence based on use_count (more used = more reliable)
		$use_count = (int) ( $candidate['use_count'] ?? 1 );
		$use_boost = min( 0.1, log( $use_count + 1 ) / 10.0 );
		$confidence += $use_boost;

		// Boost confidence based on quality_score if available
		if ( isset( $candidate['quality_score'] ) && $candidate['quality_score'] > 0 ) {
			$quality_boost = ( (int) $candidate['quality_score'] / 100.0 ) * 0.1;
			$confidence += $quality_boost;
		}

		// Cap at 1.0
		return min( 1.0, $confidence );
	}
}















