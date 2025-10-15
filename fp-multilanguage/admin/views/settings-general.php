<?php
/**
 * General settings view.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

$options = isset( $options ) ? $options : array();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
<?php settings_fields( 'fpml_settings_group' ); ?>
<table class="form-table" role="presentation">
<tbody>
<tr>
<th scope="row"><?php esc_html_e( 'Provider predefinito', 'fp-multilanguage' ); ?></th>
<td>
<select name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[provider]">
<option value="">&mdash; <?php esc_html_e( 'Seleziona', 'fp-multilanguage' ); ?> &mdash;</option>
<option value="openai" <?php selected( $options['provider'], 'openai' ); ?>>OpenAI (GPT-4o-mini) - <?php esc_html_e( 'Qualità alta, ~$0.15/1000 car.', 'fp-multilanguage' ); ?></option>
<option value="deepl" <?php selected( $options['provider'], 'deepl' ); ?>>DeepL - <?php esc_html_e( 'Ottima qualità, 500k car./mese gratis', 'fp-multilanguage' ); ?></option>
<option value="google" <?php selected( $options['provider'], 'google' ); ?>>Google Cloud - <?php esc_html_e( 'Affidabile, $20/milione car.', 'fp-multilanguage' ); ?></option>
<option value="libretranslate" <?php selected( $options['provider'], 'libretranslate' ); ?>>LibreTranslate - <?php esc_html_e( 'Open source, privacy, gratuito', 'fp-multilanguage' ); ?></option>
</select>
<p class="fpml-field-description">
<?php esc_html_e( 'Seleziona il provider di traduzione principale. La traduzione viene bloccata se la chiave manca.', 'fp-multilanguage' ); ?>
<br />
<strong><?php esc_html_e( '💡 Suggerimento:', 'fp-multilanguage' ); ?></strong>
<?php esc_html_e( 'DeepL offre il miglior rapporto qualità/prezzo per iniziare (500.000 caratteri gratis al mese). OpenAI è ottimo per contenuti tecnici. LibreTranslate è ideale per massima privacy.', 'fp-multilanguage' ); ?>
</p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Chiave OpenAI', 'fp-multilanguage' ); ?></th>
<td>
<input type="password" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[openai_api_key]" value="<?php echo esc_attr( $options['openai_api_key'] ); ?>" autocomplete="off" id="fpml-openai-api-key" />
<button 
	type="button" 
	class="button button-secondary" 
	id="fpml-check-openai-billing" 
	data-endpoint="<?php echo esc_url( rest_url( 'fpml/v1/check-billing' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	style="margin-left: 10px;">
	<?php esc_html_e( 'Verifica Billing', 'fp-multilanguage' ); ?>
</button>
<div id="fpml-billing-status" style="margin-top: 10px;"></div>
<p class="fpml-field-description">
<?php esc_html_e( 'Richiesto per usare l\'API OpenAI (modello gpt-4o-mini o successivi).', 'fp-multilanguage' ); ?>
<br />
<strong><?php esc_html_e( 'Come ottenere la chiave:', 'fp-multilanguage' ); ?></strong>
<?php
printf(
	/* translators: %s: URL to OpenAI API keys page */
	esc_html__( '1. Vai su %s', 'fp-multilanguage' ),
	'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys ↗</a>'
);
?>
<br />
<?php esc_html_e( '2. Crea un nuovo progetto o selezionane uno esistente', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '3. Clicca "Create new secret key" e copia la chiave (formato: sk-...)', 'fp-multilanguage' ); ?>
<br />
<br />
<strong style="color: #d63638;"><?php esc_html_e( '⚠️ IMPORTANTE - Configurazione billing richiesta:', 'fp-multilanguage' ); ?></strong>
<br />
<?php esc_html_e( 'OpenAI NON offre più crediti gratuiti. Prima di usare l\'API devi:', 'fp-multilanguage' ); ?>
<br />
<?php
printf(
	/* translators: %s: URL to OpenAI billing page */
	esc_html__( '• Configurare un metodo di pagamento su %s', 'fp-multilanguage' ),
	'<a href="https://platform.openai.com/account/billing/overview" target="_blank" rel="noopener"><strong>Billing OpenAI ↗</strong></a>'
);
?>
<br />
<?php esc_html_e( '• Caricare crediti (minimo $5) cliccando su "Add to credit balance"', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( 'Altrimenti riceverai un errore "quota exceeded" anche senza aver mai usato il servizio!', 'fp-multilanguage' ); ?>
<br />
<br />
<?php
printf(
	/* translators: %s: URL to OpenAI pricing page */
	esc_html__( '💰 Costi: ~$0.15 per 1000 caratteri con gpt-4o-mini. %s', 'fp-multilanguage' ),
	'<a href="https://openai.com/api/pricing/" target="_blank" rel="noopener">' . esc_html__( 'Vedi prezzi completi ↗', 'fp-multilanguage' ) . '</a>'
);
?>
</p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Modello OpenAI', 'fp-multilanguage' ); ?></th>
<td>
<input type="text" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[openai_model]" value="<?php echo esc_attr( $options['openai_model'] ); ?>" />
<p class="fpml-field-description"><?php esc_html_e( 'Modello consigliato: gpt-4o-mini. Assicurati che supporti input HTML.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Chiave DeepL', 'fp-multilanguage' ); ?></th>
<td>
<input type="password" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[deepl_api_key]" value="<?php echo esc_attr( $options['deepl_api_key'] ); ?>" autocomplete="off" />
<br />
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[deepl_use_free]" value="1" <?php checked( $options['deepl_use_free'], true ); ?> />
<?php esc_html_e( 'Uso account DeepL Free', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description">
<strong><?php esc_html_e( 'Come ottenere la chiave:', 'fp-multilanguage' ); ?></strong>
<br />
<?php
printf(
	/* translators: %s: URL to DeepL signup page */
	esc_html__( '1. Registrati su %s (piano Free o Pro)', 'fp-multilanguage' ),
	'<a href="https://www.deepl.com/pro#developer" target="_blank" rel="noopener">deepl.com/pro ↗</a>'
);
?>
<br />
<?php esc_html_e( '2. Accedi alla tua area account', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '3. Vai su "Account" → "API Keys" e copia la chiave', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '⚠️ IMPORTANTE: Spunta la casella "Uso account DeepL Free" se usi il piano gratuito (endpoint API diverso)', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '💰 Piano Free: 500.000 caratteri/mese gratis. Piano Pro: da €5.49/mese.', 'fp-multilanguage' ); ?>
</p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Google Cloud Translation', 'fp-multilanguage' ); ?></th>
<td>
<input type="text" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[google_project_id]" value="<?php echo esc_attr( $options['google_project_id'] ); ?>" placeholder="<?php esc_attr_e( 'Project ID (es: my-project-123)', 'fp-multilanguage' ); ?>" />
<br />
<input type="password" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[google_api_key]" value="<?php echo esc_attr( $options['google_api_key'] ); ?>" placeholder="<?php esc_attr_e( 'API Key', 'fp-multilanguage' ); ?>" autocomplete="off" />
<p class="fpml-field-description">
<?php esc_html_e( 'Supporto glossario se configurato nel progetto Google.', 'fp-multilanguage' ); ?>
<br />
<strong><?php esc_html_e( 'Come ottenere le credenziali:', 'fp-multilanguage' ); ?></strong>
<br />
<?php
printf(
	/* translators: %s: URL to Google Cloud Console */
	esc_html__( '1. Vai su %s', 'fp-multilanguage' ),
	'<a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com ↗</a>'
);
?>
<br />
<?php esc_html_e( '2. Crea un nuovo progetto o selezionane uno esistente (annota il Project ID)', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '3. Abilita "Cloud Translation API" nella sezione "API e servizi"', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '4. Vai su "Credenziali" → "Crea credenziali" → "Chiave API"', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '5. (Consigliato) Limita la chiave all\'API "Cloud Translation"', 'fp-multilanguage' ); ?>
<br />
<?php
printf(
	/* translators: %s: URL to Google Cloud pricing page */
	esc_html__( '💰 Costi: $20 per milione di caratteri. Primi $10/mese gratis con Free Tier. %s', 'fp-multilanguage' ),
	'<a href="https://cloud.google.com/translate/pricing" target="_blank" rel="noopener">' . esc_html__( 'Vedi prezzi ↗', 'fp-multilanguage' ) . '</a>'
);
?>
</p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'LibreTranslate', 'fp-multilanguage' ); ?></th>
<td>
<input type="url" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[libretranslate_api_url]" value="<?php echo esc_attr( $options['libretranslate_api_url'] ); ?>" placeholder="https://libretranslate.com" />
<br />
<input type="password" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[libretranslate_api_key]" value="<?php echo esc_attr( $options['libretranslate_api_key'] ); ?>" placeholder="<?php esc_attr_e( 'API Key (opzionale per istanze pubbliche)', 'fp-multilanguage' ); ?>" autocomplete="off" />
<p class="fpml-field-description">
<?php esc_html_e( 'Inserisci endpoint self-hosted per garantire performance e privacy.', 'fp-multilanguage' ); ?>
<br />
<strong><?php esc_html_e( 'Opzioni disponibili:', 'fp-multilanguage' ); ?></strong>
<br />
<?php
printf(
	/* translators: %s: URL to LibreTranslate official site */
	esc_html__( '🌐 Istanza pubblica: %s (gratuita, può richiedere chiave API)', 'fp-multilanguage' ),
	'<a href="https://libretranslate.com" target="_blank" rel="noopener">libretranslate.com ↗</a>'
);
?>
<br />
<?php
printf(
	/* translators: %s: URL to LibreTranslate GitHub */
	esc_html__( '🖥️ Self-hosted: Installa sul tuo server seguendo le istruzioni su %s', 'fp-multilanguage' ),
	'<a href="https://github.com/LibreTranslate/LibreTranslate" target="_blank" rel="noopener">GitHub ↗</a>'
);
?>
<br />
<?php esc_html_e( '💰 Costi: Gratuito (istanza pubblica con limiti) o costi hosting (self-hosted).', 'fp-multilanguage' ); ?>
<br />
<?php esc_html_e( '⚡ Consigliato per: Privacy, controllo completo, costi prevedibili.', 'fp-multilanguage' ); ?>
</p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Routing lingua', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="radio" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[routing_mode]" value="segment" <?php checked( $options['routing_mode'], 'segment' ); ?> />
<?php esc_html_e( 'Segmento /en/', 'fp-multilanguage' ); ?>
</label>
<br />
<label>
<input type="radio" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[routing_mode]" value="query" <?php checked( $options['routing_mode'], 'query' ); ?> />
<?php esc_html_e( 'Query string ?lang=en', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'Scegli il meccanismo preferito per la lingua inglese.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Redirect lingua browser', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[browser_redirect]" value="1" <?php checked( $options['browser_redirect'], true ); ?> />
<?php esc_html_e( 'Reindirizza automaticamente alla lingua inglese alla prima visita se il browser la preferisce.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'Il redirect rispetta il consenso cookie quando configurato.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Consenso cookie per redirect', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[browser_redirect_requires_consent]" value="1" <?php checked( $options['browser_redirect_requires_consent'], true ); ?> />
<?php esc_html_e( 'Attiva il redirect solo se il consenso cookie è stato espresso.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'Specifica il nome del cookie (es. cookieyes-consent) che indica il consenso. Se vuoto o assente, il redirect non avviene e non viene salvata alcuna preferenza.', 'fp-multilanguage' ); ?></p>
<input type="text" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[browser_redirect_consent_cookie]" value="<?php echo esc_attr( $options['browser_redirect_consent_cookie'] ); ?>" placeholder="<?php esc_attr_e( 'Nome cookie consenso', 'fp-multilanguage' ); ?>" />
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Modalità sandbox', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[sandbox_mode]" value="1" <?php checked( $options['sandbox_mode'], true ); ?> />
<?php esc_html_e( 'Esegui la pipeline senza salvare i risultati. Mostra anteprima diff e costi stimati.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Pulizia automatica coda', 'fp-multilanguage' ); ?></th>
<td>
<input type="number" class="small-text" min="0" max="365" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[queue_retention_days]" value="<?php echo esc_attr( $options['queue_retention_days'] ); ?>" />
<p class="fpml-field-description"><?php esc_html_e( 'Rimuove automaticamente i job completati, saltati o in errore più vecchi del numero di giorni indicato. Imposta 0 per disattivare.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Badge traduzioni', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[show_translation_badge]" value="1" <?php checked( $options['show_translation_badge'], true ); ?> />
<?php esc_html_e( 'Mostra il suffisso "(EN)" sui titoli tradotti nelle liste e negli strumenti admin.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Avviso editor', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[show_editor_notice]" value="1" <?php checked( $options['show_editor_notice'], true ); ?> />
<?php esc_html_e( 'Mostra un promemoria che le modifiche in italiano vengono replicate automaticamente in inglese.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Traduzione automatica alla pubblicazione', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[auto_translate_on_publish]" value="1" <?php checked( $options['auto_translate_on_publish'], true ); ?> />
<?php esc_html_e( 'Traduci automaticamente i contenuti appena vengono pubblicati (modalità sincrona).', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'I post verranno tradotti immediatamente alla pubblicazione. Puoi sovrascrivere questa impostazione per singoli post.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Ottimizzazione SEO automatica', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[auto_optimize_seo]" value="1" <?php checked( $options['auto_optimize_seo'], true ); ?> />
<?php esc_html_e( 'Genera automaticamente meta description, focus keyword e Open Graph tags per i contenuti tradotti.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'Compatibile con Yoast SEO, Rank Math, All in One SEO e SEOPress.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Health Check automatico', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[enable_health_check]" value="1" <?php checked( $options['enable_health_check'], true ); ?> />
<?php esc_html_e( 'Monitora lo stato del sistema e applica correzioni automatiche ogni ora.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'Rileva job bloccati, lock scaduti, errori ripetuti e li risolve automaticamente.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Rilevamento automatico contenuti', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[enable_auto_detection]" value="1" <?php checked( $options['enable_auto_detection'], true ); ?> />
<?php esc_html_e( 'Rileva automaticamente nuovi post types e tassonomie e suggerisci la traduzione.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'Quando installi nuovi plugin con custom post types, riceverai una notifica per abilitare la traduzione.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Auto-relink link interni', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[enable_auto_relink]" value="1" <?php checked( $options['enable_auto_relink'], true ); ?> />
<?php esc_html_e( 'Sostituisci automaticamente link interni nei contenuti tradotti.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'I link a post/pagine italiani vengono convertiti automaticamente alle versioni inglesi (consigliato per SEO).', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Sincronizzazione immagini in evidenza', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[sync_featured_images]" value="1" <?php checked( $options['sync_featured_images'], true ); ?> />
<?php esc_html_e( 'Sincronizza automaticamente le immagini in evidenza alle traduzioni.', 'fp-multilanguage' ); ?>
</label>
<br />
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[duplicate_featured_images]" value="1" <?php checked( $options['duplicate_featured_images'], true ); ?> />
<?php esc_html_e( 'Duplica le immagini invece di riutilizzarle (usa più spazio disco).', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Modalità Rush automatica', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[enable_rush_mode]" value="1" <?php checked( $options['enable_rush_mode'], true ); ?> />
<?php esc_html_e( 'Aumenta automaticamente performance quando la coda supera 500 job.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'Il sistema adatterà batch size e frequenza cron per smaltire la coda più velocemente.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Supporto Advanced Custom Fields', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[enable_acf_support]" value="1" <?php checked( $options['enable_acf_support'], true ); ?> />
<?php esc_html_e( 'Gestisci automaticamente relazioni ACF (post_object, relationship, taxonomy).', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'I campi ACF con relazioni verranno automaticamente collegati alle traduzioni corrette.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Integrazione automatica menu', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[auto_integrate_menu_switcher]" value="1" <?php checked( $options['auto_integrate_menu_switcher'], true ); ?> />
<?php esc_html_e( 'Aggiungi automaticamente il selettore lingua al menu principale del tema.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description">
<?php
$theme_compat = FPML_Theme_Compatibility::instance();
$theme_info = $theme_compat->get_theme_info();
if ( $theme_info['supported'] ) {
    printf(
        /* translators: %s: theme name */
        esc_html__( '✅ Tema rilevato: %s (supportato). Il selettore verrà integrato automaticamente nel menu.', 'fp-multilanguage' ),
        '<strong>' . esc_html( $theme_info['name'] ) . '</strong>'
    );
} else {
    printf(
        /* translators: %s: theme name */
        esc_html__( 'ℹ️ Tema rilevato: %s. Verrà applicato uno stile generico. Per risultati migliori, usa il Widget o lo shortcode.', 'fp-multilanguage' ),
        '<strong>' . esc_html( $theme_info['name'] ) . '</strong>'
    );
}
?>
</p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Stile selettore nel menu', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="radio" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[menu_switcher_style]" value="inline" <?php checked( $options['menu_switcher_style'], 'inline' ); ?> />
<?php esc_html_e( 'Inline (link affiancati)', 'fp-multilanguage' ); ?>
</label>
<br />
<label>
<input type="radio" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[menu_switcher_style]" value="dropdown" <?php checked( $options['menu_switcher_style'], 'dropdown' ); ?> />
<?php esc_html_e( 'Dropdown (menu a tendina)', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Bandierine nel menu', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[menu_switcher_show_flags]" value="1" <?php checked( $options['menu_switcher_show_flags'], true ); ?> />
<?php esc_html_e( 'Mostra bandierine 🇮🇹 🇬🇧 nel selettore del menu.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Posizione nel menu', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="radio" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[menu_switcher_position]" value="end" <?php checked( $options['menu_switcher_position'], 'end' ); ?> />
<?php esc_html_e( 'Alla fine (dopo tutti i link)', 'fp-multilanguage' ); ?>
</label>
<br />
<label>
<input type="radio" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[menu_switcher_position]" value="start" <?php checked( $options['menu_switcher_position'], 'start' ); ?> />
<?php esc_html_e( 'All\'inizio (prima di tutti i link)', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Elimina dati alla disinstallazione', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[remove_data]" value="1" <?php checked( $options['remove_data'], true ); ?> />
<?php esc_html_e( 'Cancella tabelle, opzioni e log quando il plugin viene rimosso.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
</tbody>
</table>
<?php submit_button(); ?>
</form>
