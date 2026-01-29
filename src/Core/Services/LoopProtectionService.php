<?php
/**
 * Loop Protection Service.
 *
 * Centralizes infinite loop detection and prevention logic.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

use FP\Multilanguage\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for detecting and preventing infinite loops.
 *
 * @since 1.0.0
 */
class LoopProtectionService {
	/**
	 * Processing flags per post ID.
	 *
	 * @var array<int, bool>
	 */
	protected static $processing = array();

	/**
	 * Call counts per post ID.
	 *
	 * @var array<int, array<float>>
	 */
	protected static $call_counts = array();

	/**
	 * Last call time per post ID.
	 *
	 * @var array<int, float>
	 */
	protected static $last_call_times = array();

	/**
	 * Check if should skip processing (already processing or blocked).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $hook    Hook name.
	 * @return bool
	 */
	public function shouldSkip( int $post_id, string $hook = 'save_post' ): bool {
		// Check global flags
		if ( isset( $GLOBALS['fpml_infinite_loop_detected'] ) && $GLOBALS['fpml_infinite_loop_detected'] ) {
			return true;
		}

		if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
			return true;
		}

		if ( isset( $GLOBALS['fpml_creating_translation'] ) && $GLOBALS['fpml_creating_translation'] ) {
			return true;
		}

		// Check if post is blocked
		$blocked = get_transient( 'fpml_blocked_hooks_' . $post_id );
		if ( $blocked || ( isset( $GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] ) && $GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] ) ) {
			return true;
		}

		// Check if already processing
		if ( isset( self::$processing[ $post_id ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check rate limit.
	 *
	 * @param int    $post_id      Post ID.
	 * @param float  $min_interval Minimum interval in seconds.
	 * @param int    $max_calls    Maximum calls in time window.
	 * @param float  $time_window  Time window in seconds.
	 * @return bool True if should be rate limited.
	 */
	public function checkRateLimit( int $post_id, float $min_interval = 3.0, int $max_calls = 2, float $time_window = 10.0 ): bool {
		$current_time = microtime( true );

		// Check minimum interval
		if ( isset( self::$last_call_times[ $post_id ] ) ) {
			$time_since_last = $current_time - self::$last_call_times[ $post_id ];
			if ( $time_since_last < $min_interval ) {
				Logger::debug( 'Rate limited - minimum interval not met', array(
					'post_id' => $post_id,
					'time_since_last' => $time_since_last,
				) );
				return true;
			}
		}

		// Check call count in time window
		if ( ! isset( self::$call_counts[ $post_id ] ) ) {
			self::$call_counts[ $post_id ] = array();
		}

		// Remove calls older than time window
		self::$call_counts[ $post_id ] = array_filter(
			self::$call_counts[ $post_id ],
			function( $time ) use ( $current_time, $time_window ) {
				return ( $current_time - $time ) < $time_window;
			}
		);

		// Add current call
		self::$call_counts[ $post_id ][] = $current_time;

		// Check if exceeded max calls
		if ( count( self::$call_counts[ $post_id ] ) > $max_calls ) {
			Logger::error( 'Rate limit exceeded - too many calls', array(
				'post_id' => $post_id,
				'count' => count( self::$call_counts[ $post_id ] ),
			) );
			return true;
		}

		return false;
	}

	/**
	 * Mark as processing.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function markProcessing( int $post_id ): void {
		self::$processing[ $post_id ] = true;
		self::$last_call_times[ $post_id ] = microtime( true );
	}

	/**
	 * Mark as done processing.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function markDone( int $post_id ): void {
		unset( self::$processing[ $post_id ] );
	}

	/**
	 * Block hooks for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $seconds Duration in seconds.
	 * @return void
	 */
	public function blockPost( int $post_id, int $seconds = 30 ): void {
		set_transient( 'fpml_blocked_hooks_' . $post_id, true, $seconds );
		$GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] = true;
	}

	/**
	 * Clear processing state for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clearState( int $post_id ): void {
		unset( self::$processing[ $post_id ] );
		unset( self::$call_counts[ $post_id ] );
		unset( self::$last_call_times[ $post_id ] );
		delete_transient( 'fpml_blocked_hooks_' . $post_id );
		unset( $GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] );
	}
}








