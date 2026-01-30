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
		
		// Shortcode attribute handling
		$prompt .= ' SHORTCODE ATTRIBUTES: Translate text content in these attributes: text, heading, subheading, title, subtitle, caption, h2, h4, message, content, quote, description, button_text, link_text, tab_title, accordion_title.';
		$prompt .= ' Do NOT translate technical attributes like: css, css_class, el_class, el_id, link, url, image, icon, color, font_size, font_family, animation, delay, offset, width, height, id, name, type, style.';

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















