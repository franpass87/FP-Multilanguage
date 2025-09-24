<?php
namespace FPMultilanguage;

use FPMultilanguage\Admin\Settings;

class CurrentLanguage {

	private static ?string $cached = null;

	public static function resolve(): string {
		if ( self::$cached !== null ) {
				return self::$cached;
		}

			$source = self::normalize_language_code( Settings::get_source_language() );

			$allowedLanguages = array_merge(
				array( $source ),
				Settings::get_target_languages()
			);
			$allowedLanguages = array_values(
				array_unique(
					array_filter(
						array_map( array( self::class, 'normalize_language_code' ), $allowedLanguages )
					)
				)
			);

			$language = self::match_allowed_language( self::detect_from_query(), $allowedLanguages );

		if ( '' === $language ) {
				$language = self::match_allowed_language( self::detect_from_cookie(), $allowedLanguages );
		}

		if ( '' === $language && function_exists( 'wp_get_current_user' ) ) {
				$user = wp_get_current_user();
			if ( $user && isset( $user->ID ) && $user->ID > 0 ) {
					$userLanguage = get_user_meta( $user->ID, 'fp_multilanguage_language', true );
				if ( is_string( $userLanguage ) ) {
					$language = self::match_allowed_language( $userLanguage, $allowedLanguages );
				}
			}
		}

		if ( '' === $language && function_exists( 'determine_locale' ) ) {
				$locale = determine_locale();
			if ( is_string( $locale ) && $locale !== '' ) {
					$candidate = self::normalize_language_code( $locale );
					$language  = self::match_allowed_language( $candidate, $allowedLanguages );

				if ( '' === $language ) {
					$language = $candidate;
				}
			}
		}

		if ( '' === $language && isset( $_SERVER['REQUEST_URI'] ) ) {
				$candidate = self::detect_from_path( (string) $_SERVER['REQUEST_URI'] );
			if ( $candidate !== '' ) {
					$language = self::match_allowed_language( $candidate, $allowedLanguages );

				if ( '' === $language ) {
					$language = $candidate;
				}
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
				$language = (string) apply_filters(
					'fp_multilanguage_current_language',
					$language,
					array(
						'source' => $source,
					)
				);
		}

			$language = self::match_allowed_language( $language, $allowedLanguages );

		if ( '' === $language ) {
				$language = $source;
		}

			self::$cached = $language;

			return self::$cached;
	}

	public static function remember( string $language ): void {
			$language = self::normalize_language_code( $language );
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

			$_COOKIE['fp_multilanguage_lang'] = $language; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE

		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( $user && isset( $user->ID ) && $user->ID > 0 ) {
					update_user_meta( $user->ID, 'fp_multilanguage_language', $language );
			}
		}

			self::$cached = $language;
	}

	public static function clear_cache(): void {
			self::$cached = null;
	}

	private static function detect_from_query(): string {
		if ( function_exists( 'get_query_var' ) ) {
				$queryVar = (string) get_query_var( 'fp_lang' );
			if ( $queryVar !== '' ) {
				return self::normalize_language_code( $queryVar );
			}
		}

		if ( isset( $_GET['fp_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return self::normalize_language_code( (string) wp_unslash( $_GET['fp_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

			return '';
	}

	private static function detect_from_cookie(): string {
		if ( isset( $_COOKIE['fp_multilanguage_lang'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
				return self::normalize_language_code( (string) $_COOKIE['fp_multilanguage_lang'] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		}

			return '';
	}

	private static function detect_from_path( string $path ): string {
			$segments = array_filter( explode( '/', trim( $path, '/' ) ) );
		if ( empty( $segments ) ) {
				return '';
		}

			$first     = self::normalize_language_code( (string) array_shift( $segments ) );
			$targets   = Settings::get_target_languages();
			$targets[] = Settings::get_source_language();

			$targets = array_map( array( self::class, 'normalize_language_code' ), $targets );

			$targets = array_filter( $targets );

		if ( in_array( $first, $targets, true ) ) {
				return $first;
		}

			return '';
	}

	private static function normalize_language_code( string $value ): string {
			$value = strtolower( trim( $value ) );
			$value = str_replace( array( ' ', '_' ), '-', $value );

			$value = preg_replace( '/[^a-z0-9-]/', '', $value );
		if ( null === $value ) {
				return '';
		}

			$value = preg_replace( '/-+/', '-', $value );
		if ( null === $value ) {
				return '';
		}

			return trim( $value, '-' );
	}

	private static function match_allowed_language( string $language, array $allowed ): string {
			$language = self::normalize_language_code( $language );
		if ( '' === $language ) {
				return '';
		}

		if ( in_array( $language, $allowed, true ) ) {
				return $language;
		}

		if ( strpos( $language, '-' ) !== false ) {
				$base = strstr( $language, '-', true );
			if ( false !== $base ) {
					$base = self::normalize_language_code( $base );
				if ( in_array( $base, $allowed, true ) ) {
					return $base;
				}
			}
		}

			return '';
	}
}
