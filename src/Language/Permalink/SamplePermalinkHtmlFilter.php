<?php
/**
 * Sample Permalink HTML Filter - Handles HTML filtering for sample permalinks in admin.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Permalink;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters the sample permalink HTML shown in admin edit screen.
 *
 * @since 0.10.0
 */
class SamplePermalinkHtmlFilter {
	/**
	 * Cached settings instance.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Language resolver instance.
	 *
	 * @var \FP\Multilanguage\Language\LanguageResolver
	 */
	protected $resolver;

	/**
	 * Post permalink filter instance.
	 *
	 * @var PostPermalinkFilter
	 */
	protected $post_filter;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Settings                              $settings   Settings instance.
	 * @param \FP\Multilanguage\Language\LanguageResolver $resolver   Language resolver instance.
	 * @param PostPermalinkFilter                         $post_filter Post permalink filter instance.
	 */
	public function __construct( $settings, $resolver, PostPermalinkFilter $post_filter ) {
		$this->settings   = $settings;
		$this->resolver   = $resolver;
		$this->post_filter = $post_filter;
	}

	/**
	 * Filter the sample permalink HTML shown in admin edit screen.
	 *
	 * @since 0.9.5
	 *
	 * @param string  $html      The sample permalink HTML.
	 * @param int     $post_id   Post ID.
	 * @param string  $new_title New sample permalink title.
	 * @param string  $new_slug  New sample permalink slug.
	 * @param WP_Post $post      Post object.
	 *
	 * @return string
	 */
	public function filter_sample_permalink_html( $html, $post_id, $new_title, $new_slug, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return $html;
		}

		if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			return $html;
		}

		$routing = $this->settings->get( 'routing_mode', 'segment' );
		if ( 'segment' !== $routing ) {
			return $html;
		}

		$correct_permalink = $this->post_filter->filter_translation_permalink( get_permalink( $post ), $post, true );
		$decoded_permalink = urldecode( $correct_permalink );
		
		$old_permalink = get_permalink( $post );
		$old_decoded = urldecode( $old_permalink );
		
		if ( false !== strpos( $html, 'href=' ) ) {
			if ( preg_match( '/href=["\']([^"\']+)["\']/', $html, $matches ) ) {
				$old_url = $matches[1];
				if ( $old_url !== $correct_permalink && ! fpml_url_contains_target_language( $old_url ) ) {
					$html = str_replace( 'href="' . $old_url . '"', 'href="' . $correct_permalink . '"', $html );
					$html = str_replace( "href='" . $old_url . "'", "href='" . $correct_permalink . "'", $html );
				}
			}
		}
		
		if ( preg_match( '/(<a[^>]*href=["\'][^"\']*["\'][^>]*>)([^<]*)(<span[^>]*id=["\']editable-post-name["\'][^>]*>)/', $html, $matches ) ) {
			$link_open = $matches[1];
			$url_prefix = trim( $matches[2] );
			$span_open = $matches[3];
			
			$current_lang = $this->resolver->get_current_language();
			$language_manager = fpml_get_language_manager();
			$lang_info = $language_manager->get_language_info( $current_lang );
			$lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
			
			if ( ! fpml_url_contains_target_language( $url_prefix ) && fpml_url_contains_target_language( $correct_permalink ) ) {
				$base_url = trailingslashit( home_url() );
				$correct_base = $base_url . $lang_slug . '/';
				
				$html = str_replace( $link_open . $url_prefix . $span_open, $link_open . $correct_base . $span_open, $html );
			}
		} else {
			if ( preg_match( '/(<a[^>]*href=["\'][^"\']*["\'][^>]*>)([^<]+)(<\/a>)/', $html, $matches ) ) {
				$link_open = $matches[1];
				$link_text = trim( $matches[2] );
				$link_close = $matches[3];
				
				if ( false !== strpos( $link_text, $old_decoded ) || ( ! fpml_url_contains_target_language( $link_text ) && fpml_url_contains_target_language( $correct_permalink ) ) ) {
					$html = str_replace( $link_open . $link_text . $link_close, $link_open . $decoded_permalink . $link_close, $html );
				}
			}
		}
		
		if ( false !== strpos( $html, $old_decoded ) && ! fpml_url_contains_target_language( $old_decoded ) ) {
			$html = str_replace( $old_decoded, $decoded_permalink, $html );
		}

		return $html;
	}
}















