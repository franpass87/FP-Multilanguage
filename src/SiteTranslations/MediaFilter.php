<?php
/**
 * Site Translations Media Filter - Handles media translations.
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
 * Handles media translations (alt text, captions, descriptions).
 *
 * @since 0.10.0
 */
class MediaFilter {
	/**
	 * Filter image alt text.
	 *
	 * @param array        $attr      Image attributes.
	 * @param \WP_Post     $attachment Attachment object.
	 * @param string|array $size      Image size.
	 * @return array Filtered attributes.
	 */
	public function filter_image_alt( $attr, $attachment, $size ) {
		if ( ! isset( $attachment->ID ) ) {
			return $attr;
		}

		$translated_alt = get_post_meta( $attachment->ID, '_fpml_en_alt_text', true );
		if ( $translated_alt ) {
			$attr['alt'] = $translated_alt;
		}

		return $attr;
	}

	/**
	 * Filter image captions.
	 *
	 * @param string $caption Original caption.
	 * @param int    $post_id Attachment post ID.
	 * @return string Translated caption.
	 */
	public function filter_image_caption( $caption, $post_id ) {
		if ( empty( $caption ) ) {
			return $caption;
		}

		$translated = get_post_meta( $post_id, '_fpml_en_caption', true );
		return $translated ? $translated : $caption;
	}

	/**
	 * Filter image description.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Filtered metadata.
	 */
	public function filter_image_description( $metadata, $attachment_id ) {
		// Description is in post_content, not in metadata
		// This filter is mainly for compatibility
		return $metadata;
	}

	/**
	 * Filter attachment content (description).
	 *
	 * @param string $content Original content.
	 * @return string Translated content.
	 */
	public function filter_attachment_content( $content ) {
		global $post;

		// Only for attachments
		if ( ! isset( $post ) || 'attachment' !== $post->post_type ) {
			return $content;
		}

		if ( empty( $content ) ) {
			return $content;
		}

		$translated = get_post_meta( $post->ID, '_fpml_en_description', true );
		return $translated ? $translated : $content;
	}

	/**
	 * Filter attachment excerpt (caption).
	 *
	 * @param string   $excerpt Original excerpt.
	 * @param \WP_Post $post    Post object.
	 * @return string Translated excerpt.
	 */
	public function filter_attachment_excerpt( $excerpt, $post = null ) {
		if ( ! $post ) {
			global $post;
		}

		if ( ! isset( $post ) || 'attachment' !== $post->post_type ) {
			return $excerpt;
		}

		if ( empty( $excerpt ) ) {
			return $excerpt;
		}

		$translated = get_post_meta( $post->ID, '_fpml_en_caption', true );
		return $translated ? $translated : $excerpt;
	}
}
















