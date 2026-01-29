<?php
/**
 * REST Service Provider.
 *
 * Registers REST API endpoints and handlers.
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
 * REST service provider.
 *
 * @since 1.0.0
 */
class RESTServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// REST Admin Controller (registers routes)
		$container->bind( 'rest.admin', function( Container $c ) {
			// Try new location first
			if ( class_exists( '\FP\Multilanguage\Rest\Controllers\AdminController' ) ) {
				return \FP\Multilanguage\Rest\Controllers\AdminController::instance();
			}
			// Fallback to old location for backward compatibility
			if ( class_exists( '\FP\Multilanguage\Rest\RestAdmin' ) ) {
				return \FP\Multilanguage\Rest\RestAdmin::instance();
			}
			return null;
		}, true );

		// REST Route Registrar
		$container->bind( 'rest.route_registrar', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Rest\RouteRegistrar' ) ) {
				return new \FP\Multilanguage\Rest\RouteRegistrar();
			}
			return null;
		}, true );

		// REST Handlers
		$container->bind( 'rest.handlers.provider', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Rest\Handlers\ProviderHandler' ) ) {
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				$queue = $c->has( 'queue' ) ? $c->get( 'queue' ) : null;
				return new \FP\Multilanguage\Rest\Handlers\ProviderHandler( $logger, $queue );
			}
			return null;
		}, true );

		$container->bind( 'rest.handlers.queue', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Rest\Handlers\QueueHandler' ) ) {
				$queue = $c->has( 'queue' ) ? $c->get( 'queue' ) : null;
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				return new \FP\Multilanguage\Rest\Handlers\QueueHandler( $queue, $logger );
			}
			return null;
		}, true );

		$container->bind( 'rest.handlers.translation', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Rest\Handlers\TranslationHandler' ) ) {
				$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				return new \FP\Multilanguage\Rest\Handlers\TranslationHandler( $translation_manager, $logger );
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
		// REST routes are registered via rest_api_init hook
		// This is handled by RestAdmin::__construct()
		// No additional boot logic needed here
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'rest.admin',
			'rest.route_registrar',
			'rest.handlers.provider',
			'rest.handlers.queue',
			'rest.handlers.translation',
		);
	}
}









