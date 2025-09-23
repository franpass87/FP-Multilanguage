<?php
namespace FPMultilanguage;

use FPMultilanguage\Admin\Settings;

class CurrentLanguage {

	private static ?string $cached = null;

	public static function resolve(): string {
		if ( self::$cached !== null ) {
			return self::$cached;
		}

		$language = self::detect_from_query();
		$source   = Settings::get_source_language();

		if ( $language === '' ) {
			$language = self::detect_from_cookie();
		}

		if ( $language === '' && function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( $user && isset( $user->ID ) && $user->ID > 0 ) {
				$userLanguage = get_user_meta( $user->ID, 'fp_multilanguage_language', true );
				if ( is_string( $userLanguage ) && $userLanguage !== '' ) {
					$language = $userLanguage;
				}
			}
		}

		if ( $language === '' && function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
			if ( is_string( $locale ) && $locale !== '' ) {
				$language = substr( $locale, 0, 2 );
			}
		}

		if ( $language === '' && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uriLanguage = self::detect_from_path( (string) $_SERVER['REQUEST_URI'] );
			if ( $uriLanguage !== '' ) {
				$language = $uriLanguage;
			}
		}

		$language = strtolower( $language );

		if ( function_exists( 'apply_filters' ) ) {
			$language = (string) apply_filters(
				'fp_multilanguage_current_language',
				$language,
				array(
					'source' => $source,
				)
			);
		}

		if ( $language === '' ) {
			$language = $source;
		}

		self::$cached = strtolower( $language );

		return self::$cached;
	}

	public static function remember( string $language ): void {
		$language = strtolower( $language );
		if ( $language === '' ) {
			return;
		}

		$cookiePath   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$cookieDomain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		$secure       = function_exists( 'is_ssl' ) ? is_ssl() : false;
		$httpOnly     = true;
		$expire       = time() + ( defined( 'YEAR_IN_SECONDS' ) ? YEAR_IN_SECONDS : 31536000 );

		if ( function_exists( 'setcookie' ) ) {
			setcookie( 'fp_multilanguage_lang', $language, $expire, $cookiePath, $cookieDomain, $secure, $httpOnly );
		}

		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( $user && isset( $user->ID ) && $user->ID > 0 ) {
				update_user_meta( $user->ID, 'fp_multilanguage_language', $language );
			}
		}

		self::$cached = $language;
	}

	private static function detect_from_query(): string {
		if ( function_exists( 'get_query_var' ) ) {
			$queryVar = (string) get_query_var( 'fp_lang' );
			if ( $queryVar !== '' ) {
				return sanitize_key( $queryVar );
			}
		}

		if ( isset( $_GET['fp_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_key( (string) wp_unslash( $_GET['fp_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return '';
	}

	private static function detect_from_cookie(): string {
		if ( isset( $_COOKIE['fp_multilanguage_lang'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			return sanitize_key( (string) $_COOKIE['fp_multilanguage_lang'] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		}

		return '';
	}

	private static function detect_from_path( string $path ): string {
		$segments = array_filter( explode( '/', trim( $path, '/' ) ) );
		if ( empty( $segments ) ) {
			return '';
		}

		$first     = strtolower( (string) array_shift( $segments ) );
		$targets   = Settings::get_target_languages();
		$targets[] = Settings::get_source_language();

		if ( in_array( $first, $targets, true ) ) {
			return $first;
		}

		return '';
	}
}
