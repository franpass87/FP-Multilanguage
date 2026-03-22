<?php
/**
 * Multi-Language Manager (IT → EN, DE, FR, ES, PT, NL, PL, RU, ZH, JA, AR).
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

declare(strict_types=1);

namespace FP\Multilanguage\MultiLanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LanguageManager {
	use ContainerAwareTrait;
	protected static $instance = null;
	private const MAX_LANGUAGE_CODES = 64;
	private bool $resolving_enabled_languages = false;
	private bool $resolving_available_languages = false;
	private bool $logged_enabled_reentry = false;
	private bool $logged_available_reentry = false;
	/**
	 * @var array<int, string>|null
	 */
	private ?array $enabled_languages_cache = null;
	/**
	 * @var array<string, array>|null
	 */
	private ?array $available_languages_cache = null;
	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $settings_cache = null;

	/**
	 * All languages known to the plugin. Extendable via the `fpml_available_languages` filter.
	 * Keys are ISO 639-1 codes; values include name, flag, URL slug, and WP locale.
	 */
	protected $available_languages = array(
		'it' => array( 'name' => 'Italiano',    'flag' => '🇮🇹', 'slug' => '/',    'locale' => 'it_IT' ),
		'en' => array( 'name' => 'English',     'flag' => '🇬🇧', 'slug' => '/en/', 'locale' => 'en_US' ),
		'de' => array( 'name' => 'Deutsch',     'flag' => '🇩🇪', 'slug' => '/de/', 'locale' => 'de_DE' ),
		'fr' => array( 'name' => 'Français',    'flag' => '🇫🇷', 'slug' => '/fr/', 'locale' => 'fr_FR' ),
		'es' => array( 'name' => 'Español',     'flag' => '🇪🇸', 'slug' => '/es/', 'locale' => 'es_ES' ),
		'pt' => array( 'name' => 'Português',   'flag' => '🇵🇹', 'slug' => '/pt/', 'locale' => 'pt_PT' ),
		'nl' => array( 'name' => 'Nederlands',  'flag' => '🇳🇱', 'slug' => '/nl/', 'locale' => 'nl_NL' ),
		'pl' => array( 'name' => 'Polski',      'flag' => '🇵🇱', 'slug' => '/pl/', 'locale' => 'pl_PL' ),
		'ru' => array( 'name' => 'Русский',     'flag' => '🇷🇺', 'slug' => '/ru/', 'locale' => 'ru_RU' ),
		'zh' => array( 'name' => '中文',         'flag' => '🇨🇳', 'slug' => '/zh/', 'locale' => 'zh_CN' ),
		'ja' => array( 'name' => '日本語',       'flag' => '🇯🇵', 'slug' => '/ja/', 'locale' => 'ja'    ),
		'ar' => array( 'name' => 'العربية',     'flag' => '🇸🇦', 'slug' => '/ar/', 'locale' => 'ar'    ),
	);

	/**
	 * Get singleton instance.
	 *
	 * @since 0.5.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get enabled languages.
	 *
	 * @since 0.5.0
	 *
	 * @return array Array of enabled language codes.
	 */
	public function get_enabled_languages(): array {
		if ( is_array( $this->enabled_languages_cache ) ) {
			return $this->enabled_languages_cache;
		}

		$settings = $this->get_settings_array();
		$enabled  = $settings['enabled_languages'] ?? array();
		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}

		// Prevent re-entrant recursion via fpml_enabled_languages callbacks.
		if ( ! $this->resolving_enabled_languages ) {
			$this->resolving_enabled_languages = true;
			try {
				$enabled = apply_filters( 'fpml_enabled_languages', $enabled );
			} finally {
				$this->resolving_enabled_languages = false;
			}
		} elseif ( ! $this->logged_enabled_reentry ) {
			$this->logged_enabled_reentry = true;
			$this->debug_log( 'Re-entrant call blocked in get_enabled_languages()', array( 'filter' => current_filter() ) );
		}

		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}

		$enabled = array_values(
			array_filter(
				array_map( 'sanitize_key', $enabled ),
				static function ( $code ) {
					return is_string( $code ) && '' !== $code;
				}
			)
		);
		if ( count( $enabled ) > self::MAX_LANGUAGE_CODES ) {
			$enabled = array_slice( $enabled, 0, self::MAX_LANGUAGE_CODES );
			$this->debug_log(
				'Enabled languages list truncated to safety cap.',
				array( 'max' => self::MAX_LANGUAGE_CODES )
			);
		}

		// Filter out codes not in the known languages list (including filter-added ones).
		$valid_codes = array_keys( $this->get_all_languages() );
		$enabled     = array_values( array_intersect( $enabled, $valid_codes ) );
		$source      = $this->get_source_language();

		// Never expose source language as a target language.
		if ( '' !== $source ) {
			$enabled = array_values(
				array_filter(
					$enabled,
					static function ( string $code ) use ( $source ): bool {
						return $code !== $source;
					}
				)
			);
		}

		$this->enabled_languages_cache = array_values( array_unique( $enabled ) );
		return $this->enabled_languages_cache;
	}

	/**
	 * Resolve source language code.
	 *
	 * @since 1.0.0
	 *
	 * @return string Source language code.
	 */
	public function get_source_language(): string {
		$settings  = $this->get_settings_array();
		$source    = isset( $settings['source_language'] ) ? (string) $settings['source_language'] : '';
		$source    = sanitize_key( $source );

		if ( '' === $source ) {
			// Avoid locale-filter recursion in admin/bootstrap contexts.
			$source = 'it';
		}

		$valid_codes = array_keys( $this->get_all_languages() );
		if ( '' === $source || ! in_array( $source, $valid_codes, true ) ) {
			return 'it';
		}

		return $source;
	}

	/**
	 * Get language information by code.
	 *
	 * @since 0.5.0
	 *
	 * @param string $code Language code.
	 * @return array|null Language info array or null if not found.
	 */
	public function get_language_info( string $code ): ?array {
		return $this->get_all_languages()[ $code ] ?? null;
	}

	/**
	 * Get all available languages.
	 *
	 * Third-party code can extend the list via the `fpml_available_languages` filter.
	 *
	 * @since 0.5.0
	 *
	 * @return array Array of all available languages [code => info].
	 */
	public function get_all_languages(): array {
		if ( is_array( $this->available_languages_cache ) ) {
			return $this->available_languages_cache;
		}

		// Prevent re-entrant recursion via fpml_available_languages callbacks.
		if ( $this->resolving_available_languages ) {
			if ( ! $this->logged_available_reentry ) {
				$this->logged_available_reentry = true;
				$this->debug_log( 'Re-entrant call blocked in get_all_languages()', array( 'filter' => current_filter() ) );
			}
			return $this->available_languages;
		}

		$this->resolving_available_languages = true;
		try {
			$languages = apply_filters( 'fpml_available_languages', $this->available_languages );
		} finally {
			$this->resolving_available_languages = false;
		}

		if ( ! is_array( $languages ) ) {
			$languages = $this->available_languages;
		}

		$normalized = array();
		foreach ( $languages as $code => $info ) {
			$key = sanitize_key( (string) $code );
			if ( '' === $key || ! is_array( $info ) ) {
				continue;
			}
			$normalized[ $key ] = $info;
			if ( count( $normalized ) >= self::MAX_LANGUAGE_CODES ) {
				$this->debug_log(
					'Available languages list truncated to safety cap.',
					array( 'max' => self::MAX_LANGUAGE_CODES )
				);
				break;
			}
		}

		if ( $normalized === array() ) {
			$normalized = $this->available_languages;
		}

		$this->available_languages_cache = $normalized;
		return $this->available_languages_cache;
	}

	/**
	 * Load plugin settings directly from options table (cached per request).
	 *
	 * This avoids DI container re-entry loops in admin bootstrap.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings_array(): array {
		if ( is_array( $this->settings_cache ) ) {
			return $this->settings_cache;
		}

		$settings = get_option( 'fpml_settings', array() );
		if ( ! is_array( $settings ) || $settings === array() ) {
			$legacy = get_option( '\FPML_settings', array() );
			$settings = is_array( $legacy ) ? $legacy : array();
		}

		$this->settings_cache = $settings;
		return $this->settings_cache;
	}

	/**
	 * Emit debug logs only when WP_DEBUG is enabled.
	 *
	 * @param string $message Log message.
	 * @param array  $context Context payload.
	 * @return void
	 */
	private function debug_log( string $message, array $context = array() ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'error_log' ) ) {
			return;
		}

		$payload = empty( $context ) ? '' : ' ' . wp_json_encode( $context );
		error_log( '[FPML LanguageManager] ' . $message . $payload );
	}
}

