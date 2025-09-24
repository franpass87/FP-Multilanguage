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
                                $selected   = isset( $provider['formality'] ) ? (string) $provider['formality'] : 'default';
                                foreach ( $formalities as $value => $label ) :
                                        ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php
                                endforeach;
                                ?>
                        </select>
                </p>
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


	private function sanitize_language( string $value ): string {
			$value = sanitize_text_field( strtolower( $value ) );
			$value = str_replace( array( ' ', '_' ), '-', $value );

			$value = preg_replace( '/[^a-z0-9-]/', '', $value );
		if ( null === $value ) {
				return '';
		}

			return trim( $value, '-' );
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

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'translations' => wp_json_encode( $translations ),
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( 'string_key' => $key ),
				array( '%s', '%s' ),
				array( '%s' )
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
