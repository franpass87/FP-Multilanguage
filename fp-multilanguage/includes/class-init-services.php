<?php
/**
 * Services Initialization
 *
 * Initializes all services to activate their hooks and functionality.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Init_Services
 *
 * Initializes all plugin services on plugins_loaded.
 * This ensures that all singleton instances are created and their
 * constructor hooks are registered.
 *
 * @since 0.5.0
 */
class FPML_Init_Services {

	/**
	 * Initialize all services.
	 *
	 * @return void
	 */
	public static function init() {
		// Wait for container to be ready
		if ( ! class_exists( 'FPML_Container' ) ) {
			return;
		}

		// Initialize v0.5.0 services
		self::init_v050_services();
	}

	/**
	 * Initialize v0.5.0 services.
	 *
	 * @return void
	 */
	private static function init_v050_services() {
		// Bulk Translation Manager
		if ( class_exists( 'FPML_Bulk_Translation_Manager' ) ) {
			FPML_Bulk_Translation_Manager::instance();
		}

		// Analytics Dashboard
		if ( class_exists( 'FPML_Analytics_Dashboard' ) ) {
			FPML_Analytics_Dashboard::instance();
		}

		// Advanced Glossary
		if ( class_exists( 'FPML_Advanced_Glossary' ) ) {
			FPML_Advanced_Glossary::instance();
		}

		// Translation Memory
		if ( class_exists( 'FPML_Translation_Memory' ) ) {
			FPML_Translation_Memory::instance();
		}

		// Public API
		if ( class_exists( 'FPML_Public_API' ) ) {
			FPML_Public_API::instance();
		}

		// Webhook Notifications
		if ( class_exists( 'FPML_Webhook_Notifications' ) ) {
			FPML_Webhook_Notifications::instance();
		}

		// Debug Mode
		if ( class_exists( 'FPML_Debug_Mode' ) ) {
			FPML_Debug_Mode::instance();
		}
	}
}

// Initialize on plugins_loaded with priority 20 (after core services)
add_action( 'plugins_loaded', array( 'FPML_Init_Services', 'init' ), 20 );
