<?php
/**
 * Plugin Detector Whitelist Manager - Manages field whitelist.
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
 * Manages field whitelist for detected plugins.
 *
 * @since 0.10.0
 */
class WhitelistManager {
	/**
	 * Add detected plugin fields to whitelist.
	 *
	 * @since 0.10.0
	 *
	 * @param array $whitelist        Current whitelist.
	 * @param array $detected_plugins Detected plugins.
	 * @return array
	 */
	public function add_detected_fields_to_whitelist( array $whitelist, array $detected_plugins ): array {
		foreach ( $detected_plugins as $slug => $rule ) {
			// Add static fields
			if ( ! empty( $rule['fields'] ) ) {
				foreach ( $rule['fields'] as $field ) {
					if ( ! in_array( $field, $whitelist, true ) ) {
						$whitelist[] = $field;
					}
				}
			}

			// Execute callback for dynamic field detection
			if ( isset( $rule['callback'] ) && is_callable( $rule['callback'] ) ) {
				$dynamic_fields = call_user_func( $rule['callback'], null );
				if ( is_array( $dynamic_fields ) ) {
					foreach ( $dynamic_fields as $field ) {
						if ( ! in_array( $field, $whitelist, true ) ) {
							$whitelist[] = $field;
						}
					}
				}
			}
		}

		return $whitelist;
	}
}















