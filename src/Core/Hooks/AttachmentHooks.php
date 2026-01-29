<?php
/**
 * Attachment Hooks Handler.
 *
 * Handles WordPress hooks related to media attachments.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Hooks;

use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages attachment-related WordPress hooks.
 *
 * @since 1.0.0
 */
class AttachmentHooks extends BaseHookHandler {

	/**
	 * Translation manager instance.
	 *
	 * @var TranslationManager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer instance.
	 *
	 * @var JobEnqueuer
	 */
	protected $job_enqueuer;

	/**
	 * Constructor.
	 *
	 * @param TranslationManager $translation_manager Translation manager instance.
	 * @param JobEnqueuer        $job_enqueuer        Job enqueuer instance.
	 */
	public function __construct( TranslationManager $translation_manager, JobEnqueuer $job_enqueuer ) {
		$this->translation_manager = $translation_manager;
		$this->job_enqueuer        = $job_enqueuer;
	}

	/**
	 * Register attachment hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Only register if not in assisted mode
		if ( ! $this->shouldRegister() ) {
			return;
		}

		add_action( 'add_attachment', array( $this, 'handle_add_attachment' ), 10, 1 );
		add_action( 'edit_attachment', array( $this, 'handle_edit_attachment' ), 10, 1 );
	}

	/**
	 * Handle add_attachment action.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function handle_add_attachment( $attachment_id ) {
		// Try PostHandlers first (attachments are posts in WordPress)
		$result = $this->delegateToHandler( 'content.post_handler', 'handle_add_attachment', (int) $attachment_id );
		if ( null !== $result ) {
			return;
		}
		
		// Fallback to ContentHandlers singleton
		$this->delegateToLegacyClass( '\FP\Multilanguage\Core\ContentHandlers', 'handle_add_attachment', (int) $attachment_id );
	}

	/**
	 * Handle edit_attachment action.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function handle_edit_attachment( $attachment_id ) {
		// Try PostHandlers first (attachments are posts in WordPress)
		$result = $this->delegateToHandler( 'content.post_handler', 'handle_edit_attachment', (int) $attachment_id );
		if ( null !== $result ) {
			return;
		}
		
		// Fallback to ContentHandlers singleton
		$this->delegateToLegacyClass( '\FP\Multilanguage\Core\ContentHandlers', 'handle_edit_attachment', (int) $attachment_id );
	}
}

