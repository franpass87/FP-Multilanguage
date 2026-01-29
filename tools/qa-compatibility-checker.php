<?php
/**
 * Compatibility Checker for FP Multilanguage
 * 
 * Checks plugin compatibility with:
 * - PHP versions
 * - WordPress versions
 * - Required plugins
 * - Theme compatibility
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Run as standalone script
	require_once __DIR__ . '/../../../../wp-load.php';
}

/**
 * Compatibility Checker Class
 */
class FPML_CompatibilityChecker {
	
	private $checks = [];
	
	/**
	 * Run all compatibility checks
	 */
	public function check() {
		$this->check_php_version();
		$this->check_wordpress_version();
		$this->check_required_extensions();
		$this->check_optional_plugins();
		$this->check_theme_compatibility();
	}
	
	/**
	 * Check PHP version
	 */
	private function check_php_version() {
		$required = '8.0.0';
		$current = PHP_VERSION;
		
		$this->checks[] = [
			'type' => 'php_version',
			'status' => version_compare( $current, $required, '>=' ) ? 'pass' : 'fail',
			'message' => "PHP Version: {$current} (Required: {$required}+)",
		];
	}
	
	/**
	 * Check WordPress version
	 */
	private function check_wordpress_version() {
		global $wp_version;
		$required = '5.8';
		$current = $wp_version;
		
		$this->checks[] = [
			'type' => 'wordpress_version',
			'status' => version_compare( $current, $required, '>=' ) ? 'pass' : 'fail',
			'message' => "WordPress Version: {$current} (Required: {$required}+)",
		];
	}
	
	/**
	 * Check required PHP extensions
	 */
	private function check_required_extensions() {
		$required = ['mbstring', 'xml', 'curl', 'json'];
		
		foreach ( $required as $ext ) {
			$loaded = extension_loaded( $ext );
			$this->checks[] = [
				'type' => 'php_extension',
				'status' => $loaded ? 'pass' : 'fail',
				'message' => "PHP Extension '{$ext}': " . ( $loaded ? 'Loaded' : 'Missing' ),
			];
		}
	}
	
	/**
	 * Check optional plugins
	 */
	private function check_optional_plugins() {
		$plugins = [
			'woocommerce/woocommerce.php' => 'WooCommerce',
			'elementor/elementor.php' => 'Elementor',
			'js_composer/js_composer.php' => 'WPBakery',
		];
		
		foreach ( $plugins as $plugin => $name ) {
			$active = is_plugin_active( $plugin );
			$this->checks[] = [
				'type' => 'optional_plugin',
				'status' => $active ? 'pass' : 'info',
				'message' => "{$name}: " . ( $active ? 'Active' : 'Not installed' ),
			];
		}
	}
	
	/**
	 * Check theme compatibility
	 */
	private function check_theme_compatibility() {
		$theme = wp_get_theme();
		$theme_name = $theme->get( 'Name' );
		
		$compatible_themes = ['Salient', 'Astra', 'GeneratePress', 'Twenty Twenty-Four'];
		$is_compatible = false;
		
		foreach ( $compatible_themes as $compatible ) {
			if ( strpos( $theme_name, $compatible ) !== false ) {
				$is_compatible = true;
				break;
			}
		}
		
		$this->checks[] = [
			'type' => 'theme',
			'status' => $is_compatible ? 'pass' : 'warning',
			'message' => "Active Theme: {$theme_name} " . ( $is_compatible ? '(Compatible)' : '(Compatibility not tested)' ),
		];
	}
	
	/**
	 * Generate report
	 */
	public function report() {
		echo "=== FP Multilanguage Compatibility Check ===\n\n";
		
		$passed = 0;
		$failed = 0;
		$warnings = 0;
		$info = 0;
		
		foreach ( $this->checks as $check ) {
			$status = $check['status'];
			$icon = $status === 'pass' ? '✓' : ( $status === 'fail' ? '✗' : ( $status === 'warning' ? '⚠' : 'ℹ' ) );
			
			echo "{$icon} {$check['message']}\n";
			
			if ( $status === 'pass' ) {
				$passed++;
			} elseif ( $status === 'fail' ) {
				$failed++;
			} elseif ( $status === 'warning' ) {
				$warnings++;
			} else {
				$info++;
			}
		}
		
		echo "\n=== Summary ===\n";
		echo "Passed: {$passed}\n";
		echo "Failed: {$failed}\n";
		echo "Warnings: {$warnings}\n";
		echo "Info: {$info}\n";
		
		if ( $failed > 0 ) {
			echo "\n⚠ Some compatibility checks failed. Please review the requirements.\n";
			exit( 1 );
		}
	}
}

// Run if executed directly
if ( php_sapi_name() === 'cli' || ! defined( 'ABSPATH' ) ) {
	$checker = new FPML_CompatibilityChecker();
	$checker->check();
	$checker->report();
}














