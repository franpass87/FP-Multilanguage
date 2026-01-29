<?php
/**
 * Base CLI Command - Abstract base class for all WP-CLI commands.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\CLI;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Base command for WP-CLI commands.
 *
 * Provides common functionality for logging and error handling.
 *
 * @since 1.0.0
 */
abstract class BaseCommand extends \WP_CLI_Command {
	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
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
	 * Log an error message.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function logError( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->error( $message, $context );
		}
		\WP_CLI::error( $message );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Warning message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function logWarning( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->warning( $message, $context );
		}
		\WP_CLI::warning( $message );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Info message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function logInfo( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->info( $message, $context );
		}
		\WP_CLI::log( $message );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function logDebug( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->debug( $message, $context );
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\WP_CLI::debug( $message );
		}
	}

	/**
	 * Check if plugin is in assisted mode.
	 *
	 * @return bool True if in assisted mode.
	 */
	protected function isAssistedMode(): bool {
		if ( class_exists( '\FPML_Plugin' ) ) {
			$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
			return method_exists( $plugin, 'is_assisted_mode' ) ? $plugin->is_assisted_mode() : false;
		}
		return false;
	}

	/**
	 * Ensure queue is available (not in assisted mode).
	 *
	 * @return void
	 * @throws \WP_CLI\ExitException If in assisted mode.
	 */
	protected function ensureQueueAvailable(): void {
		if ( $this->isAssistedMode() ) {
			\WP_CLI::error( __( 'Modalità assistita attiva (WPML/Polylang): la coda interna di FP Multilanguage è disabilitata.', 'fp-multilanguage' ) );
		}
	}
}









