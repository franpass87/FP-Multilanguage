<?php
/**
 * Content settings view.
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
<th scope="row"><?php esc_html_e( 'Dimensione batch', 'fp-multilanguage' ); ?></th>
<td>
<input type="number" min="1" class="small-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[batch_size]" value="<?php echo esc_attr( $options['batch_size'] ); ?>" />
<p class="fpml-field-description"><?php esc_html_e( 'Numero di elementi processati per ciclo cron (post, termini, stringhe).', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Max caratteri per richiesta', 'fp-multilanguage' ); ?></th>
<td>
<input type="number" min="500" class="small-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[max_chars]" value="<?php echo esc_attr( $options['max_chars'] ); ?>" />
<p class="fpml-field-description"><?php esc_html_e( 'Limite per chunk inviato al provider (consigliato 4500).', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Max caratteri per batch', 'fp-multilanguage' ); ?></th>
<td>
<input type="number" min="0" class="small-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[max_chars_per_batch]" value="<?php echo esc_attr( $options['max_chars_per_batch'] ); ?>" />
<p class="fpml-field-description"><?php esc_html_e( 'Limite totale di caratteri elaborati in un singolo run della coda (0 per nessun limite).', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Frequenza cron', 'fp-multilanguage' ); ?></th>
<td>
<select name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[cron_frequency]">
<option value="5min" <?php selected( $options['cron_frequency'], '5min' ); ?>><?php esc_html_e( 'Ogni 5 minuti', 'fp-multilanguage' ); ?></option>
<option value="15min" <?php selected( $options['cron_frequency'], '15min' ); ?>><?php esc_html_e( 'Ogni 15 minuti', 'fp-multilanguage' ); ?></option>
<option value="hourly" <?php selected( $options['cron_frequency'], 'hourly' ); ?>><?php esc_html_e( 'Ogni ora', 'fp-multilanguage' ); ?></option>
</select>
<p class="fpml-field-description"><?php esc_html_e( 'Imposta la frequenza degli eventi WP-Cron per la coda.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Tono marketing', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[marketing_tone]" value="1" <?php checked( $options['marketing_tone'], true ); ?> />
<?php esc_html_e( 'Richiedi un tono leggermente promozionale nei testi.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Preserva HTML', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[preserve_html]" value="1" <?php checked( $options['preserve_html'], true ); ?> />
<?php esc_html_e( 'Mantieni tag HTML, shortcode e attributi senza tradurli.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Traduci slug', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" data-fpml-toggle-target="#fpml-slug-redirect" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[translate_slugs]" value="1" <?php checked( $options['translate_slugs'], true ); ?> />
<?php esc_html_e( 'Genera slug inglesi ottimizzati usando il provider.', 'fp-multilanguage' ); ?>
</label>
<p class="fpml-field-description"><?php esc_html_e( 'La traslitterazione base viene migliorata dal provider per mantenere semantica SEO.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr id="fpml-slug-redirect" style="<?php echo esc_attr( $options['translate_slugs'] ? '' : 'display:none;' ); ?>">
<th scope="row"><?php esc_html_e( 'Redirect slug precedenti', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[slug_redirect]" value="1" <?php checked( $options['slug_redirect'], true ); ?> />
<?php esc_html_e( 'Registra redirect 301 automatici quando cambia lo slug EN.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Whitelist meta', 'fp-multilanguage' ); ?></th>
<td>
<textarea name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[meta_whitelist]" rows="3" cols="50" class="large-text code"><?php echo esc_textarea( $options['meta_whitelist'] ); ?></textarea>
<p class="fpml-field-description"><?php esc_html_e( 'Meta key da copiare senza traduzione (separate da virgola).', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Regex esclusione campi', 'fp-multilanguage' ); ?></th>
<td>
<textarea name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[exclude_regex]" rows="3" cols="50" class="large-text code"><?php echo esc_textarea( $options['exclude_regex'] ); ?></textarea>
<p class="fpml-field-description"><?php esc_html_e( 'Pattern (uno per riga) per saltare campi o blocchi di contenuto.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Shortcode esclusi', 'fp-multilanguage' ); ?></th>
<td>
<textarea name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[excluded_shortcodes]" rows="3" cols="50" class="large-text code"><?php echo esc_textarea( $options['excluded_shortcodes'] ); ?></textarea>
<p class="fpml-field-description"><?php esc_html_e( 'Inserisci slug shortcode da ignorare (uno per riga).', 'fp-multilanguage' ); ?></p>
</td>
</tr>
</tbody>
</table>
<?php submit_button(); ?>
</form>
