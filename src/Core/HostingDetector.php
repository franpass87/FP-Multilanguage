<?php
/**
 * Hosting Environment Detector.
 *
 * Detects hosting type (VPS, shared, cloud) for adaptive performance tuning.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.5.0
 */

namespace FP\Multilanguage\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect hosting environment capabilities.
 *
 * @since 0.5.0
 */
class HostingDetector {
	/**
	 * Singleton instance.
	 *
	 * @var HostingDetector|null
	 */
	protected static $instance = null;

	/**
	 * Cached hosting type.
	 *
	 * @var string|null
	 */
	protected $hosting_type = null;

	/**
	 * Cached performance score (0-100).
	 *
	 * @var int|null
	 */
	protected $performance_score = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// Cache per 24 ore
		$cached = get_transient( 'fpml_hosting_profile' );
		if ( $cached && is_array( $cached ) ) {
			$this->hosting_type       = $cached['type'] ?? null;
			$this->performance_score  = $cached['score'] ?? null;
		}
	}

	/**
	 * Detect hosting type.
	 *
	 * @since 0.5.0
	 *
	 * @return string 'vps', 'cloud', 'shared', 'unknown'
	 */
	public function get_hosting_type() {
		if ( null !== $this->hosting_type ) {
			return $this->hosting_type;
		}

		$this->hosting_type = $this->detect_hosting_type();

		$this->cache_profile();

		return $this->hosting_type;
	}

	/**
	 * Get performance score (0-100).
	 *
	 * Higher = more capable hosting.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function get_performance_score() {
		if ( null !== $this->performance_score ) {
			return $this->performance_score;
		}

		$this->performance_score = $this->calculate_performance_score();

		$this->cache_profile();

		return $this->performance_score;
	}

	/**
	 * Get recommended batch size for queue processing.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function get_recommended_batch_size() {
		$score = $this->get_performance_score();

		// Adaptive batching basato su score
		if ( $score >= 80 ) {
			return 20; // VPS/Cloud potente
		} elseif ( $score >= 50 ) {
			return 10; // VPS medio/Cloud base
		} elseif ( $score >= 30 ) {
			return 5;  // Shared hosting buono
		} else {
			return 3;  // Shared hosting limitato
		}
	}

	/**
	 * Detect hosting type based on environment variables and capabilities.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	protected function detect_hosting_type() {
		// Local development
		if ( $this->is_local_environment() ) {
			return 'local';
		}

		// Cloud platforms (AWS, Google Cloud, Azure, etc.)
		if ( $this->is_cloud_platform() ) {
			return 'cloud';
		}

		// VPS detection
		if ( $this->is_vps() ) {
			return 'vps';
		}

		// Default to shared hosting
		return 'shared';
	}

	/**
	 * Calculate performance score.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	protected function calculate_performance_score() {
		$score = 0;

		// Memory limit (max 40 punti)
		$memory_limit = $this->get_memory_limit_mb();
		if ( $memory_limit >= 512 ) {
			$score += 40;
		} elseif ( $memory_limit >= 256 ) {
			$score += 30;
		} elseif ( $memory_limit >= 128 ) {
			$score += 20;
		} else {
			$score += 10;
		}

		// Max execution time (max 20 punti)
		$max_execution = ini_get( 'max_execution_time' );
		if ( $max_execution >= 300 || $max_execution == 0 ) {
			$score += 20;
		} elseif ( $max_execution >= 120 ) {
			$score += 15;
		} elseif ( $max_execution >= 60 ) {
			$score += 10;
		} else {
			$score += 5;
		}

		// CPU cores (max 20 punti) - stima
		if ( function_exists( 'sys_getloadavg' ) ) {
			$load = sys_getloadavg();
			if ( isset( $load[0] ) && $load[0] < 1.0 ) {
				$score += 20; // Basso carico = risorse disponibili
			} elseif ( isset( $load[0] ) && $load[0] < 2.0 ) {
				$score += 10;
			}
		} else {
			$score += 10; // Neutro se non disponibile
		}

		// OpCache (max 10 punti)
		if ( function_exists( 'opcache_get_status' ) && opcache_get_status() ) {
			$score += 10;
		}

		// Database performance test (max 10 punti)
		$db_score = $this->test_database_performance();
		$score += min( 10, $db_score );

		return min( 100, $score );
	}

	/**
	 * Check if running in local environment.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	protected function is_local_environment() {
		$server_name = $_SERVER['SERVER_NAME'] ?? '';

		$local_patterns = array(
			'localhost',
			'127.0.0.1',
			'.local',
			'.test',
			'.dev',
			'::1',
		);

		foreach ( $local_patterns as $pattern ) {
			if ( false !== stripos( $server_name, $pattern ) ) {
				return true;
			}
		}

		// Local by Flywheel
		if ( defined( 'LOCAL_SITE_ID' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if running on cloud platform.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	protected function is_cloud_platform() {
		// AWS
		if ( isset( $_SERVER['AWS_EXECUTION_ENV'] ) || file_exists( '/sys/hypervisor/uuid' ) ) {
			return true;
		}

		// Google Cloud
		if ( isset( $_SERVER['GAE_SERVICE'] ) || isset( $_SERVER['GOOGLE_CLOUD_PROJECT'] ) ) {
			return true;
		}

		// Azure
		if ( isset( $_SERVER['WEBSITE_INSTANCE_ID'] ) ) {
			return true;
		}

		// Cloudways
		if ( defined( 'IS_PRESSABLE' ) || isset( $_SERVER['CLOUDWAYS_APP_URL'] ) ) {
			return true;
		}

		// WP Engine
		if ( defined( 'WPE_PLUGIN_VERSION' ) ) {
			return true;
		}

		// Kinsta
		if ( defined( 'KINSTA_CACHE_ZONE' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if running on VPS.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	protected function is_vps() {
		// Dedicated/buona memoria = probabile VPS
		$memory_limit = $this->get_memory_limit_mb();
		if ( $memory_limit >= 256 ) {
			return true;
		}

		// Controllo virtualizzazione
		if ( file_exists( '/proc/cpuinfo' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$cpuinfo = file_get_contents( '/proc/cpuinfo' );
			if ( false !== stripos( $cpuinfo, 'QEMU' ) || false !== stripos( $cpuinfo, 'KVM' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get memory limit in MB.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	protected function get_memory_limit_mb() {
		$memory_limit = ini_get( 'memory_limit' );

		if ( -1 === (int) $memory_limit ) {
			return 9999; // Unlimited
		}

		$value = (int) $memory_limit;
		$unit  = strtoupper( substr( $memory_limit, -1 ) );

		switch ( $unit ) {
			case 'G':
				$value *= 1024;
				break;
			case 'M':
				// Already in MB
				break;
			case 'K':
				$value = (int) ( $value / 1024 );
				break;
		}

		return $value;
	}

	/**
	 * Test database performance.
	 *
	 * @since 0.5.0
	 *
	 * @return int Score 0-10
	 */
	protected function test_database_performance() {
		global $wpdb;

		$start = microtime( true );

		// Query semplice
		$wpdb->get_var( "SELECT 1" );

		$duration = ( microtime( true ) - $start ) * 1000; // ms

		// Sotto 5ms = ottimo (10 punti)
		// 5-10ms = buono (7 punti)
		// 10-20ms = medio (5 punti)
		// 20-50ms = lento (3 punti)
		// Oltre 50ms = molto lento (1 punto)

		if ( $duration < 5 ) {
			return 10;
		} elseif ( $duration < 10 ) {
			return 7;
		} elseif ( $duration < 20 ) {
			return 5;
		} elseif ( $duration < 50 ) {
			return 3;
		} else {
			return 1;
		}
	}

	/**
	 * Cache hosting profile.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	protected function cache_profile() {
		$profile = array(
			'type'  => $this->hosting_type,
			'score' => $this->performance_score,
		);

		set_transient( 'fpml_hosting_profile', $profile, DAY_IN_SECONDS );
	}

	/**
	 * Clear cached profile (for testing or reset).
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( 'fpml_hosting_profile' );
		$this->hosting_type      = null;
		$this->performance_score = null;
	}
}

