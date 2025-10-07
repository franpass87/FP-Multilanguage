<?php
/**
 * Provider fallback automatico se il provider principale fallisce.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce fallback automatico tra provider.
 *
 * @since 0.4.0
 */
class FPML_Provider_Fallback {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Provider_Fallback|null
	 */
	protected static $instance = null;

	/**
	 * Settings reference.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Logger reference.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Provider fallback chain.
	 *
	 * @var array
	 */
	protected $fallback_chain = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Provider_Fallback
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->settings = FPML_Settings::instance();
		$this->logger   = FPML_Logger::instance();

		// Hook per intercettare errori del provider.
		add_filter( 'fpml_translate_error', array( $this, 'handle_translation_error' ), 10, 4 );

		// Costruisci chain fallback.
		$this->build_fallback_chain();
	}

	/**
	 * Costruisce la catena di fallback basata sui provider configurati.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function build_fallback_chain() {
		if ( ! $this->settings ) {
			return;
		}

		$primary = $this->settings->get( 'provider', '' );

		// Ordine di fallback consigliato.
		$preferred_order = array( 'openai', 'deepl', 'google', 'libretranslate' );

		// Inizia con primary.
		if ( $primary ) {
			$this->fallback_chain[] = $primary;
		}

		// Aggiungi gli altri in ordine se configurati.
		foreach ( $preferred_order as $provider ) {
			if ( $provider === $primary ) {
				continue; // Già in chain.
			}

			if ( $this->is_provider_configured( $provider ) ) {
				$this->fallback_chain[] = $provider;
			}
		}

		$this->logger->log(
			'debug',
			'Fallback chain costruita: ' . implode( ' → ', $this->fallback_chain ),
			array( 'chain' => $this->fallback_chain )
		);
	}

	/**
	 * Controlla se un provider è configurato.
	 *
	 * @since 0.4.0
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return bool
	 */
	protected function is_provider_configured( $provider ) {
		if ( ! $this->settings ) {
			return false;
		}

		switch ( $provider ) {
			case 'openai':
				return ! empty( $this->settings->get( 'openai_api_key', '' ) );
			case 'deepl':
				return ! empty( $this->settings->get( 'deepl_api_key', '' ) );
			case 'google':
				return ! empty( $this->settings->get( 'google_api_key', '' ) ) && ! empty( $this->settings->get( 'google_project_id', '' ) );
			case 'libretranslate':
				return ! empty( $this->settings->get( 'libretranslate_api_url', '' ) );
		}

		return false;
	}

	/**
	 * Gestisce errore di traduzione e prova fallback.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Error $error    Errore originale.
	 * @param string   $text     Testo da tradurre.
	 * @param string   $source   Lingua sorgente.
	 * @param string   $target   Lingua destinazione.
	 *
	 * @return string|WP_Error Traduzione o errore.
	 */
	public function handle_translation_error( $error, $text, $source, $target ) {
		if ( ! is_wp_error( $error ) ) {
			return $error; // Non è un errore, passa oltre.
		}

		$error_code = $error->get_error_code();

		// Errori che NON richiedono fallback (es. testo vuoto).
		$no_fallback_codes = array(
			'fpml_empty_text',
			'fpml_invalid_language',
		);

		if ( in_array( $error_code, $no_fallback_codes, true ) ) {
			return $error;
		}

		// Prova fallback.
		return $this->try_fallback( $text, $source, $target, $error );
	}

	/**
	 * Prova fallback su provider alternativi.
	 *
	 * @since 0.4.0
	 *
	 * @param string   $text   Testo da tradurre.
	 * @param string   $source Lingua sorgente.
	 * @param string   $target Lingua destinazione.
	 * @param WP_Error $original_error Errore originale.
	 *
	 * @return string|WP_Error
	 */
	protected function try_fallback( $text, $source, $target, $original_error ) {
		if ( empty( $this->fallback_chain ) || count( $this->fallback_chain ) <= 1 ) {
			// Nessun fallback disponibile.
			return $original_error;
		}

		$primary_provider = $this->fallback_chain[0];

		$this->logger->log(
			'warning',
			sprintf( 'Provider %s fallito, provo fallback', $primary_provider ),
			array(
				'error'  => $original_error->get_error_message(),
				'chain'  => $this->fallback_chain,
			)
		);

		// Prova provider successivi.
		for ( $i = 1; $i < count( $this->fallback_chain ); $i++ ) {
			$fallback_provider = $this->fallback_chain[ $i ];

			$this->logger->log(
				'info',
				sprintf( 'Tentativo fallback con provider: %s', $fallback_provider ),
				array( 'provider' => $fallback_provider )
			);

			// Crea istanza provider.
			$translator = $this->create_provider_instance( $fallback_provider );

			if ( is_wp_error( $translator ) ) {
				$this->logger->log(
					'warning',
					sprintf( 'Provider %s non disponibile', $fallback_provider ),
					array( 'error' => $translator->get_error_message() )
				);
				continue;
			}

			// Prova traduzione.
			$result = $translator->translate( $text, $source, $target );

			if ( ! is_wp_error( $result ) ) {
				// Successo!
				$this->logger->log(
					'success',
					sprintf( 'Fallback riuscito con provider: %s', $fallback_provider ),
					array(
						'provider'        => $fallback_provider,
						'original_error'  => $original_error->get_error_message(),
						'characters'      => strlen( $text ),
					)
				);

				// Aggiorna statistiche fallback.
				$this->increment_fallback_stats( $primary_provider, $fallback_provider );

				return $result;
			}

			$this->logger->log(
				'warning',
				sprintf( 'Provider %s fallito anche come fallback', $fallback_provider ),
				array( 'error' => $result->get_error_message() )
			);
		}

		// Tutti i fallback falliti.
		$this->logger->log(
			'error',
			'Tutti i provider fallback hanno fallito',
			array(
				'chain'          => $this->fallback_chain,
				'original_error' => $original_error->get_error_message(),
			)
		);

		return new WP_Error(
			'fpml_all_providers_failed',
			sprintf(
				/* translators: %s: messaggio errore originale */
				__( 'Tutti i provider hanno fallito. Errore originale: %s', 'fp-multilanguage' ),
				$original_error->get_error_message()
			)
		);
	}

	/**
	 * Crea istanza provider.
	 *
	 * @since 0.4.0
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return FPML_TranslatorInterface|WP_Error
	 */
	protected function create_provider_instance( $provider ) {
		switch ( $provider ) {
			case 'openai':
				if ( ! class_exists( 'FPML_Provider_OpenAI' ) ) {
					return new WP_Error( 'fpml_provider_missing', 'OpenAI provider non disponibile' );
				}
				$instance = new FPML_Provider_OpenAI();
				break;

			case 'deepl':
				if ( ! class_exists( 'FPML_Provider_DeepL' ) ) {
					return new WP_Error( 'fpml_provider_missing', 'DeepL provider non disponibile' );
				}
				$instance = new FPML_Provider_DeepL();
				break;

			case 'google':
				if ( ! class_exists( 'FPML_Provider_Google' ) ) {
					return new WP_Error( 'fpml_provider_missing', 'Google provider non disponibile' );
				}
				$instance = new FPML_Provider_Google();
				break;

			case 'libretranslate':
				if ( ! class_exists( 'FPML_Provider_LibreTranslate' ) ) {
					return new WP_Error( 'fpml_provider_missing', 'LibreTranslate provider non disponibile' );
				}
				$instance = new FPML_Provider_LibreTranslate();
				break;

			default:
				return new WP_Error( 'fpml_unknown_provider', 'Provider sconosciuto: ' . $provider );
		}

		if ( ! $instance->is_configured() ) {
			return new WP_Error( 'fpml_provider_not_configured', 'Provider non configurato: ' . $provider );
		}

		return $instance;
	}

	/**
	 * Incrementa statistiche fallback.
	 *
	 * @since 0.4.0
	 *
	 * @param string $failed_provider  Provider fallito.
	 * @param string $success_provider Provider riuscito.
	 *
	 * @return void
	 */
	protected function increment_fallback_stats( $failed_provider, $success_provider ) {
		$stats = get_option( 'fpml_fallback_stats', array() );

		$key = $failed_provider . '_to_' . $success_provider;

		if ( ! isset( $stats[ $key ] ) ) {
			$stats[ $key ] = array(
				'count'      => 0,
				'last_used'  => 0,
			);
		}

		$stats[ $key ]['count']++;
		$stats[ $key ]['last_used'] = current_time( 'timestamp', true );

		update_option( 'fpml_fallback_stats', $stats, false );
	}

	/**
	 * Ottieni statistiche fallback.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_fallback_stats() {
		return get_option( 'fpml_fallback_stats', array() );
	}

	/**
	 * Ottieni chain fallback corrente.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_fallback_chain() {
		return $this->fallback_chain;
	}
}
