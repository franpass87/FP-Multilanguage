<?php
/**
 * Core plugin - VERSIONE MINIMAL per test.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin core con costruttore MINIMAL.
 */
class FPML_Plugin_Core_Minimal {
	protected static $instance = null;

	/**
	 * Costruttore VUOTO - non fa nulla.
	 */
	protected function __construct() {
		// Vuoto - test
	}

	/**
	 * Singleton.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Activation - vuoto.
	 */
	public static function activate() {
		update_option( 'fpml_needs_setup', '1', false );
	}

	/**
	 * Deactivation - vuoto.
	 */
	public static function deactivate() {
		// Vuoto
	}
}

