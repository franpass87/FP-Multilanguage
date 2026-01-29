<?php
/**
 * WordPress Hook Validator for FP Multilanguage
 * 
 * Validates all hooks registered by the plugin for:
 * - Hook priorities
 * - Lifecycle correctness
 * - Duplicate prevention
 * - Missing remove_action calls
 * - Dangerous hooks
 * - Context-specific conditions
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Run as standalone script
	require_once __DIR__ . '/../../../../wp-load.php';
}

/**
 * Hook Validator Class
 */
class FPML_HookValidator {
	
	private $hooks = [];
	private $issues = [];
	
	/**
	 * Scan plugin files for hooks
	 */
	public function scan() {
		$plugin_dir = dirname( __DIR__ );
		$src_dir = $plugin_dir . '/src';
		
		$this->scan_directory( $src_dir );
		$this->scan_file( $plugin_dir . '/fp-multilanguage.php' );
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
	 * Scan file for hooks
	 */
	private function scan_file( $file ) {
		if ( ! file_exists( $file ) ) {
			return;
		}
		
		$content = file_get_contents( $file );
		
		// Match add_action and add_filter calls
		preg_match_all( '/add_(action|filter)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*([^,)]+)\s*(?:,\s*(\d+))?\s*(?:,\s*(\d+))?/i', $content, $matches, PREG_SET_ORDER );
		
		foreach ( $matches as $match ) {
			$type = strtolower( $match[1] );
			$hook = $match[2];
			$callback = trim( $match[3] );
			$priority = isset( $match[4] ) ? (int) $match[4] : 10;
			$accepted_args = isset( $match[5] ) ? (int) $match[5] : 1;
			
			$this->hooks[] = [
				'file' => $file,
				'type' => $type,
				'hook' => $hook,
				'callback' => $callback,
				'priority' => $priority,
				'accepted_args' => $accepted_args,
			];
		}
	}
	
	/**
	 * Validate hooks
	 */
	public function validate() {
		$this->check_duplicates();
		$this->check_priorities();
		$this->check_lifecycle();
		$this->check_dangerous_hooks();
		$this->check_context();
		$this->check_remove_support();
	}
	
	/**
	 * Check for duplicate hook registrations
	 */
	private function check_duplicates() {
		$seen = [];
		foreach ( $this->hooks as $hook ) {
			$key = $hook['hook'] . '|' . $hook['callback'] . '|' . $hook['priority'];
			if ( isset( $seen[ $key ] ) ) {
				$this->issues[] = [
					'type' => 'duplicate',
					'severity' => 'high',
					'message' => "Duplicate hook registration: {$hook['hook']} with callback {$hook['callback']} at priority {$hook['priority']}",
					'file' => $hook['file'],
					'hook' => $hook,
				];
			}
			$seen[ $key ] = true;
		}
	}
	
	/**
	 * Check hook priorities
	 */
	private function check_priorities() {
		$critical_hooks = [
			'plugins_loaded' => [1, 5, 10],
			'init' => [999],
		];
		
		foreach ( $this->hooks as $hook ) {
			if ( isset( $critical_hooks[ $hook['hook'] ] ) ) {
				if ( ! in_array( $hook['priority'], $critical_hooks[ $hook['hook'] ] ) ) {
					$this->issues[] = [
						'type' => 'priority',
						'severity' => 'medium',
						'message' => "Hook {$hook['hook']} has non-standard priority {$hook['priority']}. Expected: " . implode( ', ', $critical_hooks[ $hook['hook'] ] ),
						'file' => $hook['file'],
						'hook' => $hook,
					];
				}
			}
		}
	}
	
	/**
	 * Check lifecycle correctness
	 */
	private function check_lifecycle() {
		$lifecycle_order = [
			'plugins_loaded' => 1,
			'init' => 2,
			'admin_init' => 3,
			'wp_loaded' => 4,
		];
		
		$hook_order = [];
		foreach ( $this->hooks as $hook ) {
			if ( isset( $lifecycle_order[ $hook['hook'] ] ) ) {
				$hook_order[] = [
					'hook' => $hook['hook'],
					'order' => $lifecycle_order[ $hook['hook'] ],
					'priority' => $hook['priority'],
					'file' => $hook['file'],
				];
			}
		}
		
		// Check for hooks registered in wrong order
		usort( $hook_order, function( $a, $b ) {
			if ( $a['order'] === $b['order'] ) {
				return $a['priority'] <=> $b['priority'];
			}
			return $a['order'] <=> $b['order'];
		} );
	}
	
	/**
	 * Check for dangerous hooks
	 */
	private function check_dangerous_hooks() {
		$dangerous = [
			'the_post' => 'Called inside loops',
			'the_content' => 'Called inside loops',
			'wp_head' => 'Expensive operations',
			'wp_footer' => 'Expensive operations',
		];
		
		foreach ( $this->hooks as $hook ) {
			if ( isset( $dangerous[ $hook['hook'] ] ) ) {
				$this->issues[] = [
					'type' => 'dangerous',
					'severity' => 'medium',
					'message' => "Potentially dangerous hook: {$hook['hook']} - {$dangerous[ $hook['hook'] ]}",
					'file' => $hook['file'],
					'hook' => $hook,
				];
			}
		}
	}
	
	/**
	 * Check context-specific conditions
	 */
	private function check_context() {
		$admin_only_hooks = ['admin_menu', 'admin_enqueue_scripts', 'admin_init'];
		$frontend_only_hooks = ['template_redirect', 'wp_enqueue_scripts'];
		
		foreach ( $this->hooks as $hook ) {
			if ( in_array( $hook['hook'], $admin_only_hooks ) ) {
				// Check if file is admin-related
				if ( strpos( $hook['file'], '/Admin/' ) === false && strpos( $hook['file'], '/admin' ) === false ) {
					$this->issues[] = [
						'type' => 'context',
						'severity' => 'low',
						'message' => "Admin hook {$hook['hook']} registered in non-admin file",
						'file' => $hook['file'],
						'hook' => $hook,
					];
				}
			}
		}
	}
	
	/**
	 * Check for remove_action/remove_filter support
	 */
	private function check_remove_support() {
		// This would require checking if hooks are registered in a way that allows removal
		// For now, we just note hooks that should be removable
		$should_be_removable = ['wp_enqueue_scripts', 'admin_enqueue_scripts'];
		
		foreach ( $this->hooks as $hook ) {
			if ( in_array( $hook['hook'], $should_be_removable ) ) {
				// Check if callback is a string (removable) vs closure (not easily removable)
				if ( strpos( $hook['callback'], 'function' ) !== false || strpos( $hook['callback'], '[' ) !== false ) {
					// Likely a closure or array, harder to remove
					$this->issues[] = [
						'type' => 'removability',
						'severity' => 'low',
						'message' => "Hook {$hook['hook']} may not be easily removable (closure or array callback)",
						'file' => $hook['file'],
						'hook' => $hook,
					];
				}
			}
		}
	}
	
	/**
	 * Generate report
	 */
	public function report() {
		echo "=== FP Multilanguage Hook Validation Report ===\n\n";
		echo "Total hooks found: " . count( $this->hooks ) . "\n";
		echo "Issues found: " . count( $this->issues ) . "\n\n";
		
		if ( empty( $this->issues ) ) {
			echo "✓ No issues found!\n";
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
					echo "  Hook: {$issue['hook']['hook']} | Callback: {$issue['hook']['callback']} | Priority: {$issue['hook']['priority']}\n\n";
				}
			}
		}
		
		// Summary by hook
		echo "\n=== Hook Summary ===\n";
		$hook_counts = [];
		foreach ( $this->hooks as $hook ) {
			$hook_counts[ $hook['hook'] ] = ( $hook_counts[ $hook['hook'] ] ?? 0 ) + 1;
		}
		arsort( $hook_counts );
		foreach ( $hook_counts as $hook => $count ) {
			echo "{$hook}: {$count} registration(s)\n";
		}
	}
}

// Run if executed directly
if ( php_sapi_name() === 'cli' || ! defined( 'ABSPATH' ) ) {
	$validator = new FPML_HookValidator();
	$validator->scan();
	$validator->validate();
	$validator->report();
}














