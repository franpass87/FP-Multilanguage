<?php
/**
 * Compatibility Checker.
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
 * Compatibility checker for required extensions and functions.
 *
 * @since 1.0.0
 */
class CompatibilityChecker {
	/**
	 * Required PHP extensions.
	 *
	 * @var array
	 */
	protected $required_extensions = array(
		'mbstring',
		'json',
	);

	/**
	 * Required WordPress functions.
	 *
	 * @var array
	 */
	protected $required_functions = array(
		'wp_remote_request',
		'wp_json_encode',
	);

	/**
	 * Check if all required PHP extensions are loaded.
	 *
	 * @return array Missing extensions.
	 */
	public function checkPhpExtensions(): array {
		$missing = array();

		foreach ( $this->required_extensions as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[] = $ext;
			}
		}

		return $missing;
	}

	/**
	 * Check if all required WordPress functions exist.
	 *
	 * @return array Missing functions.
	 */
	public function checkWordPressFunctions(): array {
		$missing = array();

		foreach ( $this->required_functions as $func ) {
			if ( ! function_exists( $func ) ) {
				$missing[] = $func;
			}
		}

		return $missing;
	}

	/**
	 * Check if plugin is compatible with current environment.
	 *
	 * @return bool True if compatible.
	 */
	public function isCompatible(): bool {
		$env_checker = new EnvironmentChecker();

		if ( ! $env_checker->checkPhpVersion() ) {
			return false;
		}

		if ( ! $env_checker->checkWordPressVersion() ) {
			return false;
		}

		$missing_ext = $this->checkPhpExtensions();
		if ( ! empty( $missing_ext ) ) {
			return false;
		}

		$missing_func = $this->checkWordPressFunctions();
		if ( ! empty( $missing_func ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get all compatibility issues.
	 *
	 * @return array Issues found.
	 */
	public function getIssues(): array {
		$env_checker = new EnvironmentChecker();
		$issues = array();

		if ( ! $env_checker->checkPhpVersion() ) {
			$issues[] = sprintf(
				'PHP version %s required, found %s',
				'8.0.0',
				PHP_VERSION
			);
		}

		$missing_ext = $this->checkPhpExtensions();
		if ( ! empty( $missing_ext ) ) {
			$issues[] = 'Missing PHP extensions: ' . implode( ', ', $missing_ext );
		}

		$missing_func = $this->checkWordPressFunctions();
		if ( ! empty( $missing_func ) ) {
			$issues[] = 'Missing WordPress functions: ' . implode( ', ', $missing_func );
		}

		return $issues;
	}
}













