<?php
/**
 * Options Service Implementation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options service implementation.
 *
 * Manages plugin settings with defaults, validation, and migration support.
 *
 * @since 1.0.0
 */
class Options implements OptionsInterface {
	/**
	 * Option key in database.
	 *
	 * @var string
	 */
	protected $option_key;

	/**
	 * Cached settings.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	protected $defaults = array();

	/**
	 * Constructor.
	 *
	 * @param string $option_key Option key in database.
	 * @param array  $defaults   Default settings.
	 */
	public function __construct( string $option_key, array $defaults = array() ) {
		$this->option_key = $option_key;
		$this->defaults = $defaults;
		$this->load();
	}

	/**
	 * Load settings from database.
	 *
	 * @return void
	 */
	protected function load(): void {
		$saved = get_option( $this->option_key, array() );
		$this->settings = wp_parse_args( $saved, $this->defaults );
	}

	/**
	 * Get an option value.
	 *
	 * @param string $key     Option key (supports dot notation).
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Option value or default.
	 */
	public function get( string $key, $default = null ) {
		// Support dot notation for nested keys
		if ( strpos( $key, '.' ) !== false ) {
			return $this->getNested( $key, $default );
		}

		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Get nested option value using dot notation.
	 *
	 * @param string $key     Option key with dot notation.
	 * @param mixed  $default Default value.
	 * @return mixed Option value or default.
	 */
	protected function getNested( string $key, $default = null ) {
		$keys = explode( '.', $key );
		$value = $this->settings;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Set an option value.
	 *
	 * @param string $key   Option key (supports dot notation).
	 * @param mixed  $value Value to set.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value ): bool {
		// Support dot notation for nested keys
		if ( strpos( $key, '.' ) !== false ) {
			$this->setNested( $key, $value );
		} else {
			$this->settings[ $key ] = $value;
		}

		return $this->save();
	}

	/**
	 * Set nested option value using dot notation.
	 *
	 * @param string $key   Option key with dot notation.
	 * @param mixed  $value Value to set.
	 * @return void
	 */
	protected function setNested( string $key, $value ): void {
		$keys = explode( '.', $key );
		$current = &$this->settings;

		foreach ( $keys as $i => $segment ) {
			if ( $i === count( $keys ) - 1 ) {
				$current[ $segment ] = $value;
				return;
			}

			if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
				$current[ $segment ] = array();
			}

			$current = &$current[ $segment ];
		}
	}

	/**
	 * Delete an option.
	 *
	 * @param string $key Option key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key ): bool {
		if ( isset( $this->settings[ $key ] ) ) {
			unset( $this->settings[ $key ] );
			return $this->save();
		}

		return true;
	}

	/**
	 * Get all options.
	 *
	 * @return array All options.
	 */
	public function all(): array {
		return $this->settings;
	}

	/**
	 * Check if an option exists.
	 *
	 * @param string $key Option key.
	 * @return bool True if exists, false otherwise.
	 */
	public function has( string $key ): bool {
		return isset( $this->settings[ $key ] );
	}

	/**
	 * Save settings to database.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function save(): bool {
		return update_option( $this->option_key, $this->settings );
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	public function getDefaults(): array {
		return $this->defaults;
	}

	/**
	 * Reset to defaults.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function reset(): bool {
		$this->settings = $this->defaults;
		return $this->save();
	}
}













