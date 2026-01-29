<?php
/**
 * Security Service Provider.
 *
 * Registers security-related services (SecurityHeaders, AuditLog).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security service provider.
 *
 * @since 1.0.0
 */
class SecurityServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Security Headers
		$container->bind( 'security.headers', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\SecurityHeaders' ) ) {
				return \FP\Multilanguage\SecurityHeaders::instance();
			}
			return null;
		}, true );

		// Audit Log
		$container->bind( 'security.audit_log', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\AuditLog' ) ) {
				return \FP\Multilanguage\AuditLog::instance();
			}
			return null;
		}, true );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Security services use singleton pattern
		// They are initialized when ::instance() is called
		// No additional boot logic needed
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'security.headers',
			'security.audit_log',
		);
	}
}
