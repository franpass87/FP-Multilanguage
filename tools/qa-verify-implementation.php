<?php
/**
 * QA Implementation Verification Script
 * 
 * Verifies that all components of the QA plan are properly implemented.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Run as standalone script
	require_once __DIR__ . '/../../../../wp-load.php';
}

/**
 * QA Implementation Verifier
 */
class FPML_QAImplementationVerifier {
	
	private $plugin_dir;
	private $results = [];
	private $errors = [];
	private $warnings = [];
	
	public function __construct() {
		$this->plugin_dir = dirname( __DIR__ );
	}
	
	/**
	 * Verify all QA components
	 */
	public function verify() {
		echo "=== FP Multilanguage QA Implementation Verification ===\n\n";
		
		$this->verify_documentation();
		$this->verify_tools();
		$this->verify_tests();
		$this->verify_cicd();
		$this->verify_configuration();
		
		$this->report();
	}
	
	/**
	 * Verify documentation files
	 */
	private function verify_documentation() {
		echo "1. Verifying Documentation...\n";
		
		$docs_dir = $this->plugin_dir . '/docs/QA';
		$required_docs = [
			'01-GLOBAL-QA-STRATEGY.md',
			'02-TEST-MATRIX.md',
			'03-MODULE-CHECKLISTS.md',
			'04-HOOK-VALIDATION.md',
			'05-FRONTEND-QA.md',
			'06-ADMIN-QA.md',
			'07-REST-API-QA.md',
			'08-CLI-QA.md',
			'09-DATABASE-QA.md',
			'10-MULTISITE-QA.md',
			'11-MULTILANGUAGE-QA.md',
			'12-PERFORMANCE-QA.md',
			'13-SECURITY-QA.md',
			'14-AUTOMATED-TESTING.md',
			'15-RELEASE-CHECKLIST.md',
			'README.md',
			'TOOLS-GUIDE.md',
			'TEST-EXECUTION-GUIDE.md',
			'IMPLEMENTATION-SUMMARY.md',
			'IMPLEMENTATION-STATUS.md',
		];
		
		$found = 0;
		$missing = [];
		
		foreach ( $required_docs as $doc ) {
			$path = $docs_dir . '/' . $doc;
			if ( file_exists( $path ) ) {
				$found++;
			} else {
				$missing[] = $doc;
			}
		}
		
		$this->results['documentation'] = [
			'found' => $found,
			'total' => count( $required_docs ),
			'missing' => $missing,
		];
		
		if ( ! empty( $missing ) ) {
			$this->errors[] = 'Missing documentation files: ' . implode( ', ', $missing );
		}
		
		echo "   Found: {$found}/" . count( $required_docs ) . " documentation files\n";
		if ( ! empty( $missing ) ) {
			echo "   ⚠ Missing: " . implode( ', ', $missing ) . "\n";
		} else {
			echo "   ✓ All documentation files present\n";
		}
		echo "\n";
	}
	
	/**
	 * Verify QA tools
	 */
	private function verify_tools() {
		echo "2. Verifying QA Tools...\n";
		
		$tools_dir = $this->plugin_dir . '/tools';
		$required_tools = [
			'qa-hook-validator.php',
			'qa-security-scanner.php',
			'qa-compatibility-checker.php',
			'qa-performance-profiler.php',
			'qa-run-all.php',
		];
		
		$found = 0;
		$missing = [];
		
		foreach ( $required_tools as $tool ) {
			$path = $tools_dir . '/' . $tool;
			if ( file_exists( $path ) ) {
				$found++;
				// Check if file is executable/readable
				if ( ! is_readable( $path ) ) {
					$this->warnings[] = "Tool {$tool} exists but is not readable";
				}
			} else {
				$missing[] = $tool;
			}
		}
		
		$this->results['tools'] = [
			'found' => $found,
			'total' => count( $required_tools ),
			'missing' => $missing,
		];
		
		if ( ! empty( $missing ) ) {
			$this->errors[] = 'Missing QA tools: ' . implode( ', ', $missing );
		}
		
		echo "   Found: {$found}/" . count( $required_tools ) . " QA tools\n";
		if ( ! empty( $missing ) ) {
			echo "   ⚠ Missing: " . implode( ', ', $missing ) . "\n";
		} else {
			echo "   ✓ All QA tools present\n";
		}
		echo "\n";
	}
	
	/**
	 * Verify test files
	 */
	private function verify_tests() {
		echo "3. Verifying Test Files...\n";
		
		$tests_dir = $this->plugin_dir . '/tests';
		
		// Check PHPUnit tests
		$unit_tests = glob( $tests_dir . '/Unit/**/*Test.php' );
		$integration_tests = glob( $tests_dir . '/Integration/**/*Test.php' );
		$phpunit_tests = glob( $tests_dir . '/phpunit/*Test.php' );
		
		$total_phpunit = count( $unit_tests ) + count( $integration_tests ) + count( $phpunit_tests );
		
		// Check E2E tests
		$e2e_tests = glob( $tests_dir . '/e2e/*.spec.js' );
		$playwright_config = file_exists( $tests_dir . '/e2e/playwright.config.js' );
		
		$this->results['tests'] = [
			'phpunit' => [
				'unit' => count( $unit_tests ),
				'integration' => count( $integration_tests ),
				'phpunit' => count( $phpunit_tests ),
				'total' => $total_phpunit,
			],
			'e2e' => [
				'tests' => count( $e2e_tests ),
				'config' => $playwright_config,
			],
		];
		
		echo "   PHPUnit Tests:\n";
		echo "     Unit: " . count( $unit_tests ) . "\n";
		echo "     Integration: " . count( $integration_tests ) . "\n";
		echo "     PHPUnit: " . count( $phpunit_tests ) . "\n";
		echo "     Total: {$total_phpunit}\n";
		
		echo "   E2E Tests:\n";
		echo "     Test files: " . count( $e2e_tests ) . "\n";
		if ( $playwright_config ) {
			echo "     ✓ Playwright config present\n";
		} else {
			echo "     ⚠ Playwright config missing\n";
			$this->warnings[] = 'Playwright configuration file missing';
		}
		
		if ( $total_phpunit === 0 ) {
			$this->warnings[] = 'No PHPUnit tests found';
		}
		
		if ( count( $e2e_tests ) === 0 ) {
			$this->warnings[] = 'No E2E tests found';
		}
		
		echo "\n";
	}
	
	/**
	 * Verify CI/CD configuration
	 */
	private function verify_cicd() {
		echo "4. Verifying CI/CD Configuration...\n";
		
		$workflow_file = $this->plugin_dir . '/.github/workflows/qa-tests.yml';
		
		if ( file_exists( $workflow_file ) ) {
			$content = file_get_contents( $workflow_file );
			
			$has_phpunit = strpos( $content, 'phpunit' ) !== false;
			$has_e2e = strpos( $content, 'playwright' ) !== false || strpos( $content, 'e2e' ) !== false;
			$has_phpcs = strpos( $content, 'phpcs' ) !== false;
			$has_phpstan = strpos( $content, 'phpstan' ) !== false;
			$has_hook_validation = strpos( $content, 'hook-validator' ) !== false;
			$has_security_scan = strpos( $content, 'security-scanner' ) !== false;
			
			$this->results['cicd'] = [
				'exists' => true,
				'phpunit' => $has_phpunit,
				'e2e' => $has_e2e,
				'phpcs' => $has_phpcs,
				'phpstan' => $has_phpstan,
				'hook_validation' => $has_hook_validation,
				'security_scan' => $has_security_scan,
			];
			
			echo "   ✓ GitHub Actions workflow exists\n";
			echo "   Jobs configured:\n";
			echo "     PHPUnit: " . ( $has_phpunit ? '✓' : '✗' ) . "\n";
			echo "     E2E: " . ( $has_e2e ? '✓' : '✗' ) . "\n";
			echo "     PHPCS: " . ( $has_phpcs ? '✓' : '✗' ) . "\n";
			echo "     PHPStan: " . ( $has_phpstan ? '✓' : '✗' ) . "\n";
			echo "     Hook Validation: " . ( $has_hook_validation ? '✓' : '✗' ) . "\n";
			echo "     Security Scan: " . ( $has_security_scan ? '✓' : '✗' ) . "\n";
			
			if ( ! $has_phpunit ) {
				$this->warnings[] = 'PHPUnit job not found in CI/CD workflow';
			}
			if ( ! $has_e2e ) {
				$this->warnings[] = 'E2E tests job not found in CI/CD workflow';
			}
		} else {
			$this->results['cicd'] = ['exists' => false];
			$this->errors[] = 'GitHub Actions workflow file not found';
			echo "   ✗ GitHub Actions workflow not found\n";
		}
		
		echo "\n";
	}
	
	/**
	 * Verify configuration files
	 */
	private function verify_configuration() {
		echo "5. Verifying Configuration Files...\n";
		
		$configs = [
			'phpunit.xml.dist' => $this->plugin_dir . '/phpunit.xml.dist',
			'composer.json' => $this->plugin_dir . '/composer.json',
			'package.json' => $this->plugin_dir . '/package.json',
		];
		
		$found = 0;
		$missing = [];
		
		foreach ( $configs as $name => $path ) {
			if ( file_exists( $path ) ) {
				$found++;
				
				// Check for required content
				if ( $name === 'composer.json' ) {
					$content = file_get_contents( $path );
					if ( strpos( $content, 'phpunit' ) === false ) {
						$this->warnings[] = "PHPUnit not found in {$name}";
					}
				}
				
				if ( $name === 'package.json' ) {
					$content = file_get_contents( $path );
					if ( strpos( $content, 'playwright' ) === false ) {
						$this->warnings[] = "Playwright not found in {$name}";
					}
				}
			} else {
				$missing[] = $name;
			}
		}
		
		$this->results['configuration'] = [
			'found' => $found,
			'total' => count( $configs ),
			'missing' => $missing,
		];
		
		if ( ! empty( $missing ) ) {
			$this->errors[] = 'Missing configuration files: ' . implode( ', ', $missing );
		}
		
		echo "   Found: {$found}/" . count( $configs ) . " configuration files\n";
		if ( ! empty( $missing ) ) {
			echo "   ⚠ Missing: " . implode( ', ', $missing ) . "\n";
		} else {
			echo "   ✓ All configuration files present\n";
		}
		echo "\n";
	}
	
	/**
	 * Generate report
	 */
	private function report() {
		echo "\n=== Verification Summary ===\n\n";
		
		// Documentation
		$doc_result = $this->results['documentation'];
		$doc_percent = round( ( $doc_result['found'] / $doc_result['total'] ) * 100 );
		echo "Documentation: {$doc_result['found']}/{$doc_result['total']} ({$doc_percent}%)\n";
		
		// Tools
		$tools_result = $this->results['tools'];
		$tools_percent = round( ( $tools_result['found'] / $tools_result['total'] ) * 100 );
		echo "QA Tools: {$tools_result['found']}/{$tools_result['total']} ({$tools_percent}%)\n";
		
		// Tests
		$tests_result = $this->results['tests'];
		echo "PHPUnit Tests: {$tests_result['phpunit']['total']}\n";
		echo "E2E Tests: {$tests_result['e2e']['tests']}\n";
		
		// CI/CD
		$cicd_result = $this->results['cicd'];
		if ( $cicd_result['exists'] ) {
			echo "CI/CD: ✓ Configured\n";
		} else {
			echo "CI/CD: ✗ Not configured\n";
		}
		
		// Configuration
		$config_result = $this->results['configuration'];
		$config_percent = round( ( $config_result['found'] / $config_result['total'] ) * 100 );
		echo "Configuration: {$config_result['found']}/{$config_result['total']} ({$config_percent}%)\n";
		
		echo "\n";
		
		// Errors
		if ( ! empty( $this->errors ) ) {
			echo "=== Errors ===\n";
			foreach ( $this->errors as $error ) {
				echo "✗ {$error}\n";
			}
			echo "\n";
		}
		
		// Warnings
		if ( ! empty( $this->warnings ) ) {
			echo "=== Warnings ===\n";
			foreach ( $this->warnings as $warning ) {
				echo "⚠ {$warning}\n";
			}
			echo "\n";
		}
		
		// Final status
		if ( empty( $this->errors ) && empty( $this->warnings ) ) {
			echo "✅ All QA components verified successfully!\n";
			exit( 0 );
		} elseif ( empty( $this->errors ) ) {
			echo "⚠ Verification completed with warnings\n";
			exit( 0 );
		} else {
			echo "✗ Verification failed with errors\n";
			exit( 1 );
		}
	}
}

// Run if executed directly
if ( php_sapi_name() === 'cli' || ! defined( 'ABSPATH' ) ) {
	$verifier = new FPML_QAImplementationVerifier();
	$verifier->verify();
}














