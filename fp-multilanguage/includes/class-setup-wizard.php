<?php
/**
 * Setup Wizard interattivo per configurazione iniziale.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wizard di configurazione step-by-step.
 *
 * @since 0.4.0
 */
class FPML_Setup_Wizard {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Setup_Wizard|null
	 */
	protected static $instance = null;

	/**
	 * Settings reference.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Current step.
	 *
	 * @var int
	 */
	protected $current_step = 1;

	/**
	 * Total steps.
	 *
	 * @var int
	 */
	protected $total_steps = 5;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Setup_Wizard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->settings = FPML_Settings::instance();

		// Hook per redirect al wizard al primo avvio.
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );

		// Registra pagina wizard.
		add_action( 'admin_menu', array( $this, 'register_wizard_page' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_fpml_wizard_save_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_fpml_wizard_test_provider', array( $this, 'ajax_test_provider' ) );
		add_action( 'wp_ajax_fpml_wizard_detect_hosting', array( $this, 'ajax_detect_hosting' ) );
	}

	/**
	 * Redirect al wizard se non è stato completato.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function maybe_redirect_to_wizard() {
		// Solo per admin che possono gestire opzioni.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Solo se non è già stato completato.
		if ( $this->settings && $this->settings->get( 'setup_completed', false ) ) {
			return;
		}

		// Solo su pagine admin (non AJAX/cron).
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

	// Non redirect se siamo già nel wizard.
	if ( isset( $_GET['page'] ) && 'fpml-setup-wizard' === $_GET['page'] ) {
		return;
	}

	// Non redirect se l'utente vuole saltare il wizard (DEVE essere controllato PRIMA della pagina settings).
	if ( isset( $_GET['fpml_skip_wizard'] ) && '1' === $_GET['fpml_skip_wizard'] ) {
		// Segna il wizard come completato per evitare redirect futuri.
		$settings = $this->settings ? $this->settings->all() : array();
		$settings['setup_completed'] = true;
		update_option( FPML_Settings::OPTION_KEY, $settings );
		return;
	}

	// Non redirect se l'utente sta accedendo alle settings del plugin.
	if ( isset( $_GET['page'] ) && 'fpml-settings' === $_GET['page'] ) {
		return;
	}

		// Non redirect subito dopo l'attivazione (dà fastidio).
		$activation_redirect_done = get_option( 'fpml_activation_redirect_done', false );
		if ( ! $activation_redirect_done ) {
			update_option( 'fpml_activation_redirect_done', true, false );
			return;
		}

		// Redirect al wizard.
		wp_safe_redirect( admin_url( 'admin.php?page=fpml-setup-wizard' ) );
		exit;
	}

	/**
	 * Registra pagina wizard nel menu.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function register_wizard_page() {
		add_submenu_page(
			null, // Nascosto dal menu.
			__( 'Setup FP Multilanguage', 'fp-multilanguage' ),
			__( 'Setup', 'fp-multilanguage' ),
			'manage_options',
			'fpml-setup-wizard',
			array( $this, 'render_wizard' )
		);
	}

	/**
	 * Renderizza il wizard.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function render_wizard() {
		$this->current_step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		$this->current_step = max( 1, min( $this->total_steps, $this->current_step ) );

		?>
		<div class="wrap fpml-setup-wizard">
			<style>
				.fpml-setup-wizard { max-width: 800px; margin: 40px auto; }
				.fpml-wizard-header { text-align: center; margin-bottom: 40px; }
				.fpml-wizard-header h1 { font-size: 32px; margin-bottom: 10px; }
				.fpml-wizard-progress { display: flex; justify-content: space-between; margin: 30px 0; }
				.fpml-wizard-progress-step { flex: 1; text-align: center; position: relative; padding: 10px; }
				.fpml-wizard-progress-step:before { content: ''; position: absolute; top: 20px; left: 0; right: 50%; height: 2px; background: #ddd; z-index: 0; }
				.fpml-wizard-progress-step:first-child:before { display: none; }
				.fpml-wizard-progress-step.active:before,
				.fpml-wizard-progress-step.completed:before { background: #46b450; }
				.fpml-wizard-progress-number { width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; position: relative; z-index: 1; }
				.fpml-wizard-progress-step.active .fpml-wizard-progress-number { background: #2271b1; }
				.fpml-wizard-progress-step.completed .fpml-wizard-progress-number { background: #46b450; }
				.fpml-wizard-progress-label { display: block; margin-top: 10px; font-size: 12px; color: #666; }
				.fpml-wizard-content { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
				.fpml-wizard-step { display: none; }
				.fpml-wizard-step.active { display: block; }
				.fpml-wizard-buttons { margin-top: 30px; display: flex; justify-content: space-between; }
				.fpml-wizard-field { margin-bottom: 20px; }
				.fpml-wizard-field label { display: block; margin-bottom: 8px; font-weight: 600; }
				.fpml-wizard-field input[type="text"],
				.fpml-wizard-field input[type="password"],
				.fpml-wizard-field select { width: 100%; padding: 10px; font-size: 14px; }
				.fpml-wizard-field .description { font-size: 13px; color: #666; margin-top: 5px; }
				.fpml-test-result { padding: 10px; margin-top: 10px; border-radius: 4px; }
				.fpml-test-result.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
				.fpml-test-result.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
				.fpml-wizard-checklist label { display: block; margin: 10px 0; }
				.fpml-wizard-summary { background: #f9f9f9; padding: 20px; border-radius: 4px; margin: 20px 0; }
				.fpml-wizard-summary h3 { margin-top: 0; }
			</style>

			<div class="fpml-wizard-header">
				<h1>🚀 <?php esc_html_e( 'Benvenuto in FP Multilanguage', 'fp-multilanguage' ); ?></h1>
				<p><?php esc_html_e( 'Ti guideremo nella configurazione in 5 semplici step', 'fp-multilanguage' ); ?></p>
			</div>

			<!-- Progress Bar -->
			<div class="fpml-wizard-progress">
				<?php for ( $i = 1; $i <= $this->total_steps; $i++ ) : ?>
					<div class="fpml-wizard-progress-step <?php echo $i === $this->current_step ? 'active' : ( $i < $this->current_step ? 'completed' : '' ); ?>">
						<span class="fpml-wizard-progress-number"><?php echo esc_html( $i ); ?></span>
						<span class="fpml-wizard-progress-label"><?php echo esc_html( $this->get_step_label( $i ) ); ?></span>
					</div>
				<?php endfor; ?>
			</div>

			<div class="fpml-wizard-content">
				<?php
				// Renderizza step corrente.
				$method = 'render_step_' . $this->current_step;
				if ( method_exists( $this, $method ) ) {
					$this->$method();
				}
				?>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Test provider
			$('.fpml-test-provider').on('click', function() {
				var $btn = $(this);
				var $result = $('.fpml-test-result');
				var provider = $('select[name="provider"]').val();
				var apiKey = '';
				
				if (provider === 'openai') apiKey = $('input[name="openai_api_key"]').val();
				if (provider === 'google') apiKey = $('input[name="google_api_key"]').val();
				
				$btn.prop('disabled', true).text('<?php esc_html_e( 'Test in corso...', 'fp-multilanguage' ); ?>');
				$result.hide();
				
				$.post(ajaxurl, {
					action: 'fpml_wizard_test_provider',
					provider: provider,
					api_key: apiKey,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_wizard' ) ); ?>'
				}, function(response) {
					$btn.prop('disabled', false).text('<?php esc_html_e( 'Testa Connessione', 'fp-multilanguage' ); ?>');
					if (response.success) {
						$result.removeClass('error').addClass('success').html('✓ ' + response.data.message).show();
					} else {
						$result.removeClass('success').addClass('error').html('✗ ' + response.data.message).show();
					}
				});
			});

			// Auto-detect hosting
			$('.fpml-detect-hosting').on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php esc_html_e( 'Rilevamento...', 'fp-multilanguage' ); ?>');
				
				$.post(ajaxurl, {
					action: 'fpml_wizard_detect_hosting',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_wizard' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$('input[name="batch_size"]').val(response.data.batch_size);
						$('input[name="max_chars"]').val(response.data.max_chars);
						$('select[name="cron_frequency"]').val(response.data.cron_frequency);
						alert('✓ Impostazioni ottimizzate per il tuo hosting!');
					}
					$btn.prop('disabled', false).text('<?php esc_html_e( 'Rileva Automaticamente', 'fp-multilanguage' ); ?>');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Label per step.
	 *
	 * @since 0.4.0
	 *
	 * @param int $step Numero step.
	 *
	 * @return string
	 */
	protected function get_step_label( $step ) {
		$labels = array(
			1 => __( 'Benvenuto', 'fp-multilanguage' ),
			2 => __( 'Provider', 'fp-multilanguage' ),
			3 => __( 'Ottimizzazione', 'fp-multilanguage' ),
			4 => __( 'Funzionalità', 'fp-multilanguage' ),
			5 => __( 'Completa', 'fp-multilanguage' ),
		);

		return isset( $labels[ $step ] ) ? $labels[ $step ] : '';
	}

	/**
	 * Step 1: Benvenuto.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function render_step_1() {
		?>
		<div class="fpml-wizard-step active">
			<h2><?php esc_html_e( 'Benvenuto! 👋', 'fp-multilanguage' ); ?></h2>
			<p><?php esc_html_e( 'FP Multilanguage tradurrà automaticamente i tuoi contenuti da italiano a inglese usando AI di ultima generazione.', 'fp-multilanguage' ); ?></p>
			
			<h3><?php esc_html_e( 'Cosa faremo:', 'fp-multilanguage' ); ?></h3>
			<ul style="line-height: 2;">
				<li>✅ <?php esc_html_e( 'Configureremo il provider di traduzione (OpenAI o Google)', 'fp-multilanguage' ); ?></li>
				<li>✅ <?php esc_html_e( 'Ottimizzeremo le performance per il tuo hosting', 'fp-multilanguage' ); ?></li>
				<li>✅ <?php esc_html_e( 'Abiliteremo le funzionalità automatiche (auto-translate, SEO, health check)', 'fp-multilanguage' ); ?></li>
				<li>✅ <?php esc_html_e( 'Avvieremo il primo reindex per tradurre i contenuti esistenti', 'fp-multilanguage' ); ?></li>
			</ul>

			<p><strong><?php esc_html_e( 'Tempo stimato: 5 minuti', 'fp-multilanguage' ); ?></strong></p>

			<div class="fpml-wizard-buttons">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-settings&fpml_skip_wizard=1' ) ); ?>" class="button button-link">
					<?php esc_html_e( 'Salta wizard e vai alle impostazioni', 'fp-multilanguage' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-setup-wizard&step=2' ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Iniziamo!', 'fp-multilanguage' ); ?> →
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Step 2: Provider.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function render_step_2() {
		$current = $this->settings ? $this->settings->all() : array();
		?>
		<div class="fpml-wizard-step active">
			<h2><?php esc_html_e( 'Configura Provider di Traduzione', 'fp-multilanguage' ); ?></h2>
			<p><?php esc_html_e( 'Scegli il servizio che preferisci per le traduzioni automatiche:', 'fp-multilanguage' ); ?></p>

			<form method="post" id="fpml-wizard-step-2">
				<?php wp_nonce_field( 'fpml_wizard_step_2', 'fpml_wizard_nonce' ); ?>

				<div class="fpml-wizard-field">
					<label><?php esc_html_e( 'Provider Preferito', 'fp-multilanguage' ); ?></label>
					<select name="provider" required>
						<option value=""><?php esc_html_e( '-- Seleziona --', 'fp-multilanguage' ); ?></option>
						<option value="openai" <?php selected( $current['provider'] ?? '', 'openai' ); ?>>OpenAI (GPT-5) - <?php esc_html_e( 'Consigliato', 'fp-multilanguage' ); ?></option>
						<option value="google" <?php selected( $current['provider'] ?? '', 'google' ); ?>>Google Cloud Translation</option>
					</select>
					<p class="description"><?php esc_html_e( 'OpenAI offre la migliore qualità con contesto e tono naturale.', 'fp-multilanguage' ); ?></p>
				</div>

				<div class="fpml-wizard-field">
					<label><?php esc_html_e( 'API Key', 'fp-multilanguage' ); ?> <span style="color: red;">*</span></label>
					<input type="password" name="openai_api_key" value="<?php echo esc_attr( $current['openai_api_key'] ?? '' ); ?>" placeholder="sk-proj-..." autocomplete="off" style="width: 100%; padding: 10px; font-size: 14px;" />
					<input type="password" name="google_api_key" value="<?php echo esc_attr( $current['google_api_key'] ?? '' ); ?>" placeholder="AIza..." autocomplete="off" style="display:none; width: 100%; padding: 10px; font-size: 14px;" />
					<p class="description">
						<?php esc_html_e( 'Ottieni la tua chiave API su:', 'fp-multilanguage' ); ?>
						<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a> |
						<a href="https://console.cloud.google.com/" target="_blank">Google Cloud</a>
					</p>
				</div>

				<button type="button" class="button fpml-test-provider"><?php esc_html_e( 'Testa Connessione', 'fp-multilanguage' ); ?></button>
				<div class="fpml-test-result" style="display:none;"></div>

				<div class="fpml-wizard-buttons">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-setup-wizard&step=1' ) ); ?>" class="button button-large">
						← <?php esc_html_e( 'Indietro', 'fp-multilanguage' ); ?>
					</a>
					<div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-settings&fpml_skip_wizard=1' ) ); ?>" class="button button-link" style="margin-right: 10px;">
							<?php esc_html_e( 'Salta', 'fp-multilanguage' ); ?>
						</a>
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →
						</button>
					</div>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Gestione cambio provider
			$('select[name="provider"]').on('change', function() {
				$('input[type="password"]').hide().prop('required', false);
				if ($(this).val() === 'openai') {
					$('input[name="openai_api_key"]').show().prop('required', true);
				}
				if ($(this).val() === 'google') {
					$('input[name="google_api_key"]').show().prop('required', true);
				}
			}).trigger('change');

			// Submit form
			$('#fpml-wizard-step-2').on('submit', function(e) {
				e.preventDefault();
				
				// Validazione lato client
				var provider = $('select[name="provider"]').val();
				if (!provider) {
					alert('<?php esc_html_e( 'Seleziona un provider di traduzione.', 'fp-multilanguage' ); ?>');
					return false;
				}
				
				var apiKey = '';
				if (provider === 'openai') {
					apiKey = $('input[name="openai_api_key"]').val();
				} else if (provider === 'google') {
					apiKey = $('input[name="google_api_key"]').val();
				}
				
				if (!apiKey || apiKey.trim() === '') {
					alert('<?php esc_html_e( 'Inserisci la tua API key.', 'fp-multilanguage' ); ?>');
					return false;
				}
				
				// Disabilita il pulsante per evitare doppi submit
				var $submitBtn = $(this).find('button[type="submit"]');
				$submitBtn.prop('disabled', true).text('<?php esc_html_e( 'Salvataggio...', 'fp-multilanguage' ); ?>');
				
				// Prepara i dati - includi solo il campo API key visibile
				var formData = new FormData();
				formData.append('provider', provider);
				if (provider === 'openai') {
					formData.append('openai_api_key', $('input[name="openai_api_key"]').val());
				} else if (provider === 'google') {
					formData.append('google_api_key', $('input[name="google_api_key"]').val());
				}
				formData.append('action', 'fpml_wizard_save_step');
				formData.append('step', '2');
				formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'fpml_wizard' ) ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							window.location.href = '<?php echo esc_js( admin_url( 'admin.php?page=fpml-setup-wizard&step=3' ) ); ?>';
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Errore durante il salvataggio.', 'fp-multilanguage' ); ?>');
							$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →');
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Errore di connessione. Riprova.', 'fp-multilanguage' ); ?>');
						$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →');
					}
				});
				
				return false;
			});
		});
		</script>
		<?php
	}

	/**
	 * Step 3: Ottimizzazione.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function render_step_3() {
		$current = $this->settings ? $this->settings->all() : array();
		?>
		<div class="fpml-wizard-step active">
			<h2><?php esc_html_e( 'Ottimizzazione Performance', 'fp-multilanguage' ); ?></h2>
			<p><?php esc_html_e( 'Configuriamo i parametri ottimali per il tuo hosting:', 'fp-multilanguage' ); ?></p>

			<form method="post" id="fpml-wizard-step-3">
				<?php wp_nonce_field( 'fpml_wizard_step_3', 'fpml_wizard_nonce' ); ?>

				<p>
					<button type="button" class="button button-secondary fpml-detect-hosting">
						🔍 <?php esc_html_e( 'Rileva Automaticamente', 'fp-multilanguage' ); ?>
					</button>
				</p>

				<div class="fpml-wizard-field">
					<label><?php esc_html_e( 'Dimensione Batch', 'fp-multilanguage' ); ?></label>
					<input type="number" name="batch_size" value="<?php echo esc_attr( $current['batch_size'] ?? 5 ); ?>" min="1" max="20" required />
					<p class="description"><?php esc_html_e( 'Numero di job processati per volta (5-10 consigliato per hosting condivisi).', 'fp-multilanguage' ); ?></p>
				</div>

				<div class="fpml-wizard-field">
					<label><?php esc_html_e( 'Caratteri Massimi per Job', 'fp-multilanguage' ); ?></label>
					<input type="number" name="max_chars" value="<?php echo esc_attr( $current['max_chars'] ?? 4500 ); ?>" min="1000" max="10000" required />
					<p class="description"><?php esc_html_e( 'Lunghezza massima testo inviato al provider (4000-5000 consigliato).', 'fp-multilanguage' ); ?></p>
				</div>

				<div class="fpml-wizard-field">
					<label><?php esc_html_e( 'Frequenza Cron', 'fp-multilanguage' ); ?></label>
					<select name="cron_frequency" required>
						<option value="5min" <?php selected( $current['cron_frequency'] ?? '', '5min' ); ?>><?php esc_html_e( 'Ogni 5 minuti', 'fp-multilanguage' ); ?></option>
						<option value="15min" <?php selected( $current['cron_frequency'] ?? '', '15min' ); ?>><?php esc_html_e( 'Ogni 15 minuti (consigliato)', 'fp-multilanguage' ); ?></option>
						<option value="hourly" <?php selected( $current['cron_frequency'] ?? '', 'hourly' ); ?>><?php esc_html_e( 'Ogni ora', 'fp-multilanguage' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Frequenza di esecuzione della coda di traduzione.', 'fp-multilanguage' ); ?></p>
				</div>

				<div class="fpml-wizard-buttons">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-setup-wizard&step=2' ) ); ?>" class="button button-large">
						← <?php esc_html_e( 'Indietro', 'fp-multilanguage' ); ?>
					</a>
					<div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-settings&fpml_skip_wizard=1' ) ); ?>" class="button button-link" style="margin-right: 10px;">
							<?php esc_html_e( 'Salta', 'fp-multilanguage' ); ?>
						</a>
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →
						</button>
					</div>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#fpml-wizard-step-3').on('submit', function(e) {
				e.preventDefault();
				
				// Disabilita il pulsante per evitare doppi submit
				var $submitBtn = $(this).find('button[type="submit"]');
				$submitBtn.prop('disabled', true).text('<?php esc_html_e( 'Salvataggio...', 'fp-multilanguage' ); ?>');
				
				var data = $(this).serialize() + '&action=fpml_wizard_save_step&step=3&nonce=<?php echo esc_js( wp_create_nonce( 'fpml_wizard' ) ); ?>';
				$.post(ajaxurl, data, function(response) {
					if (response.success) {
						window.location.href = '<?php echo esc_js( admin_url( 'admin.php?page=fpml-setup-wizard&step=4' ) ); ?>';
					} else {
						alert(response.data.message || '<?php esc_html_e( 'Errore durante il salvataggio.', 'fp-multilanguage' ); ?>');
						$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →');
					}
				}).fail(function() {
					alert('<?php esc_html_e( 'Errore di connessione. Riprova.', 'fp-multilanguage' ); ?>');
					$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Step 4: Funzionalità.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function render_step_4() {
		?>
		<div class="fpml-wizard-step active">
			<h2><?php esc_html_e( 'Abilita Funzionalità Automatiche', 'fp-multilanguage' ); ?></h2>
			<p><?php esc_html_e( 'Seleziona le funzionalità che vuoi attivare:', 'fp-multilanguage' ); ?></p>

			<form method="post" id="fpml-wizard-step-4">
				<?php wp_nonce_field( 'fpml_wizard_step_4', 'fpml_wizard_nonce' ); ?>

				<div class="fpml-wizard-checklist">
					<label>
						<input type="checkbox" name="auto_translate_on_publish" value="1" checked />
						<strong><?php esc_html_e( 'Traduzione Automatica alla Pubblicazione', 'fp-multilanguage' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Traduci automaticamente i contenuti appena pubblicati (consigliato).', 'fp-multilanguage' ); ?></p>
					</label>

					<label>
						<input type="checkbox" name="auto_optimize_seo" value="1" checked />
						<strong><?php esc_html_e( 'Ottimizzazione SEO Automatica', 'fp-multilanguage' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Genera meta description, keyword e Open Graph tags (consigliato).', 'fp-multilanguage' ); ?></p>
					</label>

					<label>
						<input type="checkbox" name="enable_health_check" value="1" checked />
						<strong><?php esc_html_e( 'Health Check Automatico', 'fp-multilanguage' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Monitora e risolve problemi automaticamente (consigliato).', 'fp-multilanguage' ); ?></p>
					</label>

					<label>
						<input type="checkbox" name="enable_auto_detection" value="1" checked />
						<strong><?php esc_html_e( 'Rilevamento Automatico Contenuti', 'fp-multilanguage' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Rileva nuovi post types e tassonomie (consigliato).', 'fp-multilanguage' ); ?></p>
					</label>

					<label>
						<input type="checkbox" name="browser_redirect" value="1" />
						<strong><?php esc_html_e( 'Redirect Browser Automatico', 'fp-multilanguage' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Reindirizza utenti inglesi alla versione EN (opzionale).', 'fp-multilanguage' ); ?></p>
					</label>
				</div>

				<div class="fpml-wizard-buttons">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-setup-wizard&step=3' ) ); ?>" class="button button-large">
						← <?php esc_html_e( 'Indietro', 'fp-multilanguage' ); ?>
					</a>
					<div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-settings&fpml_skip_wizard=1' ) ); ?>" class="button button-link" style="margin-right: 10px;">
							<?php esc_html_e( 'Salta', 'fp-multilanguage' ); ?>
						</a>
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →
						</button>
					</div>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#fpml-wizard-step-4').on('submit', function(e) {
				e.preventDefault();
				
				// Disabilita il pulsante per evitare doppi submit
				var $submitBtn = $(this).find('button[type="submit"]');
				$submitBtn.prop('disabled', true).text('<?php esc_html_e( 'Salvataggio...', 'fp-multilanguage' ); ?>');
				
				var data = $(this).serialize() + '&action=fpml_wizard_save_step&step=4&nonce=<?php echo esc_js( wp_create_nonce( 'fpml_wizard' ) ); ?>';
				$.post(ajaxurl, data, function(response) {
					if (response.success) {
						window.location.href = '<?php echo esc_js( admin_url( 'admin.php?page=fpml-setup-wizard&step=5' ) ); ?>';
					} else {
						alert(response.data.message || '<?php esc_html_e( 'Errore durante il salvataggio.', 'fp-multilanguage' ); ?>');
						$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →');
					}
				}).fail(function() {
					alert('<?php esc_html_e( 'Errore di connessione. Riprova.', 'fp-multilanguage' ); ?>');
					$submitBtn.prop('disabled', false).text('<?php esc_html_e( 'Continua', 'fp-multilanguage' ); ?> →');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Step 5: Completa.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	protected function render_step_5() {
		$current = $this->settings ? $this->settings->all() : array();
		?>
		<div class="fpml-wizard-step active">
			<h2>🎉 <?php esc_html_e( 'Configurazione Completata!', 'fp-multilanguage' ); ?></h2>
			<p><?php esc_html_e( 'Il plugin è pronto! Ecco un riepilogo:', 'fp-multilanguage' ); ?></p>

			<div class="fpml-wizard-summary">
				<h3><?php esc_html_e( 'Riepilogo Configurazione', 'fp-multilanguage' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Provider:', 'fp-multilanguage' ); ?></strong> <?php echo esc_html( strtoupper( $current['provider'] ?? 'Non configurato' ) ); ?></li>
					<li><strong><?php esc_html_e( 'Batch Size:', 'fp-multilanguage' ); ?></strong> <?php echo esc_html( $current['batch_size'] ?? 5 ); ?> job</li>
					<li><strong><?php esc_html_e( 'Frequenza Cron:', 'fp-multilanguage' ); ?></strong> <?php echo esc_html( $current['cron_frequency'] ?? '15min' ); ?></li>
					<li><strong><?php esc_html_e( 'Auto-traduzione:', 'fp-multilanguage' ); ?></strong> <?php echo $current['auto_translate_on_publish'] ?? false ? '✓ Attiva' : '✗ Disattiva'; ?></li>
					<li><strong><?php esc_html_e( 'SEO Auto:', 'fp-multilanguage' ); ?></strong> <?php echo $current['auto_optimize_seo'] ?? false ? '✓ Attiva' : '✗ Disattiva'; ?></li>
					<li><strong><?php esc_html_e( 'Health Check:', 'fp-multilanguage' ); ?></strong> <?php echo $current['enable_health_check'] ?? false ? '✓ Attiva' : '✗ Disattiva'; ?></li>
				</ul>
			</div>

			<h3><?php esc_html_e( 'Prossimi Passi:', 'fp-multilanguage' ); ?></h3>
			<ol style="line-height: 2;">
				<li><?php esc_html_e( 'Vai su Diagnostics per avviare il reindex dei contenuti esistenti', 'fp-multilanguage' ); ?></li>
				<li><?php esc_html_e( 'Crea o modifica un post → verrà tradotto automaticamente!', 'fp-multilanguage' ); ?></li>
				<li><?php esc_html_e( 'Monitora lo stato dalla dashboard', 'fp-multilanguage' ); ?></li>
			</ol>

			<form method="post" id="fpml-wizard-complete">
				<?php wp_nonce_field( 'fpml_wizard_complete', 'fpml_wizard_nonce' ); ?>
				<input type="hidden" name="action" value="fpml_wizard_save_step" />
				<input type="hidden" name="step" value="complete" />
				
				<div class="fpml-wizard-buttons">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-setup-wizard&step=4' ) ); ?>" class="button button-large">
						← <?php esc_html_e( 'Indietro', 'fp-multilanguage' ); ?>
					</a>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Vai alla Dashboard', 'fp-multilanguage' ); ?> →
					</button>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#fpml-wizard-complete').on('submit', function(e) {
				e.preventDefault();
				var data = $(this).serialize();
				$.post(ajaxurl, data, function(response) {
					if (response.success) {
						window.location.href = '<?php echo esc_js( admin_url( 'options-general.php?page=fp-multilanguage' ) ); ?>';
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Salva step.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function ajax_save_step() {
		check_ajax_referer( 'fpml_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';

		if ( ! $step ) {
			wp_send_json_error( array( 'message' => __( 'Step non valido.', 'fp-multilanguage' ) ) );
		}

		// Salva le impostazioni.
		$settings = $this->settings ? $this->settings->all() : array();

		// Validazioni specifiche per step
		if ( '2' === $step ) {
			// Verifica che provider e api key siano presenti
			$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
			if ( ! $provider ) {
				wp_send_json_error( array( 'message' => __( 'Seleziona un provider di traduzione.', 'fp-multilanguage' ) ) );
			}
			
			$api_key_field = $provider . '_api_key';
			$api_key = isset( $_POST[ $api_key_field ] ) ? sanitize_text_field( $_POST[ $api_key_field ] ) : '';
			if ( ! $api_key || '' === trim( $api_key ) ) {
				wp_send_json_error( array( 'message' => __( 'Inserisci la tua API key.', 'fp-multilanguage' ) ) );
			}
		}

		foreach ( $_POST as $key => $value ) {
			if ( in_array( $key, array( 'action', 'step', 'nonce', 'fpml_wizard_nonce', '_wp_http_referer' ), true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$settings[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$settings[ $key ] = sanitize_text_field( $value );
			}
		}

		// Se step complete, marca setup come completato.
		if ( 'complete' === $step ) {
			$settings['setup_completed'] = true;
		}

		// Salva le impostazioni
		$updated = update_option( FPML_Settings::OPTION_KEY, $settings );

		// Verifica che il salvataggio sia andato a buon fine
		if ( false === $updated && ! get_option( FPML_Settings::OPTION_KEY ) ) {
			wp_send_json_error( array( 'message' => __( 'Errore durante il salvataggio delle impostazioni.', 'fp-multilanguage' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Impostazioni salvate.', 'fp-multilanguage' ) ) );
	}

	/**
	 * AJAX: Test provider.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function ajax_test_provider() {
		check_ajax_referer( 'fpml_wizard', 'nonce' );

		$provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';

		if ( ! $provider || ! $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Provider o API key mancanti.', 'fp-multilanguage' ) ) );
		}

		// Test semplice: prova a tradurre "Hello" → "Ciao".
		$test_text = 'Hello';

		try {
			// Simula chiamata al provider (in produzione useresti la classe provider reale).
			if ( 'openai' === $provider ) {
				$response = wp_remote_post(
					'https://api.openai.com/v1/chat/completions',
					array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $api_key,
							'Content-Type'  => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'model'    => 'gpt-4o-mini',
								'messages' => array(
									array(
										'role'    => 'user',
										'content' => 'Translate to Italian: ' . $test_text,
									),
								),
							)
						),
						'timeout' => 15,
					)
				);

				if ( is_wp_error( $response ) ) {
					wp_send_json_error( array( 'message' => $response->get_error_message() ) );
				}

				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $body['error'] ) ) {
					wp_send_json_error( array( 'message' => $body['error']['message'] ) );
				}

				wp_send_json_success( array( 'message' => __( 'Connessione riuscita! API key valida.', 'fp-multilanguage' ) ) );
			}

			// Altri provider...
			wp_send_json_success( array( 'message' => __( 'Test completato (implementazione da completare per questo provider).', 'fp-multilanguage' ) ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Rileva hosting.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function ajax_detect_hosting() {
		check_ajax_referer( 'fpml_wizard', 'nonce' );

		// Rileva parametri ottimali basati su hosting.
		$memory_limit = ini_get( 'memory_limit' );
		$max_execution = ini_get( 'max_execution_time' );

		$memory_mb = intval( $memory_limit );

		// Logica ottimizzazione.
		$batch_size = 5; // Default.
		$max_chars = 4500;
		$cron_frequency = '15min';

		if ( $memory_mb >= 256 ) {
			$batch_size = 10;
			$max_chars = 6000;
		}

		if ( $max_execution >= 300 ) {
			$batch_size = 15;
		}

		wp_send_json_success(
			array(
				'batch_size'     => $batch_size,
				'max_chars'      => $max_chars,
				'cron_frequency' => $cron_frequency,
			)
		);
	}
}
