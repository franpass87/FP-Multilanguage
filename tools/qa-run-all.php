<?php
/**
 * QA Run All Script
 * 
 * Runs all QA tools and tests in sequence.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Run as standalone script
	require_once __DIR__ . '/../../../../wp-load.php';
}

/**
 * QA Runner Class
 */
class FPML_QARunner {
	
	private $results = [];
	private $errors = [];
	
	/**
	 * Run all QA checks
	 */
	public function run_all() {
		echo "=== FP Multilanguage QA Suite ===\n\n";
		
		$this->run_hook_validator();
		$this->run_security_scanner();
		$this->run_compatibility_checker();
		$this->run_performance_profiler();
		
		$this->report();
	}
	
	/**
	 * Verify implementation
	 */
	public function verify_implementation() {
		$verifier_script = __DIR__ . '/qa-verify-implementation.php';
		if ( file_exists( $verifier_script ) ) {
			include $verifier_script;
		}
	}
	
	/**
	 * Run hook validator
	 */
	private function run_hook_validator() {
		echo "1. Running Hook Validator...\n";
		$script = __DIR__ . '/qa-hook-validator.php';
		
		if ( file_exists( $script ) ) {
			ob_start();
			include $script;
			$output = ob_get_clean();
			$this->results['hook_validator'] = [
				'status' => 'completed',
				'output' => $output,
			];
			echo "   ✓ Completed\n\n";
		} else {
			$this->errors[] = 'Hook validator script not found';
			echo "   ✗ Script not found\n\n";
		}
	}
	
	/**
	 * Run security scanner
	 */
	private function run_security_scanner() {
		echo "2. Running Security Scanner...\n";
		$script = __DIR__ . '/qa-security-scanner.php';
		
		if ( file_exists( $script ) ) {
			ob_start();
			include $script;
			$output = ob_get_clean();
			$this->results['security_scanner'] = [
				'status' => 'completed',
				'output' => $output,
			];
			echo "   ✓ Completed\n\n";
		} else {
			$this->errors[] = 'Security scanner script not found';
			echo "   ✗ Script not found\n\n";
		}
	}
	
	/**
	 * Run compatibility checker
	 */
	private function run_compatibility_checker() {
		echo "3. Running Compatibility Checker...\n";
		$script = __DIR__ . '/qa-compatibility-checker.php';
		
		if ( file_exists( $script ) ) {
			ob_start();
			include $script;
			$output = ob_get_clean();
			$this->results['compatibility_checker'] = [
				'status' => 'completed',
				'output' => $output,
			];
			echo "   ✓ Completed\n\n";
		} else {
			$this->errors[] = 'Compatibility checker script not found';
			echo "   ✗ Script not found\n\n";
		}
	}
	
	/**
	 * Run performance profiler
	 */
	private function run_performance_profiler() {
		echo "4. Running Performance Profiler...\n";
		$script = __DIR__ . '/qa-performance-profiler.php';
		
		if ( file_exists( $script ) ) {
			ob_start();
			include $script;
			$output = ob_get_clean();
			$this->results['performance_profiler'] = [
				'status' => 'completed',
				'output' => $output,
			];
			echo "   ✓ Completed\n\n";
		} else {
			$this->errors[] = 'Performance profiler script not found';
			echo "   ✗ Script not found\n\n";
		}
	}
	
	/**
	 * Generate report
	 */
	private function report() {
		echo "\n=== QA Suite Summary ===\n\n";
		
		$completed = count( $this->results );
		$errors = count( $this->errors );
		
		echo "Completed: {$completed}/4\n";
		echo "Errors: {$errors}\n\n";
		
		if ( ! empty( $this->errors ) ) {
			echo "Errors:\n";
			foreach ( $this->errors as $error ) {
				echo "  - {$error}\n";
			}
			echo "\n";
		}
		
		if ( $errors > 0 ) {
			exit( 1 );
		}
	}
}

// Run if executed directly
if ( php_sapi_name() === 'cli' || ! defined( 'ABSPATH' ) ) {
	$runner = new FPML_QARunner();
	$runner->run_all();
}

