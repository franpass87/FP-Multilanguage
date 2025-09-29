<?php
/**
 * Strings settings view.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$options          = isset( $options ) ? $options : array();
$scanner          = isset( $scanner ) ? $scanner : FPML_Strings_Scanner::instance();
$overrides_object = isset( $overrides ) ? $overrides : FPML_Strings_Override::instance();
$catalog          = $scanner->get_catalog();
$total_strings    = count( $catalog );
$catalog_preview  = array_slice( $catalog, 0, 100, true );
$last_scan        = $scanner->get_last_scan_time();
$last_scan_label  = $last_scan ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_scan ) : esc_html__( 'Mai eseguita', 'fp-multilanguage' );
$overrides_list   = $overrides_object->get_overrides();
?>

<h2><?php esc_html_e( 'Scanner stringhe', 'fp-multilanguage' ); ?></h2>
<p>
    <?php
    printf(
        /* translators: 1: total strings, 2: last scan timestamp */
        esc_html__( 'Stringhe rilevate: %1$d. Ultima scansione: %2$s.', 'fp-multilanguage' ),
        absint( $total_strings ),
        esc_html( $last_scan_label )
    );
    ?>
</p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-inline-form">
    <?php wp_nonce_field( 'fpml_scan_strings' ); ?>
    <input type="hidden" name="action" value="fpml_scan_strings" />
    <?php submit_button( esc_html__( 'Scansiona stringhe attive', 'fp-multilanguage' ), 'secondary', 'submit', false ); ?>
</form>

<?php if ( ! empty( $catalog_preview ) ) : ?>
    <table class="widefat striped fpml-table">
        <thead>
        <tr>
            <th scope="col"><?php esc_html_e( 'Testo originale', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Dominio', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Contesto', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Occorrenze', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'File', 'fp-multilanguage' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ( $catalog_preview as $entry ) : ?>
            <tr>
                <td><code><?php echo esc_html( $entry['original'] ); ?></code></td>
                <td><?php echo '' !== $entry['domain'] ? esc_html( $entry['domain'] ) : '&mdash;'; ?></td>
                <td><?php echo '' !== $entry['context'] ? esc_html( $entry['context'] ) : '&mdash;'; ?></td>
                <td><?php echo esc_html( (string) $entry['occurrences'] ); ?></td>
                <td>
                    <?php
                    $locations = array();
                    foreach ( $entry['files'] as $file ) {
                        $file_path = isset( $file['file'] ) ? $file['file'] : '';
                        $line      = isset( $file['line'] ) ? (int) $file['line'] : 0;
                        $locations[] = $file_path ? $file_path . ( $line ? ':' . $line : '' ) : '';
                    }
                    $locations = array_filter( array_unique( $locations ) );

                    if ( empty( $locations ) ) {
                        echo '&mdash;';
                    } else {
                        echo esc_html( implode( ', ', $locations ) );
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ( $total_strings > count( $catalog_preview ) ) : ?>
        <p class="description">
            <?php
            printf(
                /* translators: %d: number of rows displayed */
                esc_html__( 'Mostrate le prime %d stringhe. Esegui una scansione completa per aggiornare il catalogo.', 'fp-multilanguage' ),
                count( $catalog_preview )
            );
            ?>
        </p>
    <?php endif; ?>
<?php else : ?>
    <p class="description"><?php esc_html_e( 'Nessuna stringa catalogata al momento. Avvia una scansione per popolare il catalogo.', 'fp-multilanguage' ); ?></p>
<?php endif; ?>

<hr />

<h2><?php esc_html_e( 'Override manuali', 'fp-multilanguage' ); ?></h2>
<p><?php esc_html_e( 'Definisci traduzioni manuali per stringhe specifiche usate nei temi o nei plugin attivi. Le traduzioni saranno applicate solo alla lingua inglese.', 'fp-multilanguage' ); ?></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-block-form">
    <?php wp_nonce_field( 'fpml_save_overrides' ); ?>
    <input type="hidden" name="action" value="fpml_save_overrides" />
    <table class="widefat fpml-table">
        <thead>
        <tr>
            <th scope="col"><?php esc_html_e( 'Originale (IT)', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Traduzione EN', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Contesto', 'fp-multilanguage' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Azioni', 'fp-multilanguage' ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( empty( $overrides_list ) ) : ?>
            <tr>
                <td colspan="4"><?php esc_html_e( 'Nessuna override presente.', 'fp-multilanguage' ); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ( $overrides_list as $hash => $row ) : ?>
                <tr>
                    <td>
                        <code><?php echo esc_html( $row['source'] ); ?></code>
                    </td>
                    <td>
                        <label for="fpml_override_<?php echo esc_attr( $hash ); ?>" class="screen-reader-text"><?php esc_html_e( 'Traduzione EN', 'fp-multilanguage' ); ?></label>
                        <input type="text" class="regular-text" id="fpml_override_<?php echo esc_attr( $hash ); ?>" name="overrides[<?php echo esc_attr( $hash ); ?>][target]" value="<?php echo esc_attr( $row['target'] ); ?>" />
                    </td>
                    <td>
                        <label for="fpml_override_context_<?php echo esc_attr( $hash ); ?>" class="screen-reader-text"><?php esc_html_e( 'Contesto', 'fp-multilanguage' ); ?></label>
                        <input type="text" class="regular-text" id="fpml_override_context_<?php echo esc_attr( $hash ); ?>" name="overrides[<?php echo esc_attr( $hash ); ?>][context]" value="<?php echo esc_attr( isset( $row['context'] ) ? $row['context'] : '' ); ?>" />
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" name="overrides[<?php echo esc_attr( $hash ); ?>][delete]" value="1" />
                            <?php esc_html_e( 'Elimina', 'fp-multilanguage' ); ?>
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <h3><?php esc_html_e( 'Aggiungi nuova override', 'fp-multilanguage' ); ?></h3>
    <div class="fpml-flex-group">
        <p>
            <label for="fpml_new_override_source"><?php esc_html_e( 'Stringa originale (IT)', 'fp-multilanguage' ); ?></label><br />
            <input type="text" class="regular-text" id="fpml_new_override_source" name="new_source" />
        </p>
        <p>
            <label for="fpml_new_override_target"><?php esc_html_e( 'Traduzione EN', 'fp-multilanguage' ); ?></label><br />
            <input type="text" class="regular-text" id="fpml_new_override_target" name="new_target" />
        </p>
        <p>
            <label for="fpml_new_override_context"><?php esc_html_e( 'Contesto (facoltativo)', 'fp-multilanguage' ); ?></label><br />
            <input type="text" class="regular-text" id="fpml_new_override_context" name="new_context" />
        </p>
    </div>

    <?php submit_button( esc_html__( 'Salva override', 'fp-multilanguage' ) ); ?>
</form>

<div class="fpml-grid fpml-import-export">
    <div>
        <h3><?php esc_html_e( 'Importa override', 'fp-multilanguage' ); ?></h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'fpml_import_overrides' ); ?>
            <input type="hidden" name="action" value="fpml_import_overrides" />
            <p>
                <label for="fpml_import_overrides_payload" class="screen-reader-text"><?php esc_html_e( 'Payload override', 'fp-multilanguage' ); ?></label>
                <textarea id="fpml_import_overrides_payload" name="payload" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Incolla JSON o CSV (header: source,target,context)', 'fp-multilanguage' ); ?>"></textarea>
            </p>
            <p>
                <label><input type="radio" name="format" value="json" checked /> <?php esc_html_e( 'JSON', 'fp-multilanguage' ); ?></label>
                <label><input type="radio" name="format" value="csv" /> <?php esc_html_e( 'CSV', 'fp-multilanguage' ); ?></label>
            </p>
            <?php submit_button( esc_html__( 'Importa', 'fp-multilanguage' ), 'secondary' ); ?>
        </form>
    </div>
    <div>
        <h3><?php esc_html_e( 'Export override', 'fp-multilanguage' ); ?></h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'fpml_export_overrides' ); ?>
            <input type="hidden" name="action" value="fpml_export_overrides" />
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
