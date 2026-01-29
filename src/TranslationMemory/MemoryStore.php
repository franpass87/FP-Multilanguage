<?php
/**
 * Translation Memory Store.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\TranslationMemory;

use FP\Multilanguage\TranslationMemory\MemoryStore\TableInstaller;
use FP\Multilanguage\TranslationMemory\MemoryStore\TranslationStorage;
use FP\Multilanguage\TranslationMemory\MemoryStore\FuzzyMatcher;
use FP\Multilanguage\TranslationMemory\MemoryStore\TextNormalizer;
use FP\Multilanguage\TranslationMemory\MemoryStore\CacheManager;
use FP\Multilanguage\TranslationMemory\MemoryStore\StatsCollector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store and retrieve translation segments for reuse.
 *
 * @since 0.5.0
 * @since 0.10.0 Refactored to use modular components.
 */
class MemoryStore {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Singleton instance.
	 *
	 * @var MemoryStore|null
	 */
	protected static $instance = null;

	/**
	 * Table installer.
	 *
	 * @since 0.10.0
	 *
	 * @var TableInstaller
	 */
	protected TableInstaller $table_installer;

	/**
	 * Translation storage.
	 *
	 * @since 0.10.0
	 *
	 * @var TranslationStorage
	 */
	protected TranslationStorage $storage;

	/**
	 * Fuzzy matcher.
	 *
	 * @since 0.10.0
	 *
	 * @var FuzzyMatcher
	 */
	protected FuzzyMatcher $fuzzy_matcher;

	/**
	 * Stats collector.
	 *
	 * @since 0.10.0
	 *
	 * @var StatsCollector
	 */
	protected StatsCollector $stats_collector;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'FPML_translation_memory';

		// Initialize modules
		$this->table_installer = new TableInstaller( $this->table );
		$cache_manager = new CacheManager();
		$text_normalizer = new TextNormalizer();
		$this->storage = new TranslationStorage( $this->table, $cache_manager );
		$this->fuzzy_matcher = new FuzzyMatcher( $this->table, $text_normalizer );
		$this->stats_collector = new StatsCollector( $this->table );

		$this->table_installer->maybe_install();
	}

	/**
	 * Store translation in memory.
	 *
	 * @since 0.5.0
	 * @since 0.10.0 Delegates to TranslationStorage.
	 *
	 * @param string $source       Source text.
	 * @param string $target       Target text.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param string $context     Context (e.g., 'general', 'post', 'term').
	 * @param string $provider    Provider used (default: 'openai').
	 * @param int    $quality_score Quality score (0-100), optional.
	 * @return int|false Translation memory ID or false on failure.
	 */
	public function store( $source, $target, $source_lang = 'it', $target_lang = 'en', $context = 'general', $provider = 'openai', $quality_score = null ) {
		return $this->storage->store( $source, $target, $source_lang, $target_lang, $context, $provider, $quality_score );
	}

	/**
	 * Find exact translation match.
	 *
	 * @since 0.5.0
	 * @since 0.10.0 Delegates to TranslationStorage.
	 *
	 * @param string $source      Source text.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return string|null Translation or null if not found.
	 */
	public function find_exact( $source, $source_lang = 'it', $target_lang = 'en' ) {
		return $this->storage->find_exact( $source, $source_lang, $target_lang );
	}

	/**
	 * Find similar translations using fuzzy matching.
	 *
	 * @since 0.5.0
	 * @since 0.10.0 Delegates to FuzzyMatcher.
	 *
	 * @param string $source      Source text.
	 * @param float  $threshold   Similarity threshold (0.0-1.0), default 0.75.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param int    $limit       Maximum results to return, default 5.
	 * @return array Array of matches with confidence scores.
	 */
	public function find_similar( $source, $threshold = 0.75, $source_lang = 'it', $target_lang = 'en', $limit = 5 ): array {
		return $this->fuzzy_matcher->find_similar( $source, $threshold, $source_lang, $target_lang, $limit );
	}

	/**
	 * Get automatic translation suggestions.
	 *
	 * @since 0.10.0
	 *
	 * @param string $source      Source text.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param int    $max_suggestions Maximum suggestions to return.
	 * @return array Array of suggestions with confidence scores.
	 */
	public function get_suggestions( string $source, string $source_lang = 'it', string $target_lang = 'en', int $max_suggestions = 3 ): array {
		// First try exact match
		$exact = $this->storage->find_exact( $source, $source_lang, $target_lang );
		if ( $exact ) {
			return array(
				array(
					'target_text' => $exact,
					'confidence'  => 1.0,
					'match_type'  => 'exact',
				),
			);
		}

		// Get fuzzy matches
		$similar = $this->fuzzy_matcher->find_similar( $source, 0.6, $source_lang, $target_lang, $max_suggestions );

		$suggestions = array();
		foreach ( $similar as $match ) {
			$suggestions[] = array(
				'target_text' => $match['target_text'],
				'confidence'  => $match['confidence'],
				'match_type'  => $match['confidence'] >= 0.9 ? 'high' : ( $match['confidence'] >= 0.75 ? 'medium' : 'low' ),
				'similarity'  => $match['similarity'],
			);
		}

		return $suggestions;
	}

	/**
	 * Get statistics.
	 *
	 * @since 0.5.0
	 * @since 0.10.0 Delegates to StatsCollector.
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		return $this->stats_collector->get_stats();
	}
}
