<?php
namespace FPMultilanguage\Install;

use FPMultilanguage\Admin\Settings\Repository as SettingsRepository;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;

class UpgradeManager {
	private SettingsRepository $settings;

	private Logger $logger;

	public function __construct( SettingsRepository $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function run( ?string $fromVersion ): void {
		$this->settings->register_cache_hooks();

		$this->ensure_manual_strings_store();

		$this->clear_cached_configuration();

		$this->flush_runtime_caches();

		$this->log_completion( $fromVersion );
	}

	private function ensure_manual_strings_store(): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}

		if ( false === get_option( SettingsRepository::MANUAL_STRINGS_OPTION, false ) ) {
			update_option( SettingsRepository::MANUAL_STRINGS_OPTION, array() );
		}

		if ( false === get_option( SettingsRepository::MANUAL_STRINGS_FALLBACK_OPTION, false ) ) {
			update_option( SettingsRepository::MANUAL_STRINGS_FALLBACK_OPTION, array() );
		}
	}

	private function clear_cached_configuration(): void {
		$this->settings->clear_manual_strings_metadata_cache();
		$this->settings->clear_manual_strings_cache();
		$this->settings->clear_cache();
	}

	private function flush_runtime_caches(): void {
		TranslationService::flush_cache();
		CurrentLanguage::clear_cache();

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		if ( function_exists( 'opcache_reset' ) ) {
			opcache_reset();
		}
	}

	private function log_completion( ?string $fromVersion ): void {
		$context = array(
			'previous_version' => $fromVersion !== null && '' !== $fromVersion ? $fromVersion : null,
			'current_version'  => defined( 'FP_MULTILANGUAGE_VERSION' ) ? FP_MULTILANGUAGE_VERSION : null,
		);

		$this->logger->info( 'Upgrade routine completed.', $context );
	}
}
