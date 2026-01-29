<?php
/**
 * Site Translations Comment Filter - Handles comment translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SiteTranslations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles comment translations.
 *
 * @since 0.10.0
 */
class CommentFilter {
	/**
	 * Filter comment content.
	 *
	 * @param string      $content Original content.
	 * @param \WP_Comment $comment Comment object.
	 * @param array       $args    Additional arguments.
	 * @return string Translated content.
	 */
	public function filter_comment_text( $content, $comment = null, $args = array() ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// If $comment is a WP_Comment object
		if ( is_object( $comment ) && isset( $comment->comment_ID ) ) {
			$comment_id = $comment->comment_ID;
		} elseif ( is_numeric( $comment ) ) {
			$comment_id = (int) $comment;
		} else {
			// Try to get ID from context
			global $comment;
			if ( isset( $comment->comment_ID ) ) {
				$comment_id = $comment->comment_ID;
			} else {
				return $content;
			}
		}

		$translated = get_comment_meta( $comment_id, '_fpml_en_content', true );
		return $translated ? $translated : $content;
	}
}
















