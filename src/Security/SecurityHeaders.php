<?php
/**
 * Security Headers.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecurityHeaders {
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'send_headers', array( $this, 'send_security_headers' ) );
	}

	public function send_security_headers() {
		if ( ! is_admin() ) {
			return;
		}

		// Prevent MIME-type sniffing
		header( 'X-Content-Type-Options: nosniff' );

		// Prevent clickjacking
		header( 'X-Frame-Options: SAMEORIGIN' );

		// XSS Protection
		header( 'X-XSS-Protection: 1; mode=block' );

		// Referrer Policy
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		// Permissions Policy
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	}
}

