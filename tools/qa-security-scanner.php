<?php
/**
 * Security Scanner for FP Multilanguage
 * 
 * Scans plugin code for common security issues:
 * - SQL injection vulnerabilities
 * - XSS vulnerabilities
 * - Missing nonce validation
 * - Missing capability checks
 * - Unsafe output escaping
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Run as standalone script
	require_once __DIR__ . '/../../../../wp-load.php';
}

/**
 * Security Scanner Class
 */
class FPML_SecurityScanner {
	
	private $issues = [];
	private $plugin_dir;
	
	public function __construct() {
		$this->plugin_dir = dirname( __DIR__ );
	}
	
	/**
	 * Scan plugin for security issues
	 */
	public function scan() {
		$src_dir = $this->plugin_dir . '/src';
		$this->scan_directory( $src_dir );
		$this->scan_file( $this->plugin_dir . '/fp-multilanguage.php' );
	}
	
	/**
	 * Scan directory recursively
	 */
	private function scan_directory( $dir ) {
		$files = glob( $dir . '/*.php' );
		foreach ( $files as $file ) {
			$this->scan_file( $file );
		}
		
		$dirs = glob( $dir . '/*', GLOB_ONLYDIR );
		foreach ( $dirs as $subdir ) {
			$this->scan_directory( $subdir );
		}
	}
	
	/**
	 * Scan file for security issues
	 */
	private function scan_file( $file ) {
		if ( ! file_exists( $file ) ) {
			return;
		}
		
		$content = file_get_contents( $file );
		$lines = explode( "\n", $content );
		
		$this->check_sql_injection( $file, $content, $lines );
		$this->check_xss( $file, $content, $lines );
		$this->check_nonce_validation( $file, $content, $lines );
		$this->check_capability_checks( $file, $content, $lines );
		$this->check_output_escaping( $file, $content, $lines );
	}
	
	/**
	 * Check for SQL injection vulnerabilities
	 */
	private function check_sql_injection( $file, $content, $lines ) {
		// Check for direct string concatenation in queries
		if ( preg_match_all( '/\$wpdb->(query|get_var|get_row|get_results|prepare)\s*\(\s*["\']([^"\']*\$[^"\']*)["\']/', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$line = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
				$this->issues[] = [
					'type' => 'sql_injection',
					'severity' => 'high',
					'message' => 'Potential SQL injection: Direct string concatenation in database query',
					'file' => $file,
					'line' => $line,
				];
			}
		}
		
		// Check for missing prepare() calls
		if ( preg_match_all( '/\$wpdb->(query|get_var|get_row|get_results)\s*\(\s*["\']([^"\']*\{[^"\']*\})["\']/', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$line = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
				$this->issues[] = [
					'type' => 'sql_injection',
					'severity' => 'high',
					'message' => 'Potential SQL injection: Variable interpolation in query without prepare()',
					'file' => $file,
					'line' => $line,
				];
			}
		}
	}
	
	/**
	 * Check for XSS vulnerabilities
	 */
	private function check_xss( $file, $content, $lines ) {
		// Check for unescaped echo/print
		if ( preg_match_all( '/\b(echo|print)\s+([^;]+);/', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $idx => $match ) {
				$output = $matches[2][$idx][0];
				if ( strpos( $output, '$_' ) !== false || strpos( $output, '$_GET' ) !== false || strpos( $output, '$_POST' ) !== false ) {
					if ( strpos( $output, 'esc_' ) === false && strpos( $output, 'wp_kses' ) === false ) {
						$line = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
						$this->issues[] = [
							'type' => 'xss',
							'severity' => 'high',
							'message' => 'Potential XSS: Unescaped output of user input',
							'file' => $file,
							'line' => $line,
						];
					}
				}
			}
		}
	}
	
	/**
	 * Check for missing nonce validation
	 */
	private function check_nonce_validation( $file, $content, $lines ) {
		// Check AJAX handlers
		if ( preg_match( '/wp_ajax_/', $content ) || preg_match( '/wp_ajax_nopriv_/', $content ) ) {
			if ( strpos( $content, 'check_ajax_referer' ) === false && strpos( $content, 'wp_verify_nonce' ) === false ) {
				$this->issues[] = [
					'type' => 'nonce',
					'severity' => 'high',
					'message' => 'Missing nonce validation in AJAX handler',
					'file' => $file,
					'line' => 0,
				];
			}
		}
		
		// Check form submissions
		if ( preg_match( '/\$_POST\[/', $content ) || preg_match( '/\$_REQUEST\[/', $content ) ) {
			if ( strpos( $content, 'check_admin_referer' ) === false && strpos( $content, 'wp_verify_nonce' ) === false ) {
				// Might be false positive, but worth flagging
				if ( strpos( $file, '/Admin/' ) !== false || strpos( $file, '/Rest/' ) !== false ) {
					$this->issues[] = [
						'type' => 'nonce',
						'severity' => 'medium',
						'message' => 'Potential missing nonce validation in form handler',
						'file' => $file,
						'line' => 0,
					];
				}
			}
		}
	}
	
	/**
	 * Check for missing capability checks
	 */
	private function check_capability_checks( $file, $content, $lines ) {
		// Check admin functions
		if ( strpos( $file, '/Admin/' ) !== false || strpos( $file, '/Rest/' ) !== false ) {
			if ( preg_match( '/\$_POST\[/', $content ) || preg_match( '/\$_REQUEST\[/', $content ) ) {
				if ( strpos( $content, 'current_user_can' ) === false && strpos( $content, 'check_ajax_referer' ) === false ) {
					$this->issues[] = [
						'type' => 'capability',
						'severity' => 'high',
						'message' => 'Missing capability check in admin/API handler',
						'file' => $file,
						'line' => 0,
					];
				}
			}
		}
	}
	
	/**
	 * Check for unsafe output escaping
	 */
	private function check_output_escaping( $file, $content, $lines ) {
		// Check for direct variable output in HTML context
		if ( preg_match_all( '/<\?php\s+echo\s+([^;]+);\s*\?>/', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[1] as $idx => $match ) {
				$var = trim( $match[0] );
				if ( preg_match( '/\$[a-zA-Z_][a-zA-Z0-9_]*/', $var ) && strpos( $var, 'esc_' ) === false ) {
					$line = substr_count( substr( $content, 0, $match[1] ), "\n" ) + 1;
					$this->issues[] = [
						'type' => 'escaping',
						'severity' => 'medium',
						'message' => 'Potential unsafe output: Variable output without escaping',
						'file' => $file,
						'line' => $line,
					];
				}
			}
		}
	}
	
	/**
	 * Generate report
	 */
	public function report() {
		echo "=== FP Multilanguage Security Scan Report ===\n\n";
		echo "Issues found: " . count( $this->issues ) . "\n\n";
		
		if ( empty( $this->issues ) ) {
			echo "✓ No security issues found!\n";
			return;
		}
		
		$by_severity = ['high' => [], 'medium' => [], 'low' => []];
		foreach ( $this->issues as $issue ) {
			$by_severity[ $issue['severity'] ][] = $issue;
		}
		
		foreach ( ['high', 'medium', 'low'] as $severity ) {
			if ( ! empty( $by_severity[ $severity ] ) ) {
				echo "\n=== {$severity} Severity Issues ===\n";
				foreach ( $by_severity[ $severity ] as $issue ) {
					echo "[{$issue['type']}] {$issue['message']}\n";
					echo "  File: {$issue['file']}\n";
					if ( $issue['line'] > 0 ) {
						echo "  Line: {$issue['line']}\n";
					}
					echo "\n";
				}
			}
		}
	}
}

// Run if executed directly
if ( php_sapi_name() === 'cli' || ! defined( 'ABSPATH' ) ) {
	$scanner = new FPML_SecurityScanner();
	$scanner->scan();
	$scanner->report();
}














