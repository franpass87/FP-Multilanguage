<?php
/**
 * Multi-Language Manager (IT → EN, DE, FR, ES).
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

	protected $available_languages = array(
		'it' => array(
			'name'   => 'Italiano',
			'flag'   => '🇮🇹',
			'slug'   => '/',
			'locale' => 'it_IT',
		),
		'en' => array(
			'name'   => 'English',
			'flag'   => '🇬🇧',
			'slug'   => '/en/',
			'locale' => 'en_US',
		),
		'de' => array(
			'name'   => 'Deutsch',
			'flag'   => '🇩🇪',
			'slug'   => '/de/',
			'locale' => 'de_DE',
		),
		'fr' => array(
			'name'   => 'Français',
			'flag'   => '🇫🇷',
			'slug'   => '/fr/',
			'locale' => 'fr_FR',
		),
		'es' => array(
			'name'   => 'Español',
			'flag'   => '🇪🇸',
			'slug'   => '/es/',
			'locale' => 'es_ES',
		),
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
		$settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
		// Default: Italian (source), English and German as main languages
		$enabled = $settings->get( 'enabled_languages', array( 'en', 'de' ) );
		$enabled = apply_filters( 'fpml_enabled_languages', $enabled );
		
		// Ensure we always return a valid array
		if ( ! is_array( $enabled ) ) {
			$enabled = array( 'en', 'de' );
		}
		
		// Filter out invalid language codes
		$valid_codes = array_keys( $this->available_languages );
		$enabled = array_intersect( $enabled, $valid_codes );
		
		// Ensure at least one language is enabled (default to en and de)
		if ( empty( $enabled ) ) {
			$enabled = array( 'en', 'de' );
		}
		
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
		return $this->available_languages[ $code ] ?? null;
	}

	/**
	 * Get all available languages.
	 *
	 * @since 0.5.0
	 *
	 * @return array Array of all available languages [code => info].
	 */
	public function get_all_languages(): array {
		return $this->available_languages;
	}
}

