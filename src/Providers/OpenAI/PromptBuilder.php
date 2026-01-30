<?php
/**
 * OpenAI Provider Prompt Builder - Builds system and user prompts.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Providers\OpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds system and user prompts for OpenAI API.
 *
 * @since 0.10.0
 */
class PromptBuilder {
	/**
	 * Build system prompt.
	 *
	 * @since 0.10.0
	 *
	 * @param bool $marketing Whether marketing tone is enabled.
	 * @return string
	 */
	public function build_system_prompt( bool $marketing ): string {
		$prompt  = 'You are a professional Italian to English (United States) translator. Preserve HTML tags, shortcode structure, and URLs exactly. Respond with English content only.';
		$prompt .= ' Maintain neutral, clear language suitable for a broad audience.';
		$prompt .= ' IMPORTANT: Preserve brand names, company names, product names, and technical terms exactly as they appear. Do not translate proper nouns, brand names, or technical terminology unless they are clearly meant to be translated.';
		
		// Shortcode attribute handling - comprehensive list for WPBakery and Salient/Nectar
		$prompt .= ' SHORTCODE ATTRIBUTES TO TRANSLATE: ';
		$prompt .= 'text, content, message, quote, excerpt, '; // Base text
		$prompt .= 'title, heading, subheading, subtitle, h2, h4, h6, main_heading, sub_heading, '; // Headings
		$prompt .= 'label, values, placeholder, alt, add_caption, '; // Labels
		$prompt .= 'button_text, link_text, btn_title, cta_text, read_more_text, '; // Buttons
		$prompt .= 'tab_title, toggle_title, section_title, accordion_title, panel_title, '; // Tabs/Accordions
		$prompt .= 'caption, image_caption, video_caption, '; // Captions
		$prompt .= 'author, job_position, job_title, role, testimonial, testimonial_name, '; // People (name excluded - often ID)
		$prompt .= 'price_label, price_unit, currency, features, feature_text, '; // Pricing
		$prompt .= 'milestone_content, counter_title, stat_title, '; // Stats
		$prompt .= 'description, short_description, summary, intro_text, tagline, '; // Descriptions
		$prompt .= 'field_label, submit_text, success_message, error_message, '; // Forms
		$prompt .= 'tooltip, badge_text, ribbon_text, notice_text, before, after.'; // Others
		$prompt .= ' DO NOT TRANSLATE these technical attributes: ';
		$prompt .= 'css, css_class, el_class, el_id, extra_class, custom_css, '; // CSS
		$prompt .= 'link, url, href, image, icon, video_url, source, '; // URLs
		$prompt .= 'width, height, font_size, line_height, padding, margin, '; // Dimensions
		$prompt .= 'color, bg_color, text_color, border_color, accent_color, '; // Colors
		$prompt .= 'animation, delay, offset, duration, speed, '; // Animations
		$prompt .= 'columns, gap, alignment, position, type, style, '; // Layout
		$prompt .= 'id, name, post_id, category, tag, taxonomy.'; // IDs/References

		if ( $marketing ) {
			$prompt .= ' When possible, adopt a slightly promotional tone while remaining natural and trustworthy.';
		}

		return $prompt;
	}

	/**
	 * Build user prompt.
	 *
	 * @since 0.10.0
	 *
	 * @param string $text   Input chunk.
	 * @param string $source Source language.
	 * @param string $target Target language.
	 * @param string $domain Context domain.
	 * @return string
	 */
	public function build_user_prompt( string $text, string $source, string $target, string $domain ): string {
		$instructions  = sprintf( 'Translate the following %1$s content from %2$s to %3$s. Preserve formatting, HTML structure, and shortcode syntax exactly.', $domain, $source, $target );
		$instructions .= ' Do not translate URLs, HTML class/id attributes, or CSS. Translate human-readable text in shortcode attributes (text, heading, title, caption, h2, h4, etc.) but preserve technical attributes unchanged.';
		$instructions .= ' Return only the translated content without additional commentary.';

		return $instructions . "\n\n" . $text;
	}
}















