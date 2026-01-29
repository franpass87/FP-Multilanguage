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
		$prompt  = 'You are a professional Italian to English (United States) translator. Preserve HTML tags, attributes, shortcodes, and URLs. Never translate shortcode names, attribute values, or code samples. Respond with English content only.';
		$prompt .= ' Maintain neutral, clear language suitable for a broad audience.';
		$prompt .= ' IMPORTANT: Preserve brand names, company names, product names, and technical terms exactly as they appear. Do not translate proper nouns, brand names, or technical terminology unless they are clearly meant to be translated.';

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
		$instructions  = sprintf( 'Translate the following %1$s content from %2$s to %3$s. Preserve formatting, HTML structure, and shortcodes exactly.', $domain, $source, $target );
		$instructions .= ' Do not translate URLs, HTML attributes, CSS classes, IDs, or shortcode parameters. Return only the translated content without additional commentary.';

		return $instructions . "\n\n" . $text;
	}
}















