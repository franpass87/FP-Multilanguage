<?php
/**
 * Structured logging system for FP Multilanguage.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized logging system with levels and rotation.
 *
 * Uses an in-memory buffer that is flushed to the DB once on shutdown,
 * avoiding a get_option + update_option pair for every single log entry.
 *
 * @since 0.5.0
 */
class Logger {
	/**
	 * Log levels.
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
	 * Current log level (only log this level and above).
	 *
	 * @var string
	 */
	protected $min_level = self::LEVEL_INFO;

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
	 * In-memory buffer of pending log entries to be flushed on shutdown.
	 *
	 * @var array
	 */
	protected $buffer = array();

	/**
	 * Whether the shutdown flush hook has been registered.
	 *
	 * @var bool
	 */
	protected $flush_registered = false;

	/**
	 * Get singleton instance.
	 *
	 * Static methods delegate to this instance without emitting deprecation
	 * notices. External callers should use DI when possible.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 * @since 1.0.0 Now accepts optional Settings dependency for DI
	 *
	 * @param Settings|null $settings Optional settings instance for DI.
	 */
	public function __construct( $settings = null ) {
		if ( null === $settings ) {
			$settings = function_exists( 'fpml_get_settings' ) ? fpml_get_settings() : null;
		}

		if ( $settings ) {
			$this->min_level = $settings->get( 'log_level', defined( 'WP_DEBUG' ) && WP_DEBUG ? self::LEVEL_DEBUG : self::LEVEL_INFO );
		} else {
			$this->min_level = defined( 'WP_DEBUG' ) && WP_DEBUG ? self::LEVEL_DEBUG : self::LEVEL_INFO;
		}

		$this->max_entries = apply_filters( 'fpml_log_max_entries', 1000 );
	}

	/**
	 * Log a message.
	 *
	 * @param string $level   Log level.
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return void
	 */
	public static function log( $level, $message, $context = array() ) {
		$logger = self::instance();

		if ( ! $logger->should_log( $level ) ) {
			return;
		}

		$entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
			'user_id'   => get_current_user_id(),
			'ip'        => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		);

		$logger->buffer_entry( $entry );

		if ( self::LEVEL_ERROR === $level && defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			error_log( sprintf( '[FPML %s] %s', strtoupper( $level ), $message ) );
		}
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 *
	 * @return void
	 */
	public static function debug( $message, $context = array() ) {
		self::log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 *
	 * @return void
	 */
	public static function info( $message, $context = array() ) {
		self::log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 *
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 *
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Check if we should log this level.
	 *
	 * @param string $level Level to check.
	 *
	 * @return bool
	 */
	protected function should_log( $level ) {
		$levels = array(
			self::LEVEL_DEBUG   => 0,
			self::LEVEL_INFO    => 1,
			self::LEVEL_WARNING => 2,
			self::LEVEL_ERROR   => 3,
		);

		$level_priority = isset( $levels[ $level ] ) ? $levels[ $level ] : 1;
		$min_priority   = isset( $levels[ $this->min_level ] ) ? $levels[ $this->min_level ] : 1;

		return $level_priority >= $min_priority;
	}

	/**
	 * Add a log entry to the in-memory buffer and register the shutdown flush.
	 *
	 * @param array $entry Log entry.
	 *
	 * @return void
	 */
	protected function buffer_entry( array $entry ): void {
		$this->buffer[] = $entry;

		if ( ! $this->flush_registered ) {
			$this->flush_registered = true;
			add_action( 'shutdown', array( $this, 'flush_buffer' ), 999 );
		}
	}

	/**
	 * Flush buffered log entries to the database (called once on shutdown).
	 *
	 * @return void
	 */
	public function flush_buffer(): void {
		if ( empty( $this->buffer ) ) {
			return;
		}

		$logs = get_option( $this->option_name, array() );

		foreach ( $this->buffer as $entry ) {
			$logs[] = $entry;
		}

		$this->buffer = array();

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
	public static function get_logs( $args = array() ) {
		$defaults = array(
			'level'   => null,
			'limit'   => 100,
			'offset'  => 0,
			'order'   => 'DESC',
			'search'  => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$logger = self::instance();
		$logs    = get_option( $logger->option_name, array() );

		// Filter by level
		if ( $args['level'] ) {
			$logs = array_filter(
				$logs,
				function( $log ) use ( $args ) {
					return $log['level'] === $args['level'];
				}
			);
		}

		// Search
		if ( $args['search'] ) {
			$search = strtolower( $args['search'] );
			$logs   = array_filter(
				$logs,
				function( $log ) use ( $search ) {
					return false !== strpos( strtolower( $log['message'] ), $search );
				}
			);
		}

		// Sort
		if ( 'ASC' === $args['order'] ) {
			usort(
				$logs,
				function( $a, $b ) {
					return strtotime( $a['timestamp'] ) - strtotime( $b['timestamp'] );
				}
			);
		} else {
			usort(
				$logs,
				function( $a, $b ) {
					return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
				}
			);
		}

		// Paginate
		$logs = array_slice( $logs, $args['offset'], $args['limit'] );

		return $logs;
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public static function clear_logs() {
		$logger = self::instance();
		delete_option( $logger->option_name );
	}

	/**
	 * Get log statistics.
	 *
	 * @return array
	 */
	public static function get_stats() {
		$logger = self::instance();
		$logs   = get_option( $logger->option_name, array() );

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
