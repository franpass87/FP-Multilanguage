<?php
/**
 * Performance Profiler for FP Multilanguage
 * 
 * Profiles plugin performance:
 * - Memory usage
 * - Database query count
 * - Execution time
 * - Asset sizes
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Run as standalone script
	require_once __DIR__ . '/../../../../wp-load.php';
}

/**
 * Performance Profiler Class
 */
class FPML_PerformanceProfiler {
	
	private $metrics = [];
	private $start_time;
	private $start_memory;
	
	public function __construct() {
		$this->start_time = microtime( true );
		$this->start_memory = memory_get_usage();
	}
	
	/**
	 * Profile plugin initialization
	 */
	public function profile_init() {
		// Measure plugin load time
		$load_time = microtime( true ) - $this->start_time;
		$memory_used = memory_get_usage() - $this->start_memory;
		
		$this->metrics['init'] = [
			'load_time' => $load_time,
			'memory_used' => $memory_used,
		];
	}
	
	/**
	 * Profile database queries
	 */
	public function profile_queries() {
		global $wpdb;
		
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$query_count = count( $wpdb->queries );
			$slow_queries = 0;
			
			foreach ( $wpdb->queries as $query ) {
				if ( isset( $query[1] ) && $query[1] > 0.1 ) {
					$slow_queries++;
				}
			}
			
			$this->metrics['queries'] = [
				'total' => $query_count,
				'slow' => $slow_queries,
			];
		} else {
			$this->metrics['queries'] = [
				'total' => 'N/A (SAVEQUERIES not enabled)',
				'slow' => 'N/A',
			];
		}
	}
	
	/**
	 * Profile asset sizes
	 */
	public function profile_assets() {
		$plugin_dir = dirname( __DIR__ );
		$assets_dir = $plugin_dir . '/assets';
		
		$css_size = 0;
		$js_size = 0;
		
		if ( is_dir( $assets_dir ) ) {
			$css_files = glob( $assets_dir . '/**/*.css' );
			$js_files = glob( $assets_dir . '/**/*.js' );
			
			foreach ( $css_files as $file ) {
				$css_size += filesize( $file );
			}
			
			foreach ( $js_files as $file ) {
				$js_size += filesize( $file );
			}
		}
		
		$this->metrics['assets'] = [
			'css_size' => $this->format_bytes( $css_size ),
			'js_size' => $this->format_bytes( $js_size ),
			'total_size' => $this->format_bytes( $css_size + $js_size ),
		];
	}
	
	/**
	 * Format bytes to human readable
	 */
	private function format_bytes( $bytes ) {
		$units = ['B', 'KB', 'MB', 'GB'];
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		
		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
	
	/**
	 * Generate report
	 */
	public function report() {
		$this->profile_queries();
		$this->profile_assets();
		
		echo "=== FP Multilanguage Performance Profile ===\n\n";
		
		if ( isset( $this->metrics['init'] ) ) {
			echo "Initialization:\n";
			echo "  Load Time: " . number_format( $this->metrics['init']['load_time'], 4 ) . "s\n";
			echo "  Memory Used: " . $this->format_bytes( $this->metrics['init']['memory_used'] ) . "\n\n";
		}
		
		if ( isset( $this->metrics['queries'] ) ) {
			echo "Database Queries:\n";
			echo "  Total Queries: {$this->metrics['queries']['total']}\n";
			echo "  Slow Queries (>0.1s): {$this->metrics['queries']['slow']}\n\n";
		}
		
		if ( isset( $this->metrics['assets'] ) ) {
			echo "Assets:\n";
			echo "  CSS Size: {$this->metrics['assets']['css_size']}\n";
			echo "  JS Size: {$this->metrics['assets']['js_size']}\n";
			echo "  Total Size: {$this->metrics['assets']['total_size']}\n\n";
		}
		
		$peak_memory = memory_get_peak_usage( true );
		echo "Peak Memory: " . $this->format_bytes( $peak_memory ) . "\n";
	}
}

// Run if executed directly
if ( php_sapi_name() === 'cli' || ! defined( 'ABSPATH' ) ) {
	$profiler = new FPML_PerformanceProfiler();
	$profiler->profile_init();
	$profiler->report();
}














