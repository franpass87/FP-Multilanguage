<?php
/**
 * Test Secure Settings encryption functionality.
 *
 * @package FP_Multilanguage
 */

class Test_FPML_Secure_Settings extends WP_UnitTestCase {
	/**
	 * Secure settings instance.
	 *
	 * @var FPML_Secure_Settings
	 */
	protected $secure_settings;

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->secure_settings = FPML_Secure_Settings::instance();
	}

	/**
	 * Test encryption is available.
	 */
	public function test_encryption_available() {
		$available = FPML_Secure_Settings::is_encryption_available();
		$this->assertTrue( $available, 'OpenSSL should be available' );
	}

	/**
	 * Test encryption and decryption works.
	 */
	public function test_encryption_decryption() {
		if ( ! FPML_Secure_Settings::is_encryption_available() ) {
			$this->markTestSkipped( 'OpenSSL not available' );
		}

		$original = 'sk-test-api-key-12345678';
		
		// Use reflection to access protected methods
		$encrypt_method = new ReflectionMethod( $this->secure_settings, 'encrypt' );
		$encrypt_method->setAccessible( true );
		$encrypted = $encrypt_method->invoke( $this->secure_settings, $original );

		// Verify encrypted value has prefix
		$this->assertStringStartsWith( 'ENC:', $encrypted, 'Encrypted value should have ENC: prefix' );
		$this->assertNotEquals( $original, $encrypted, 'Encrypted value should differ from original' );

		// Decrypt and verify
		$decrypt_method = new ReflectionMethod( $this->secure_settings, 'decrypt' );
		$decrypt_method->setAccessible( true );
		$decrypted = $decrypt_method->invoke( $this->secure_settings, $encrypted );

		$this->assertEquals( $original, $decrypted, 'Decrypted value should match original' );
	}

	/**
	 * Test settings encryption filter.
	 */
	public function test_settings_encryption_filter() {
		$settings = array(
			'openai_api_key' => 'sk-test-openai-key',
			'provider'       => 'openai',
		);

		$encrypted_settings = $this->secure_settings->encrypt_settings( $settings, array() );

		// API keys should be encrypted
		$this->assertStringStartsWith( 'ENC:', $encrypted_settings['openai_api_key'], 'OpenAI key should be encrypted' );
		
		// Non-sensitive fields should remain unchanged
		$this->assertEquals( 'openai', $encrypted_settings['provider'], 'Provider should not be encrypted' );
	}

	/**
	 * Test settings decryption filter.
	 */
	public function test_settings_decryption_filter() {
		$encrypted_settings = array(
			'openai_api_key' => 'ENC:dGVzdC1lbmNyeXB0ZWQta2V5',
			'provider'       => 'openai',
		);

		$decrypted_settings = $this->secure_settings->decrypt_settings( $encrypted_settings );

		// Should attempt to decrypt
		$this->assertIsString( $decrypted_settings['openai_api_key'] );
		
		// Non-encrypted fields should remain unchanged
		$this->assertEquals( 'openai', $decrypted_settings['provider'] );
	}

	/**
	 * Test empty value handling.
	 */
	public function test_empty_value_handling() {
		$settings = array(
			'openai_api_key' => '',
			'provider'       => 'openai',
		);

		$encrypted = $this->secure_settings->encrypt_settings( $settings, array() );
		
		// Empty values should remain empty
		$this->assertEquals( '', $encrypted['openai_api_key'], 'Empty value should remain empty' );
	}

	/**
	 * Test migration of existing keys.
	 */
	public function test_migration() {
		// Setup: Add plain text API key
		update_option( 'fpml_settings', array(
			'openai_api_key' => 'plain-text-key',
			'provider'       => 'openai',
		) );

		// Run migration
		$migrated = $this->secure_settings->migrate_existing_keys();

		$this->assertGreaterThan( 0, $migrated, 'Should migrate at least one key' );

		// Verify key is now encrypted
		$settings = get_option( 'fpml_settings' );
		$this->assertStringStartsWith( 'ENC:', $settings['openai_api_key'], 'Key should be encrypted after migration' );
	}

	/**
	 * Test double encryption prevention.
	 */
	public function test_double_encryption_prevention() {
		$settings = array(
			'openai_api_key' => 'ENC:already-encrypted',
			'provider'       => 'openai',
		);

		$encrypted = $this->secure_settings->encrypt_settings( $settings, array() );

		// Should not double-encrypt
		$this->assertEquals( 'ENC:already-encrypted', $encrypted['openai_api_key'], 'Should not double-encrypt' );
	}
}
