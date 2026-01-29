<?php
/**
 * PSR-3 Compatible Logger Implementation.
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
 * PSR-3 compatible logger implementation.
 *
 * @since 1.0.0
 */
class Logger implements LoggerInterface {
	/**
	 * Current log level (only log this level and above).
	 *
	 * @var string
	 */
	protected $min_level = LogLevel::INFO;

	/**
	 * Maximum log entries to keep.
	 *
	 * @var int
	 */
	protected $max_entries = 1000;

	/**
	 * Option name for storing logs.
	 *
	 * @var string
	 */
	protected $option_name = 'fpml_logs';

	/**
	 * Constructor.
	 *
	 * @param string|null $min_level Minimum log level.
	 * @param int    $max_entries Maximum entries to keep.
	 */
	public function __construct( ?string $min_level = null, int $max_entries = 1000 ) {
		$this->min_level = $min_level ?? ( defined( 'WP_DEBUG' ) && WP_DEBUG ? LogLevel::DEBUG : LogLevel::INFO );
		$this->max_entries = apply_filters( 'fpml_log_max_entries', $max_entries );
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function emergency( string $message, array $context = array() ): void {
		$this->log( LogLevel::EMERGENCY, $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function alert( string $message, array $context = array() ): void {
		$this->log( LogLevel::ALERT, $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->log( LogLevel::CRITICAL, $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( LogLevel::ERROR, $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( LogLevel::WARNING, $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void {
		$this->log( LogLevel::NOTICE, $message, $context );
	}

	/**
	 * Informational messages.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( LogLevel::INFO, $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( LogLevel::DEBUG, $message, $context );
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
		$level = (string) $level;

		// Check if we should log this level
		if ( ! LogLevel::shouldLog( $level, $this->min_level ) ) {
			return;
		}

		$entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $this->interpolate( $message, $context ),
			'context'   => $context,
			'user_id'   => get_current_user_id(),
			'ip'        => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		);

		$this->addEntry( $entry );

		// Also log to error_log if error level or above
		if ( LogLevel::getPriority( $level ) >= LogLevel::getPriority( LogLevel::ERROR ) ) {
			error_log( sprintf( '[FPML %s] %s', strtoupper( $level ), $message ) );
		}
	}

	/**
	 * Interpolate context values into message.
	 *
	 * @param string $message Message template.
	 * @param array  $context Context data.
	 * @return string Interpolated message.
	 */
	protected function interpolate( string $message, array $context = array() ): string {
		if ( empty( $context ) ) {
			return $message;
		}

		$replace = array();
		foreach ( $context as $key => $val ) {
			if ( ! is_array( $val ) && ( ! is_object( $val ) || method_exists( $val, '__toString' ) ) ) {
				$replace[ '{' . $key . '}' ] = $val;
			}
		}

		return strtr( $message, $replace );
	}

	/**
	 * Add log entry.
	 *
	 * @param array $entry Log entry.
	 * @return void
	 */
	protected function addEntry( array $entry ): void {
		$logs = get_option( $this->option_name, array() );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Add new entry
		$logs[] = $entry;

		// Rotate if needed
		if ( count( $logs ) > $this->max_entries ) {
			$logs = array_slice( $logs, -$this->max_entries );
		}

		update_option( $this->option_name, $logs, false );
	}

	/**
	 * Get logs.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function getLogs( array $args = array() ): array {
		$defaults = array(
			'level'   => null,
			'limit'   => 100,
			'offset'  => 0,
			'order'   => 'DESC',
			'search'  => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$logs = get_option( $this->option_name, array() );

		if ( ! is_array( $logs ) ) {
			return array();
		}

		// Filter by level
		if ( $args['level'] ) {
			$logs = array_filter(
				$logs,
				function( $log ) use ( $args ) {
					return isset( $log['level'] ) && $log['level'] === $args['level'];
				}
			);
		}

		// Search
		if ( $args['search'] ) {
			$search = strtolower( $args['search'] );
			$logs   = array_filter(
				$logs,
				function( $log ) use ( $search ) {
					$message = isset( $log['message'] ) ? strtolower( $log['message'] ) : '';
					return false !== strpos( $message, $search );
				}
			);
		}

		// Sort
		if ( 'ASC' === $args['order'] ) {
			usort(
				$logs,
				function( $a, $b ) {
					$time_a = isset( $a['timestamp'] ) ? strtotime( $a['timestamp'] ) : 0;
					$time_b = isset( $b['timestamp'] ) ? strtotime( $b['timestamp'] ) : 0;
					return $time_a - $time_b;
				}
			);
		} else {
			usort(
				$logs,
				function( $a, $b ) {
					$time_a = isset( $a['timestamp'] ) ? strtotime( $a['timestamp'] ) : 0;
					$time_b = isset( $b['timestamp'] ) ? strtotime( $b['timestamp'] ) : 0;
					return $time_b - $time_a;
				}
			);
		}

		// Paginate
		$logs = array_slice( $logs, $args['offset'], $args['limit'] );

		return array_values( $logs );
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public function clearLogs(): void {
		delete_option( $this->option_name );
	}

	/**
	 * Get log statistics.
	 *
	 * @return array
	 */
	public function getStats(): array {
		$logs = get_option( $this->option_name, array() );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$stats = array(
			'total'    => count( $logs ),
			'debug'    => 0,
			'info'     => 0,
			'warning'  => 0,
			'error'    => 0,
			'last_24h' => 0,
		);

		$last_24h = time() - DAY_IN_SECONDS;

		foreach ( $logs as $log ) {
			$level = isset( $log['level'] ) ? $log['level'] : 'info';
			if ( isset( $stats[ $level ] ) ) {
				$stats[ $level ]++;
			}

			$timestamp = isset( $log['timestamp'] ) ? strtotime( $log['timestamp'] ) : 0;
			if ( $timestamp > $last_24h ) {
				$stats['last_24h']++;
			}
		}

		return $stats;
	}
}













