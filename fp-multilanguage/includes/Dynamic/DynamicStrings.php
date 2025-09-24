<?php
namespace FPMultilanguage\Dynamic;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;

class DynamicStrings {

	private TranslationService $translationService;

	private Settings $settings;

	private AdminNotices $notices;

	private Logger $logger;

	public function __construct( TranslationService $translationService, Settings $settings, AdminNotices $notices, Logger $logger ) {
		$this->translationService = $translationService;
		$this->settings           = $settings;
		$this->notices            = $notices;
		$this->logger             = $logger;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_fp_multilanguage_save_string', array( $this, 'handle_ajax_save' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_filter( 'gettext', array( $this, 'filter_gettext' ), 10, 3 );
		add_filter( 'ngettext', array( $this, 'filter_ngettext' ), 10, 5 );
		add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 10, 4 );
		add_filter( 'widget_title', array( $this, 'filter_generic_string' ) );
		add_filter( 'widget_text_content', array( $this, 'filter_generic_string' ) );
		add_filter( 'nav_menu_item_title', array( $this, 'filter_generic_string' ), 10, 4 );
		add_filter( 'theme_mod_custom_logo', array( $this, 'filter_generic_string' ) );
	}

	public function translate_dynamic_string( $value, $instance = null, $id = '' ) {
		unset( $instance, $id );

		return $this->filter_generic_string( $value );
	}

	public function register_assets(): void {
		wp_register_script(
			'fp-multilanguage-dynamic',
			FP_MULTILANGUAGE_URL . 'assets/js/dynamic-translations.js',
			array( 'jquery' ),
			FP_MULTILANGUAGE_VERSION,
			true
		);

		wp_register_script(
			'fp-multilanguage-frontend',
			FP_MULTILANGUAGE_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			FP_MULTILANGUAGE_VERSION,
			true
		);
	}

	public function enqueue_assets(): void {
		if ( ! wp_script_is( 'fp-multilanguage-dynamic', 'registered' ) ) {
			$this->register_assets();
		}

		$manualStrings = Settings::get_manual_strings();
		$language      = CurrentLanguage::resolve();

		wp_enqueue_script( 'fp-multilanguage-dynamic' );
		wp_localize_script(
			'fp-multilanguage-dynamic',
			'fpMultilanguageDynamic',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'fp_multilanguage_manual_string' ),
				'language'      => $language,
				'manualStrings' => $manualStrings,
				'restUrl'       => rest_url( 'fp-multilanguage/v1/strings' ),
			)
		);

		if ( ! wp_script_is( 'fp-multilanguage-frontend', 'enqueued' ) ) {
			wp_enqueue_script( 'fp-multilanguage-frontend' );
		}
	}

	public function handle_ajax_save(): void {
		check_ajax_referer( 'fp_multilanguage_manual_string', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$language = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value    = isset( $_POST['value'] ) ? wp_kses_post( wp_unslash( $_POST['value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $key === '' || $language === '' ) {
			wp_send_json_error( array( 'message' => 'invalid_parameters' ), 400 );
		}

		Settings::update_manual_string( $key, $language, $value );
		TranslationService::flush_cache();

		wp_send_json_success(
			array(
				'key'      => $key,
				'language' => $language,
				'value'    => $value,
			)
		);
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'fp-multilanguage/v1',
			'/strings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function () {
						return rest_ensure_response( Settings::get_manual_strings() );
					},
					'permission_callback' => array( $this, 'rest_permissions' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_update_strings' ),
					'permission_callback' => array( $this, 'rest_permissions' ),
				),
			)
		);
	}

	public function rest_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	public function rest_update_strings( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'invalid_body', __( 'Formato non valido.', 'fp-multilanguage' ), array( 'status' => 400 ) );
		}

		foreach ( $body as $key => $translations ) {
			if ( ! is_array( $translations ) ) {
				continue;
			}

			foreach ( $translations as $language => $value ) {
				Settings::update_manual_string( sanitize_text_field( $key ), sanitize_key( $language ), (string) $value );
			}
		}

		TranslationService::flush_cache();

		return rest_ensure_response( Settings::get_manual_strings() );
	}

	public function filter_gettext( string $translation, string $text, string $domain ): string {
		if ( $text === '' ) {
			return $translation;
		}

		$identifier = $this->build_identifier( '', $domain );
		$key        = $this->get_manual_key( $text, '', $domain );
		$this->store_string( $key, $text, $identifier );

		$language      = CurrentLanguage::resolve();
		$manualStrings = Settings::get_manual_strings();
		if ( $language !== '' && isset( $manualStrings[ $key ][ $language ] ) ) {
			return $manualStrings[ $key ][ $language ];
		}

		$forceTranslation = false;
		$serviceArgs      = array();
		if ( function_exists( 'apply_filters' ) ) {
			$payload = apply_filters(
				'fp_multilanguage_force_gettext_translation',
				array(
					'force'        => false,
					'service_args' => array(),
				),
				$translation,
				$text,
				$domain,
				$language,
				Settings::get_source_language(),
				array()
			);

			if ( is_array( $payload ) ) {
				$forceTranslation = ! empty( $payload['force'] );
				if ( isset( $payload['service_args'] ) && is_array( $payload['service_args'] ) ) {
					$serviceArgs = $payload['service_args'];
				}
			} else {
				$forceTranslation = (bool) $payload;
			}
		}

		if ( $translation !== $text && ! $forceTranslation ) {
			return $translation;
		}

		return $this->translate_string( $text, $identifier, $serviceArgs );
	}

	public function filter_gettext_with_context( string $translation, string $text, string $context, string $domain ): string {
		$identifier = $this->build_identifier( $context, $domain );

		if ( $translation !== $text ) {
			$language      = CurrentLanguage::resolve();
			$manualStrings = Settings::get_manual_strings();
			$key           = $this->get_manual_key( $text, $context, $domain );
			if ( $language !== '' && isset( $manualStrings[ $key ][ $language ] ) ) {
				return $manualStrings[ $key ][ $language ];
			}

			return $translation;
		}

		$key = $this->get_manual_key( $text, $context, $domain );
		$this->store_string( $key, $text, $identifier );

		return $this->translate_string( $text, $identifier );
	}


        public function filter_ngettext( string $translation, string $single, string $plural, int $number, string $domain ): string {
                $text = $number === 1 ? $single : $plural;

                if ( $translation !== $text ) {
                        $result = $this->filter_gettext( $translation, $text, $domain );
                        if ( $result !== $translation && $result !== $text ) {
                                return $result;
                        }

                        return $translation;
                }

                return $this->filter_gettext( $translation, $text, $domain );
        }

        public function filter_generic_string( $value, $item = null, $depth = null, $args = null ) {
                unset( $item, $depth, $args );

		if ( ! is_string( $value ) || trim( $value ) === '' ) {
			return $value;
		}

		return $this->translate_string( $value, 'generic' );
	}

	private function translate_string( string $text, string $identifier = '', array $args = array() ): string {
		$language = CurrentLanguage::resolve();
		$source   = Settings::get_source_language();

		if ( $language === '' || $language === $source ) {
			return $text;
		}

		$manualStrings = Settings::get_manual_strings();
		$key           = $this->get_manual_key( $text, $identifier );
		if ( isset( $manualStrings[ $key ][ $language ] ) ) {
			return $manualStrings[ $key ][ $language ];
		}

		$translated = $this->translationService->translate_text( $text, $source, $language, $args );
		if ( $translated !== '' ) {
			return $translated;
		}

		return $text;
	}

	private function get_manual_key( string $text, string $context = '', string $domain = '' ): string {
		if ( $domain !== '' ) {
			$context = $this->build_identifier( $context, $domain );
		}

		return hash( 'sha1', $context . '|' . $text );
	}

	private function build_identifier( string $context = '', string $domain = '' ): string {
		if ( $context !== '' && $domain !== '' ) {
			return $context . '|' . $domain;
		}

		if ( $context !== '' ) {
			return $context;
		}

		if ( $domain !== '' ) {
			return $domain;
		}

		return '';
}

	private function store_string( string $key, string $original, string $context ): void {
		global $wpdb;
		if ( isset( $wpdb ) && isset( $wpdb->prefix ) ) {
			$table = $wpdb->prefix . 'fp_multilanguage_strings';
			$data  = array(
				'string_key'   => $key,
				'context'      => $context,
				'original'     => $original,
				'translations' => wp_json_encode( Settings::get_manual_strings()[ $key ] ?? array() ),
				'updated_at'   => current_time( 'mysql', true ),
			);

			if ( $this->table_exists( $table ) ) {
				$wpdb->replace( $table, $data, array( '%s', '%s', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

				return;
			}
		}

		// Fallback: keep a simple cache in options for tests environments.
		$option = get_option( 'fp_multilanguage_strings', array() );
		if ( ! is_array( $option ) ) {
			$option = array();
		}

		$option[ $key ] = array(
			'context'    => $context,
			'original'   => $original,
			'updated_at' => time(),
		);

		update_option( 'fp_multilanguage_strings', $option );
	}

	private function table_exists( string $table ): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return false;
		}

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $exists !== null;
	}
}
