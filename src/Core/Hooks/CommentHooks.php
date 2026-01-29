<?php
/**
 * Comment Hooks Handler.
 *
 * Handles all WordPress hooks related to comments.
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
 * Manages all comment-related WordPress hooks.
 *
 * @since 1.0.0
 */
class CommentHooks extends BaseHookHandler {
	/**
	 * Register comment hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Only register if not in assisted mode
		if ( ! $this->shouldRegister() ) {
			return;
		}

		add_action( 'comment_post', array( $this, 'handle_comment_post' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'handle_edit_comment' ), 10, 1 );
	}

	/**
	 * Handle comment_post hook.
	 *
	 * @param int   $comment_id Comment ID.
	 * @param int   $approved   Approval status.
	 * @param array $commentdata Comment data.
	 * @return void
	 */
	public function handle_comment_post( $comment_id, $approved, $commentdata ) {
		$this->delegateWithFallback( 'content.comment_handler', 'handle_comment_post', $comment_id, $approved, $commentdata );
	}

	/**
	 * Handle edit_comment hook.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function handle_edit_comment( $comment_id ) {
		$this->delegateWithFallback( 'content.comment_handler', 'handle_edit_comment', $comment_id );
	}
}

