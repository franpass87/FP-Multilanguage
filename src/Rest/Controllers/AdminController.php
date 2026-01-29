<?php
/**
 * Internal REST API for admin actions.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.2.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Rest\Controllers;

use FP\Multilanguage\Rest\RouteRegistrar;
use FP\Multilanguage\Rest\Contracts\ControllerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register REST endpoints used by the admin UI.
 *
 * @since 0.2.0
 * @since 0.10.0 Refactored to use modular components.
 */
class AdminController implements ControllerInterface {
	/**
	 * Singleton instance.
	 *
	 * @var \FPML_REST_Admin|null
	 */
	protected static $instance = null;

	/**
	 * Route registrar instance.
	 *
	 * @since 0.10.0
	 *
	 * @var RouteRegistrar
	 */
	protected RouteRegistrar $route_registrar;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.2.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		$this->route_registrar = new RouteRegistrar();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @since 0.2.0
	 * @since 0.10.0 Delegates to RouteRegistrar.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$this->route_registrar->register_routes();
	}

	/**
	 * Get the REST API namespace.
	 *
	 * @return string Namespace.
	 */
	public function get_namespace(): string {
		return 'fpml/v1';
	}
}
