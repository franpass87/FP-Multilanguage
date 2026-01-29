<?php
/**
 * Language Service Provider.
 *
 * Registers language-related services (MemoryStore, LanguageManager, MenuSync, etc.).
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
 * Language service provider.
 *
 * @since 1.0.0
 */
class LanguageServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Memory Store (Translation Memory)
		$container->bind( 'language.memory_store', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\MemoryStore' ) ) {
				return \FP\Multilanguage\MemoryStore::instance();
			}
			return null;
		}, true );

		// Language Manager
		$container->bind( 'language.manager', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\MultiLanguage\LanguageManager' ) ) {
				return fpml_get_language_manager();
			}
			// Fallback to old namespace
			if ( class_exists( '\FP\Multilanguage\LanguageManager' ) ) {
				return \FP\Multilanguage\fpml_get_language_manager();
			}
			return null;
		}, true );

		// Menu Sync
		$container->bind( 'language.menu_sync', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\MenuSync' ) ) {
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				$settings = $c->has( 'options' ) ? $c->get( 'options' ) : null;
				return new \FP\Multilanguage\MenuSync( $logger, $settings );
			}
			return null;
		}, true );

		// Auto String Translator
		$container->bind( 'language.auto_string_translator', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\AutoStringTranslator' ) ) {
				return \FP\Multilanguage\AutoStringTranslator::instance();
			}
			return null;
		}, true );

		// Site Translations
		$container->bind( 'language.site_translations', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\SiteTranslations' ) ) {
				return \FP\Multilanguage\SiteTranslations::instance();
			}
			return null;
		}, true );

		// Aliases for backward compatibility with helper functions
		$container->alias( 'menu_sync', 'language.menu_sync' );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Language services use singleton pattern
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
			'language.memory_store',
			'language.manager',
			'language.menu_sync',
			'language.auto_string_translator',
			'language.site_translations',
		);
	}
}
