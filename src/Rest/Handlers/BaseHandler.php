<?php
/**
 * Base REST Handler - Abstract base class for all REST handlers.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base handler for REST API endpoints.
 *
 * Provides common functionality for authentication, validation, and error handling.
 *
 * @since 1.0.0
 */
abstract class BaseHandler {
	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Check if user has permission to access this endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function checkPermission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'fp-multilanguage' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate request parameters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validateRequest( WP_REST_Request $request ) {
		// Override in child classes for specific validation
		return true;
	}

	/**
	 * Log an error.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function logError( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->error( $message, $context );
		}
	}

	/**
	 * Log debug information.
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function logDebug( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->debug( $message, $context );
		}
	}

	/**
	 * Create a success response.
	 *
	 * @param mixed  $data Response data.
	 * @param int    $status HTTP status code.
	 * @param array  $headers Additional headers.
	 * @return WP_REST_Response
	 */
	protected function success( $data = null, int $status = 200, array $headers = array() ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		
		foreach ( $headers as $key => $value ) {
			$response->header( $key, $value );
		}

		return $response;
	}

	/**
	 * Create an error response.
	 *
	 * @param string $message Error message.
	 * @param string $code Error code.
	 * @param int    $status HTTP status code.
	 * @param array  $data Additional error data.
	 * @return WP_Error
	 */
	protected function error( string $message, string $code = 'error', int $status = 400, array $data = array() ): WP_Error {
		$this->logError( $message, $data );
		
		return new WP_Error( $code, $message, array_merge( $data, array( 'status' => $status ) ) );
	}

	/**
	 * Sanitize request parameter.
	 *
	 * @param mixed  $value Value to sanitize.
	 * @param string $type Sanitization type (text, email, int, etc.).
	 * @return mixed Sanitized value.
	 */
	protected function sanitize( $value, string $type = 'text' ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );
			case 'int':
				return absint( $value );
			case 'url':
				return esc_url_raw( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}
}









