<?php
/**
 * Frontend Service Provider.
 *
 * Registers frontend services (language switcher, URL filters, redirects).
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
 * Frontend service provider.
 *
 * @since 1.0.0
 */
class FrontendServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Only register if not in admin
		if ( is_admin() ) {
			return;
		}

		// Rewrites (URL routing)
		$container->bind( 'frontend.rewrites', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Frontend\Routing\Rewrites' ) ) {
				return \FP\Multilanguage\Frontend\Routing\Rewrites::instance();
			}
			// Fallback to old location for backward compatibility
			if ( class_exists( '\FP\Multilanguage\Rewrites' ) ) {
				return \FP\Multilanguage\Rewrites::instance();
			}
			return null;
		}, true );

		// Language Manager
		$container->bind( 'frontend.language', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Language' ) ) {
				return \FP\Multilanguage\Language::instance();
			}
			return null;
		}, true );

		// Site Translations
		$container->bind( 'frontend.site_translations', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Frontend\Content\SiteTranslations' ) ) {
				return \FP\Multilanguage\Frontend\Content\SiteTranslations::instance();
			}
			// Fallback to old location for backward compatibility
			if ( class_exists( '\FP\Multilanguage\SiteTranslations' ) ) {
				return \FP\Multilanguage\SiteTranslations::instance();
			}
			return null;
		}, true );

		// Language Switcher Widget (using Frontend\Widgets location)
		$container->bind( 'frontend.language_switcher_widget', function( Container $c ) {
			// Use Frontend\Widgets\LanguageSwitcherWidget (preferred location)
			if ( class_exists( '\FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget' ) ) {
				return new \FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget();
			}
			// Should not happen, but return null if class doesn't exist
			return null;
		}, true );

		// Language Resolver
		$container->bind( 'frontend.language_resolver', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Language\LanguageResolver' ) ) {
				$rewrites = $c->has( 'frontend.rewrites' ) ? $c->get( 'frontend.rewrites' ) : null;
				return new \FP\Multilanguage\Language\LanguageResolver( $rewrites );
			}
			return null;
		}, true );

		// URL Filter
		$container->bind( 'frontend.url_filter', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Language\UrlFilter' ) ) {
				$language = $c->has( 'frontend.language' ) ? $c->get( 'frontend.language' ) : null;
				return new \FP\Multilanguage\Language\UrlFilter( $language );
			}
			return null;
		}, true );

		// Aliases for backward compatibility with helper functions
		$container->alias( 'rewrites', 'frontend.rewrites' );
		$container->alias( 'language', 'frontend.language' );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Initialize frontend services on frontend requests
		// These services register WordPress hooks in their constructors
		if ( ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			// Initialize Rewrites (registers rewrite rules and routing hooks)
			if ( $container->has( 'frontend.rewrites' ) ) {
				$container->get( 'frontend.rewrites' );
			}
			
			// Initialize Language (registers language_attributes, locale, and permalink filters)
			if ( $container->has( 'frontend.language' ) ) {
				$container->get( 'frontend.language' );
			}
		}
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'frontend.rewrites',
			'frontend.language',
			'frontend.language_resolver',
			'frontend.url_filter',
			'frontend.site_translations',
			'frontend.language_switcher_widget',
		);
	}
}









