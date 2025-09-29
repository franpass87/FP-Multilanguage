<?php
/**
 * Glossary settings view.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options  = isset( $options ) ? $options : array();
$glossary = isset( $glossary ) ? $glossary : FPML_Glossary::instance();
$entries  = $glossary->get_entries();
?>

<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
    <?php settings_fields( 'fpml_settings_group' ); ?>
    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row"><?php esc_html_e( 'Case sensitive', 'fp-multilanguage' ); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[glossary_case_sensitive]" value="1" <?php checked( $options['glossary_case_sensitive'], true ); ?> />
                    <?php esc_html_e( 'Applica le voci del glossario rispettando maiuscole/minuscole.', 'fp-multilanguage' ); ?>
                </label>
            </td>
        </tr>
        </tbody>
    </table>
    <?php submit_button( esc_html__( 'Salva impostazioni', 'fp-multilanguage' ) ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Voci glossario', 'fp-multilanguage' ); ?></h2>
<p><?php esc_html_e( 'Il glossario ha priorità assoluta sulle traduzioni automatiche: i termini italiani verranno sostituiti con le forme inglesi indicate prima e dopo la chiamata al provider.', 'fp-multilanguage' ); ?></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-block-form">
    <?php wp_nonce_field( 'fpml_save_glossary' ); ?>
    <input type="hidden" name="action" value="fpml_save_glossary" />
    <table class="widefat fpml-table">
        <thead>
        <tr>
            <th scope="col"><?php esc_html_e( 'Termine IT', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Traduzione EN', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Contesto', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Azioni', 'fp-multilanguage' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( empty( $entries ) ) : ?>
            <tr>
                <td colspan="4"><?php esc_html_e( 'Nessuna voce salvata.', 'fp-multilanguage' ); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ( $entries as $key => $row ) : ?>
                <tr>
                    <td>
                        <input type="text" class="regular-text" name="entries[<?php echo esc_attr( $key ); ?>][source]" value="<?php echo esc_attr( $row['source'] ); ?>" />
                    </td>
                    <td>
                        <input type="text" class="regular-text" name="entries[<?php echo esc_attr( $key ); ?>][target]" value="<?php echo esc_attr( $row['target'] ); ?>" />
                    </td>
                    <td>
                        <input type="text" class="regular-text" name="entries[<?php echo esc_attr( $key ); ?>][context]" value="<?php echo esc_attr( isset( $row['context'] ) ? $row['context'] : '' ); ?>" />
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" name="entries[<?php echo esc_attr( $key ); ?>][delete]" value="1" />
                            <?php esc_html_e( 'Elimina', 'fp-multilanguage' ); ?>
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <h3><?php esc_html_e( 'Aggiungi nuova voce', 'fp-multilanguage' ); ?></h3>
    <div class="fpml-flex-group">
        <p>
            <label for="fpml_new_glossary_source"><?php esc_html_e( 'Termine italiano', 'fp-multilanguage' ); ?></label><br />
            <input type="text" class="regular-text" id="fpml_new_glossary_source" name="new_glossary_source" />
        </p>
        <p>
            <label for="fpml_new_glossary_target"><?php esc_html_e( 'Traduzione inglese', 'fp-multilanguage' ); ?></label><br />
            <input type="text" class="regular-text" id="fpml_new_glossary_target" name="new_glossary_target" />
        </p>
        <p>
            <label for="fpml_new_glossary_context"><?php esc_html_e( 'Contesto (facoltativo)', 'fp-multilanguage' ); ?></label><br />
            <input type="text" class="regular-text" id="fpml_new_glossary_context" name="new_glossary_context" />
        </p>
    </div>

    <?php submit_button( esc_html__( 'Salva glossario', 'fp-multilanguage' ) ); ?>
</form>

<div class="fpml-grid fpml-import-export">
    <div>
        <h3><?php esc_html_e( 'Importa glossario', 'fp-multilanguage' ); ?></h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'fpml_import_glossary' ); ?>
            <input type="hidden" name="action" value="fpml_import_glossary" />
            <p>
                <label for="fpml_import_glossary_payload" class="screen-reader-text"><?php esc_html_e( 'Payload glossario', 'fp-multilanguage' ); ?></label>
                <textarea id="fpml_import_glossary_payload" name="payload" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Incolla JSON o CSV (header: source,target,context)', 'fp-multilanguage' ); ?>"></textarea>
            </p>
            <p>
                <label><input type="radio" name="format" value="json" checked /> <?php esc_html_e( 'JSON', 'fp-multilanguage' ); ?></label>
                <label><input type="radio" name="format" value="csv" /> <?php esc_html_e( 'CSV', 'fp-multilanguage' ); ?></label>
            </p>
            <?php submit_button( esc_html__( 'Importa', 'fp-multilanguage' ), 'secondary' ); ?>
        </form>
    </div>
    <div>
        <h3><?php esc_html_e( 'Export glossario', 'fp-multilanguage' ); ?></h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'fpml_export_glossary' ); ?>
            <input type="hidden" name="action" value="fpml_export_glossary" />
            <p>
                <select name="format">
                    <option value="json"><?php esc_html_e( 'JSON', 'fp-multilanguage' ); ?></option>
                    <option value="csv"><?php esc_html_e( 'CSV', 'fp-multilanguage' ); ?></option>
                </select>
            </p>
            <?php submit_button( esc_html__( 'Scarica', 'fp-multilanguage' ), 'secondary' ); ?>
        </form>
    </div>
</div>

<p class="description"><?php esc_html_e( 'Suggerimento: ordina le voci per lunghezza (termini più lunghi prima) per evitare sovrapposizioni indesiderate.', 'fp-multilanguage' ); ?></p>
