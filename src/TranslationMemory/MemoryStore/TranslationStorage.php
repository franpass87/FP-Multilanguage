<?php
/**
 * Translation Memory Store Translation Storage - Stores and retrieves translations.
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
 * Stores and retrieves translations.
 *
 * @since 0.10.0
 */
class TranslationStorage {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Cache manager instance.
	 *
	 * @var CacheManager
	 */
	protected CacheManager $cache_manager;

	/**
	 * Constructor.
	 *
	 * @param string        $table         Table name.
	 * @param CacheManager  $cache_manager Cache manager instance.
	 */
	public function __construct( string $table, CacheManager $cache_manager ) {
		$this->table = $table;
		$this->cache_manager = $cache_manager;
	}

	/**
	 * Store translation in memory.
	 *
	 * @since 0.10.0
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
	public function store( string $source, string $target, string $source_lang = 'it', string $target_lang = 'en', string $context = 'general', string $provider = 'openai', ?int $quality_score = null ): int|false {
		global $wpdb;

		// Check if exists
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE source_text = %s AND source_lang = %s AND target_lang = %s",
				$source,
				$source_lang,
				$target_lang
			)
		);

		if ( $existing ) {
			// Update use count
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->table} SET use_count = use_count + 1 WHERE id = %d",
					$existing
				)
			);
			return (int) $existing;
		}

		// Insert new
		$insert_data = array(
			'source_text'  => $source,
			'target_text'  => $target,
			'source_lang'  => $source_lang,
			'target_lang'  => $target_lang,
			'provider'     => $provider,
			'context'      => $context,
			'created_at'   => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( null !== $quality_score ) {
			$insert_data['quality_score'] = max( 0, min( 100, $quality_score ) );
			$format[] = '%d';
		}

		$wpdb->insert(
			$this->table,
			$insert_data,
			$format
		);

		return $wpdb->insert_id;
	}

	/**
	 * Find exact translation match.
	 *
	 * @since 0.10.0
	 *
	 * @param string $source      Source text.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return string|null Translation or null if not found.
	 */
	public function find_exact( string $source, string $source_lang = 'it', string $target_lang = 'en' ): ?string {
		$cache_key = $this->cache_manager->get_cache_key( $source, $source_lang, $target_lang );

		// Layer 1: Runtime cache
		$runtime_result = $this->cache_manager->get_runtime_cache( $cache_key );
		if ( null !== $runtime_result ) {
			return $runtime_result;
		}

		// Layer 2: Transient cache
		$transient_result = $this->cache_manager->get_transient_cache( $cache_key );
		if ( null !== $transient_result ) {
			$this->cache_manager->set_runtime_cache( $cache_key, $transient_result );
			return $transient_result;
		}

		// Layer 3: Database
		global $wpdb;
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT target_text FROM {$this->table} WHERE source_text = %s AND source_lang = %s AND target_lang = %s",
				$source,
				$source_lang,
				$target_lang
			)
		);

		// Cache the result (even if null to avoid repeated queries)
		if ( null !== $result ) {
			$this->cache_manager->cache_translation( $cache_key, $result );
		}

		return $result;
	}
}















