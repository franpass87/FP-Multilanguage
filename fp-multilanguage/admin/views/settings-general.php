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
<option value="openai" <?php selected( $options['provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'fp-multilanguage' ); ?></option>
<option value="deepl" <?php selected( $options['provider'], 'deepl' ); ?>><?php esc_html_e( 'DeepL', 'fp-multilanguage' ); ?></option>
<option value="google" <?php selected( $options['provider'], 'google' ); ?>><?php esc_html_e( 'Google Cloud Translation', 'fp-multilanguage' ); ?></option>
<option value="libretranslate" <?php selected( $options['provider'], 'libretranslate' ); ?>><?php esc_html_e( 'LibreTranslate', 'fp-multilanguage' ); ?></option>
</select>
<p class="fpml-field-description"><?php esc_html_e( 'Seleziona il provider di traduzione principale. La traduzione viene bloccata se la chiave manca.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Chiave OpenAI', 'fp-multilanguage' ); ?></th>
<td>
<input type="password" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[openai_api_key]" value="<?php echo esc_attr( $options['openai_api_key'] ); ?>" autocomplete="off" />
<p class="fpml-field-description"><?php esc_html_e( 'Richiesto per usare l\'API OpenAI (modello gpt-4o-mini o successivi).', 'fp-multilanguage' ); ?></p>
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
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[deepl_use_free]" value="1" <?php checked( $options['deepl_use_free'], true ); ?> />
<?php esc_html_e( 'Uso account DeepL Free', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Google Cloud Translation', 'fp-multilanguage' ); ?></th>
<td>
<input type="text" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[google_project_id]" value="<?php echo esc_attr( $options['google_project_id'] ); ?>" placeholder="<?php esc_attr_e( 'Project ID', 'fp-multilanguage' ); ?>" />
<br />
<input type="password" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[google_api_key]" value="<?php echo esc_attr( $options['google_api_key'] ); ?>" placeholder="<?php esc_attr_e( 'API Key', 'fp-multilanguage' ); ?>" autocomplete="off" />
<p class="fpml-field-description"><?php esc_html_e( 'Supporto glossario se configurato nel progetto Google.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'LibreTranslate', 'fp-multilanguage' ); ?></th>
<td>
<input type="url" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[libretranslate_api_url]" value="<?php echo esc_attr( $options['libretranslate_api_url'] ); ?>" placeholder="https://" />
<br />
<input type="password" class="regular-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[libretranslate_api_key]" value="<?php echo esc_attr( $options['libretranslate_api_key'] ); ?>" autocomplete="off" />
<p class="fpml-field-description"><?php esc_html_e( 'Inserisci endpoint self-hosted per garantire performance e privacy.', 'fp-multilanguage' ); ?></p>
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
