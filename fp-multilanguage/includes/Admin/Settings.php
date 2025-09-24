<?php
namespace FPMultilanguage\Admin;

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
		'providers'         => array(
			'google' => array(
				'enabled' => true,
				'api_key' => '',
				'timeout' => 20,
			),
			'deepl'  => array(
				'enabled'  => true,
				'api_key'  => '',
				'endpoint' => 'https://api.deepl.com/v2/translate',
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
	}

	public function register_menu(): void {
		add_options_page(
			__( 'FP Multilanguage', 'fp-multilanguage' ),
			__( 'FP Multilanguage', 'fp-multilanguage' ),
			'manage_options',
			'fp-multilanguage-settings',
			array( $this, 'render_page' )
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
	}

	public function rest_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	public function rest_get_settings( WP_REST_Request $request ): WP_REST_Response {
			unset( $request );

			return rest_ensure_response( $this->get_options() );
	}

	public function rest_update_settings( WP_REST_Request $request ) {
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

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'settings_page_fp-multilanguage-settings' ) {
			return;
		}

		wp_enqueue_script(
			'fp-multilanguage-admin',
			FP_MULTILANGUAGE_URL . 'assets/js/admin.js',
			array( 'wp-element' ),
			FP_MULTILANGUAGE_VERSION,
			true
		);

		wp_localize_script(
			'fp-multilanguage-admin',
			'fpMultilanguageSettings',
			array(
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'options' => $this->get_options(),
				'restUrl' => rest_url( self::REST_NAMESPACE . '/settings' ),
			)
		);
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

	public function render_google_field(): void {
		$options  = $this->get_options();
		$provider = $options['providers']['google'] ?? array();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][google][enabled]" value="1" <?php checked( ! empty( $provider['enabled'] ) ); ?>>
			<?php esc_html_e( 'Abilita Google Cloud Translation', 'fp-multilanguage' ); ?>
		</label>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][google][api_key]" value="<?php echo esc_attr( $provider['api_key'] ?? '' ); ?>">
		<?php
	}

	public function render_deepl_field(): void {
		$options  = $this->get_options();
		$provider = $options['providers']['deepl'] ?? array();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][deepl][enabled]" value="1" <?php checked( ! empty( $provider['enabled'] ) ); ?>>
			<?php esc_html_e( 'Abilita DeepL', 'fp-multilanguage' ); ?>
		</label>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][deepl][api_key]" value="<?php echo esc_attr( $provider['api_key'] ?? '' ); ?>">
		<p class="description"><?php esc_html_e( 'Utilizza endpoint EU/US personalizzato se necessario.', 'fp-multilanguage' ); ?></p>
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

                foreach ( array( 'google', 'deepl' ) as $provider ) {
                        $providerOptions            = $sanitized['providers'][ $provider ] ?? array();
			$providerOptions            = wp_parse_args( $providerOptions, self::$defaults['providers'][ $provider ] );
			$providerOptions['enabled'] = ! empty( $providerOptions['enabled'] );
			$providerOptions['api_key'] = sanitize_text_field( $providerOptions['api_key'] );
			if ( isset( $providerOptions['endpoint'] ) ) {
				$providerOptions['endpoint'] = esc_url_raw( $providerOptions['endpoint'] );
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

                return $sanitized;
        }


	private function sanitize_language( string $value ): string {
		$value = sanitize_text_field( strtolower( $value ) );

		return preg_replace( '/[^a-z0-9_-]/', '', $value );
	}

        public static function get_options(): array {
                if ( null === self::$cachedOptions ) {
                        if ( ! function_exists( 'get_option' ) ) {
                                self::$cachedOptions = self::$defaults;
                        } else {
                                $optionsRaw = get_option( self::OPTION_NAME, array() );
                                self::$cachedOptions = wp_parse_args( is_array( $optionsRaw ) ? $optionsRaw : array(), self::$defaults );
                        }
                }

                $options = self::$cachedOptions;
                $options['quote_tracking'] = self::get_quote_tracking();

                return $options;
        }

        public static function get_manual_strings(): array {
                $stored = get_option( self::MANUAL_STRINGS_OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	public static function get_quote_tracking(): array {
		return TranslationService::get_usage_stats();
	}

	public static function update_manual_string( string $key, string $language, string $value ): void {
		$strings = self::get_manual_strings();
		if ( ! isset( $strings[ $key ] ) || ! is_array( $strings[ $key ] ) ) {
			$strings[ $key ] = array();
		}

		$strings[ $key ][ $language ] = $value;
		update_option( self::MANUAL_STRINGS_OPTION, $strings );
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

        public static function is_auto_translate_enabled(): bool {
                return (bool) self::get_options()['auto_translate'];
        }

        public static function clear_cache(): void {
                self::$cachedOptions = null;
        }

        private static function set_cached_options( array $options ): void {
                self::$cachedOptions = $options;
        }
}
