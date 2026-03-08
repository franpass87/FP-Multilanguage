<?php
/**
 * Multi-Language Manager (IT → EN, DE, FR, ES, PT, NL, PL, RU, ZH, JA, AR).
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\MultiLanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LanguageManager {
	use ContainerAwareTrait;
	protected static $instance = null;

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
		$container = $this->getContainer();
		$settings  = $container && $container->has( 'options' ) ? $container->get( 'options' ) : ( class_exists( '\FPML_Settings' ) ? \FPML_Settings::instance() : null );
		$enabled   = ( $settings && method_exists( $settings, 'get' ) ) ? $settings->get( 'enabled_languages', array() ) : array();
		$enabled   = apply_filters( 'fpml_enabled_languages', $enabled );

		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}

		// Filter out codes not in the known languages list (including filter-added ones).
		$valid_codes = array_keys( $this->get_all_languages() );
		$enabled     = array_values( array_intersect( $enabled, $valid_codes ) );

		return array_values( array_unique( $enabled ) );
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
		return apply_filters( 'fpml_available_languages', $this->available_languages );
	}
}

