<?php
/**
 * REST Controller Interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\REST\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for REST API controllers.
 *
 * @since 1.0.0
 */
interface ControllerInterface {
	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void;

	/**
	 * Get the REST API namespace.
	 *
	 * @return string Namespace.
	 */
	public function get_namespace(): string;
}














