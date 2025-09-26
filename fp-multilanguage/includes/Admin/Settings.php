<?php
namespace FPMultilanguage\Admin;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings\ManualStringsUI;
use FPMultilanguage\Admin\Settings\Repository as SettingsRepository;
use FPMultilanguage\Admin\Settings\RestController as SettingsRestController;
use FPMultilanguage\Services\Logger;

class Settings {

	public const OPTION_NAME = SettingsRepository::OPTION_NAME;

	public const MANUAL_STRINGS_OPTION = SettingsRepository::MANUAL_STRINGS_OPTION;

	public const NONCE_ACTION = SettingsRestController::NONCE_ACTION;

	public const REST_NAMESPACE = SettingsRestController::REST_NAMESPACE;

	private Logger $logger;

	private AdminNotices $notices;

	private SettingsRepository $repository;

	private ManualStringsUI $manualStrings;

	private SettingsRestController $restController;

	private static ?SettingsRepository $repositoryInstance = null;

	public function __construct(
		Logger $logger,
		AdminNotices $notices,
		SettingsRepository $repository,
		ManualStringsUI $manualStrings,
		SettingsRestController $restController
	) {
		$this->logger         = $logger;
		$this->notices        = $notices;
		$this->repository     = $repository;
		$this->manualStrings  = $manualStrings;
		$this->restController = $restController;

		self::$repositoryInstance = $repository;
		$this->repository->register_cache_hooks();
	}

	public static function bootstrap_defaults(): void {
		self::repository()->bootstrap_defaults();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->restController->register_hooks();
		add_action( 'admin_post_fp_multilanguage_save_strings', array( $this->manualStrings, 'handle_save' ) );

		$this->notices->register();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Registered FP Multilanguage admin settings hooks.' );
		}
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
				'default'           => $this->repository->get_defaults(),
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
							'emptyValue'          => __( 'Non impostato', 'fp-multilanguage' ),
							'autoEnabled'         => __( 'Attivo', 'fp-multilanguage' ),
							'autoDisabled'        => __( 'Disattivo', 'fp-multilanguage' ),
							'providersDisabled'   => __( 'Nessun provider attivo', 'fp-multilanguage' ),
							/* translators: %s: elenco dei provider che richiedono la verifica delle credenziali. */
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
			$this->manualStrings->render_page();
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
				$label              = isset( $object->labels->singular_name ) && $object->labels->singular_name !== ''
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

		$size = max( 4, min( 10, count( $available ) ) );
		?>
<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[post_types][]" multiple size="<?php echo esc_attr( (string) $size ); ?>">
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
			$options   = $this->get_options();
			$selected  = array_map( 'strval', (array) ( $options['taxonomies'] ?? array() ) );
			$available = array();

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

		$size = max( 4, min( 10, count( $available ) ) );
		?>
<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[taxonomies][]" multiple size="<?php echo esc_attr( (string) $size ); ?>">
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
					$options   = $this->get_options();
					$provider  = $options['providers']['google'] ?? array();
					$labelText = $this->get_provider_label( 'google' );
		?>
				<div class="fp-multilanguage-provider" data-provider="google" data-provider-label="<?php echo esc_attr( $labelText ); ?>">
						<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][google][enabled]" value="1" <?php checked( ! empty( $provider['enabled'] ) ); ?>>
		<?php
		/* translators: %s: nome descrittivo del provider di traduzione. */
		printf( esc_html__( 'Abilita %s', 'fp-multilanguage' ), esc_html( $labelText ) );
		?>
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

			$emptyLabel       = __( 'Non impostato', 'fp-multilanguage' );
			$targetDisplay    = '' !== trim( $targetsString ) ? $targetsString : $emptyLabel;
			$providerSummary  = empty( $providerLabels ) ? __( 'Nessun provider attivo', 'fp-multilanguage' ) : implode( ', ', $providerLabels );
			$autoSummaryLabel = $autoEnabled ? __( 'Attivo', 'fp-multilanguage' ) : __( 'Disattivo', 'fp-multilanguage' );
			$taxonomySummary  = empty( $taxonomyLabels ) ? $emptyLabel : implode( ', ', $taxonomyLabels );
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
					$options   = $this->get_options();
					$provider  = $options['providers']['deepl'] ?? array();
					$labelText = $this->get_provider_label( 'deepl' );
		?>
				<div class="fp-multilanguage-provider" data-provider="deepl" data-provider-label="<?php echo esc_attr( $labelText ); ?>">
						<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[providers][deepl][enabled]" value="1" <?php checked( ! empty( $provider['enabled'] ) ); ?>>
		<?php
		/* translators: %s: nome descrittivo del provider di traduzione. */
		printf( esc_html__( 'Abilita %s', 'fp-multilanguage' ), esc_html( $labelText ) );
		?>
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
			return $this->repository->sanitize_options( $input );
	}

	public static function get_options(): array {
			return self::repository()->get_options();
	}

	public static function get_manual_strings(): array {
			return self::repository()->get_manual_strings();
	}

	public static function get_manual_strings_catalog(): array {
			return self::repository()->get_manual_strings_catalog();
	}

	public static function get_quote_tracking(): array {
			return self::repository()->get_quote_tracking();
	}

	public static function update_manual_string( string $key, string $language, string $value ): void {
			self::repository()->update_manual_string( $key, $language, $value );
	}

	public static function get_enabled_providers(): array {
			return self::repository()->get_enabled_providers();
	}

	public static function get_provider_settings( string $provider ): array {
			return self::repository()->get_provider_settings( $provider );
	}

	public static function get_source_language(): string {
			return self::repository()->get_source_language();
	}

	public static function get_fallback_language(): string {
			return self::repository()->get_fallback_language();
	}

	public static function get_target_languages(): array {
			return self::repository()->get_target_languages();
	}

	public static function get_translatable_post_types(): array {
			return self::repository()->get_translatable_post_types();
	}

	public static function get_translatable_taxonomies(): array {
			return self::repository()->get_translatable_taxonomies();
	}

	public static function get_translatable_meta_keys(): array {
			return self::repository()->get_translatable_meta_keys();
	}

	public static function is_auto_translate_enabled(): bool {
			return self::repository()->is_auto_translate_enabled();
	}

	public static function clear_cache(): void {
			$repository = self::repository();

			$repository->clear_cache();
			$repository->clear_manual_strings_cache();
			$repository->clear_manual_strings_metadata_cache();
	}

	public static function clear_manual_strings_cache(): void {
			self::repository()->clear_manual_strings_cache();
	}

	public static function clear_manual_strings_metadata_cache(): void {
			self::repository()->clear_manual_strings_metadata_cache();
	}

	private static function repository(): SettingsRepository {
		if ( null === self::$repositoryInstance ) {
				self::$repositoryInstance = new SettingsRepository( new AdminNotices( new Logger() ) );
		}

		return self::$repositoryInstance;
	}
}
