<?php
/**
 * Diagnostics Service.
 *
 * Handles plugin diagnostics and health checks.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Diagnostics\CostEstimator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing diagnostics and health checks.
 *
 * @since 1.0.0
 */
class DiagnosticsService {
	/**
	 * Get diagnostics snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function getSnapshot(): array {
		$snapshot = array(
			'timestamp' => current_time( 'mysql' ),
			'version' => defined( 'FPML_VERSION' ) ? FPML_VERSION : 'unknown',
			'php_version' => PHP_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
			'assisted_mode' => $this->getAssistedModeStatus(),
			'queue_status' => $this->getQueueStatus(),
			'translations_count' => $this->getTranslationsCount(),
			'settings' => $this->getSettingsSummary(),
		);

		return apply_filters( 'fpml_diagnostics_snapshot', $snapshot );
	}

	/**
	 * Get health status.
	 *
	 * @return array<string,mixed>
	 */
	public function getHealthStatus(): array {
		$status = array(
			'overall' => 'healthy',
			'checks' => array(),
		);

		// Check queue
		$queue_status = $this->getQueueStatus();
		if ( isset( $queue_status['failed_count'] ) && $queue_status['failed_count'] > 100 ) {
			$status['checks']['queue'] = 'warning';
			$status['overall'] = 'warning';
		} else {
			$status['checks']['queue'] = 'healthy';
		}

		// Check translations
		$translations_count = $this->getTranslationsCount();
		if ( $translations_count['total'] === 0 ) {
			$status['checks']['translations'] = 'info';
		} else {
			$status['checks']['translations'] = 'healthy';
		}

		return apply_filters( 'fpml_health_status', $status );
	}

	/**
	 * Get system info.
	 *
	 * @return array<string,mixed>
	 */
	public function getSystemInfo(): array {
		global $wpdb;

		return array(
			'php_version' => PHP_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
			'mysql_version' => $wpdb->db_version(),
			'memory_limit' => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size' => ini_get( 'post_max_size' ),
		);
	}

	/**
	 * Get assisted mode status.
	 *
	 * @return array<string,mixed>
	 */
	protected function getAssistedModeStatus(): array {
		$assisted_mode_service = $this->getAssistedModeService();
		
		if ( $assisted_mode_service ) {
			return array(
				'active' => $assisted_mode_service->isActive(),
				'reason' => $assisted_mode_service->getReason(),
				'label' => $assisted_mode_service->getReasonLabel(),
			);
		}
		
		return array(
			'active' => false,
			'reason' => '',
			'label' => '',
		);
	}

	/**
	 * Get queue status.
	 *
	 * @return array<string,mixed>
	 */
	protected function getQueueStatus(): array {
		$queue = $this->getQueue();
		
		if ( ! $queue || ! method_exists( $queue, 'get_status' ) ) {
			return array(
				'available' => false,
				'total' => 0,
				'pending' => 0,
				'processing' => 0,
				'completed' => 0,
				'failed' => 0,
			);
		}
		
		return $queue->get_status();
	}

	/**
	 * Get translations count.
	 *
	 * @return array<string,int>
	 */
	protected function getTranslationsCount(): array {
		global $wpdb;
		
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_fpml_is_translation' AND meta_value = '1'"
		);
		
		return array(
			'total' => $total,
		);
	}

	/**
	 * Get settings summary.
	 *
	 * @return array<string,mixed>
	 */
	protected function getSettingsSummary(): array {
		$settings = $this->getSettings();
		
		if ( ! $settings ) {
			return array();
		}
		
		return array(
			'auto_translate' => $settings->get( 'auto_translate', false ),
			'default_language' => $settings->get( 'default_language', 'it' ),
			'enabled_languages' => $settings->get( 'enabled_languages', array() ),
		);
	}

	/**
	 * Get AssistedModeService instance.
	 *
	 * @return AssistedModeService|null
	 */
	protected function getAssistedModeService(): ?AssistedModeService {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'service.assisted_mode' ) ) {
					return $container->get( 'service.assisted_mode' );
				}
			}
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\AssistedModeService' ) ) {
			return new AssistedModeService();
		}
		
		return null;
	}

	/**
	 * Get Queue instance.
	 *
	 * @return object|null
	 */
	protected function getQueue() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'queue' ) ) {
					return $container->get( 'queue' );
				}
			}
		}
		
		return Container::get( 'queue' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return object|null
	 */
	protected function getSettings() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'options' ) ) {
					return $container->get( 'options' );
				}
			}
		}
		
		return Container::get( 'settings' );
	}
}








