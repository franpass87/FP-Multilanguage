<?php
/**
 * HTTP Client Implementation using wp_remote_*.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP client implementation using WordPress HTTP API.
 *
 * @since 1.0.0
 */
class HttpClient implements HttpClientInterface {
	/**
	 * Default timeout in seconds.
	 *
	 * @var int
	 */
	protected $timeout = 30;

	/**
	 * Constructor.
	 *
	 * @param int $timeout Request timeout.
	 */
	public function __construct( int $timeout = 30 ) {
		$this->timeout = $timeout;
	}

	/**
	 * Make a GET request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function get( string $url, array $headers = array() ): array {
		return $this->request( 'GET', $url, array(), $headers );
	}

	/**
	 * Make a POST request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $data    Request body data.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function post( string $url, array $data = array(), array $headers = array() ): array {
		return $this->request( 'POST', $url, $data, $headers );
	}

	/**
	 * Make a PUT request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $data    Request body data.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function put( string $url, array $data = array(), array $headers = array() ): array {
		return $this->request( 'PUT', $url, $data, $headers );
	}

	/**
	 * Make a DELETE request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function delete( string $url, array $headers = array() ): array {
		return $this->request( 'DELETE', $url, array(), $headers );
	}

	/**
	 * Make an HTTP request.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     URL to request.
	 * @param array  $data    Request body data.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	protected function request( string $method, string $url, array $data = array(), array $headers = array() ): array {
		$args = array(
			'method'  => $method,
			'timeout' => $this->timeout,
			'headers' => $headers,
		);

		if ( ! empty( $data ) ) {
			if ( in_array( $method, array( 'POST', 'PUT' ), true ) ) {
				$args['body'] = wp_json_encode( $data );
				$args['headers']['Content-Type'] = 'application/json';
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'body'     => '',
				'headers'  => array(),
				'response' => array(
					'code'    => 0,
					'message' => $response->get_error_message(),
				),
				'error'    => true,
			);
		}

		return array(
			'body'     => wp_remote_retrieve_body( $response ),
			'headers'  => wp_remote_retrieve_headers( $response ),
			'response' => array(
				'code'    => wp_remote_retrieve_response_code( $response ),
				'message' => wp_remote_retrieve_response_message( $response ),
			),
			'error'    => false,
		);
	}
}













