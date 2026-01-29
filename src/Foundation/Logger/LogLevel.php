<?php
/**
 * Log Level Constants.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log level constants matching PSR-3.
 *
 * @since 1.0.0
 */
class LogLevel {
	const EMERGENCY = 'emergency';
	const ALERT     = 'alert';
	const CRITICAL  = 'critical';
	const ERROR     = 'error';
	const WARNING   = 'warning';
	const NOTICE    = 'notice';
	const INFO      = 'info';
	const DEBUG     = 'debug';

	/**
	 * Get numeric priority for level (higher = more important).
	 *
	 * @param string $level Log level.
	 * @return int Priority (0-7).
	 */
	public static function getPriority( string $level ): int {
		$priorities = array(
			self::DEBUG     => 0,
			self::INFO      => 1,
			self::NOTICE    => 2,
			self::WARNING   => 3,
			self::ERROR     => 4,
			self::CRITICAL  => 5,
			self::ALERT     => 6,
			self::EMERGENCY => 7,
		);

		return $priorities[ $level ] ?? 0;
	}

	/**
	 * Check if level should be logged based on minimum level.
	 *
	 * @param string $level     Level to check.
	 * @param string $min_level Minimum level.
	 * @return bool True if should log.
	 */
	public static function shouldLog( string $level, string $min_level ): bool {
		return self::getPriority( $level ) >= self::getPriority( $min_level );
	}
}













