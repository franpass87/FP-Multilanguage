<?php
/**
 * Integration Interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Integrations\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for third-party integrations.
 *
 * @since 1.0.0
 */
interface IntegrationInterface {
	/**
	 * Get the integration name.
	 *
	 * @return string Integration name.
	 */
	public function getName(): string;

	/**
	 * Check if the integration is available.
	 *
	 * @return bool True if available.
	 */
	public function isAvailable(): bool;

	/**
	 * Register the integration.
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Boot the integration.
	 *
	 * @return void
	 */
	public function boot(): void;
}














