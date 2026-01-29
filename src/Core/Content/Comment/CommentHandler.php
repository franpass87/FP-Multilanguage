<?php
/**
 * Comment Handler - Manages comment-related translation hooks.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Content\Comment;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles comment-related translation hooks and events.
 *
 * @since 1.0.0
 */
class CommentHandler {
	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Handle comment posted.
	 *
	 * @param int    $comment_id  Comment ID.
	 * @param bool   $approved    Whether comment is approved.
	 * @param array  $commentdata Comment data.
	 * @return void
	 */
	public function handleCommentPost( int $comment_id, bool $approved, array $commentdata ): void {
		// Check if we're creating a translation
		if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
			return;
		}

		// Handle comment translation logic here if needed
		// Currently comments are not translated, but this handler is here for future use
	}

	/**
	 * Handle comment edited.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function handleEditComment( int $comment_id ): void {
		// Check if we're creating a translation
		if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
			return;
		}

		// Handle comment translation logic here if needed
	}
}









