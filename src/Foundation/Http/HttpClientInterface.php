<?php
/**
 * HTTP Client Interface.
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
 * HTTP client interface for making HTTP requests.
 *
 * @since 1.0.0
 */
interface HttpClientInterface {
	/**
	 * Make a GET request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function get( string $url, array $headers = array() ): array;

	/**
	 * Make a POST request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $data    Request body data.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function post( string $url, array $data = array(), array $headers = array() ): array;

	/**
	 * Make a PUT request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $data    Request body data.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function put( string $url, array $data = array(), array $headers = array() ): array;

	/**
	 * Make a DELETE request.
	 *
	 * @param string $url     URL to request.
	 * @param array  $headers Request headers.
	 * @return array Response with 'body', 'headers', 'response' keys.
	 */
	public function delete( string $url, array $headers = array() ): array;
}













