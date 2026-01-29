<?php
/**
 * Post Hooks Handler.
 *
 * Handles all WordPress hooks related to posts.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages all post-related WordPress hooks.
 *
 * @since 1.0.0
 */
class PostHooks extends BaseHookHandler {
	/**
	 * Register post hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Only register if not in assisted mode
		if ( ! $this->shouldRegister() ) {
			return;
		}

		// Post lifecycle hooks
		add_action( 'all', array( $this, 'handle_all_hooks' ), -99999, 10 );
		add_action( 'on_publish', array( $this, 'handle_on_publish' ), -9999, 1 );
		add_action( 'publish_post', array( $this, 'handle_publish_post' ), 1, 1 );
		add_action( 'publish_page', array( $this, 'handle_publish_post' ), 1, 1 );
		add_action( 'save_post', array( $this, 'handle_save_post' ), 999, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_delete_post' ), 10, 1 );
		add_action( 'fpml_after_translation_saved', array( $this, 'enqueue_jobs_after_translation' ), 10, 2 );
	}

	/**
	 * Handle save_post hook.
	 *
	 * Delegates to PostHandlers or Core\Plugin for backward compatibility.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function handle_save_post( $post_id, $post, $update ) {
		$this->delegateWithFallback( 'content.post_handler', 'handle_save_post', $post_id, $post, $update );
	}

	/**
	 * Handle publish_post hook.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_publish_post( $post_id ) {
		$this->delegateWithFallback( 'content.post_handler', 'handle_publish_post', $post_id );
	}

	/**
	 * Handle on_publish hook.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_on_publish( $post_id ) {
		$this->delegateWithFallback( 'content.post_handler', 'handle_on_publish', $post_id );
	}

	/**
	 * Handle all hooks (catch-all).
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  ...$args   Hook arguments.
	 * @return void
	 */
	public function handle_all_hooks( $hook_name, ...$args ) {
		$this->delegateWithFallback( 'content.post_handler', 'handle_all_hooks', $hook_name, ...$args );
	}

	/**
	 * Handle delete_post hook.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_delete_post( $post_id ) {
		$this->delegateWithFallback( 'content.post_handler', 'handle_delete_post', $post_id );
	}

	/**
	 * Enqueue jobs after translation saved.
	 *
	 * @param int $target_id Target post ID.
	 * @param int $source_id Source post ID.
	 * @return void
	 */
	public function enqueue_jobs_after_translation( $target_id, $source_id ) {
		$this->delegateWithFallback( 'content.post_handler', 'enqueue_jobs_after_translation', $target_id, $source_id );
	}
}

