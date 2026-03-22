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

		// Safety: avoid gettext interception in admin/AJAX contexts where
		// multiple plugins register dynamic hooks and can trigger recursion.
		if ( ( function_exists( 'is_admin' ) && is_admin() ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
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
	 * Get current language code.
	 *
	 * @return string Language code (e.g. 'en', 'de', 'fr') or empty string if source language.
	 */
	protected function get_current_target_language(): string {
		$lang_instance = function_exists( 'fpml_get_language' ) ? fpml_get_language() : ( class_exists( '\FPML_Language' ) ? \FPML_Language::instance() : null );
		if ( ! $lang_instance ) {
			return '';
		}
		$current = $lang_instance->get_current_language();
	$source  = ( class_exists( '\FPML_Language' ) && defined( 'FPML_Language::SOURCE' ) ) ? \FPML_Language::SOURCE : ( function_exists( 'fpml_get_source_language' ) ? fpml_get_source_language() : 'it' );
	return ( $current && $current !== $source ) ? $current : '';
	}

	/**
	 * Check if current language is a target language (not source).
	 *
	 * @return bool
	 */
	protected function is_english_language() {
		return '' !== $this->get_current_target_language();
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
	 * If the translation is not cached, schedules an async job and returns the
	 * original text immediately to avoid blocking the request with a synchronous
	 * HTTP call to the AI provider.
	 *
	 * @param string $text    Text to translate.
	 * @param string $domain  Text domain.
	 * @param string $context Context (optional).
	 *
	 * @return string
	 */
	protected function get_translation( $text, $domain = 'default', $context = '' ) {
		if ( $this->translating ) {
			return $text;
		}

		$this->translating = true;
		try {
		$target_lang = $this->get_current_target_language();
		$cache_key   = 'string_' . md5( $text . $domain . $context . $target_lang );

		// Verifica cache runtime
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		// Verifica cache persistente usando transients
		$transient_key = 'fpml_string_' . $cache_key;
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			$this->cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Traduzione non in cache: schedula un job asincrono e ritorna il testo originale.
		// Questo evita chiamate HTTP sincrone durante il rendering della pagina.
		$this->schedule_async_translation( $text, $domain, $context, $target_lang, $transient_key );

		return $text;
		} finally {
			$this->translating = false;
		}
	}

	/**
	 * Schedule an asynchronous translation job via WP-Cron.
	 *
	 * @param string $text          Text to translate.
	 * @param string $domain        Text domain.
	 * @param string $context       Context.
	 * @param string $target_lang   Target language code.
	 * @param string $transient_key Transient key to store the result.
	 *
	 * @return void
	 */
	protected function schedule_async_translation( string $text, string $domain, string $context, string $target_lang, string $transient_key ): void {
		$lock_key = $transient_key . '_pending';

		// Evita di schedulare lo stesso job più volte
		if ( get_transient( $lock_key ) ) {
			return;
		}

		set_transient( $lock_key, 1, HOUR_IN_SECONDS );

		$args = array(
			'text'          => $text,
			'domain'        => $domain,
			'context'       => $context,
			'target_lang'   => $target_lang,
			'transient_key' => $transient_key,
			'lock_key'      => $lock_key,
		);

		wp_schedule_single_event( time() + 5, 'fpml_translate_string_async', array( $args ) );

		if ( ! has_action( 'fpml_translate_string_async', array( $this, 'handle_async_translation' ) ) ) {
			add_action( 'fpml_translate_string_async', array( $this, 'handle_async_translation' ) );
		}
	}

	/**
	 * Handle an async translation job (called via WP-Cron).
	 *
	 * @param array $args Job arguments.
	 *
	 * @return void
	 */
	public function handle_async_translation( array $args ): void {
		$text          = $args['text'] ?? '';
		$domain        = $args['domain'] ?? 'default';
		$context       = $args['context'] ?? '';
		$target_lang   = $args['target_lang'] ?? ( function_exists( 'fpml_get_primary_target_language' ) ? fpml_get_primary_target_language() : '' );
		$transient_key = $args['transient_key'] ?? '';
		$lock_key      = $args['lock_key'] ?? '';

		if ( '' === $text || '' === $transient_key ) {
			return;
		}

		$translation = $this->translate_with_ai( $text, $domain, $context, $target_lang );

		if ( ! is_wp_error( $translation ) && $translation !== $text ) {
			set_transient( $transient_key, $translation, DAY_IN_SECONDS );
		}

		if ( $lock_key ) {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Translate text using AI provider.
	 *
	 * @param string $text        Text to translate.
	 * @param string $domain      Text domain.
	 * @param string $context     Context.
	 * @param string $target_lang Target language code.
	 *
	 * @return string|\WP_Error
	 */
	protected function translate_with_ai( string $text, string $domain, string $context, string $target_lang = 'en' ) {
		if ( ! $this->processor ) {
			$this->processor = fpml_get_processor();
		}

		if ( ! $this->processor ) {
			return $text;
		}

		$translator = $this->processor->get_translator_instance();

		if ( ! $translator || is_wp_error( $translator ) ) {
			return $text;
		}

	$source = ( class_exists( '\FPML_Language' ) && defined( 'FPML_Language::SOURCE' ) ) ? \FPML_Language::SOURCE : ( function_exists( 'fpml_get_source_language' ) ? fpml_get_source_language() : 'it' );
	$result = $translator->translate( $text, $source, $target_lang, 'general' );

		if ( is_wp_error( $result ) ) {
			return $text;
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



