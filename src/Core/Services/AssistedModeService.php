<?php
/**
 * Assisted Mode Service.
 *
 * Centralizes all assisted mode detection and management logic.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing assisted mode detection and status.
 *
 * @since 1.0.0
 */
class AssistedModeService {
	/**
	 * Cached detection result.
	 *
	 * @var string|null
	 */
	protected static $cached_reason = null;

	/**
	 * Detect active multilingual plugins that require assisted mode.
	 *
	 * @return string Empty string when no external plugin is detected, otherwise the identifier.
	 */
	public function detect(): string {
		if ( null !== self::$cached_reason ) {
			return self::$cached_reason;
		}

		if ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) ) {
			self::$cached_reason = 'wpml';
			return self::$cached_reason;
		}

		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
			self::$cached_reason = 'polylang';
			return self::$cached_reason;
		}

		self::$cached_reason = '';
		return self::$cached_reason;
	}

	/**
	 * Check if assisted mode is active.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return ! empty( $this->detect() );
	}

	/**
	 * Get the reason identifier.
	 *
	 * @return string
	 */
	public function getReason(): string {
		return $this->detect();
	}

	/**
	 * Get a human readable label for the reason.
	 *
	 * @return string
	 */
	public function getReasonLabel(): string {
		$reason = $this->getReason();
		switch ( $reason ) {
			case 'wpml':
				return 'WPML';
			case 'polylang':
				return 'Polylang';
			default:
				return '';
		}
	}

	/**
	 * Check if a feature should be disabled in assisted mode.
	 *
	 * @param string $feature Feature name.
	 * @return bool
	 */
	public function shouldDisableFeature( string $feature ): bool {
		if ( ! $this->isActive() ) {
			return false;
		}

		// Features that should be disabled in assisted mode
		$disabled_features = array(
			'reindex',
			'auto_translation',
			'auto_duplication',
		);

		return in_array( $feature, $disabled_features, true );
	}

	/**
	 * Clear cached detection result.
	 *
	 * @return void
	 */
	public function clearCache(): void {
		self::$cached_reason = null;
	}
}








