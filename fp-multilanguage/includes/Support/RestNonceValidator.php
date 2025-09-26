<?php
namespace FPMultilanguage\Support;

use WP_REST_Request;

class RestNonceValidator {
	/**
	 * Validate a REST nonce extracted from the given request against one or more actions.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @param array<int, string>                    $actions
	 */
	public function validate( WP_REST_Request $request, array $actions ): bool {
		$nonce = $this->extract_nonce( $request );

		if ( '' === $nonce ) {
			return ! $this->requires_nonce( $request );
		}

		if ( function_exists( 'wp_unslash' ) ) {
			$nonce = wp_unslash( $nonce );
		}

		if ( function_exists( 'sanitize_text_field' ) ) {
			$nonce = sanitize_text_field( $nonce );
		}

		if ( '' === $nonce ) {
			return false;
		}

		foreach ( $actions as $action ) {
			if ( '' === $action ) {
				continue;
			}

			if ( function_exists( 'wp_verify_nonce' ) && false !== wp_verify_nonce( $nonce, $action ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 */
	private function extract_nonce( WP_REST_Request $request ): string {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( is_string( $nonce ) && '' !== $nonce ) {
			return $nonce;
		}

		$paramNonce = $request->get_param( '_wpnonce' );
		if ( is_string( $paramNonce ) ) {
			return $paramNonce;
		}

		return '';
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 */
	private function requires_nonce( WP_REST_Request $request ): bool {
		if ( $this->has_authorization_header( $request ) ) {
			return false;
		}

		if ( function_exists( 'is_user_logged_in' ) ) {
			return is_user_logged_in();
		}

		return true;
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 */
	private function has_authorization_header( WP_REST_Request $request ): bool {
		$header = $request->get_header( 'Authorization' );
		if ( is_string( $header ) && '' !== trim( $header ) ) {
			return true;
		}

		$serverHeader = $this->get_server_value( 'HTTP_AUTHORIZATION' );
		if ( '' !== $serverHeader ) {
			return true;
		}

		$phpAuthUser = $this->get_server_value( 'PHP_AUTH_USER' );
		if ( '' !== $phpAuthUser ) {
			return true;
		}

		return false;
	}

	private function get_server_value( string $key ): string {
		$value = '';

		if ( function_exists( 'filter_input' ) ) {
			$filtered = filter_input( INPUT_SERVER, $key, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );
			if ( is_string( $filtered ) ) {
				$value = $filtered;
			}
		}

		if ( '' === $value && isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$value = $_SERVER[ $key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return trim( (string) $value );
	}
}
