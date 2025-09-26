<?php
namespace FPMultilanguage\Admin;

use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Settings {

	public const OPTION_NAME = 'fp_multilanguage_options';

	public const MANUAL_STRINGS_OPTION = 'fp_multilanguage_manual_strings';

	private const NONCE_ACTION = 'fp_multilanguage_settings';

	private const REST_NAMESPACE = 'fp-multilanguage/v1';

	private Logger $logger;

	private AdminNotices $notices;

	private static ?array $cachedOptions = null;

	private static array $defaults = array(
		'source_language'   => 'en',
		'fallback_language' => 'en',
                'target_languages'  => array( 'it' ),
                'post_types'        => array( 'post', 'page' ),
                'taxonomies'        => array( 'category', 'post_tag' ),
                'custom_fields'     => array(),
		'providers'         => array(
			'google' => array(
				'enabled'              => false,
				'api_key'              => '',
				'timeout'              => 20,
				'glossary_id'          => '',
				'glossary_ignore_case' => false,
			),
			'deepl'  => array(
				'enabled'     => false,
				'api_key'     => '',
				'endpoint'    => 'https://api.deepl.com/v2/translate',
				'glossary_id' => '',
				'formality'   => 'default',
			),
		),
		'auto_translate'    => true,
		'seo'               => array(
			'hreflang'   => true,
			'canonical'  => true,
			'open_graph' => true,
		),
		'quote_tracking'    => array(),
	);

	public function __construct( Logger $logger, AdminNotices $notices ) {
			$this->logger  = $logger;
			$this->notices = $notices;

			self::clear_cache();

		if ( function_exists( 'add_action' ) ) {
				add_action( 'update_option_' . self::OPTION_NAME, array( __CLASS__, 'clear_cache' ), 10, 0 );
				add_action( 'add_option_' . self::OPTION_NAME, array( __CLASS__, 'clear_cache' ), 10, 0 );
				add_action( 'delete_option_' . self::OPTION_NAME, array( __CLASS__, 'clear_cache' ), 10, 0 );
		}
	}

	public static function bootstrap_defaults(): void {
		if ( ! function_exists( 'get_option' ) ) {
				return;
		}

			$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) || empty( $options ) ) {
				$options = self::$defaults;
		} else {
				$options = wp_parse_args( $options, self::$defaults );
		}

			update_option( self::OPTION_NAME, $options );

			self::set_cached_options( $options );

		if ( false === get_option( self::MANUAL_STRINGS_OPTION, false ) ) {
				update_option( self::MANUAL_STRINGS_OPTION, array() );
		}
	}

        public function register(): void {
                add_action( 'admin_menu', array( $this, 'register_menu' ) );
                add_action( 'admin_init', array( $this, 'register_settings' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
                add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
                add_action( 'admin_post_fp_multilanguage_save_strings', array( $this, 'handle_strings_save' ) );
        }

        public function register_menu(): void {
                add_options_page(
                        __( 'FP Multilanguage', 'fp-multilanguage' ),
                        __( 'FP Multilanguage', 'fp-multilanguage' ),
                        'manage_options',
                        'fp-multilanguage-settings',
                        array( $this, 'render_page' )
                );

                add_submenu_page(
                        'options-general.php',
                        __( 'Configurazione guidata FP Multilanguage', 'fp-multilanguage' ),
                        __( 'Configurazione guidata', 'fp-multilanguage' ),
                        'manage_options',
                        'fp-multilanguage-onboarding',
                        array( $this, 'render_onboarding_page' )
                );

                add_submenu_page(
                        'options-general.php',
                        __( 'Registro FP Multilanguage', 'fp-multilanguage' ),
                        __( 'Registro FP Multilanguage', 'fp-multilanguage' ),
                        'manage_options',
                        'fp-multilanguage-log',
                        array( $this, 'render_log_page' )
                );

                add_submenu_page(
                        'options-general.php',
                        __( 'Stringhe dinamiche FP Multilanguage', 'fp-multilanguage' ),
                        __( 'Stringhe FP Multilanguage', 'fp-multilanguage' ),
                        'manage_options',
                        'fp-multilanguage-strings',
                        array( $this, 'render_strings_page' )
                );
        }

	public function register_settings(): void {
		register_setting(
			'fp_multilanguage',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::$defaults,
			)
		);

		add_settings_section( 'fp_multilanguage_general', __( 'Generale', 'fp-multilanguage' ), '__return_false', 'fp-multilanguage-settings' );
		add_settings_field(
			'fp_multilanguage_source_language',
			__( 'Lingua sorgente', 'fp-multilanguage' ),
			array( $this, 'render_source_field' ),
			'fp-multilanguage-settings',
			'fp_multilanguage_general'
		);

		add_settings_field(
			'fp_multilanguage_target_languages',
			__( 'Lingue di destinazione', 'fp-multilanguage' ),
			array( $this, 'render_target_field' ),
			'fp-multilanguage-settings',
			'fp_multilanguage_general'
		);

		add_settings_field(
			'fp_multilanguage_fallback_language',
			__( 'Lingua di fallback', 'fp-multilanguage' ),
			array( $this, 'render_fallback_field' ),
			'fp-multilanguage-settings',
			'fp_multilanguage_general'
		);

                add_settings_field(
                        'fp_multilanguage_auto_translate',
                        __( 'Traduzione automatica', 'fp-multilanguage' ),
                        array( $this, 'render_auto_translate_field' ),
                        'fp-multilanguage-settings',
                        'fp_multilanguage_general'
                );

                add_settings_field(
                        'fp_multilanguage_post_types',
                        __( 'Tipi di contenuto', 'fp-multilanguage' ),
                        array( $this, 'render_post_types_field' ),
                        'fp-multilanguage-settings',
                        'fp_multilanguage_general'
                );

                add_settings_field(
                        'fp_multilanguage_taxonomies',
                        __( 'Tassonomie', 'fp-multilanguage' ),
                        array( $this, 'render_taxonomies_field' ),
                        'fp-multilanguage-settings',
                        'fp_multilanguage_general'
                );

                add_settings_field(
                        'fp_multilanguage_custom_fields',
                        __( 'Campi personalizzati', 'fp-multilanguage' ),
                        array( $this, 'render_custom_fields_field' ),
                        'fp-multilanguage-settings',
                        'fp_multilanguage_general'
                );

		add_settings_section( 'fp_multilanguage_providers', __( 'Provider', 'fp-multilanguage' ), '__return_false', 'fp-multilanguage-settings' );
		add_settings_field(
			'fp_multilanguage_google_api_key',
			__( 'API Key Google', 'fp-multilanguage' ),
			array( $this, 'render_google_field' ),
			'fp-multilanguage-settings',
			'fp_multilanguage_providers'
		);

		add_settings_field(
			'fp_multilanguage_deepl_api_key',
			__( 'API Key DeepL', 'fp-multilanguage' ),
			array( $this, 'render_deepl_field' ),
			'fp-multilanguage-settings',
			'fp_multilanguage_providers'
		);

		add_settings_section( 'fp_multilanguage_seo', __( 'SEO', 'fp-multilanguage' ), '__return_false', 'fp-multilanguage-settings' );
		add_settings_field(
			'fp_multilanguage_seo_options',
			__( 'Opzioni SEO', 'fp-multilanguage' ),
			array( $this, 'render_seo_field' ),
			'fp-multilanguage-settings',
			'fp_multilanguage_seo'
		);
	}

        public function register_rest_routes(): void {
                register_rest_route(
                        self::REST_NAMESPACE,
                        '/settings',
                        array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_get_settings' ),
					'permission_callback' => array( $this, 'rest_permissions' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_update_settings' ),
					'permission_callback' => array( $this, 'rest_permissions' ),
                                ),
                        )
                );

                register_rest_route(
                        self::REST_NAMESPACE,
                        '/providers/test',
                        array(
                                array(
                                        'methods'             => 'POST',
                                        'callback'            => array( $this, 'rest_test_provider' ),
                                        'permission_callback' => array( $this, 'rest_permissions' ),
                                ),
                        )
                );
        }

	public function rest_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	public function rest_get_settings( WP_REST_Request $request ): WP_REST_Response {
			unset( $request );

			return rest_ensure_response( $this->get_options() );
	}

        public function rest_update_settings( WP_REST_Request $request ) {
                if ( ! $this->verify_rest_nonce( $request ) ) {
                        $message = __( 'Nonce di sicurezza non valido.', 'fp-multilanguage' );

                        $this->logger->warning( $message );
			$this->notices->add_error( $message );

			return new WP_Error( 'invalid_nonce', $message, array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$message = __( 'Payload non valido', 'fp-multilanguage' );
			$this->logger->error( $message );
			$this->notices->add_error( $message );

			return new WP_Error( 'invalid_payload', $message, array( 'status' => 400 ) );
		}

		$options = $this->sanitize( $params );
		update_option( self::OPTION_NAME, $options );
		TranslationService::flush_cache();

		$options = $this->get_options();
		$this->logger->info( 'Settings updated via REST API.' );
		$this->notices->add_notice( __( 'Impostazioni aggiornate correttamente.', 'fp-multilanguage' ) );

                return rest_ensure_response( $options );
        }


        public function rest_test_provider( WP_REST_Request $request ) {
                if ( ! $this->verify_rest_nonce( $request ) ) {
                        $message = __( 'Nonce di sicurezza non valido.', 'fp-multilanguage' );

                        $this->logger->warning( 'Provider test blocked by invalid nonce.' );

                        return new WP_Error( 'invalid_nonce', $message, array( 'status' => 403 ) );
                }

                $params   = $request->get_json_params();
                $provider = is_array( $params ) ? sanitize_key( (string) ( $params['provider'] ?? '' ) ) : '';
                $options  = is_array( $params['options'] ?? null ) ? (array) $params['options'] : array();

                if ( '' === $provider ) {
                        $message = __( 'Provider non valido.', 'fp-multilanguage' );

                        return new WP_Error( 'invalid_provider', $message, array( 'status' => 400 ) );
                }

                $sanitized = $this->sanitize_provider_options( $provider, $options );
                if ( is_wp_error( $sanitized ) ) {
                        return $sanitized;
                }

                $result = $this->test_provider_credentials( $provider, $sanitized );

                return rest_ensure_response( $result );
        }


	private function verify_rest_nonce( WP_REST_Request $request ): bool {
		$nonce = '';

		if ( method_exists( $request, 'get_header' ) ) {
			$headerNonce = $request->get_header( 'X-WP-Nonce' );
			if ( is_string( $headerNonce ) ) {
				$nonce = $headerNonce;
			}
		}

		if ( '' === $nonce && method_exists( $request, 'get_param' ) ) {
			$paramNonce = $request->get_param( '_wpnonce' );
			if ( is_string( $paramNonce ) ) {
				$nonce = $paramNonce;
			}
		}

		if ( '' === $nonce ) {
			return false;
		}

		if ( function_exists( 'wp_unslash' ) ) {
			$nonce = wp_unslash( $nonce );
		}

		if ( function_exists( 'sanitize_text_field' ) ) {
			$nonce = sanitize_text_field( $nonce );
		}

		if ( '' === $nonce ) {
			return false;
		}

		if ( function_exists( 'wp_verify_nonce' ) ) {
			return false !== wp_verify_nonce( $nonce, self::NONCE_ACTION );
		}

		return true;
	}
        public function enqueue_assets( string $hook ): void {
                $allowedHooks = array(
                        'settings_page_fp-multilanguage-settings',
                        'settings_page_fp-multilanguage-strings',
                        'settings_page_fp-multilanguage-onboarding',
                );

                if ( ! in_array( $hook, $allowedHooks, true ) ) {
                        return;
                }

                wp_enqueue_script(
                        'fp-multilanguage-admin',
                        FP_MULTILANGUAGE_URL . 'assets/js/admin.js',
                        array( 'wp-element' ),
                        FP_MULTILANGUAGE_VERSION,
                        true
                );

                if ( in_array( $hook, array( 'settings_page_fp-multilanguage-settings', 'settings_page_fp-multilanguage-onboarding' ), true ) ) {
                        wp_localize_script(
                                'fp-multilanguage-admin',
                                'fpMultilanguageSettings',
                                array(
                                        'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
                                        'options'    => $this->get_options(),
                                        'restUrl'    => rest_url( self::REST_NAMESPACE . '/settings' ),
                                        'testUrl'    => rest_url( self::REST_NAMESPACE . '/providers/test' ),
                                        'optionName' => self::OPTION_NAME,
                                        'i18n'       => array(
                                                'testing'      => __( 'Verifica in corso…', 'fp-multilanguage' ),
                                                'networkError' => __( 'Errore di rete durante la verifica.', 'fp-multilanguage' ),
                                                'unknownError' => __( 'La verifica non è riuscita.', 'fp-multilanguage' ),
                                        ),
                                )
                        );
                }

                if ( 'settings_page_fp-multilanguage-onboarding' === $hook ) {
                        wp_localize_script(
                                'fp-multilanguage-admin',
                                'fpMultilanguageOnboarding',
                                array(
                                        'i18n' => array(
                                                'emptyValue'         => __( 'Non impostato', 'fp-multilanguage' ),
                                                'autoEnabled'        => __( 'Attivo', 'fp-multilanguage' ),
                                                'autoDisabled'       => __( 'Disattivo', 'fp-multilanguage' ),
                                                'providersDisabled'  => __( 'Nessun provider attivo', 'fp-multilanguage' ),
                                                'validationProviders' => __( 'Prima di proseguire testa le credenziali per: %s', 'fp-multilanguage' ),
                                        ),
                                )
                        );
                }

                if ( 'settings_page_fp-multilanguage-strings' === $hook ) {
                        wp_localize_script(
                                'fp-multilanguage-admin',
                                'fpMultilanguageStrings',
                                array(
                                        'noResults' => __( 'Nessuna stringa corrisponde alla ricerca.', 'fp-multilanguage' ),
                                )
                        );
                }
        }

        public function render_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

		$tabs = array(
			'general'   => __( 'Generale', 'fp-multilanguage' ),
			'providers' => __( 'Provider', 'fp-multilanguage' ),
			'seo'       => __( 'SEO', 'fp-multilanguage' ),
			'quote'     => __( 'Quote', 'fp-multilanguage' ),
		);

		$active = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'general';
		}

		?>
		<div class="wrap fp-multilanguage-settings">
			<h1><?php esc_html_e( 'FP Multilanguage', 'fp-multilanguage' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab ) ); ?>" class="nav-tab <?php echo $tab === $active ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<div class="fp-multilanguage-tab" data-tab="<?php echo esc_attr( $active ); ?>">
				<?php if ( $active === 'quote' ) : ?>
					<?php $this->render_quote_tab(); ?>
				<?php else : ?>
					<form action="options.php" method="post">
						<?php
						settings_fields( 'fp_multilanguage' );
						do_settings_sections( 'fp-multilanguage-settings' );
						submit_button();
						?>
					</form>
				<?php endif; ?>
			</div>
			<div id="fp-multilanguage-settings-app"></div>
		</div>
		<?php
	}

	public function render_source_field(): void {
		$options = $this->get_options();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_language]" value="<?php echo esc_attr( $options['source_language'] ); ?>" maxlength="10">
		<p class="description"><?php esc_html_e( 'Codice lingua predefinito (es. en).', 'fp-multilanguage' ); ?></p>
		<?php
	}

	public function render_target_field(): void {
		$options = $this->get_options();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[target_languages]" value="<?php echo esc_attr( implode( ', ', (array) $options['target_languages'] ) ); ?>">
		<p class="description"><?php esc_html_e( 'Inserisci le lingue di destinazione separate da virgola.', 'fp-multilanguage' ); ?></p>
		<?php
	}

	public function render_fallback_field(): void {
		$options = $this->get_options();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fallback_language]" value="<?php echo esc_attr( $options['fallback_language'] ); ?>" maxlength="10">
		<p class="description"><?php esc_html_e( 'Lingua utilizzata come fallback automatico.', 'fp-multilanguage' ); ?></p>
		<?php
	}

        public function render_auto_translate_field(): void {
                $options = $this->get_options();
                ?>
                <label>
                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[auto_translate]" value="1" <?php checked( ! empty( $options['auto_translate'] ) ); ?>>
                        <?php esc_html_e( 'Traduci automaticamente i contenuti al salvataggio.', 'fp-multilanguage' ); ?>
                </label>
                <?php
        }

        public function render_log_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                if ( isset( $_POST['fp_multilanguage_clear_logs'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                        check_admin_referer( 'fp_multilanguage_clear_logs' );

                        Logger::clear_stored_entries();
                        add_settings_error(
                                'fp_multilanguage_logs',
                                'fp_multilanguage_logs_cleared',
                                __( 'Registro svuotato con successo.', 'fp-multilanguage' ),
                                'updated'
                        );
                }

                $entries = Logger::get_stored_entries();
                $entries = array_map(
                        static function ( array $entry ): array {
                                $entry['timestamp'] = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;

                                return $entry;
                        },
                        $entries
                );

                usort(
                        $entries,
                        static function ( array $a, array $b ): int {
                                return $b['timestamp'] <=> $a['timestamp'];
                        }
                );

                $dateFormat = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );

                echo '<div class="wrap fp-multilanguage-log">';
                echo '<h1>' . esc_html__( 'Registro FP Multilanguage', 'fp-multilanguage' ) . '</h1>';
                settings_errors( 'fp_multilanguage_logs' );

                echo '<form method="post" style="margin-bottom:1em;">';
                wp_nonce_field( 'fp_multilanguage_clear_logs' );
                echo '<input type="hidden" name="fp_multilanguage_clear_logs" value="1" />';
                submit_button( __( 'Svuota registro', 'fp-multilanguage' ), 'delete', 'submit', false );
                echo '</form>';

                if ( empty( $entries ) ) {
                        echo '<p>' . esc_html__( 'Nessun evento registrato al momento.', 'fp-multilanguage' ) . '</p>';
                        echo '</div>';

                        return;
                }

                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__( 'Data', 'fp-multilanguage' ) . '</th>';
                echo '<th>' . esc_html__( 'Livello', 'fp-multilanguage' ) . '</th>';
                echo '<th>' . esc_html__( 'Messaggio', 'fp-multilanguage' ) . '</th>';
                echo '</tr></thead>';
                echo '<tbody>';

                foreach ( $entries as $entry ) {
                        $timestamp = (int) ( $entry['timestamp'] ?? 0 );
                        $level     = isset( $entry['level'] ) ? (string) $entry['level'] : '';
                        $message   = isset( $entry['message'] ) ? (string) $entry['message'] : '';

                        echo '<tr>';
                        echo '<td>' . esc_html( $timestamp > 0 ? date_i18n( $dateFormat, $timestamp ) : '-' ) . '</td>';
                        echo '<td>' . esc_html( strtoupper( $level ) ) . '</td>';
                        echo '<td style="word-break:break-word;">' . esc_html( $message ) . '</td>';
                        echo '</tr>';
                }

                echo '</tbody></table>';
                echo '</div>';
        }

        public function render_strings_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                $strings   = self::get_manual_strings_catalog();
                $languages = $this->get_manual_string_languages( $strings );

                $updatedFlag = isset( $_GET['strings-updated'] ) ? sanitize_key( (string) $_GET['strings-updated'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $showSuccess = '1' === $updatedFlag;

                echo '<div class="wrap fp-multilanguage-strings">';
                echo '<h1>' . esc_html__( 'Stringhe dinamiche', 'fp-multilanguage' ) . '</h1>';

                if ( $showSuccess ) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Traduzioni manuali aggiornate.', 'fp-multilanguage' ) . '</p></div>';
                }

                if ( empty( $strings ) ) {
                        echo '<p>' . esc_html__( 'Non sono ancora state intercettate stringhe dinamiche. Aggiungi l\'attributo data-fp-translatable agli elementi front-end e visita le pagine per popolare l\'elenco.', 'fp-multilanguage' ) . '</p>';
                        echo '</div>';

                        return;
                }

                echo '<p>' . esc_html__( 'Rivedi e sovrascrivi le traduzioni manuali salvate dal front-end. Lascia vuoto un campo per ripristinare la traduzione automatica.', 'fp-multilanguage' ) . '</p>';

                echo '<div class="fp-multilanguage-strings-toolbar" style="margin:1em 0;">';
                echo '<label for="fp-multilanguage-strings-search" class="screen-reader-text">' . esc_html__( 'Cerca stringhe', 'fp-multilanguage' ) . '</label>';
                echo '<input type="search" id="fp-multilanguage-strings-search" class="regular-text" placeholder="' . esc_attr__( 'Cerca per testo o contesto…', 'fp-multilanguage' ) . '">';
                echo '</div>';

                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="fp-multilanguage-strings-form">';
                wp_nonce_field( 'fp_multilanguage_save_strings' );
                echo '<input type="hidden" name="action" value="fp_multilanguage_save_strings">';

                echo '<table class="widefat fixed striped" id="fp-multilanguage-strings-table">';
                echo '<thead><tr>';
                echo '<th style="width:15%;">' . esc_html__( 'Chiave', 'fp-multilanguage' ) . '</th>';
                echo '<th style="width:30%;">' . esc_html__( 'Originale', 'fp-multilanguage' ) . '</th>';
                echo '<th style="width:20%;">' . esc_html__( 'Contesto', 'fp-multilanguage' ) . '</th>';

                foreach ( $languages as $language ) {
                        echo '<th>' . esc_html( sprintf( __( 'Traduzione (%s)', 'fp-multilanguage' ), strtoupper( $language ) ) ) . '</th>';
                }

                echo '</tr></thead><tbody>';

                foreach ( $strings as $string ) {
                        $key          = isset( $string['key'] ) ? (string) $string['key'] : '';
                        $original     = isset( $string['original'] ) ? (string) $string['original'] : '';
                        $context      = isset( $string['context'] ) ? (string) $string['context'] : '';
                        $translations = isset( $string['translations'] ) && is_array( $string['translations'] ) ? $string['translations'] : array();

                        $contextDisplay = $context !== '' ? str_replace( '|', ' → ', $context ) : '—';

                        echo '<tr>';
                        echo '<td><code>' . esc_html( $key ) . '</code></td>';
                        echo '<td>' . ( $original !== '' ? nl2br( esc_html( $original ) ) : '<span class="description">' . esc_html__( 'Non disponibile', 'fp-multilanguage' ) . '</span>' ) . '</td>';
                        echo '<td>' . ( $contextDisplay !== '—' ? esc_html( $contextDisplay ) : '&#8212;' ) . '</td>';

                        foreach ( $languages as $language ) {
                                $value = isset( $translations[ $language ] ) ? (string) $translations[ $language ] : '';
                                $rows  = max( 2, min( 6, substr_count( $value, "\n" ) + 1 ) );

                                echo '<td>';
                                echo '<textarea class="large-text code" name="strings[' . esc_attr( $key ) . '][' . esc_attr( $language ) . ']" rows="' . esc_attr( (string) $rows ) . '">' . esc_textarea( $value ) . '</textarea>';
                                echo '</td>';
                        }

                        echo '</tr>';
                }

                echo '</tbody></table>';
                echo '<p id="fp-multilanguage-strings-empty" class="description" style="display:none;margin-top:1em;">' . esc_html__( 'Nessuna stringa corrisponde ai criteri di ricerca.', 'fp-multilanguage' ) . '</p>';
                submit_button( __( 'Salva traduzioni', 'fp-multilanguage' ) );
                echo '</form>';
                echo '</div>';
        }

        public function handle_strings_save(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        if ( function_exists( 'wp_die' ) ) {
                                wp_die( esc_html__( 'Non hai i permessi per aggiornare le traduzioni manuali.', 'fp-multilanguage' ) );
                        }

                        return;
                }

                check_admin_referer( 'fp_multilanguage_save_strings' );

                $submitted = isset( $_POST['strings'] ) ? wp_unslash( $_POST['strings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( ! is_array( $submitted ) ) {
                        $submitted = array();
                }

                $updates = 0;

                foreach ( $submitted as $key => $translations ) {
                        $normalizedKey = sanitize_key( (string) $key );
                        if ( '' === $normalizedKey || ! is_array( $translations ) ) {
                                continue;
                        }

                        foreach ( $translations as $language => $value ) {
                                $normalizedLanguage = sanitize_key( (string) $language );
                                if ( '' === $normalizedLanguage ) {
                                        continue;
                                }

                                $stringValue = is_scalar( $value ) ? (string) $value : '';
                                self::update_manual_string( $normalizedKey, $normalizedLanguage, $stringValue );
                                ++$updates;
                        }
                }

                $this->logger->info(
                        'Manual strings updated via admin page.',
                        array(
                                'updates' => $updates,
                        )
                );

                $redirect = add_query_arg(
                        array(
                                'page'            => 'fp-multilanguage-strings',
                                'strings-updated' => '1',
                        ),
                        admin_url( 'options-general.php' )
                );

                wp_safe_redirect( $redirect );
                exit;
        }

        /**
         * @param array<string, array<string, mixed>> $strings
         *
         * @return array<int, string>
         */
        private function get_manual_string_languages( array $strings ): array {
                $languages = array();

                foreach ( (array) self::get_target_languages() as $language ) {
                        $languages[] = (string) $language;
                }

                $fallback = self::get_fallback_language();
                if ( $fallback !== '' ) {
                        $languages[] = $fallback;
                }

                foreach ( $strings as $string ) {
                        if ( empty( $string['translations'] ) || ! is_array( $string['translations'] ) ) {
                                continue;
                        }

                        foreach ( $string['translations'] as $language => $value ) {
                                unset( $value );
                                $languages[] = (string) $language;
                        }
                }

                $languages = array_filter(
                        array_map(
                                static function ( $language ): string {
                                        return strtolower( trim( (string) $language ) );
                                },
                                $languages
                        )
                );

                $source = strtolower( self::get_source_language() );
                $languages = array_filter(
                        $languages,
                        static function ( string $language ) use ( $source ): bool {
                                return $language !== '' && $language !== $source;
                        }
                );

                $unique = array();
                foreach ( $languages as $language ) {
                        if ( in_array( $language, $unique, true ) ) {
                                continue;
                        }

                        $unique[] = $language;
                }

                return $unique;
        }

        public function render_post_types_field(): void {
                $options  = $this->get_options();
                $selected = array_map( 'strval', (array) ( $options['post_types'] ?? array() ) );

                $available = array();
                if ( function_exists( 'get_post_types' ) ) {
                        $postTypes = get_post_types(
                                array(
                                        'show_ui' => true,
                                ),
                                'objects'
                        );

                        foreach ( $postTypes as $name => $object ) {
                                $label               = isset( $object->labels->singular_name ) && $object->labels->singular_name !== ''
                                        ? $object->labels->singular_name
                                        : $name;
                                $available[ $name ] = $label;
                        }
                }

                if ( empty( $available ) ) {
                        $available = array(
                                'post' => __( 'Articolo', 'fp-multilanguage' ),
                                'page' => __( 'Pagina', 'fp-multilanguage' ),
                        );
                }

                ?>
                <select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[post_types][]" multiple size="<?php echo esc_attr( max( 4, min( 10, count( $available ) ) ) ); ?>">
                        <?php foreach ( $available as $name => $label ) : ?>
                                <option value="<?php echo esc_attr( $name ); ?>" <?php selected( in_array( (string) $name, $selected, true ) ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                </option>
                        <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Seleziona i post type che devono essere tradotti automaticamente.', 'fp-multilanguage' ); ?></p>
                <?php
        }

        public function render_taxonomies_field(): void {
                $options    = $this->get_options();
                $selected   = array_map( 'strval', (array) ( $options['taxonomies'] ?? array() ) );
                $available  = array();

                if ( function_exists( 'get_taxonomies' ) ) {
                        $taxonomies = get_taxonomies(
                                array(
                                        'show_ui' => true,
                                ),
                                'objects'
                        );

                        foreach ( $taxonomies as $name => $object ) {
                                $label              = isset( $object->labels->singular_name ) && $object->labels->singular_name !== ''
                                        ? $object->labels->singular_name
                                        : $name;
                                $available[ $name ] = $label;
                        }
                }

                if ( empty( $available ) ) {
                        $available = array(
                                'category' => __( 'Categoria', 'fp-multilanguage' ),
                                'post_tag' => __( 'Tag', 'fp-multilanguage' ),
                        );
                }

                ?>
                <select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[taxonomies][]" multiple size="<?php echo esc_attr( max( 4, min( 10, count( $available ) ) ) ); ?>">
                        <?php foreach ( $available as $name => $label ) : ?>
                                <option value="<?php echo esc_attr( $name ); ?>" <?php selected( in_array( (string) $name, $selected, true ) ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                </option>
                        <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Scegli le tassonomie da tradurre automaticamente.', 'fp-multilanguage' ); ?></p>
                <?php
        }

        /**
         * @param array<int, string> $taxonomies
         *
         * @return array<int, string>
         */
        private function resolve_taxonomy_labels( array $taxonomies ): array {
                $labels = array();

                if ( function_exists( 'get_taxonomies' ) ) {
                        $objects = get_taxonomies( array(), 'objects' );

                        foreach ( $taxonomies as $taxonomy ) {
                                $taxonomy = (string) $taxonomy;

                                if ( isset( $objects[ $taxonomy ] ) ) {
                                        $object = $objects[ $taxonomy ];
                                        $label  = isset( $object->labels->singular_name ) && $object->labels->singular_name !== ''
                                                ? $object->labels->singular_name
                                                : $taxonomy;

                                        $labels[] = (string) $label;
                                        continue;
                                }

                                $labels[] = $taxonomy;
                        }
                } else {
                        $labels = $taxonomies;
                }

                $labels = array_map(
                        static function ( $label ): string {
                                return trim( (string) $label );
                        },
                        $labels
                );

                $labels = array_filter(
                        $labels,
                        static function ( string $label ): bool {
                                return '' !== $label;
                        }
                );

                return array_values( array_unique( $labels ) );
        }

        public function render_custom_fields_field(): void {
                $options      = $this->get_options();
                $customFields = array_map( 'strval', (array) ( $options['custom_fields'] ?? array() ) );
                ?>
                <textarea name="<?php echo esc_attr( self::OPTION_NAME ); ?>[custom_fields]" rows="4" class="large-text" placeholder="_custom_meta_key&#10;another_meta_key"><?php echo esc_textarea( implode( "\n", $customFields ) ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Inserisci una chiave meta per riga per includerla nella traduzione automatica.', 'fp-multilanguage' ); ?></p>
                <?php
        }

        private function get_provider_label( string $provider ): string {
                switch ( $provider ) {
                        case 'google':
                                return __( 'Google Cloud Translation', 'fp-multilanguage' );
                        case 'deepl':
                                return __( 'DeepL', 'fp-multilanguage' );
                        default:
                                return ucfirst( $provider );
                }
        }

        public function render_google_field(): void {
                        $options    = $this->get_options();
                        $provider   = $options['providers']['google'] ?? array();
                        $labelText  = $this->get_provider_label( 'google' );
                ?>
                <div class="fp-multilanguage-provider" data-provider="google" data-provider-label="<?php echo esc_attr( $labelText ); ?>">
                        <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][google][enabled]" value="1" <?php checked( ! empty( $provider['enabled'] ) ); ?>>
                                <?php printf( esc_html__( 'Abilita %s', 'fp-multilanguage' ), esc_html( $labelText ) ); ?>
                        </label>
                        <input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][google][api_key]" value="<?php echo esc_attr( $provider['api_key'] ?? '' ); ?>">
                        <p class="description"><?php esc_html_e( 'Chiave API del progetto Google Cloud con Translation API abilitata.', 'fp-multilanguage' ); ?></p>
                        <p>
                                <label for="fp-multilanguage-google-glossary">
                                        <?php esc_html_e( 'ID glossario personalizzato', 'fp-multilanguage' ); ?>
                                </label>
                                <input id="fp-multilanguage-google-glossary" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][google][glossary_id]" value="<?php echo esc_attr( $provider['glossary_id'] ?? '' ); ?>" placeholder="projects/xxx/locations/xx/glossaries/xxx">
                                <span class="description"><?php esc_html_e( 'Percorso completo della risorsa glossario. Lascia vuoto per disabilitare.', 'fp-multilanguage' ); ?></span>
                        </p>
                        <p>
                                <label>
                                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][google][glossary_ignore_case]" value="1" <?php checked( ! empty( $provider['glossary_ignore_case'] ) ); ?>>
                                        <?php esc_html_e( 'Applica il glossario ignorando maiuscole/minuscole.', 'fp-multilanguage' ); ?>
                                </label>
                        </p>
                        <p class="fp-multilanguage-provider-actions">
                                <button type="button" class="button button-secondary fp-multilanguage-provider-test" data-provider="google">
                                        <?php esc_html_e( 'Verifica credenziali', 'fp-multilanguage' ); ?>
                                </button>
                                <span class="fp-multilanguage-provider-status" data-provider="google" aria-live="polite"></span>
                        </p>
                </div>
                <?php
        }

        public function render_onboarding_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                $options        = $this->get_options();
                $source         = isset( $options['source_language'] ) ? (string) $options['source_language'] : '';
                $fallback       = isset( $options['fallback_language'] ) ? (string) $options['fallback_language'] : '';
                $targets        = isset( $options['target_languages'] ) ? (array) $options['target_languages'] : array();
                $targetsString  = implode( ', ', array_map( 'strval', $targets ) );
                $autoEnabled    = ! empty( $options['auto_translate'] );
                $taxonomies     = isset( $options['taxonomies'] ) ? array_map( 'strval', (array) $options['taxonomies'] ) : array();
                $taxonomyLabels = $this->resolve_taxonomy_labels( $taxonomies );

                $providerLabels = array();
                foreach ( array( 'google', 'deepl' ) as $providerKey ) {
                        $providerOptions = isset( $options['providers'][ $providerKey ] ) && is_array( $options['providers'][ $providerKey ] )
                                ? $options['providers'][ $providerKey ]
                                : array();

                        if ( ! empty( $providerOptions['enabled'] ) ) {
                                $providerLabels[] = $this->get_provider_label( $providerKey );
                        }
                }

                $emptyLabel        = __( 'Non impostato', 'fp-multilanguage' );
                $targetDisplay     = '' !== trim( $targetsString ) ? $targetsString : $emptyLabel;
                $providerSummary   = empty( $providerLabels ) ? __( 'Nessun provider attivo', 'fp-multilanguage' ) : implode( ', ', $providerLabels );
                $autoSummaryLabel  = $autoEnabled ? __( 'Attivo', 'fp-multilanguage' ) : __( 'Disattivo', 'fp-multilanguage' );
                $taxonomySummary   = empty( $taxonomyLabels ) ? $emptyLabel : implode( ', ', $taxonomyLabels );
                ?>
                <div class="wrap fp-multilanguage-onboarding" id="fp-multilanguage-onboarding">
                        <h1><?php esc_html_e( 'Configurazione guidata', 'fp-multilanguage' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Completa i passaggi per preparare il plugin alle traduzioni automatiche.', 'fp-multilanguage' ); ?></p>
                        <ol class="fp-multilanguage-steps">
                                <li class="is-active" data-step="1"><?php esc_html_e( 'Lingue', 'fp-multilanguage' ); ?></li>
                                <li data-step="2"><?php esc_html_e( 'Provider', 'fp-multilanguage' ); ?></li>
                                <li data-step="3"><?php esc_html_e( 'Riepilogo', 'fp-multilanguage' ); ?></li>
                        </ol>
                        <div class="fp-multilanguage-onboarding-notice" style="display:none;"></div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                                <?php settings_fields( 'fp_multilanguage' ); ?>
                                <section class="fp-multilanguage-step" data-step="1">
                                        <h2><?php esc_html_e( 'Lingue e contenuti', 'fp-multilanguage' ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Definisci le lingue disponibili e quali contenuti devono essere sincronizzati.', 'fp-multilanguage' ); ?></p>
                                        <table class="form-table">
                                                <tr>
                                                        <th scope="row"><label for="fp-multilanguage-onboarding-source"><?php esc_html_e( 'Lingua sorgente', 'fp-multilanguage' ); ?></label></th>
                                                        <td>
                                                                <input type="text" id="fp-multilanguage-onboarding-source" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_language]" value="<?php echo esc_attr( $source ); ?>" maxlength="10">
                                                                <p class="description"><?php esc_html_e( 'Codice lingua predefinito (es. en).', 'fp-multilanguage' ); ?></p>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><label for="fp-multilanguage-onboarding-targets"><?php esc_html_e( 'Lingue di destinazione', 'fp-multilanguage' ); ?></label></th>
                                                        <td>
                                                                <input type="text" id="fp-multilanguage-onboarding-targets" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[target_languages]" value="<?php echo esc_attr( $targetsString ); ?>">
                                                                <p class="description"><?php esc_html_e( 'Inserisci le lingue di destinazione separate da virgola.', 'fp-multilanguage' ); ?></p>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><label for="fp-multilanguage-onboarding-fallback"><?php esc_html_e( 'Lingua di fallback', 'fp-multilanguage' ); ?></label></th>
                                                        <td>
                                                                <input type="text" id="fp-multilanguage-onboarding-fallback" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[fallback_language]" value="<?php echo esc_attr( $fallback ); ?>" maxlength="10">
                                                                <p class="description"><?php esc_html_e( 'Lingua utilizzata come fallback automatico.', 'fp-multilanguage' ); ?></p>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Traduzione automatica', 'fp-multilanguage' ); ?></th>
                                                        <td>
                                                                <label>
                                                                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[auto_translate]" value="1" <?php checked( $autoEnabled ); ?>>
                                                                        <?php esc_html_e( 'Traduci automaticamente i contenuti al salvataggio.', 'fp-multilanguage' ); ?>
                                                                </label>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Tipi di contenuto', 'fp-multilanguage' ); ?></th>
                                                        <td>
                                                                <?php $this->render_post_types_field(); ?>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Tassonomie', 'fp-multilanguage' ); ?></th>
                                                        <td>
                                                                <?php $this->render_taxonomies_field(); ?>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Campi personalizzati', 'fp-multilanguage' ); ?></th>
                                                        <td>
                                                                <?php $this->render_custom_fields_field(); ?>
                                                        </td>
                                                </tr>
                                        </table>
                                </section>
                                <section class="fp-multilanguage-step" data-step="2" hidden="hidden">
                                        <h2><?php esc_html_e( 'Collega i provider di traduzione', 'fp-multilanguage' ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Inserisci le credenziali e utilizza “Verifica credenziali” per assicurarti che la connessione funzioni prima di proseguire.', 'fp-multilanguage' ); ?></p>
                                        <div class="fp-multilanguage-provider-grid">
                                                <?php $this->render_google_field(); ?>
                                                <?php $this->render_deepl_field(); ?>
                                        </div>
                                </section>
                                <section class="fp-multilanguage-step" data-step="3" hidden="hidden">
                                        <h2><?php esc_html_e( 'Riepilogo', 'fp-multilanguage' ); ?></h2>
                                        <p class="description"><?php esc_html_e( 'Controlla le impostazioni e completa la configurazione guidata.', 'fp-multilanguage' ); ?></p>
                                        <table class="form-table">
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Lingua sorgente', 'fp-multilanguage' ); ?></th>
                                                        <td><strong data-summary="source"><?php echo esc_html( '' !== $source ? $source : $emptyLabel ); ?></strong></td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Lingue di destinazione', 'fp-multilanguage' ); ?></th>
                                                        <td><strong data-summary="targets"><?php echo esc_html( $targetDisplay ); ?></strong></td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Lingua di fallback', 'fp-multilanguage' ); ?></th>
                                                        <td><strong data-summary="fallback"><?php echo esc_html( '' !== $fallback ? $fallback : $emptyLabel ); ?></strong></td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Traduzione automatica', 'fp-multilanguage' ); ?></th>
                                                        <td><strong data-summary="auto"><?php echo esc_html( $autoSummaryLabel ); ?></strong></td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Provider attivi', 'fp-multilanguage' ); ?></th>
                                                        <td><strong data-summary="providers"><?php echo esc_html( $providerSummary ); ?></strong></td>
                                                </tr>
                                                <tr>
                                                        <th scope="row"><?php esc_html_e( 'Tassonomie', 'fp-multilanguage' ); ?></th>
                                                        <td><strong data-summary="taxonomies"><?php echo esc_html( $taxonomySummary ); ?></strong></td>
                                                </tr>
                                        </table>
                                        <p class="description"><?php esc_html_e( 'Se qualcosa non è corretto puoi tornare agli step precedenti senza perdere i dati inseriti.', 'fp-multilanguage' ); ?></p>
                                </section>
                                <div class="fp-multilanguage-onboarding-actions">
                                        <button type="button" class="button button-secondary" data-action="prev"><?php esc_html_e( 'Indietro', 'fp-multilanguage' ); ?></button>
                                        <button type="button" class="button button-primary" data-action="next"><?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?></button>
                                        <button type="submit" class="button button-primary" data-action="submit"><?php esc_html_e( 'Salva impostazioni', 'fp-multilanguage' ); ?></button>
                                </div>
                        </form>
                </div>
                <?php
        }

        public function render_deepl_field(): void {
                        $options    = $this->get_options();
                        $provider   = $options['providers']['deepl'] ?? array();
                        $labelText  = $this->get_provider_label( 'deepl' );
                ?>
                <div class="fp-multilanguage-provider" data-provider="deepl" data-provider-label="<?php echo esc_attr( $labelText ); ?>">
                        <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][deepl][enabled]" value="1" <?php checked( ! empty( $provider['enabled'] ) ); ?>>
                                <?php printf( esc_html__( 'Abilita %s', 'fp-multilanguage' ), esc_html( $labelText ) ); ?>
                        </label>
                        <input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][deepl][api_key]" value="<?php echo esc_attr( $provider['api_key'] ?? '' ); ?>">
                        <p class="description"><?php esc_html_e( 'Utilizza endpoint EU/US personalizzato se necessario.', 'fp-multilanguage' ); ?></p>
                        <p>
                                <label for="fp-multilanguage-deepl-glossary">
                                        <?php esc_html_e( 'ID glossario DeepL', 'fp-multilanguage' ); ?>
                                </label>
                                <input id="fp-multilanguage-deepl-glossary" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][deepl][glossary_id]" value="<?php echo esc_attr( $provider['glossary_id'] ?? '' ); ?>" placeholder="1234-5678-...">
                                <span class="description"><?php esc_html_e( 'Inserisci l\'ID del glossario pubblicato su DeepL (opzionale).', 'fp-multilanguage' ); ?></span>
                        </p>
                        <p>
                                <label for="fp-multilanguage-deepl-formality">
                                        <?php esc_html_e( 'Livello di formalità', 'fp-multilanguage' ); ?>
                                </label>
                                <select id="fp-multilanguage-deepl-formality" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][deepl][formality]">
                                        <?php
                                        $formalities = array(
                                                'default' => __( 'Predefinito', 'fp-multilanguage' ),
                                                'more'    => __( 'Più formale', 'fp-multilanguage' ),
                                                'less'    => __( 'Meno formale', 'fp-multilanguage' ),
                                        );
                                        $selected    = isset( $provider['formality'] ) ? (string) $provider['formality'] : 'default';
                                        foreach ( $formalities as $value => $label ) :
                                                ?>
                                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php
                                        endforeach;
                                        ?>
                                </select>
                        </p>
                        <p class="fp-multilanguage-provider-actions">
                                <button type="button" class="button button-secondary fp-multilanguage-provider-test" data-provider="deepl">
                                        <?php esc_html_e( 'Verifica credenziali', 'fp-multilanguage' ); ?>
                                </button>
                                <span class="fp-multilanguage-provider-status" data-provider="deepl" aria-live="polite"></span>
                        </p>
                </div>
                <?php
        }

	public function render_seo_field(): void {
		$options = $this->get_options();
		$seo     = $options['seo'];
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[seo][hreflang]" value="1" <?php checked( ! empty( $seo['hreflang'] ) ); ?>>
			<?php esc_html_e( 'Genera hreflang automatici', 'fp-multilanguage' ); ?>
		</label><br>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[seo][canonical]" value="1" <?php checked( ! empty( $seo['canonical'] ) ); ?>>
			<?php esc_html_e( 'Gestisci tag canonical per lingua', 'fp-multilanguage' ); ?>
		</label><br>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[seo][open_graph]" value="1" <?php checked( ! empty( $seo['open_graph'] ) ); ?>>
			<?php esc_html_e( 'Sincronizza meta Open Graph', 'fp-multilanguage' ); ?>
		</label>
		<?php
	}

	private function render_quote_tab(): void {
		$quotes = $this->get_options()['quote_tracking'] ?? array();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'fp-multilanguage' ); ?></th>
					<th><?php esc_html_e( 'Lingua', 'fp-multilanguage' ); ?></th>
					<th><?php esc_html_e( 'Caratteri utilizzati', 'fp-multilanguage' ); ?></th>
					<th><?php esc_html_e( 'Ultimo aggiornamento', 'fp-multilanguage' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $quotes ) ) : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'Nessun dato disponibile. Le quote verranno aggiornate dopo le prime traduzioni.', 'fp-multilanguage' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $quotes as $provider => $data ) : ?>
						<?php foreach ( $data as $language => $usage ) : ?>
							<tr>
								<td><?php echo esc_html( $provider ); ?></td>
								<td><?php echo esc_html( $language ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $usage['characters'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( isset( $usage['updated_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $usage['updated_at'] ) : '-' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

        public function sanitize( $input ): array {
			$sanitized = wp_parse_args( is_array( $input ) ? $input : array(), self::$defaults );

			$sanitized['source_language']   = $this->sanitize_language( $sanitized['source_language'] );
			$sanitized['fallback_language'] = $this->sanitize_language( $sanitized['fallback_language'] );

		if ( '' === $sanitized['fallback_language'] ) {
				$sanitized['fallback_language'] = $sanitized['source_language'];
		}

			$targets = $sanitized['target_languages'];
		if ( is_string( $targets ) ) {
				$targets = preg_split( '/[,\s]+/', $targets );
		}
			$targets = array_map( array( $this, 'sanitize_language' ), (array) $targets );
			$targets = array_filter( $targets );
			$targets = array_values( array_unique( $targets ) );
			$targets = array_values(
				array_filter(
					$targets,
					static function ( string $language ) use ( $sanitized ): bool {
									return $language !== $sanitized['source_language'];
					}
				)
			);

		if ( $sanitized['fallback_language'] !== $sanitized['source_language'] && ! in_array( $sanitized['fallback_language'], $targets, true ) ) {
				$targets[] = $sanitized['fallback_language'];
		}

		if ( empty( $targets ) ) {
				$defaultTargets = array_map( array( $this, 'sanitize_language' ), (array) self::$defaults['target_languages'] );
				$defaultTargets = array_filter(
					$defaultTargets,
					static function ( string $language ) use ( $sanitized ): bool {
								return $language !== $sanitized['source_language'];
					}
				);

			if ( empty( $defaultTargets ) && $sanitized['fallback_language'] !== $sanitized['source_language'] ) {
				$defaultTargets = array( $sanitized['fallback_language'] );
			}

				$targets = array_values( array_unique( $defaultTargets ) );
		}

			$sanitized['target_languages'] = array_values( array_unique( $targets ) );

                $sanitized['auto_translate'] = ! empty( $sanitized['auto_translate'] );

                $postTypes = $sanitized['post_types'] ?? array();
                if ( is_string( $postTypes ) ) {
                        $postTypes = preg_split( '/[,\s]+/', $postTypes );
                }
                $postTypes = array_map( 'sanitize_key', (array) $postTypes );
                $postTypes = array_filter( $postTypes );
                $postTypes = array_values( array_unique( $postTypes ) );

                if ( function_exists( 'post_type_exists' ) ) {
                        $postTypes = array_values(
                                array_filter(
                                        $postTypes,
                                        static function ( string $postType ): bool {
                                                return post_type_exists( $postType );
                                        }
                                )
                        );
                }

                if ( empty( $postTypes ) ) {
                        $postTypes = array_map( 'sanitize_key', (array) self::$defaults['post_types'] );
                }

                $sanitized['post_types'] = $postTypes;

                $taxonomies = $sanitized['taxonomies'] ?? array();
                if ( is_string( $taxonomies ) ) {
                        $taxonomies = preg_split( '/[,\s]+/', $taxonomies );
                }

                $taxonomies = array_map( 'sanitize_key', (array) $taxonomies );
                $taxonomies = array_filter( $taxonomies );
                $taxonomies = array_values( array_unique( $taxonomies ) );

                if ( function_exists( 'taxonomy_exists' ) ) {
                        $taxonomies = array_values(
                                array_filter(
                                        $taxonomies,
                                        static function ( string $taxonomy ): bool {
                                                return taxonomy_exists( $taxonomy );
                                        }
                                )
                        );
                }

                if ( empty( $taxonomies ) ) {
                        $taxonomies = array_map( 'sanitize_key', (array) self::$defaults['taxonomies'] );
                }

                $sanitized['taxonomies'] = $taxonomies;

                $customFields = $sanitized['custom_fields'] ?? array();
                if ( is_string( $customFields ) ) {
                        $customFields = preg_split( '/[\r\n,]+/', $customFields );
                }

                $customFields = array_map( array( $this, 'sanitize_custom_field_key' ), (array) $customFields );
                $customFields = array_filter( $customFields );
                $sanitized['custom_fields'] = array_values( array_unique( $customFields ) );

                foreach ( array( 'google', 'deepl' ) as $provider ) {
						$providerOptions            = $sanitized['providers'][ $provider ] ?? array();
						$providerOptions            = wp_parse_args( $providerOptions, self::$defaults['providers'][ $provider ] );
						$providerOptions['enabled'] = ! empty( $providerOptions['enabled'] );
						$providerOptions['api_key'] = sanitize_text_field( $providerOptions['api_key'] );
			if ( isset( $providerOptions['timeout'] ) ) {
						$providerOptions['timeout'] = max( 5, (int) $providerOptions['timeout'] );
			}
			if ( $providerOptions['enabled'] && '' === $providerOptions['api_key'] ) {
									$providerOptions['enabled'] = false;

									$providerLabel = 'deepl' === $provider ? 'DeepL' : ucfirst( $provider );
									$message       = sprintf(
						/* translators: %s is the provider name. */
										__( 'Il provider %s è stato disabilitato perché manca la chiave API.', 'fp-multilanguage' ),
										$providerLabel
									);

						$this->notices->add_notice( $message, 'warning', false );
			}
			if ( isset( $providerOptions['endpoint'] ) ) {
						$providerOptions['endpoint'] = esc_url_raw( $providerOptions['endpoint'] );
			}
			if ( isset( $providerOptions['glossary_id'] ) ) {
				$glossaryId = sanitize_text_field( $providerOptions['glossary_id'] );
				$glossaryId = html_entity_decode( $glossaryId, ENT_QUOTES, 'UTF-8' );
				$glossaryId = preg_replace( '/[\r\n]+/', '', $glossaryId );
				if ( null === $glossaryId ) {
						$glossaryId = '';
				}

				$providerOptions['glossary_id'] = trim( $glossaryId );
			}
			if ( 'google' === $provider ) {
				$providerOptions['glossary_ignore_case'] = ! empty( $providerOptions['glossary_ignore_case'] );
				if ( '' === $providerOptions['glossary_id'] ) {
						$providerOptions['glossary_ignore_case'] = false;
				}
			}
			if ( 'deepl' === $provider ) {
				$formality = strtolower( sanitize_text_field( (string) ( $providerOptions['formality'] ?? '' ) ) );
				$allowed   = array( 'default', 'more', 'less' );
				if ( ! in_array( $formality, $allowed, true ) ) {
						$formality = 'default';
				}

				$providerOptions['formality'] = $formality;
			}
						$sanitized['providers'][ $provider ] = $providerOptions;
		}

		if ( ! isset( $sanitized['seo'] ) || ! is_array( $sanitized['seo'] ) ) {
			$sanitized['seo'] = self::$defaults['seo'];
		} else {
			foreach ( self::$defaults['seo'] as $key => $default ) {
				$sanitized['seo'][ $key ] = isset( $sanitized['seo'][ $key ] ) ? (bool) $sanitized['seo'][ $key ] : (bool) $default;
			}
		}

			$sanitized['quote_tracking'] = self::get_quote_tracking();

			TranslationService::flush_cache();

						self::set_cached_options( $sanitized );
						CurrentLanguage::clear_cache();

                        return $sanitized;
        }


        /**
         * @param array<string, mixed> $options
         * @return array<string, mixed>|WP_Error
         */
        private function sanitize_provider_options( string $provider, array $options ) {
                if ( ! isset( self::$defaults['providers'][ $provider ] ) ) {
                        $message = __( 'Provider non valido.', 'fp-multilanguage' );

                        return new WP_Error( 'invalid_provider', $message, array( 'status' => 400 ) );
                }

                $sanitized = wp_parse_args( $options, self::$defaults['providers'][ $provider ] );
                $sanitized['enabled'] = ! empty( $sanitized['enabled'] );
                $sanitized['api_key'] = sanitize_text_field( (string) ( $sanitized['api_key'] ?? '' ) );

                if ( isset( $sanitized['timeout'] ) ) {
                        $sanitized['timeout'] = max( 5, (int) $sanitized['timeout'] );
                } else {
                        $sanitized['timeout'] = 20;
                }

                if ( isset( $sanitized['endpoint'] ) ) {
                        $sanitized['endpoint'] = esc_url_raw( (string) $sanitized['endpoint'] );
                }

                if ( isset( $sanitized['glossary_id'] ) ) {
                        $glossaryId = sanitize_text_field( (string) $sanitized['glossary_id'] );
                        $glossaryId = html_entity_decode( $glossaryId, ENT_QUOTES, 'UTF-8' );
                        $glossaryId = preg_replace( '/[\r\n]+/', '', $glossaryId );
                        if ( null === $glossaryId ) {
                                $glossaryId = '';
                        }

                        $sanitized['glossary_id'] = trim( $glossaryId );
                }

                if ( 'google' === $provider ) {
                        $sanitized['glossary_ignore_case'] = ! empty( $sanitized['glossary_ignore_case'] ) && '' !== $sanitized['glossary_id'];
                }

                if ( 'deepl' === $provider ) {
                        $formality = strtolower( sanitize_text_field( (string) ( $sanitized['formality'] ?? '' ) ) );
                        $allowed   = array( 'default', 'more', 'less' );
                        if ( ! in_array( $formality, $allowed, true ) ) {
                                $formality = 'default';
                        }

                        $sanitized['formality'] = $formality;
                }

                return $sanitized;
        }


        /**
         * @param array<string, mixed> $options
         * @return array{success:bool,message:string,details:array<string,mixed>}
         */
        private function test_provider_credentials( string $provider, array $options ): array {
                switch ( $provider ) {
                        case 'google':
                                return $this->test_google_credentials( $options );
                        case 'deepl':
                                return $this->test_deepl_credentials( $options );
                        default:
                                return $this->provider_test_response( false, __( 'Provider non supportato.', 'fp-multilanguage' ) );
                }
        }


        /**
         * @param array<string, mixed> $options
         * @return array{success:bool,message:string,details:array<string,mixed>}
         */
        private function test_google_credentials( array $options ): array {
                $apiKey = (string) ( $options['api_key'] ?? '' );

                if ( '' === $apiKey ) {
                        return $this->provider_test_response( false, __( 'Inserisci una chiave API Google prima di avviare la verifica.', 'fp-multilanguage' ) );
                }

                if ( ! function_exists( 'wp_remote_get' ) ) {
                        return $this->provider_test_response( false, __( 'La funzione di rete di WordPress non è disponibile.', 'fp-multilanguage' ) );
                }

                $endpoint = add_query_arg(
                        array(
                                'key'    => $apiKey,
                                'target' => 'en',
                        ),
                        'https://translation.googleapis.com/language/translate/v2/languages'
                );

                $timeout  = isset( $options['timeout'] ) ? max( 5, (int) $options['timeout'] ) : 20;
                $response = wp_remote_get(
                        $endpoint,
                        array(
                                'timeout' => $timeout,
                        )
                );

                if ( is_wp_error( $response ) ) {
                        $errorMessage = $response->get_error_message();
                        $this->logger->warning( 'Google credential test failed with WP_Error', array( 'error' => $errorMessage ) );

                        return $this->provider_test_response(
                                false,
                                sprintf(
                                        /* translators: %s error message */
                                        __( 'Errore di connessione a Google: %s', 'fp-multilanguage' ),
                                        $errorMessage
                                )
                        );
                }

                $status = wp_remote_retrieve_response_code( $response );
                $body   = wp_remote_retrieve_body( $response );

                if ( 200 !== $status ) {
                        $message = $this->extract_google_error_message( $body );
                        $this->logger->warning( 'Google credential test returned non-200 status', array( 'status' => $status, 'body' => $body ) );

                        return $this->provider_test_response(
                                false,
                                sprintf(
                                        /* translators: %s error message */
                                        __( 'Verifica Google non riuscita: %s', 'fp-multilanguage' ),
                                        $message
                                )
                        );
                }

                $decoded = json_decode( $body, true );
                $languagesCount = 0;
                if ( is_array( $decoded ) && isset( $decoded['data']['languages'] ) && is_array( $decoded['data']['languages'] ) ) {
                        $languagesCount = count( $decoded['data']['languages'] );
                }

                return $this->provider_test_response(
                        true,
                        __( 'Connessione a Google riuscita.', 'fp-multilanguage' ),
                        array(
                                'languages' => $languagesCount,
                        )
                );
        }


        /**
         * @param array<string, mixed> $options
         * @return array{success:bool,message:string,details:array<string,mixed>}
         */
        private function test_deepl_credentials( array $options ): array {
                $apiKey = (string) ( $options['api_key'] ?? '' );

                if ( '' === $apiKey ) {
                        return $this->provider_test_response( false, __( 'Inserisci una chiave API DeepL prima di avviare la verifica.', 'fp-multilanguage' ) );
                }

                if ( ! function_exists( 'wp_remote_get' ) ) {
                        return $this->provider_test_response( false, __( 'La funzione di rete di WordPress non è disponibile.', 'fp-multilanguage' ) );
                }

                $endpoint = (string) ( $options['endpoint'] ?? self::$defaults['providers']['deepl']['endpoint'] );
                if ( '' === $endpoint ) {
                        $endpoint = self::$defaults['providers']['deepl']['endpoint'];
                }

                $baseEndpoint = rtrim( $endpoint, '/' );
                if ( '' === $baseEndpoint ) {
                        $baseEndpoint = 'https://api.deepl.com/v2/translate';
                }

                $usageEndpoint = preg_replace( '/\/translate$/', '/usage', $baseEndpoint );
                if ( ! is_string( $usageEndpoint ) || '' === $usageEndpoint ) {
                        $usageEndpoint = $baseEndpoint;
                        if ( substr( $usageEndpoint, -6 ) !== '/usage' ) {
                                $usageEndpoint .= '/usage';
                        }
                }

                $usageEndpoint = rtrim( $usageEndpoint, '/' );
                if ( '' === $usageEndpoint ) {
                        $usageEndpoint = 'https://api.deepl.com/v2/usage';
                }

                $timeout = isset( $options['timeout'] ) ? max( 5, (int) $options['timeout'] ) : 20;

                $response = wp_remote_get(
                        $usageEndpoint,
                        array(
                                'timeout' => $timeout,
                                'headers' => array(
                                        'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
                                ),
                        )
                );

                if ( is_wp_error( $response ) ) {
                        $errorMessage = $response->get_error_message();
                        $this->logger->warning( 'DeepL credential test failed with WP_Error', array( 'error' => $errorMessage ) );

                        return $this->provider_test_response(
                                false,
                                sprintf(
                                        /* translators: %s error message */
                                        __( 'Errore di connessione a DeepL: %s', 'fp-multilanguage' ),
                                        $errorMessage
                                )
                        );
                }

                $status = wp_remote_retrieve_response_code( $response );
                $body   = wp_remote_retrieve_body( $response );

                if ( 200 !== $status ) {
                        $message = $this->extract_deepl_error_message( $body );
                        $this->logger->warning( 'DeepL credential test returned non-200 status', array( 'status' => $status, 'body' => $body ) );

                        return $this->provider_test_response(
                                false,
                                sprintf(
                                        /* translators: %s error message */
                                        __( 'Verifica DeepL non riuscita: %s', 'fp-multilanguage' ),
                                        $message
                                )
                        );
                }

                $decoded = json_decode( $body, true );
                $charactersUsed = (int) ( $decoded['character_count'] ?? 0 );
                $characterLimit = isset( $decoded['character_limit'] ) ? (int) $decoded['character_limit'] : 0;
                $remaining      = $characterLimit > 0 ? max( 0, $characterLimit - $charactersUsed ) : null;

                $message = __( 'Connessione a DeepL riuscita.', 'fp-multilanguage' );
                if ( null !== $remaining ) {
                        $formatted = function_exists( 'number_format_i18n' ) ? number_format_i18n( $remaining ) : (string) $remaining;
                        $message   = sprintf(
                                /* translators: %s remaining characters */
                                __( 'Connessione a DeepL riuscita. Caratteri residui: %s', 'fp-multilanguage' ),
                                $formatted
                        );
                }

                return $this->provider_test_response(
                        true,
                        $message,
                        array(
                                'character_count' => $charactersUsed,
                                'character_limit' => $characterLimit,
                        )
                );
        }


        private function extract_google_error_message( $body ): string {
                if ( is_string( $body ) ) {
                        $decoded = json_decode( $body, true );
                        if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
                                return (string) $decoded['error']['message'];
                        }
                }

                return __( 'Risposta non valida da Google.', 'fp-multilanguage' );
        }


        private function extract_deepl_error_message( $body ): string {
                if ( is_string( $body ) ) {
                        $decoded = json_decode( $body, true );
                        if ( is_array( $decoded ) && isset( $decoded['message'] ) ) {
                                return (string) $decoded['message'];
                        }
                }

                return __( 'Risposta non valida da DeepL.', 'fp-multilanguage' );
        }


        /**
         * @param array<string, mixed> $details
         * @return array{success:bool,message:string,details:array<string,mixed>}
         */
        private function provider_test_response( bool $success, string $message, array $details = array() ): array {
                return array(
                        'success' => $success,
                        'message' => $message,
                        'details' => $details,
                );
        }


        private function sanitize_language( string $value ): string {
                $value = sanitize_text_field( strtolower( $value ) );
                $value = str_replace( array( ' ', '_' ), '-', $value );

                        $value = preg_replace( '/[^a-z0-9-]/', '', $value );
                if ( null === $value ) {
                                return '';
                }

                        return trim( $value, '-' );
        }

        private function sanitize_custom_field_key( $value ): string {
                if ( ! is_string( $value ) ) {
                        return '';
                }

                $value = sanitize_text_field( $value );
                $value = preg_replace( '/[^A-Za-z0-9:_-]/', '', $value );
                if ( null === $value ) {
                        return '';
                }

                return trim( $value );
        }

	public static function get_options(): array {
		if ( null === self::$cachedOptions ) {
			if ( ! function_exists( 'get_option' ) ) {
				self::$cachedOptions = self::$defaults;
			} else {
					$optionsRaw          = get_option( self::OPTION_NAME, array() );
					self::$cachedOptions = wp_parse_args( is_array( $optionsRaw ) ? $optionsRaw : array(), self::$defaults );
			}
		}

			$options                   = self::$cachedOptions;
			$options['quote_tracking'] = self::get_quote_tracking();

			return $options;
	}

        public static function get_manual_strings(): array {
                $stored = get_option( self::MANUAL_STRINGS_OPTION, array() );

                return is_array( $stored ) ? $stored : array();
        }

        /**
         * @return array<string, array{key:string,original:string,context:string,translations:array<string, string>}>|
         *         array<string, array>
         */
        public static function get_manual_strings_catalog(): array {
                $catalog      = array();
                $translations = self::get_manual_strings();
                $metadata     = self::get_manual_string_metadata();

                foreach ( $metadata as $key => $meta ) {
                        if ( ! is_string( $key ) || '' === $key ) {
                                continue;
                        }

                        $catalog[ $key ] = array(
                                'key'          => $key,
                                'original'     => isset( $meta['original'] ) ? (string) $meta['original'] : '',
                                'context'      => isset( $meta['context'] ) ? (string) $meta['context'] : '',
                                'translations' => isset( $translations[ $key ] ) && is_array( $translations[ $key ] ) ? $translations[ $key ] : array(),
                        );
                }

                foreach ( $translations as $key => $values ) {
                        if ( ! is_string( $key ) || '' === $key ) {
                                continue;
                        }

                        $catalog[ $key ] = array(
                                'key'          => $key,
                                'original'     => $catalog[ $key ]['original'] ?? '',
                                'context'      => $catalog[ $key ]['context'] ?? '',
                                'translations' => is_array( $values ) ? $values : array(),
                        );
                }

                ksort( $catalog );

                return $catalog;
        }

        /**
         * @return array<string, array{context:string,original:string,updated:string}>
         */
        private static function get_manual_string_metadata(): array {
                $metadata = array();

                global $wpdb;

                if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) && ! empty( $wpdb->prefix ) && class_exists( '\wpdb' ) ) {
                        $table = $wpdb->prefix . 'fp_multilanguage_strings';

                        if ( self::manual_strings_table_exists( $table ) ) {
                                $tableName = esc_sql( $table );
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                $rows = $wpdb->get_results( "SELECT string_key, context, original, updated_at FROM {$tableName}", ARRAY_A );

                                if ( is_array( $rows ) ) {
                                        foreach ( $rows as $row ) {
                                                if ( ! is_array( $row ) ) {
                                                        continue;
                                                }

                                                $key = isset( $row['string_key'] ) ? (string) $row['string_key'] : '';
                                                if ( '' === $key ) {
                                                        continue;
                                                }

                                                $metadata[ $key ] = array(
                                                        'context'  => isset( $row['context'] ) ? (string) $row['context'] : '',
                                                        'original' => isset( $row['original'] ) ? (string) $row['original'] : '',
                                                        'updated'  => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
                                                );
                                        }
                                }
                        }
                }

                $fallback = get_option( 'fp_multilanguage_strings', array() );
                if ( is_array( $fallback ) ) {
                        foreach ( $fallback as $key => $data ) {
                                $key = is_string( $key ) ? $key : (string) $key;
                                if ( '' === $key ) {
                                        continue;
                                }

                                if ( ! isset( $metadata[ $key ] ) ) {
                                        $metadata[ $key ] = array(
                                                'context'  => isset( $data['context'] ) ? (string) $data['context'] : '',
                                                'original' => isset( $data['original'] ) ? (string) $data['original'] : '',
                                                'updated'  => isset( $data['updated_at'] ) ? (string) $data['updated_at'] : '',
                                        );
                                } else {
                                        if ( '' === $metadata[ $key ]['context'] && isset( $data['context'] ) ) {
                                                $metadata[ $key ]['context'] = (string) $data['context'];
                                        }

                                        if ( '' === $metadata[ $key ]['original'] && isset( $data['original'] ) ) {
                                                $metadata[ $key ]['original'] = (string) $data['original'];
                                        }
                                }
                        }
                }

                return $metadata;
        }

	public static function get_quote_tracking(): array {
		return TranslationService::get_usage_stats();
	}

	public static function update_manual_string( string $key, string $language, string $value ): void {
			$key = self::normalize_manual_string_key( $key );
		if ( '' === $key ) {
				return;
		}

			$language = self::normalize_manual_string_language( $language );
		if ( '' === $language ) {
				return;
		}

			$value = self::sanitize_manual_string_value( $value );

			$strings      = self::get_manual_strings();
			$translations = isset( $strings[ $key ] ) && is_array( $strings[ $key ] ) ? $strings[ $key ] : array();
			$current      = $translations[ $language ] ?? null;

		if ( '' === $value ) {
			if ( ! isset( $translations[ $language ] ) ) {
					return;
			}

				unset( $translations[ $language ] );
			if ( empty( $translations ) ) {
					unset( $strings[ $key ] );
			} else {
					$strings[ $key ] = $translations;
			}
		} else {
			if ( $current === $value ) {
					return;
			}

				$translations[ $language ] = $value;
				$strings[ $key ]           = $translations;
		}

			update_option( self::MANUAL_STRINGS_OPTION, $strings );

			self::sync_manual_string_storage( $key, $strings[ $key ] ?? array() );

			TranslationService::flush_cache();

		if ( function_exists( 'do_action' ) ) {
				do_action(
					'fp_multilanguage_manual_string_updated',
					$key,
					$language,
					$value,
					$strings[ $key ] ?? array()
				);
		}
	}

	public static function get_enabled_providers(): array {
		$options = self::get_options();
		$enabled = array();
		foreach ( $options['providers'] as $name => $provider ) {
			if ( ! empty( $provider['enabled'] ) ) {
				$enabled[] = $name;
			}
		}

		return $enabled;
	}

	public static function get_provider_settings( string $provider ): array {
		$options = self::get_options();

		return $options['providers'][ $provider ] ?? array();
	}

	public static function get_source_language(): string {
		return self::get_options()['source_language'];
	}

	public static function get_fallback_language(): string {
		return self::get_options()['fallback_language'];
	}

        public static function get_target_languages(): array {
                return self::get_options()['target_languages'];
        }

        public static function get_translatable_post_types(): array {
                $postTypes = self::get_options()['post_types'] ?? array();

                return array_values( array_unique( array_map( 'strval', (array) $postTypes ) ) );
        }

        public static function get_translatable_taxonomies(): array {
                $taxonomies = self::get_options()['taxonomies'] ?? array();

                return array_values( array_unique( array_map( 'strval', (array) $taxonomies ) ) );
        }

        public static function get_translatable_meta_keys(): array {
                $customFields = self::get_options()['custom_fields'] ?? array();

                return array_values( array_unique( array_map( 'strval', (array) $customFields ) ) );
        }

        public static function is_auto_translate_enabled(): bool {
                        return (bool) self::get_options()['auto_translate'];
        }

	public static function clear_cache(): void {
			self::$cachedOptions = null;
	}

	private static function set_cached_options( array $options ): void {
					self::$cachedOptions = $options;
	}

	private static function normalize_manual_string_key( string $key ): string {
					$normalized = sanitize_key( $key );

			return $normalized;
	}

	private static function normalize_manual_string_language( string $language ): string {
					return sanitize_key( $language );
	}

	private static function sanitize_manual_string_value( string $value ): string {
		if ( function_exists( 'wp_kses_post' ) ) {
				$value = wp_kses_post( $value );
		} elseif ( function_exists( 'sanitize_text_field' ) ) {
				$value = sanitize_text_field( $value );
		}

			$value = preg_replace( '#<script\b[^>]*>(.*?)</script>#is', '', (string) $value );
		if ( null === $value ) {
				$value = '';
		}

					$value = trim( (string) $value );

			return $value;
	}

	private static function sync_manual_string_storage( string $key, array $translations ): void {
			self::sync_manual_string_table( $key, $translations );
			self::sync_manual_string_fallback( $key, $translations );
	}

	private static function sync_manual_string_table( string $key, array $translations ): void {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			return;
		}

		if ( empty( $wpdb->prefix ) ) {
			return;
		}

		$table = $wpdb->prefix . 'fp_multilanguage_strings';

		if ( ! self::manual_strings_table_exists( $table ) ) {
			return;
		}

		if ( empty( $translations ) ) {
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array( 'string_key' => $key ),
				array( '%s' )
			);

			return;
		}

		$context  = '';
		$original = '';

		$table_name = esc_sql( $table );
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name sanitized above.
		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT context, original FROM {$table_name} WHERE string_key = %s",
				$key
			),
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			'ARRAY_A'
		);

		if ( is_array( $existing ) ) {
			$context  = isset( $existing['context'] ) ? (string) $existing['context'] : '';
			$original = isset( $existing['original'] ) ? (string) $existing['original'] : '';
		} else {
			$fallback = get_option( 'fp_multilanguage_strings', array() );
			if ( isset( $fallback[ $key ] ) && is_array( $fallback[ $key ] ) ) {
				$context  = isset( $fallback[ $key ]['context'] ) ? (string) $fallback[ $key ]['context'] : '';
				$original = isset( $fallback[ $key ]['original'] ) ? (string) $fallback[ $key ]['original'] : '';
			}
		}

		if ( function_exists( 'wp_json_encode' ) ) {
			$translationsJson = wp_json_encode( $translations );
			if ( false === $translationsJson ) {
				$translationsJson = wp_json_encode( array() );
			}
		} else {
			$translationsJson = json_encode( $translations );
			if ( false === $translationsJson ) {
				$translationsJson = json_encode( array() );
			}
		}

		if ( false === $translationsJson || null === $translationsJson ) {
			$translationsJson = '[]';
		}

		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'string_key'   => $key,
				'context'      => $context,
				'original'     => $original,
				'translations' => $translationsJson,
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}
	private static function manual_strings_table_exists( string $table ): bool {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
				return false;
		}

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return null !== $exists;
	}

	private static function sync_manual_string_fallback( string $key, array $translations ): void {
			$option = get_option( 'fp_multilanguage_strings', array() );
		if ( ! is_array( $option ) ) {
				$option = array();
		}

			$entry = $option[ $key ] ?? array();
		if ( ! is_array( $entry ) ) {
				$entry = array();
		}

		if ( empty( $translations ) ) {
				unset( $entry['translations'] );

				$entryWithoutMeta = $entry;
				unset( $entryWithoutMeta['updated_at'] );

			if ( empty( $entryWithoutMeta ) ) {
					unset( $option[ $key ] );
			} else {
					$entry['updated_at'] = time();
					$option[ $key ]      = $entry;
			}
		} else {
				$entry['translations'] = $translations;
				$entry['updated_at']   = time();
				$option[ $key ]        = $entry;
		}

			update_option( 'fp_multilanguage_strings', $option );
	}
}
