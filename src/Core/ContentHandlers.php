<?php
/**
 * Content Handlers Service - Manages attachment, comment, and widget translation hooks.
 *
 * Extracted from Plugin.php to follow Single Responsibility Principle.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles attachment, comment, and widget-related translation hooks.
 *
 * @since 0.10.0
 */
class ContentHandlers {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

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
	 * Plugin instance (for assisted mode check).
	 *
	 * @var PluginCore|null
	 */
	protected $plugin;

	/**
	 * Get singleton instance (for backward compatibility).
	 *
	 * @since 0.10.0
	 * @deprecated 1.0.0 Use dependency injection via container instead. ContentHandlers is being replaced by dedicated hook handlers.
	 *
	 * @return self
	 */
	public static function instance(): self {
		_doing_it_wrong( 
			'FP\Multilanguage\Core\ContentHandlers::instance()', 
			'ContentHandlers is deprecated. Use dedicated hook handlers (PostHooks, TermHooks, CommentHooks, WidgetHooks, AttachmentHooks) instead.', 
			'1.0.0' 
		);
		
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 * @since 1.0.0 Now public to support dependency injection (but class is deprecated)
	 */
	public function __construct() {
		$this->translation_manager = Container::get( 'translation_manager' ) 
			?: fpml_get_translation_manager();
		$this->job_enqueuer = Container::get( 'job_enqueuer' ) 
			?: ( class_exists( JobEnqueuer::class ) 
				? fpml_get_job_enqueuer() 
				: null );
		// Plugin instance will be set via set_plugin() to avoid circular dependencies
		// Don't load plugin instance here to prevent circular dependency issues
		$this->plugin = null;
	}

	/**
	 * Set plugin instance.
	 *
	 * @param PluginCore $plugin Plugin instance.
	 *
	 * @return void
	 */
	public function set_plugin( PluginCore $plugin ): void {
		$this->plugin = $plugin;
	}

	/**
	 * Check if in assisted mode.
	 *
	 * @return bool
	 */
	protected function is_assisted_mode(): bool {
		if ( ! $this->plugin ) {
			// Try to get plugin instance if not set
			if ( class_exists( '\FPML_Plugin' ) ) {
				$this->plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
			}
		}
		return $this->plugin ? $this->plugin->is_assisted_mode() : false;
	}

	/**
	 * Handle attachment creation - create translation.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function handle_add_attachment( int $attachment_id ): void {
		if ( $this->is_assisted_mode() || ! $this->translation_manager || ! $this->job_enqueuer ) {
			return;
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		// Check if already a translation
		if ( get_post_meta( $attachment_id, '_fpml_is_translation', true ) ) {
			return;
		}

		// Get enabled languages and create translation for first enabled language
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';

		// Create translation
		$target_attachment = $this->translation_manager->ensure_post_translation( $attachment, $target_lang );

		if ( $target_attachment && $this->job_enqueuer ) {
			// Enqueue job to translate title, caption, description
			$this->job_enqueuer->enqueue_post_jobs( $attachment, $target_attachment, false );
		}
	}

	/**
	 * Handle attachment edit - update translation.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function handle_edit_attachment( int $attachment_id ): void {
		if ( $this->is_assisted_mode() || ! $this->translation_manager || ! $this->job_enqueuer ) {
			return;
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		// Check if already a translation
		if ( get_post_meta( $attachment_id, '_fpml_is_translation', true ) ) {
			return;
		}

		// Get enabled languages and find translation for first enabled language
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';

		$target_id = $this->translation_manager->get_translation_id( $attachment_id, $target_lang );

		// Backward compatibility: check legacy _fpml_pair_id
		if ( ! $target_id && 'en' === $target_lang ) {
			$target_id = (int) get_post_meta( $attachment_id, '_fpml_pair_id', true );
		}

		if ( ! $target_id ) {
			// Create translation if doesn't exist
			$target_attachment = $this->translation_manager->ensure_post_translation( $attachment, $target_lang );
		} else {
			$target_attachment = get_post( $target_id );
		}

		if ( ! $target_attachment || ! $this->job_enqueuer ) {
			return;
		}

		// Enqueue job to translate title, caption, description updated
		$this->job_enqueuer->enqueue_post_jobs( $attachment, $target_attachment, true );
	}

	/**
	 * Handle comment post - translate automatically.
	 *
	 * @param int        $comment_id  Comment ID.
	 * @param int|string $approved    1 if approved, 0 if not, 'spam' if spam.
	 * @param array      $commentdata Comment data.
	 *
	 * @return void
	 */
	public function handle_comment_post( int $comment_id, $approved, array $commentdata ): void {
		if ( $this->is_assisted_mode() || ! $this->translation_manager || ! $this->job_enqueuer ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		// Check if already a translation
		if ( get_comment_meta( $comment_id, '_fpml_is_translation', true ) ) {
			return;
		}

		// Get associated post
		$post_id = (int) $comment->comment_post_ID;
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		// Get post translation for first enabled language
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';

		$target_post_id = $this->translation_manager->get_translation_id( $post_id, $target_lang );

		// Backward compatibility: check legacy _fpml_pair_id
		if ( ! $target_post_id && 'en' === $target_lang ) {
			$target_post_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
		}

		if ( ! $target_post_id ) {
			// If post has no translation, don't translate comment
			return;
		}

		// Handle nested comments: find parent comment translation
		$comment_parent = 0;
		if ( ! empty( $comment->comment_parent ) && $comment->comment_parent > 0 ) {
			$parent_comment_id = (int) $comment->comment_parent;
			$parent_translation_id = (int) get_comment_meta( $parent_comment_id, '_fpml_pair_id', true );

			if ( $parent_translation_id > 0 ) {
				// Verify translated comment still exists
				$parent_translation = get_comment( $parent_translation_id );
				if ( $parent_translation && (int) $parent_translation->comment_post_ID === $target_post_id ) {
					$comment_parent = $parent_translation_id;
				}
			}
		}

		// Create comment translation
		$target_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $target_post_id,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_content'      => $comment->comment_content, // Will be translated by job
				'comment_type'         => $comment->comment_type,
				'comment_parent'       => $comment_parent,
				'comment_approved'      => $approved,
				'comment_meta'         => array(
					'_fpml_is_translation'  => 1,
					'_fpml_pair_source_id' => $comment_id,
				),
			)
		);

		if ( ! $target_comment_id || is_wp_error( $target_comment_id ) ) {
			return;
		}

		// Save relationship
		update_comment_meta( $comment_id, '_fpml_pair_id', $target_comment_id );
		update_comment_meta( $target_comment_id, '_fpml_pair_source_id', $comment_id );

		// Enqueue job to translate comment content
		$queue = Container::get( 'queue' ) ?: fpml_get_queue();
		$content_hash = md5( $comment->comment_content );
		$queue->enqueue( 'comment', (string) $comment_id, 'comment_content', $content_hash );
	}

	/**
	 * Handle comment edit - update translation automatically.
	 *
	 * @param int $comment_id Comment ID.
	 *
	 * @return void
	 */
	public function handle_edit_comment( int $comment_id ): void {
		if ( $this->is_assisted_mode() || ! $this->translation_manager ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return;
		}

		// Check if already a translation
		if ( get_comment_meta( $comment_id, '_fpml_is_translation', true ) ) {
			return;
		}

		// Get comment translation
		$target_comment_id = (int) get_comment_meta( $comment_id, '_fpml_pair_id', true );

		if ( ! $target_comment_id ) {
			// If translation doesn't exist, create it
			$this->handle_comment_post( $comment_id, $comment->comment_approved, array() );
			return;
		}

		// Enqueue job to translate updated content
		$queue = Container::get( 'queue' ) ?: fpml_get_queue();
		$content_hash = md5( $comment->comment_content );
		$queue->enqueue( 'comment', (string) $comment_id, 'comment_content', $content_hash );
	}

	/**
	 * Handle widget update - translate automatically.
	 *
	 * @param array      $instance     Current widget instance's settings.
	 * @param array      $new_instance New widget settings.
	 * @param array      $old_instance Old widget settings.
	 * @param \WP_Widget $widget       Current widget instance.
	 *
	 * @return array
	 */
	public function handle_widget_update( array $instance, array $new_instance, array $old_instance, \WP_Widget $widget ): array {
		if ( $this->is_assisted_mode() || ! $this->job_enqueuer ) {
			return $instance;
		}

		// Save widget translation in options
		$widget_id = $widget->id_base;
		$widget_number = $widget->number;

		// Identify translatable fields (title, text, description, etc.)
		$translatable_fields = array( 'title', 'text', 'description', 'content' );

		foreach ( $translatable_fields as $field ) {
			if ( ! isset( $new_instance[ $field ] ) || empty( $new_instance[ $field ] ) ) {
				continue;
			}

			// Save Italian value
			$option_key = "fpml_widget_{$widget_id}_{$widget_number}_{$field}_it";
			update_option( $option_key, $new_instance[ $field ], false );

			// Enqueue job to translate
			$queue = Container::get( 'queue' ) ?: fpml_get_queue();
			$content_hash = md5( $new_instance[ $field ] );
			$queue->enqueue( 'widget', "{$widget_id}_{$widget_number}", $field, $content_hash );
		}

		return $instance;
	}
}

