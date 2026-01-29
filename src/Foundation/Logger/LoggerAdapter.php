<?php
/**
 * Logger Adapter - Wraps Foundation\Logger\Logger to maintain backward compatibility.
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
 * Adapter that wraps Foundation\Logger\Logger to provide static method compatibility.
 *
 * This class maintains backward compatibility with the old Logger class
 * while using the new Foundation\Logger\Logger service internally.
 *
 * @since 1.0.0
 */
class LoggerAdapter implements LoggerInterface {
	/**
	 * Log levels (for backward compatibility).
	 */
	const LEVEL_DEBUG   = 'debug';
	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Wrapped Logger service.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Get singleton instance.
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
	 */
	protected function __construct() {
		// Try to get Logger from container, fallback to direct instantiation
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			try {
				$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
				if ( $kernel ) {
					$container = $kernel->getContainer();
					if ( $container && $container->has( 'logger' ) ) {
						$this->logger = $container->get( 'logger' );
						// If we got LoggerAdapter back, get the wrapped logger
						if ( $this->logger instanceof self ) {
							$this->logger = $this->logger->getWrapped();
						}
					}
				}
			} catch ( \Throwable $e ) {
				// Fallback to direct instantiation
			}
		}

		// If no logger service, create one
		if ( ! $this->logger ) {
			$min_level = defined( 'WP_DEBUG' ) && WP_DEBUG ? LogLevel::DEBUG : LogLevel::INFO;
			$this->logger = new Logger( $min_level );
		}
	}

	/**
	 * Handle static method calls for backward compatibility.
	 *
	 * @param string $name Method name.
	 * @param array  $args Arguments.
	 * @return mixed
	 */
	public static function __callStatic( string $name, array $args ) {
		$adapter = self::instance();
		if ( method_exists( $adapter, $name ) ) {
			return call_user_func_array( array( $adapter, $name ), $args );
		}
		return null;
	}

	/**
	 * Get logs (static method, can be called on instance or statically).
	 *
	 * @param array|int $args Query arguments or limit (for backward compatibility).
	 * @return array
	 */
	public static function get_logs( $args = array() ): array {
		$adapter = self::instance();
		if ( method_exists( $adapter->logger, 'getLogs' ) ) {
			// Handle backward compatibility: if $args is an integer, treat it as limit
			if ( is_int( $args ) ) {
				$args = array( 'limit' => $args );
			}
			// Ensure $args is an array
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			return $adapter->logger->getLogs( $args );
		}
		return array();
	}

	/**
	 * Get log statistics (static method, can be called on instance or statically).
	 *
	 * @return array
	 */
	public static function get_stats(): array {
		$adapter = self::instance();
		if ( method_exists( $adapter->logger, 'getStats' ) ) {
			return $adapter->logger->getStats();
		}
		return array();
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public static function clear_logs(): void {
		$adapter = self::instance();
		if ( method_exists( $adapter->logger, 'clearLogs' ) ) {
			$adapter->logger->clearLogs();
		}
	}

	/**
	 * Get wrapped logger instance.
	 *
	 * @return LoggerInterface
	 */
	public function getWrapped(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function emergency( string $message, array $context = array() ): void {
		$this->logger->emergency( $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function alert( string $message, array $context = array() ): void {
		$this->logger->alert( $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->logger->critical( $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->logger->error( $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->logger->warning( $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void {
		$this->logger->notice( $message, $context );
	}

	/**
	 * Informational messages.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->logger->info( $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->logger->debug( $message, $context );
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function log( $level, string $message, array $context = array() ): void {
		$this->logger->log( $level, $message, $context );
	}
}









