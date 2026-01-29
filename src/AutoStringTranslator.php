<?php
/**
 * Automatic String Translator - Traduce stringhe hardcoded automaticamente.
 *
 * Intercetta le chiamate gettext e traduce automaticamente le stringhe italiane
 * in inglese quando la lingua corrente è inglese.
 *
 * @package FP_Multilanguage
 * @since 0.9.1
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Automatic string translator class.
 *
 * @since 0.9.1
 */
class AutoStringTranslator {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Translation cache.
	 *
	 * @var array
	 */
	protected $cache = array();

	/**
	 * Processor instance.
	 *
	 * @var \FPML_Processor|null
	 */
	protected $processor = null;

	/**
	 * Settings instance.
	 *
	 * @var \FPML_Settings|null
	 */
	protected $settings = null;

	/**
	 * Flag to prevent recursion.
	 *
	 * @var bool
	 */
	protected $translating = false;

	/**
	 * Get singleton instance.
	 *
	 * @return self
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
		$container = $this->getContainer();
		$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
		
		// Verifica se la traduzione automatica stringhe è abilitata
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Registra sempre i filtri - verificheranno la lingua ad ogni chiamata
		$this->register_filters();
	}

	/**
	 * Check if automatic string translation is enabled.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		if ( ! $this->settings ) {
			return false;
		}

		// Verifica impostazione (default: true)
		return $this->settings->get( 'auto_translate_strings', true );
	}

	/**
	 * Check if current language is English.
	 *
	 * @return bool
	 */
	protected function is_english_language() {
		// Verifica se siamo in lingua inglese
		$current_lang = ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() )->get_current_language();
		return 'en' === $current_lang || 'en_US' === $current_lang;
	}

	/**
	 * Register WordPress filters for gettext.
	 */
	protected function register_filters() {
		// Intercetta gettext (__(), _e(), esc_html__(), ecc.)
		add_filter( 'gettext', array( $this, 'translate_string' ), 999, 3 );
		add_filter( 'gettext_with_context', array( $this, 'translate_string_with_context' ), 999, 4 );
		add_filter( 'ngettext', array( $this, 'translate_plural' ), 999, 5 );
		add_filter( 'ngettext_with_context', array( $this, 'translate_plural_with_context' ), 999, 6 );
	}

	/**
	 * Translate a string automatically.
	 *
	 * @param string $translation Current translation.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public function translate_string( $translation, $text, $domain ) {
		// Verifica se siamo in lingua inglese (controllo ad ogni chiamata)
		if ( ! $this->is_english_language() ) {
			return $translation;
		}

		// Evita ricorsione
		if ( $this->translating ) {
			return $translation;
		}

		// Se la traduzione è già diversa dal testo originale, è già tradotta
		if ( $translation !== $text ) {
			return $translation;
		}

		// Verifica se il testo è italiano (contiene caratteri italiani o pattern comuni)
		if ( ! $this->is_italian_text( $text ) ) {
			return $translation;
		}

		// Traduci automaticamente
		return $this->get_translation( $text, $domain );
	}

	/**
	 * Translate a string with context.
	 *
	 * @param string $translation Current translation.
	 * @param string $text        Original text.
	 * @param string $context     Context.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public function translate_string_with_context( $translation, $text, $context, $domain ) {
		// Verifica se siamo in lingua inglese (controllo ad ogni chiamata)
		if ( ! $this->is_english_language() ) {
			return $translation;
		}

		// Evita ricorsione
		if ( $this->translating ) {
			return $translation;
		}

		// Se la traduzione è già diversa dal testo originale, è già tradotta
		if ( $translation !== $text ) {
			return $translation;
		}

		// Verifica se il testo è italiano
		if ( ! $this->is_italian_text( $text ) ) {
			return $translation;
		}

		// Traduci automaticamente
		return $this->get_translation( $text, $domain, $context );
	}

	/**
	 * Translate plural string.
	 *
	 * @param string $translation Current translation.
	 * @param string $single      Single form.
	 * @param string $plural      Plural form.
	 * @param int    $number      Number.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public function translate_plural( $translation, $single, $plural, $number, $domain ) {
		// Verifica se siamo in lingua inglese (controllo ad ogni chiamata)
		if ( ! $this->is_english_language() ) {
			return $translation;
		}

		// Evita ricorsione
		if ( $this->translating ) {
			return $translation;
		}

		// Usa la forma appropriata
		$text = ( 1 === $number ) ? $single : $plural;

		// Se la traduzione è già diversa, è già tradotta
		if ( $translation !== $text ) {
			return $translation;
		}

		// Verifica se il testo è italiano
		if ( ! $this->is_italian_text( $text ) ) {
			return $translation;
		}

		// Traduci automaticamente
		return $this->get_translation( $text, $domain );
	}

	/**
	 * Translate plural string with context.
	 *
	 * @param string $translation Current translation.
	 * @param string $single      Single form.
	 * @param string $plural      Plural form.
	 * @param int    $number      Number.
	 * @param string $context     Context.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public function translate_plural_with_context( $translation, $single, $plural, $number, $context, $domain ) {
		// Verifica se siamo in lingua inglese (controllo ad ogni chiamata)
		if ( ! $this->is_english_language() ) {
			return $translation;
		}

		// Evita ricorsione
		if ( $this->translating ) {
			return $translation;
		}

		// Usa la forma appropriata
		$text = ( 1 === $number ) ? $single : $plural;

		// Se la traduzione è già diversa, è già tradotta
		if ( $translation !== $text ) {
			return $translation;
		}

		// Verifica se il testo è italiano
		if ( ! $this->is_italian_text( $text ) ) {
			return $translation;
		}

		// Traduci automaticamente
		return $this->get_translation( $text, $domain, $context );
	}

	/**
	 * Check if text is Italian.
	 *
	 * @param string $text Text to check.
	 *
	 * @return bool
	 */
	protected function is_italian_text( $text ) {
		// Testo vuoto o troppo corto
		if ( empty( $text ) || strlen( $text ) < 3 ) {
			return false;
		}

		// Controlla caratteri italiani comuni
		$italian_chars = array( 'à', 'è', 'é', 'ì', 'ò', 'ù', 'À', 'È', 'É', 'Ì', 'Ò', 'Ù' );
		foreach ( $italian_chars as $char ) {
			if ( false !== strpos( $text, $char ) ) {
				return true;
			}
		}

		// Controlla pattern italiani comuni
		$italian_patterns = array(
			'/^(Clicca|Aggiungi|Rimuovi|Salva|Modifica|Elimina|Annulla|Conferma)/i',
			'/(\b(il|la|lo|gli|le|un|una|uno|dei|delle|degli)\b)/i',
			'/(\b(è|sono|ha|hanno|fa|fanno)\b)/i',
		);

		foreach ( $italian_patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return true;
			}
		}

		// Se contiene solo caratteri ASCII e non sembra inglese, potrebbe essere italiano
		// (questo è un fallback, non perfetto)
		if ( preg_match( '/^[a-zA-Z0-9\s\.,;:!?\-\(\)\[\]{}"\']+$/', $text ) ) {
			// Verifica parole italiane comuni
			$italian_words = array( 'clicca', 'aggiungi', 'rimuovi', 'salva', 'modifica', 'elimina', 'annulla', 'conferma', 'carica', 'scarica' );
			$text_lower = strtolower( $text );
			foreach ( $italian_words as $word ) {
				if ( false !== strpos( $text_lower, $word ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get translation for a string (with cache).
	 *
	 * @param string $text    Text to translate.
	 * @param string $domain  Text domain.
	 * @param string $context Context (optional).
	 *
	 * @return string
	 */
	protected function get_translation( $text, $domain = 'default', $context = '' ) {
		$cache_key = 'string_' . md5( $text . $domain . $context );

		// Verifica cache runtime
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		// Verifica cache persistente usando transients
		$transient_key = 'fpml_string_' . $cache_key;
		$cached = get_transient( $transient_key );
		if ( false !== $cached ) {
			$this->cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Traduci usando il processor
		$this->translating = true;
		$translation = $this->translate_with_ai( $text, $domain, $context );
		$this->translating = false;

		if ( is_wp_error( $translation ) ) {
			return $text; // Fallback: ritorna testo originale
		}

		// Salva in cache runtime
		$this->cache[ $cache_key ] = $translation;

		// Salva in cache persistente usando transients (24 ore)
		set_transient( $transient_key, $translation, DAY_IN_SECONDS );

		return $translation;
	}

	/**
	 * Translate text using AI.
	 *
	 * @param string $text    Text to translate.
	 * @param string $domain  Text domain.
	 * @param string $context Context.
	 *
	 * @return string
	 */
	protected function translate_with_ai( $text, $domain, $context ) {
		if ( ! $this->processor ) {
			$this->processor = \FPML_fpml_get_processor();
		}

		if ( ! $this->processor ) {
			return $text; // Fallback: ritorna testo originale
		}

		// Usa il translator del processor
		$translator = $this->processor->get_translator_instance();

		if ( ! $translator || is_wp_error( $translator ) ) {
			return $text; // Fallback: ritorna testo originale
		}

		// Traduci direttamente usando il translator
		$result = $translator->translate( $text, 'it', 'en', 'general' );

		if ( is_wp_error( $result ) ) {
			return $text; // Fallback: ritorna testo originale
		}

		return $result;
	}

	/**
	 * Clear translation cache.
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->cache = array();
		// Elimina tutte le transients delle stringhe tradotte
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_fpml_string_' ) . '%' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_timeout_fpml_string_' ) . '%' ) );
		delete_option( 'fpml_string_translations_cache' );
	}
}



