<?php
/**
 * Integration tests for complete workflow.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test complete translation workflow.
 */
final class IntegrationTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_SERVER = array();
	}

	public function test_plugin_singleton_pattern(): void {
		$plugin1 = FPML_Plugin::instance();
		$plugin2 = FPML_Plugin::instance();

		$this->assertSame( $plugin1, $plugin2, 'Plugin should be singleton' );
	}

	public function test_all_core_classes_are_loadable(): void {
		$classes = array(
			'FPML_Plugin',
			'FPML_Queue',
			'FPML_Settings',
			'FPML_Logger',
			'FPML_Language',
			'FPML_Processor',
			'FPML_Glossary',
			'FPML_Strings_Override',
			'FPML_Strings_Scanner',
			'FPML_Export_Import',
			'FPML_Rate_Limiter',
			'FPML_Webhooks',
		);

		foreach ( $classes as $class ) {
			$this->assertTrue( class_exists( $class ), "Class $class should exist" );
		}
	}

	public function test_all_provider_classes_are_loadable(): void {
		$providers = array(
			'FPML_Provider_OpenAI',
			'FPML_Provider_DeepL',
			'FPML_Provider_Google',
			'FPML_Provider_LibreTranslate',
		);

		foreach ( $providers as $provider ) {
			$this->assertTrue( class_exists( $provider ), "Provider $provider should exist" );
		}
	}

	public function test_interface_exists(): void {
		$this->assertTrue( interface_exists( 'FPML_TranslatorInterface' ) );
	}

	public function test_plugin_detects_assisted_mode(): void {
		$plugin = FPML_Plugin::instance();

		$is_assisted = $plugin->is_assisted_mode();

		$this->assertIsBool( $is_assisted );
	}

	public function test_plugin_returns_diagnostics_snapshot(): void {
		$plugin = FPML_Plugin::instance();

		$snapshot = $plugin->get_diagnostics_snapshot();

		$this->assertIsArray( $snapshot );
	}

	public function test_language_class_defines_constants(): void {
		$this->assertEquals( 'en', FPML_Language::TARGET );
		$this->assertEquals( 'it', FPML_Language::SOURCE );
		$this->assertEquals( 'fpml_lang_pref', FPML_Language::COOKIE_NAME );
	}

	public function test_queue_table_structure_is_valid(): void {
		$queue = FPML_Queue::instance();

		$table = $queue->get_table();

		$this->assertIsString( $table );
		$this->assertStringContainsString( 'fpml_queue', $table );
	}

	public function test_settings_instance_is_accessible(): void {
		$settings = FPML_Settings::instance();

		$this->assertInstanceOf( FPML_Settings::class, $settings );
	}

	public function test_logger_stores_and_retrieves_logs(): void {
		$logger = FPML_Logger::instance();

		// Clear existing logs
		$logger->clear();

		// Add test log
		$logger->log( 'info', 'Test message', array( 'test' => true ) );

		// Retrieve logs
		$logs = $logger->get_logs( 1 );

		$this->assertIsArray( $logs );
		$this->assertCount( 1, $logs );
		$this->assertEquals( 'Test message', $logs[0]['message'] );
		$this->assertEquals( 'info', $logs[0]['level'] );
	}

	public function test_rate_limiter_tracks_requests(): void {
		FPML_Rate_Limiter::reset( 'test_provider' );

		// Should be available initially
		$this->assertTrue( FPML_Rate_Limiter::can_make_request( 'test_provider', 5 ) );

		// Record requests
		for ( $i = 0; $i < 5; $i++ ) {
			FPML_Rate_Limiter::record_request( 'test_provider' );
		}

		// Should hit limit
		$this->assertFalse( FPML_Rate_Limiter::can_make_request( 'test_provider', 5 ) );

		// Cleanup
		FPML_Rate_Limiter::reset( 'test_provider' );
	}

	public function test_glossary_instance_is_accessible(): void {
		$glossary = FPML_Glossary::instance();

		$this->assertInstanceOf( FPML_Glossary::class, $glossary );
	}

	public function test_export_import_instance_is_accessible(): void {
		$exporter = FPML_Export_Import::instance();

		$this->assertInstanceOf( FPML_Export_Import::class, $exporter );
	}

	public function test_plugin_constants_are_defined(): void {
		$this->assertTrue( defined( 'FPML_PLUGIN_VERSION' ) );
		$this->assertTrue( defined( 'FPML_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'FPML_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'FPML_PLUGIN_URL' ) );
	}

	public function test_queue_schema_version_is_defined(): void {
		$this->assertEquals( '2', FPML_Queue::SCHEMA_VERSION );
	}

	public function test_webhooks_instance_is_accessible(): void {
		$webhooks = FPML_Webhooks::instance();

		$this->assertInstanceOf( FPML_Webhooks::class, $webhooks );
	}
}
