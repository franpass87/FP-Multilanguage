<?php
namespace FPMultilanguage\Services;

use WC_Logger;
use WP_CLI;

class Logger {

	public const LOG_STORE_OPTION = 'fp_multilanguage_logs';

	private const LOG_ENTRY_LIMIT = 200;

	private ?WC_Logger $woocommerceLogger = null;

	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}

	private function log( string $level, string $message, array $context = array() ): void {
			$formatted = $this->interpolate( $message, $context );

		if ( class_exists( WC_Logger::class ) && function_exists( 'wc_get_logger' ) ) {
			if ( ! $this->woocommerceLogger instanceof WC_Logger ) {
				$this->woocommerceLogger = wc_get_logger();
			}

			$this->woocommerceLogger->log( $level, $formatted, array( 'source' => 'fp-multilanguage' ) );
		} else {
			error_log( sprintf( '[fp-multilanguage:%s] %s', $level, $formatted ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( WP_CLI::class ) ) {
			switch ( $level ) {
				case 'error':
				case 'warning':
					WP_CLI::warning( $formatted );
					break;
				case 'debug':
					WP_CLI::debug( $formatted, 'fp-multilanguage' );
					break;
				default:
					WP_CLI::log( $formatted );
			}
		}

			$this->store_entry( $level, $formatted );
	}

	private function interpolate( string $message, array $context = array() ): string {
			$replace = array();
		foreach ( $context as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$replace[ '{' . $key . '}' ] = (string) $value;
		}

			return strtr( $message, $replace );
	}

	private function store_entry( string $level, string $message ): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
				return;
		}

			$entries = get_option( self::LOG_STORE_OPTION, array() );
		if ( ! is_array( $entries ) ) {
				$entries = array();
		}

			$entries[] = array(
				'timestamp' => time(),
				'level'     => $level,
				'message'   => $message,
			);

			$entries = array_slice( $entries, -self::LOG_ENTRY_LIMIT );

			update_option( self::LOG_STORE_OPTION, $entries );
	}

	public static function get_stored_entries(): array {
		if ( ! function_exists( 'get_option' ) ) {
				return array();
		}

			$entries = get_option( self::LOG_STORE_OPTION, array() );

			return is_array( $entries ) ? $entries : array();
	}

	public static function clear_stored_entries(): void {
		if ( ! function_exists( 'update_option' ) ) {
				return;
		}

			update_option( self::LOG_STORE_OPTION, array() );
	}
}
