<?php
/**
 * Secure Settings - Encrypts sensitive data like API keys.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secure settings handler with encryption for API keys.
 *
 * @since 0.4.1
 */
class FPML_Secure_Settings {
	/**
	 * Encryption method.
	 */
	const ENCRYPTION_METHOD = 'AES-256-CBC';

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Secure_Settings|null
	 */
	protected static $instance = null;

	/**
	 * Fields to encrypt.
	 *
	 * @var array
	 */
	protected $encrypted_fields = array(
		'openai_api_key',
		'google_api_key',
	);

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return FPML_Secure_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Hook into settings save to encrypt
		add_filter( 'pre_update_option_fpml_settings', array( $this, 'encrypt_settings' ), 10, 2 );
		// Hook into settings retrieval to decrypt
		add_filter( 'option_fpml_settings', array( $this, 'decrypt_settings' ), 10, 1 );
	}

	/**
	 * Encrypt sensitive settings before saving.
	 *
	 * @since 0.4.1
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Old value.
	 *
	 * @return mixed
	 */
	public function encrypt_settings( $value, $old_value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $this->encrypted_fields as $field ) {
			if ( isset( $value[ $field ] ) && ! empty( $value[ $field ] ) ) {
				// Check if already encrypted (starts with our prefix)
				if ( 0 !== strpos( $value[ $field ], 'ENC:' ) ) {
					$value[ $field ] = $this->encrypt( $value[ $field ] );
				}
			}
		}

		return $value;
	}

	/**
	 * Decrypt sensitive settings when retrieving.
	 *
	 * @since 0.4.1
	 *
	 * @param mixed $value Option value.
	 *
	 * @return mixed
	 */
	public function decrypt_settings( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $this->encrypted_fields as $field ) {
			if ( isset( $value[ $field ] ) && ! empty( $value[ $field ] ) ) {
				// Check if encrypted (starts with our prefix)
				if ( 0 === strpos( $value[ $field ], 'ENC:' ) ) {
					$value[ $field ] = $this->decrypt( $value[ $field ] );
				}
			}
		}

		return $value;
	}

	/**
	 * Encrypt a value.
	 *
	 * @since 0.4.1
	 *
	 * @param string $value Value to encrypt.
	 *
	 * @return string Encrypted value with prefix.
	 */
	protected function encrypt( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		// Fallback if openssl not available
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $value;
		}

		$key = $this->get_encryption_key();
		$iv  = $this->get_iv( $key );

		$encrypted = openssl_encrypt(
			$value,
			self::ENCRYPTION_METHOD,
			$key,
			0,
			$iv
		);

		if ( false === $encrypted ) {
			// Encryption failed, return original
			return $value;
		}

		// Prefix to identify encrypted values
		$encoded = base64_encode( $encrypted );
		if ( false === $encoded ) {
			// Encoding failed, return original
			return $value;
		}

		return 'ENC:' . $encoded;
	}

	/**
	 * Decrypt a value.
	 *
	 * @since 0.4.1
	 *
	 * @param string $value Encrypted value with prefix.
	 *
	 * @return string Decrypted value.
	 */
	protected function decrypt( $value ) {
		if ( empty( $value ) || 0 !== strpos( $value, 'ENC:' ) ) {
			return $value;
		}

		// Fallback if openssl not available
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return substr( $value, 4 ); // Remove prefix and return as-is
		}

		// Remove prefix
		$encrypted = base64_decode( substr( $value, 4 ), true );

		if ( false === $encrypted ) {
			// Decoding failed, return empty to prevent exposure
			return '';
		}

		$key = $this->get_encryption_key();
		$iv  = $this->get_iv( $key );

		$decrypted = openssl_decrypt(
			$encrypted,
			self::ENCRYPTION_METHOD,
			$key,
			0,
			$iv
		);

		if ( false === $decrypted ) {
			// Decryption failed, return empty to prevent exposure
			return '';
		}

		return $decrypted;
	}

	/**
	 * Get encryption key.
	 *
	 * Uses WordPress salts for key derivation.
	 *
	 * @since 0.4.1
	 *
	 * @return string
	 */
	protected function get_encryption_key() {
		// Use WordPress auth key and salt for encryption
		$key = AUTH_KEY . AUTH_SALT;

		// Hash to get consistent 32-byte key for AES-256
		return hash( 'sha256', $key, true );
	}

	/**
	 * Get initialization vector.
	 *
	 * @since 0.4.1
	 *
	 * @param string $key Encryption key.
	 *
	 * @return string
	 */
	protected function get_iv( $key ) {
		// IV must be 16 bytes for AES-256-CBC
		return substr( hash( 'sha256', $key . SECURE_AUTH_KEY, true ), 0, 16 );
	}

	/**
	 * Check if encryption is available.
	 *
	 * @since 0.4.1
	 *
	 * @return bool
	 */
	public static function is_encryption_available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * Migrate existing plain-text API keys to encrypted.
	 *
	 * @since 0.4.1
	 *
	 * @return int Number of keys migrated.
	 */
	public function migrate_existing_keys() {
		$settings = get_option( 'fpml_settings', array() );

		if ( ! is_array( $settings ) ) {
			return 0;
		}

		$migrated = 0;

		foreach ( $this->encrypted_fields as $field ) {
			if ( isset( $settings[ $field ] ) && ! empty( $settings[ $field ] ) ) {
				// Check if not already encrypted
				if ( 0 !== strpos( $settings[ $field ], 'ENC:' ) ) {
					$settings[ $field ] = $this->encrypt( $settings[ $field ] );
					$migrated++;
				}
			}
		}

	if ( $migrated > 0 ) {
		// Update without triggering our filter (to avoid double encryption)
		remove_filter( 'pre_update_option_fpml_settings', array( $this, 'encrypt_settings' ), 10 );
		
		try {
			update_option( 'fpml_settings', $settings );
		} finally {
			// Always re-add filter even if update_option throws
			add_filter( 'pre_update_option_fpml_settings', array( $this, 'encrypt_settings' ), 10, 2 );
		}
	}

	return $migrated;
	}
}
