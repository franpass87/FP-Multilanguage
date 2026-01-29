<?php
/**
 * Logger Interface (PSR-3 compatible).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSR-3 compatible logger interface.
 *
 * @since 1.0.0
 */
interface LoggerInterface {
	/**
	 * System is unusable.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function emergency( string $message, array $context = array() ): void;

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function alert( string $message, array $context = array() ): void;

	/**
	 * Critical conditions.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void;

	/**
	 * Runtime errors that do not require immediate action.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void;

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void;

	/**
	 * Normal but significant events.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void;

	/**
	 * Informational messages.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void;

	/**
	 * Detailed debug information.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void;

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function log( $level, string $message, array $context = array() ): void;
}













