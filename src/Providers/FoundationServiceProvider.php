<?php
/**
 * Foundation Service Provider.
 *
 * Registers cross-cutting services (Logger, Cache, Options, etc.).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\ServiceProvider;
use FP\Multilanguage\Foundation\Logger\Logger;
use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use FP\Multilanguage\Foundation\Cache\CacheInterface;
use FP\Multilanguage\Foundation\Cache\TransientCache;
use FP\Multilanguage\Foundation\Options\OptionsInterface;
use FP\Multilanguage\Foundation\Options\Options;
use FP\Multilanguage\Foundation\Validation\ValidatorInterface;
use FP\Multilanguage\Foundation\Validation\Validator;
use FP\Multilanguage\Foundation\Sanitization\SanitizerInterface;
use FP\Multilanguage\Foundation\Sanitization\Sanitizer;
use FP\Multilanguage\Foundation\Http\HttpClientInterface;
use FP\Multilanguage\Foundation\Http\HttpClient;
use FP\Multilanguage\Foundation\Environment\EnvironmentChecker;
use FP\Multilanguage\Foundation\Environment\CompatibilityChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Foundation service provider.
 *
 * @since 1.0.0
 */
class FoundationServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Logger - Create instance with Settings dependency
		$container->bind( 'logger.core', function( Container $c ) {
			$settings = $c->has( 'settings' ) ? $c->get( 'settings' ) : null;
			$min_level = $settings ? $settings->get( 'log_level', defined( 'WP_DEBUG' ) && WP_DEBUG ? 'debug' : 'info' ) : ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'debug' : 'info' );
			
			// Create Logger with Settings dependency
			$logger = new \FP\Multilanguage\Logger( $settings );
			return $logger;
		}, true );

		// Logger - Use LoggerAdapter for backward compatibility with static methods
		$container->bind( 'logger', function( Container $c ) {
			$core_logger = $c->get( 'logger.core' );
			
			// Return LoggerAdapter which provides static method compatibility
			if ( class_exists( '\FP\Multilanguage\Foundation\Logger\LoggerAdapter' ) ) {
				$adapter = \FP\Multilanguage\Foundation\Logger\LoggerAdapter::instance();
				// Inject the logger into the adapter using reflection
				$reflection = new \ReflectionClass( $adapter );
				$property = $reflection->getProperty( 'logger' );
				$property->setAccessible( true );
				$property->setValue( $adapter, $core_logger );
				return $adapter;
			}
			
			return $core_logger;
		}, true );

		$container->alias( LoggerInterface::class, 'logger' );

		// Cache
		$container->bind( 'cache', function() {
			return new TransientCache( 'fpml_' );
		}, true );

		$container->alias( CacheInterface::class, 'cache' );

		// Options - Use SettingsAdapter which wraps Foundation\Options\Options
		// Also register Settings class directly for backward compatibility
		$container->bind( 'settings', function( Container $c ) {
			// Create new instance (no singleton) for DI
			return new \FP\Multilanguage\Settings();
		}, true );

		$container->bind( 'options', function( Container $c ) {
			// Use SettingsAdapter which maintains backward compatibility
			if ( class_exists( '\FP\Multilanguage\Foundation\Options\SettingsAdapter' ) ) {
				$adapter = \FP\Multilanguage\Foundation\Options\SettingsAdapter::instance();
				// Return the wrapped Options service
				return $adapter;
			}
			// Fallback: try to get Settings from container
			if ( $c->has( 'settings' ) ) {
				return $c->get( 'settings' );
			}
			// Fallback to new Options class with defaults
			$defaults = $this->getDefaultOptions();
			return new Options( '\FPML_settings', $defaults );
		}, true );

		$container->alias( OptionsInterface::class, 'options' );

		// Validator
		$container->bind( 'validator', function() {
			return new Validator();
		}, true );

		$container->alias( ValidatorInterface::class, 'validator' );

		// Sanitizer
		$container->bind( 'sanitizer', function() {
			return new Sanitizer();
		}, true );

		$container->alias( SanitizerInterface::class, 'sanitizer' );

		// HTTP Client
		$container->bind( 'http.client', function() {
			return new HttpClient( 30 );
		}, true );

		$container->alias( HttpClientInterface::class, 'http.client' );

		// Environment Checker
		$container->bind( 'environment.checker', function() {
			return new EnvironmentChecker();
		}, true );

		// Compatibility Checker
		$container->bind( 'compatibility.checker', function() {
			return new CompatibilityChecker();
		}, true );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Foundation services don't need booting
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'logger',
			'cache',
			'options',
			'validator',
			'sanitizer',
			'http.client',
			'environment.checker',
			'compatibility.checker',
			LoggerInterface::class,
			CacheInterface::class,
			OptionsInterface::class,
			ValidatorInterface::class,
			SanitizerInterface::class,
			HttpClientInterface::class,
		);
	}

	/**
	 * Get default options (for fallback).
	 *
	 * @return array Default options.
	 */
	protected function getDefaultOptions(): array {
		// Use defaults from SettingsAdapter if available
		if ( class_exists( '\FP\Multilanguage\Foundation\Options\SettingsAdapter' ) ) {
			$adapter = \FP\Multilanguage\Foundation\Options\SettingsAdapter::instance();
			if ( method_exists( $adapter, 'get_defaults' ) ) {
				return $adapter->get_defaults();
			}
		}
		
		// Fallback to minimal defaults
		return array(
			'log_level' => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'debug' : 'info',
		);
	}
}

