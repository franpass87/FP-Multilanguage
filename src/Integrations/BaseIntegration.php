<?php
/**
 * Base Integration - Abstract base class for all integrations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Integrations;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base integration class.
 *
 * Provides common functionality for third-party integrations.
 *
 * @since 1.0.0
 */
abstract class BaseIntegration {
	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
	 */
	protected $logger;

	/**
	 * Whether the integration is active.
	 *
	 * @var bool
	 */
	protected $active = false;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger = null ) {
		$this->logger = $logger;
		$this->checkDependencies();
	}

	/**
	 * Check if required dependencies are available.
	 *
	 * @return bool True if dependencies are available.
	 */
	abstract protected function checkDependencies(): bool;

	/**
	 * Initialize the integration.
	 *
	 * @return void
	 */
	abstract public function init(): void;

	/**
	 * Check if integration is active.
	 *
	 * @return bool True if active.
	 */
	public function isActive(): bool {
		return $this->active;
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
	 * Get integration name.
	 *
	 * @return string Integration name.
	 */
	abstract public function getName(): string;
}









