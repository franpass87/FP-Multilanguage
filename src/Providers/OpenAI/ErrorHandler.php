<?php
/**
 * OpenAI Provider Error Handler - Handles API errors and messages.
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
 * Handles OpenAI API errors and user-friendly messages.
 *
 * @since 0.10.0
 */
class ErrorHandler {
	/**
	 * Handle quota exceeded error.
	 *
	 * @since 0.10.0
	 *
	 * @param string $api_message API error message.
	 * @return \WP_Error
	 */
	public function handle_quota_error( string $api_message = '' ): \WP_Error {
		$error_message = __( '❌ Quota OpenAI superata o non configurata.', 'fp-multilanguage' ) . "\n\n";
		$error_message .= __( '📋 Cosa significa:', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '• Il tuo account OpenAI non ha crediti disponibili', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '• OpenAI non offre più crediti gratuiti per i nuovi account', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '• Devi configurare un metodo di pagamento prima di usare l\'API', 'fp-multilanguage' ) . "\n\n";
		$error_message .= __( '✅ Come risolvere:', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '1. Vai su https://platform.openai.com/account/billing/overview', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '2. Clicca su "Add payment details" e aggiungi una carta di credito', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '3. Carica crediti (minimo $5) cliccando su "Add to credit balance"', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '4. Attendi 1-2 minuti affinché i crediti vengano attivati', 'fp-multilanguage' ) . "\n";
		$error_message .= __( '5. Riprova il test', 'fp-multilanguage' ) . "\n\n";
		$error_message .= __( '💰 Costi: ~€0.00011 per 1000 caratteri con GPT-5 nano (estremamente economico e di qualità)', 'fp-multilanguage' ) . "\n\n";
		$error_message .= __( '💡 In alternativa, puoi usare Google Cloud Translation per grandi volumi.', 'fp-multilanguage' );
		
		if ( $api_message ) {
			$error_message .= "\n\n" . __( '💬 Messaggio da OpenAI:', 'fp-multilanguage' ) . "\n" . wp_kses_post( $api_message );
		}
		
		return new \WP_Error( '\FPML_openai_quota_exceeded', $error_message );
	}

	/**
	 * Handle rate limit error.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $code        HTTP status code.
	 * @param string $retry_after Retry-After header value.
	 * @param string $api_message API error message.
	 * @param int    $max_attempts Maximum retry attempts.
	 * @return \WP_Error
	 */
	public function handle_rate_limit_error( int $code, string $retry_after = '', string $api_message = '', int $max_attempts = 5 ): \WP_Error {
		$error_message = '';
		
		if ( 429 === $code ) {
			$error_message = __( '❌ Rate limit OpenAI superato.', 'fp-multilanguage' ) . "\n\n";
			$error_message .= __( '📋 Cosa significa:', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• Hai inviato troppe richieste in poco tempo', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• OpenAI limita il numero di richieste al minuto per prevenire abusi', 'fp-multilanguage' ) . "\n\n";
			$error_message .= __( '✅ Come risolvere:', 'fp-multilanguage' ) . "\n";
			if ( $retry_after ) {
				$error_message .= sprintf( __( '• Attendi %s secondi prima di riprovare', 'fp-multilanguage' ), $retry_after ) . "\n";
			} else {
				$error_message .= __( '• Attendi 1-2 minuti prima di riprovare', 'fp-multilanguage' ) . "\n";
			}
			$error_message .= __( '• Riduci la frequenza delle traduzioni nelle impostazioni', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• Verifica i limiti del tuo piano su https://platform.openai.com/account/limits', 'fp-multilanguage' ) . "\n";
		} else {
			$error_message = sprintf( __( '❌ Errore temporaneo di OpenAI (codice %d).', 'fp-multilanguage' ), $code ) . "\n\n";
			$error_message .= __( '📋 Cosa significa:', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• I server di OpenAI stanno riscontrando problemi temporanei', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• Questo errore è solitamente transitorio e si risolve da solo', 'fp-multilanguage' ) . "\n\n";
			$error_message .= __( '✅ Come risolvere:', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• Attendi 30-60 secondi e riprova', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• Controlla lo stato dei servizi OpenAI su https://status.openai.com/', 'fp-multilanguage' ) . "\n";
			$error_message .= __( '• Se il problema persiste, considera di usare Google Cloud Translation', 'fp-multilanguage' ) . "\n";
		}
		
		if ( $api_message ) {
			$error_message .= "\n\n" . __( '💬 Messaggio da OpenAI:', 'fp-multilanguage' ) . "\n" . wp_kses_post( $api_message );
		}
		
		$error_message .= "\n\n" . sprintf( __( '⚠️ Tentativo %d/%d fallito. Il sistema ha ritentato automaticamente con backoff esponenziale.', 'fp-multilanguage' ), $max_attempts, $max_attempts );
		
		return new \WP_Error( '\FPML_openai_rate_limit', $error_message );
	}

	/**
	 * Handle client error (4xx).
	 *
	 * @since 0.10.0
	 *
	 * @param int    $code        HTTP status code.
	 * @param string $api_message API error message.
	 * @return \WP_Error
	 */
	public function handle_client_error( int $code, string $api_message = '' ): \WP_Error {
		$error_code = '\FPML_openai_client_error';
		$error_message = '';

		if ( 401 === $code || 403 === $code ) {
			$error_code = '\FPML_openai_auth_error';
			$error_message = sprintf(
				__( 'Errore di autenticazione OpenAI: La chiave API non è valida o non ha i permessi necessari. Verifica la tua chiave su https://platform.openai.com/api-keys', 'fp-multilanguage' )
			);
		} elseif ( 400 === $code ) {
			$error_code = '\FPML_openai_invalid_request';
			$error_message = sprintf(
				__( 'Richiesta non valida: %s', 'fp-multilanguage' ),
				wp_kses_post( $api_message )
			);
		} else {
			$error_message = sprintf(
				__( 'Errore client OpenAI (%1$d): %2$s', 'fp-multilanguage' ),
				$code,
				wp_kses_post( $api_message )
			);
		}

		return new \WP_Error( $error_code, $error_message );
	}

	/**
	 * Parse error data from response.
	 *
	 * @since 0.10.0
	 *
	 * @param string $body Response body.
	 * @return array Parsed error data.
	 */
	public function parse_error_data( string $body ): array {
		$error_data = json_decode( $body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			\FP\Multilanguage\Logger::warning(
				'JSON decode error in OpenAI error handling',
				array(
					'error'     => json_last_error(),
					'error_msg' => json_last_error_msg(),
				)
			);
			return array();
		}
		
		return is_array( $error_data ) ? $error_data : array();
	}
}















