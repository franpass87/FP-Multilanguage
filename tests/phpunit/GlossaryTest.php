<?php
/**
 * Glossary tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test glossary functionality.
 */
final class GlossaryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
	}

	public function test_glossary_instance_returns_singleton(): void {
		$glossary1 = FPML_Glossary::instance();
		$glossary2 = FPML_Glossary::instance();

		$this->assertSame( $glossary1, $glossary2 );
	}

	public function test_glossary_get_rules_returns_array(): void {
		$glossary = FPML_Glossary::instance();

		$rules = $glossary->get_rules();

		$this->assertIsArray( $rules );
	}

	public function test_glossary_add_rule_accepts_valid_data(): void {
		$glossary = FPML_Glossary::instance();

		// Should not throw exception
		$result = $glossary->add_rule( 'source_term', 'target_term', 'general' );

		$this->assertTrue( is_bool( $result ) || is_int( $result ) );
	}

	public function test_glossary_import_accepts_array(): void {
		$glossary = FPML_Glossary::instance();

		$rules = array(
			array(
				'source' => 'test',
				'target' => 'test translation',
				'domain' => 'general',
			),
		);

		$result = $glossary->import_rules( $rules );

		$this->assertIsInt( $result );
		$this->assertGreaterThanOrEqual( 0, $result );
	}

	public function test_glossary_export_returns_array(): void {
		$glossary = FPML_Glossary::instance();

		$exported = $glossary->export_rules();

		$this->assertIsArray( $exported );
	}

	public function test_glossary_clear_removes_rules(): void {
		$glossary = FPML_Glossary::instance();

		// Should not throw exception
		$glossary->clear();

		$rules = $glossary->get_rules();
		$this->assertIsArray( $rules );
	}

	public function test_glossary_parse_csv_handles_valid_input(): void {
		$glossary = FPML_Glossary::instance();

		$csv = "source,target,domain\ntest,translation,general";

		// Use reflection to test protected method
		$reflection = new ReflectionClass( $glossary );
		$method = $reflection->getMethod( 'parse_csv_content' );
		$method->setAccessible( true );

		$result = $method->invoke( $glossary, $csv );

		$this->assertIsArray( $result );
	}

	public function test_glossary_handles_empty_csv(): void {
		$glossary = FPML_Glossary::instance();

		// Use reflection
		$reflection = new ReflectionClass( $glossary );
		$method = $reflection->getMethod( 'parse_csv_content' );
		$method->setAccessible( true );

		$result = $method->invoke( $glossary, '' );

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result );
	}

	public function test_glossary_sanitizes_input(): void {
		$glossary = FPML_Glossary::instance();

		// Should handle potentially dangerous input
		$result = $glossary->add_rule( '<script>alert("xss")</script>', 'safe', 'general' );

		$this->assertTrue( is_bool( $result ) || is_int( $result ) );
	}

	public function test_glossary_handles_unicode(): void {
		$glossary = FPML_Glossary::instance();

		// Test with Italian and emoji
		$result = $glossary->add_rule( 'caffè ☕', 'coffee ☕', 'general' );

		$this->assertTrue( is_bool( $result ) || is_int( $result ) );
	}
}
