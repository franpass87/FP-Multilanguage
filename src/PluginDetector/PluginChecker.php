<?php
/**
 * Plugin Detector Plugin Checker - Checks if plugins are active.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\PluginDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks if plugins are active based on detection rules.
 *
 * @since 0.10.0
 */
class PluginChecker {
	/**
	 * Check if a plugin is active based on detection rule.
	 *
	 * @since 0.10.0
	 *
	 * @param array $rule Detection rule.
	 * @return bool
	 */
	public function is_plugin_active( array $rule ): bool {
		if ( ! isset( $rule['check'] ) ) {
			return false;
		}

		$check = $rule['check'];

		// Check by class existence
		if ( isset( $check['class'] ) ) {
			return class_exists( $check['class'] );
		}

		// Check by function existence
		if ( isset( $check['function'] ) ) {
			return function_exists( $check['function'] );
		}

		// Check by constant
		if ( isset( $check['constant'] ) ) {
			return defined( $check['constant'] );
		}

		// Check by plugin file
		if ( isset( $check['plugin'] ) ) {
			return is_plugin_active( $check['plugin'] );
		}

		return false;
	}

	/**
	 * Detect active plugins from rules.
	 *
	 * @since 0.10.0
	 *
	 * @param array                      $rules  Detection rules.
	 * @param \FP\Multilanguage\Logger $logger Logger instance.
	 * @return array Detected plugins.
	 */
	public function detect_active_plugins( array $rules, $logger ): array {
		$detected = array();

		foreach ( $rules as $slug => $rule ) {
			if ( $this->is_plugin_active( $rule ) ) {
				$detected[ $slug ] = $rule;

				$logger->log(
					'info',
					sprintf( 'Plugin rilevato: %s', $rule['name'] ),
					array( 'slug' => $slug, 'fields_count' => count( $rule['fields'] ) )
				);
			}
		}

		// Save detection cache
		update_option( '\FPML_detected_plugins', array_keys( $detected ), false );

		return $detected;
	}
}















