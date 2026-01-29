<?php
/**
 * Environment Checker.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Environment checker for PHP version, WordPress version, and multisite.
 *
 * @since 1.0.0
 */
class EnvironmentChecker {
	/**
	 * Minimum PHP version required.
	 *
	 * @var string
	 */
	protected $min_php_version = '8.0.0';

	/**
	 * Minimum WordPress version required.
	 *
	 * @var string
	 */
	protected $min_wp_version = '5.8.0';

	/**
	 * Check if PHP version meets requirements.
	 *
	 * @param string|null $min_version Minimum version.
	 * @return bool True if meets requirements.
	 */
	public function checkPhpVersion( ?string $min_version = null ): bool {
		$min_version = $min_version ?? $this->min_php_version;
		return version_compare( PHP_VERSION, $min_version, '>=' );
	}

	/**
	 * Check if WordPress version meets requirements.
	 *
	 * @param string|null $min_version Minimum version.
	 * @return bool True if meets requirements.
	 */
	public function checkWordPressVersion( ?string $min_version = null ): bool {
		global $wp_version;
		$min_version = $min_version ?? $this->min_wp_version;
		return version_compare( $wp_version, $min_version, '>=' );
	}

	/**
	 * Check if running in multisite.
	 *
	 * @return bool True if multisite.
	 */
	public function isMultisite(): bool {
		return is_multisite();
	}

	/**
	 * Check if running in admin.
	 *
	 * @return bool True if admin.
	 */
	public function isAdmin(): bool {
		return is_admin();
	}

	/**
	 * Check if running in CLI.
	 *
	 * @return bool True if CLI.
	 */
	public function isCli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Check if running in AJAX.
	 *
	 * @return bool True if AJAX.
	 */
	public function isAjax(): bool {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Check if running in REST API.
	 *
	 * @return bool True if REST API.
	 */
	public function isRestApi(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Get all environment checks.
	 *
	 * @return array Check results.
	 */
	public function getAllChecks(): array {
		return array(
			'php_version'        => $this->checkPhpVersion(),
			'php_version_string' => PHP_VERSION,
			'wp_version'         => $this->checkWordPressVersion(),
			'wp_version_string'  => get_bloginfo( 'version' ),
			'multisite'          => $this->isMultisite(),
			'admin'              => $this->isAdmin(),
			'cli'                => $this->isCli(),
			'ajax'               => $this->isAjax(),
			'rest_api'           => $this->isRestApi(),
		);
	}
}













