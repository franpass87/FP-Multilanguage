<?php
namespace FPMultilanguage\Services;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Services\Providers\TranslationProviderInterface;

class TranslationService {

	public const CACHE_GROUP = 'fp_multilanguage_translations';

	private const CACHE_VERSION_OPTION = 'fp_multilanguage_cache_version';

	private const QUOTA_OPTION = 'fp_multilanguage_quota';

	/**
	 * @var array<string, TranslationProviderInterface>
	 */
	private array $providers = array();

	private Logger $logger;

	private AdminNotices $notices;

	private Settings $settings;

	/**
	 * @var array<string, string>
	 */
	private static array $runtimeCache = array();

	/**
	 * @var array<string, array{failures:int,next_retry:int}>
	 */
	private array $backoff = array();

	public function __construct( Logger $logger, AdminNotices $notices, Settings $settings, array $providers = array() ) {
		$this->logger   = $logger;
		$this->notices  = $notices;
		$this->settings = $settings;

		foreach ( $providers as $provider ) {
			if ( $provider instanceof TranslationProviderInterface ) {
				$this->providers[ $provider->get_name() ] = $provider;
			}
		}
	}

	public function register(): void {
		add_filter(
			'fp_multilanguage_providers',
			function ( array $providers ): array {
				return array_unique( array_merge( array_keys( $this->providers ), $providers ) );
			}
		);
	}

	public function set_provider( TranslationProviderInterface $provider ): void {
		$this->providers[ $provider->get_name() ] = $provider;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	public function translate_text( string $text, string $source, string $target, array $options = array() ): string {
		$text = (string) $text;
		if ( $text === '' || $source === $target ) {
			return $text;
		}

		$options  = $this->normalize_options( $options );
		$cacheKey = $this->get_cache_key( $text, $source, $target, $options );
		$cached   = $this->get_cache( $cacheKey );
		if ( $cached !== null ) {
			return $cached;
		}

		$manual = $this->lookup_manual_translation( $text, $target );
		if ( $manual !== null ) {
			$this->set_cache( $cacheKey, $manual );

			return $manual;
		}

		$providers = $this->get_provider_sequence();
		$this->logger->debug(
			'Translating text',
			array(
				'providers' => $providers,
				'source'    => $source,
				'target'    => $target,
			)
		);

		foreach ( $providers as $providerName ) {
			$provider = $this->providers[ $providerName ] ?? null;
			if ( ! $provider instanceof TranslationProviderInterface ) {
				continue;
			}

			if ( $this->is_rate_limited( $providerName ) ) {
				$this->logger->warning( 'Provider rate limited', array( 'provider' => $providerName ) );
				continue;
			}

			$response = $this->attempt_provider( $providerName, $provider, $text, $source, $target, $options );
			if ( $response instanceof TranslationResponse && $response->get_text() !== '' ) {
				$translation = $response->get_text();
				if ( $response->is_cacheable() ) {
					$this->set_cache( $cacheKey, $translation );
				}

				$this->register_usage( $providerName, $target, $text );
				$this->reset_backoff( $providerName );

				return $translation;
			}
		}

		$this->notices->add_error( __( 'Traduzione non disponibile. Viene mostrato il contenuto originale.', 'fp-multilanguage' ) );
		$this->logger->warning( 'All providers failed, returning fallback text.', array( 'target' => $target ) );

		return $this->apply_fallback( $text, $target, $options );
	}

	public static function flush_cache(): void {
		if ( function_exists( 'update_option' ) ) {
				$version = (int) get_option( self::CACHE_VERSION_OPTION, 1 );
				update_option( self::CACHE_VERSION_OPTION, $version + 1 );
		}

			self::$runtimeCache = array();
	}

		/**
		 * @return array<string, array<string, array{requests:int,characters:int,updated_at:int}>>
		 */
	public static function get_usage_stats(): array {
			$stored = get_option( self::QUOTA_OPTION, array() );

			return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function normalize_options( array $options ): array {
		$normalized            = $options;
		$normalized['format']  = strtolower( (string) ( $normalized['format'] ?? 'text' ) ) === 'html' ? 'html' : 'text';
		$normalized['timeout'] = isset( $normalized['timeout'] ) ? max( 5, (int) $normalized['timeout'] ) : 20;

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function get_cache_key( string $text, string $source, string $target, array $options ): string {
		$version = $this->get_cache_version();

		return md5( $version . '|' . $source . '|' . $target . '|' . wp_json_encode( $options ) . '|' . $text );
	}

	private function get_cache_version(): string {
		if ( ! function_exists( 'get_option' ) ) {
			return '1';
		}

		return (string) get_option( self::CACHE_VERSION_OPTION, '1' );
	}

	private function get_cache( string $key ): ?string {
		if ( isset( self::$runtimeCache[ $key ] ) ) {
			return self::$runtimeCache[ $key ];
		}

		if ( function_exists( 'wp_cache_get' ) ) {
			$cached = wp_cache_get( $key, self::CACHE_GROUP );
			if ( $cached !== false ) {
				self::$runtimeCache[ $key ] = $cached;

				return (string) $cached;
			}
		}

		if ( function_exists( 'get_transient' ) ) {
			$transient = get_transient( self::CACHE_GROUP . '_' . $key );
			if ( $transient !== false ) {
				self::$runtimeCache[ $key ] = $transient;

				return (string) $transient;
			}
		}

		return null;
	}

	private function set_cache( string $key, string $value ): void {
		self::$runtimeCache[ $key ] = $value;

		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $key, $value, self::CACHE_GROUP, DAY_IN_SECONDS );
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::CACHE_GROUP . '_' . $key, $value, DAY_IN_SECONDS );
		}
	}

	private function lookup_manual_translation( string $text, string $target ): ?string {
		$manualStrings = Settings::get_manual_strings();
		$key           = hash( 'sha1', $text );
		if ( isset( $manualStrings[ $key ][ $target ] ) ) {
			return $manualStrings[ $key ][ $target ];
		}

		return null;
	}

	private function apply_fallback( string $text, string $target, array $options ): string {
		$fallbackLanguage = Settings::get_fallback_language();
		if ( $fallbackLanguage === $target ) {
			return $text;
		}

		$manualStrings = Settings::get_manual_strings();
		$key           = hash( 'sha1', $text );
		if ( isset( $manualStrings[ $key ][ $fallbackLanguage ] ) ) {
			return $manualStrings[ $key ][ $fallbackLanguage ];
		}

		if ( function_exists( 'apply_filters' ) ) {
			$fallback = apply_filters( 'fp_multilanguage_translation_fallback', $text, $target, $options );
			if ( is_string( $fallback ) && $fallback !== '' ) {
				return $fallback;
			}
		}

		return $text;
	}

	private function get_provider_sequence(): array {
		$enabled = Settings::get_enabled_providers();
		if ( function_exists( 'apply_filters' ) ) {
			$enabled = (array) apply_filters( 'fp_multilanguage_provider_sequence', $enabled );
		}

		return $enabled;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function attempt_provider( string $name, TranslationProviderInterface $provider, string $text, string $source, string $target, array $options ): ?TranslationResponse {
		$providerOptions = Settings::get_provider_settings( $name );
		$payloadOptions  = array_merge( $providerOptions, $options );

		$attempts    = 0;
		$maxAttempts = 3;
		$delay       = 1;

		while ( $attempts < $maxAttempts ) {
			++$attempts;
			$payloadOptions['attempt'] = $attempts;

			$response = $provider->translate( $text, $source, $target, $payloadOptions );
			if ( $response instanceof TranslationResponse && $response->get_text() !== '' ) {
				return $response;
			}

			$this->logger->warning(
				'Provider attempt failed',
				array(
					'provider' => $name,
					'attempt'  => $attempts,
				)
			);

			if ( $attempts < $maxAttempts ) {
				$delay = min( $delay * 2, 8 );
				usleep( $delay * 100000 ); // 0.1s, 0.2s, ...
			}
		}

		$this->register_failure( $name );

		return null;
	}

	private function register_failure( string $provider ): void {
		if ( ! isset( $this->backoff[ $provider ] ) ) {
			$this->backoff[ $provider ] = array(
				'failures'   => 0,
				'next_retry' => time(),
			);
		}

		++$this->backoff[ $provider ]['failures'];
		$delay                                    = min( 3600, pow( 2, $this->backoff[ $provider ]['failures'] ) * 60 );
		$this->backoff[ $provider ]['next_retry'] = time() + (int) $delay;
	}

	private function reset_backoff( string $provider ): void {
		unset( $this->backoff[ $provider ] );
	}

	private function is_rate_limited( string $provider ): bool {
		if ( isset( $this->backoff[ $provider ] ) && $this->backoff[ $provider ]['next_retry'] > time() ) {
			return true;
		}

		$quota         = $this->get_quota();
		$providerQuota = $quota[ $provider ] ?? array();
		$limits        = apply_filters(
			'fp_multilanguage_quota_limits',
			array(
				'requests'   => 1000,
				'characters' => 500000,
			),
			$provider
		);

		$totals = array(
			'requests'   => 0,
			'characters' => 0,
		);
		foreach ( $providerQuota as $language => $data ) {
			unset( $language );
			$totals['requests']   += (int) ( $data['requests'] ?? 0 );
			$totals['characters'] += (int) ( $data['characters'] ?? 0 );
		}

		if ( $totals['requests'] >= $limits['requests'] ) {
			return true;
		}

		if ( $totals['characters'] >= $limits['characters'] ) {
			return true;
		}

		return false;
	}

	private function register_usage( string $provider, string $language, string $text ): void {
		$length = $this->get_text_length( $text );
		$quota  = $this->get_quota();

		if ( ! isset( $quota[ $provider ] ) ) {
			$quota[ $provider ] = array();
		}

		if ( ! isset( $quota[ $provider ][ $language ] ) ) {
			$quota[ $provider ][ $language ] = array(
				'requests'   => 0,
				'characters' => 0,
				'updated_at' => time(),
			);
		}

		++$quota[ $provider ][ $language ]['requests'];
		$quota[ $provider ][ $language ]['characters'] += $length;
		$quota[ $provider ][ $language ]['updated_at']  = time();

		update_option( self::QUOTA_OPTION, $quota );
	}

		/**
		 * @return array<string, array<string, array{requests:int,characters:int,updated_at:int}>>
		 */
	private function get_quota(): array {
			$stored = self::get_usage_stats();

			return $stored;
	}

	protected function get_text_length( string $text ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text );
		}

		return strlen( $text );
	}
}
