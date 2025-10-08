<?php
/**
 * Core plugin bootstrap - Backward compatibility wrapper.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class - Compatibility wrapper.
 *
 * This class now extends FPML_Plugin_Core for backward compatibility.
 * All functionality has been moved to modular services.
 *
 * @since 0.2.0
 * @deprecated 0.4.0 Use FPML_Plugin_Core and modular services via FPML_Container.
 */
class FPML_Plugin extends FPML_Plugin_Core {
	/**
	 * Singleton instance for backward compatibility.
	 *
	 * @var FPML_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * Maintains separate singleton from parent class for BC.
	 *
	 * @since 0.2.0
	 *
	 * @return FPML_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Backward compatibility: creating_translation flag.
	 *
	 * @deprecated 0.4.0 Use translation_manager->is_creating_translation() instead.
	 *
	 * @return bool
	 */
	public function __get( $name ) {
		if ( 'creating_translation' === $name && $this->translation_manager ) {
			return $this->translation_manager->is_creating_translation();
		}

		if ( 'creating_term_translation' === $name && $this->translation_manager ) {
			return $this->translation_manager->is_creating_term_translation();
		}

		return null;
	}
}
