<?php
/**
 * Integration Service Provider.
 *
 * Registers third-party integration services (WooCommerce, ACF, etc.).
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
 * Integration service provider.
 *
 * @since 1.0.0
 */
class IntegrationServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// ACF Support
		$container->bind( 'integration.acf', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\ACFSupport' ) ) {
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				$queue = $c->has( 'queue' ) ? $c->get( 'queue' ) : null;
				return new \FP\Multilanguage\ACFSupport( $logger, $queue );
			}
			return null;
		}, true );

		// WooCommerce Support
		$container->bind( 'integration.woocommerce', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\WooCommerceSupport' ) && class_exists( 'WooCommerce' ) ) {
				return \FP\Multilanguage\Integrations\WooCommerceSupport::instance();
			}
			return null;
		}, true );

		// FP SEO Support
		$container->bind( 'integration.fp_seo', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\FpSeoSupport' ) ) {
				return \FP\Multilanguage\Integrations\FpSeoSupport::instance();
			}
			return null;
		}, true );

		// FP Experiences Support
		$container->bind( 'integration.fp_experiences', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\FpExperiencesSupport' ) ) {
				return \FP\Multilanguage\Integrations\FpExperiencesSupport::instance();
			}
			return null;
		}, true );

		// FP Reservations Support
		$container->bind( 'integration.fp_reservations', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\FpReservationsSupport' ) ) {
				return \FP\Multilanguage\Integrations\FpReservationsSupport::instance();
			}
			return null;
		}, true );

		// FP Forms Support
		$container->bind( 'integration.fp_forms', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\FpFormsSupport' ) ) {
				return \FP\Multilanguage\Integrations\FpFormsSupport::instance();
			}
			return null;
		}, true );

		// WPBakery Support
		$container->bind( 'integration.wpbakery', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\WPBakerySupport' ) ) {
				return \FP\Multilanguage\Integrations\WPBakerySupport::instance();
			}
			return null;
		}, true );

		// Elementor Support
		$container->bind( 'integration.elementor', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\ElementorSupport' ) ) {
				return \FP\Multilanguage\Integrations\ElementorSupport::instance();
			}
			return null;
		}, true );

		// Salient Theme Support
		$container->bind( 'integration.salient', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\SalientThemeSupport' ) ) {
				return \FP\Multilanguage\Integrations\SalientThemeSupport::instance();
			}
			return null;
		}, true );

		// FP Plugins Support (Auto-detect FP-* plugins)
		$container->bind( 'integration.fp_plugins', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\FpPluginsSupport' ) ) {
				return \FP\Multilanguage\Integrations\FpPluginsSupport::instance();
			}
			return null;
		}, true );

		// Popular Plugins Support (Auto-detect popular WordPress plugins)
		$container->bind( 'integration.popular_plugins', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Integrations\PopularPluginsSupport' ) ) {
				return \FP\Multilanguage\Integrations\PopularPluginsSupport::instance();
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
		// Initialize integrations that need booting
		$this->initializeIntegrations( $container );
	}

	/**
	 * Initialize integrations.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	protected function initializeIntegrations( Container $container ): void {
		// ACF Support
		if ( $container->has( 'integration.acf' ) && function_exists( 'acf_get_field_groups' ) ) {
			$acf = $container->get( 'integration.acf' );
			if ( $acf && method_exists( $acf, 'init' ) ) {
				$acf->init();
			}
		}

		// WooCommerce Support
		if ( $container->has( 'integration.woocommerce' ) && class_exists( 'WooCommerce' ) ) {
			$woocommerce = $container->get( 'integration.woocommerce' );
			if ( $woocommerce && method_exists( $woocommerce, 'init' ) ) {
				$woocommerce->init();
			}
		}

		// FP SEO Support
		if ( $container->has( 'integration.fp_seo' ) ) {
			$fp_seo = $container->get( 'integration.fp_seo' );
			if ( $fp_seo && method_exists( $fp_seo, 'init' ) ) {
				$fp_seo->init();
			}
		}

		// FP Experiences Support
		if ( $container->has( 'integration.fp_experiences' ) ) {
			$fp_experiences = $container->get( 'integration.fp_experiences' );
			if ( $fp_experiences && method_exists( $fp_experiences, 'register' ) ) {
				$fp_experiences->register();
			} elseif ( $fp_experiences && method_exists( $fp_experiences, 'init' ) ) {
				$fp_experiences->init();
			}
		}

		// FP Reservations Support
		if ( $container->has( 'integration.fp_reservations' ) ) {
			$fp_reservations = $container->get( 'integration.fp_reservations' );
			if ( $fp_reservations && method_exists( $fp_reservations, 'register' ) ) {
				$fp_reservations->register();
			} elseif ( $fp_reservations && method_exists( $fp_reservations, 'init' ) ) {
				$fp_reservations->init();
			}
		}

		// FP Forms Support
		if ( $container->has( 'integration.fp_forms' ) ) {
			$fp_forms = $container->get( 'integration.fp_forms' );
			if ( $fp_forms && method_exists( $fp_forms, 'register' ) ) {
				$fp_forms->register();
			} elseif ( $fp_forms && method_exists( $fp_forms, 'init' ) ) {
				$fp_forms->init();
			}
		}

		// WPBakery Support
		if ( $container->has( 'integration.wpbakery' ) ) {
			$wpbakery = $container->get( 'integration.wpbakery' );
			// WPBakerySupport uses singleton, already initialized
		}

		// Elementor Support
		if ( $container->has( 'integration.elementor' ) ) {
			$elementor = $container->get( 'integration.elementor' );
			// ElementorSupport uses singleton, already initialized
		}

		// Salient Theme Support
		if ( $container->has( 'integration.salient' ) ) {
			$salient = $container->get( 'integration.salient' );
			// SalientThemeSupport uses singleton, already initialized
		}

		// FP Plugins Support
		if ( $container->has( 'integration.fp_plugins' ) ) {
			$fp_plugins = $container->get( 'integration.fp_plugins' );
			// FpPluginsSupport uses singleton, already initialized
		}

		// Popular Plugins Support
		if ( $container->has( 'integration.popular_plugins' ) ) {
			$popular_plugins = $container->get( 'integration.popular_plugins' );
			// PopularPluginsSupport uses singleton, already initialized
		}
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'integration.acf',
			'integration.woocommerce',
			'integration.fp_seo',
			'integration.fp_experiences',
			'integration.fp_reservations',
			'integration.fp_forms',
			'integration.wpbakery',
			'integration.elementor',
			'integration.salient',
			'integration.fp_plugins',
			'integration.popular_plugins',
		);
	}
}


