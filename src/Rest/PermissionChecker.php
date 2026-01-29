<?php
/**
 * REST Permission Checker - Handles permission checks for REST endpoints.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Rest;

use FP\Multilanguage\Security\ApiRateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles permission checks for REST endpoints.
 *
 * @since 0.10.0
 */
class PermissionChecker {
	/**
	 * Ensure the current request is authorized.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permissions( \WP_REST_Request $request ): bool|\WP_Error {
		// Rate limiting check
		$rate_check = ApiRateLimiter::check();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'\FPML_rest_forbidden',
				__( 'Permessi insufficienti.', 'fp-multilanguage' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'\FPML_rest_nonce_invalid',
				__( 'Nonce non valido.', 'fp-multilanguage' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Check admin permissions without nonce verification.
	 * Used only for nonce refresh endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_admin_permissions( \WP_REST_Request $request ): bool|\WP_Error { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'\FPML_rest_forbidden',
				__( 'Permessi insufficienti.', 'fp-multilanguage' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Check permissions for refresh nonce endpoint.
	 * This endpoint should be accessible without nonce validation to allow nonce refresh.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_refresh_nonce_permissions( \WP_REST_Request $request ): bool|\WP_Error { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Only require user to be logged in and have manage_options capability
		// Do NOT require nonce validation since this endpoint is used to refresh nonces
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'\FPML_rest_forbidden',
				__( 'Permessi insufficienti.', 'fp-multilanguage' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}
}
















