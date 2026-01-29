<?php
/**
 * SEO settings view.
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
<th scope="row"><?php esc_html_e( 'Noindex lingua EN', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[noindex_en]" value="1" <?php checked( $options['noindex_en'], true ); ?> />
<?php esc_html_e( 'Applica meta robots noindex,nofollow alle versioni inglesi.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Sitemap EN', 'fp-multilanguage' ); ?></th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[sitemap_en]" value="1" <?php checked( $options['sitemap_en'], true ); ?> />
<?php esc_html_e( 'Pubblica sitemap inglese e integra con plugin SEO presenti.', 'fp-multilanguage' ); ?>
</label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Tariffe provider (€/K caratteri)', 'fp-multilanguage' ); ?></th>
<td>
<div class="fpml-rate-grid">
<label>
<span><?php esc_html_e( 'OpenAI ($/1000 caratteri)', 'fp-multilanguage' ); ?></span><br />
<input type="text" class="small-text" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[rate_openai]" value="<?php echo esc_attr( $options['rate_openai'] ?: '0.00011' ); ?>" placeholder="0.00011" />
</label>
</div>
<p class="fpml-field-description"><?php esc_html_e( 'Utilizzato per stimare i costi nelle dashboard e nei report.', 'fp-multilanguage' ); ?></p>
</td>
</tr>
</tbody>
</table>
<?php submit_button(); ?>
</form>
