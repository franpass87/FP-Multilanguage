<?php
/**
 * AI-Powered Quality Scorer.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\AI;

use FP\Multilanguage\Providers\ProviderOpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QualityScorer {
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function score_translation( $source, $target, $source_lang = 'it', $target_lang = 'en' ) {
		$provider = new ProviderOpenAI();

		if ( ! $provider->is_configured() ) {
			return new \WP_Error( 'not_configured', __( 'OpenAI non configurato.', 'fp-multilanguage' ) );
		}

		$prompt = "Rate the quality of this translation from {$source_lang} to {$target_lang} on a scale of 0-100.\n\n";
		$prompt .= "Source ({$source_lang}): {$source}\n\n";
		$prompt .= "Translation ({$target_lang}): {$target}\n\n";
		$prompt .= "Respond with ONLY a number between 0-100. Consider accuracy, fluency, and naturalness.";

		$response = $this->call_openai( $prompt );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$score = (int) filter_var( $response, FILTER_SANITIZE_NUMBER_INT );

		return max( 0, min( 100, $score ) );
	}

	protected function call_openai( $prompt ) {
		$provider = new ProviderOpenAI();
		return $provider->translate( $prompt, 'en', 'en', 'system' ); // Use as completion
	}
}

