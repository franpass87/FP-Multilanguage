<?php
/**
 * Options Interface.
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
 * Options interface for managing plugin settings.
 *
 * @since 1.0.0
 */
interface OptionsInterface {
	/**
	 * Get an option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Option value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Set an option value.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Value to set.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value ): bool;

	/**
	 * Delete an option.
	 *
	 * @param string $key Option key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key ): bool;

	/**
	 * Get all options.
	 *
	 * @return array All options.
	 */
	public function all(): array;

	/**
	 * Check if an option exists.
	 *
	 * @param string $key Option key.
	 * @return bool True if exists, false otherwise.
	 */
	public function has( string $key ): bool;
}













