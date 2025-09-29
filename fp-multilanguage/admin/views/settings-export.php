<?php
/**
 * Export & Import view.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

$previews = isset( $exporter ) ? $exporter->get_sandbox_previews() : array();
?>
<div class="fpml-grid fpml-import-export">
        <div>
                <h2><?php esc_html_e( 'Stato traduzioni', 'fp-multilanguage' ); ?></h2>
                <p><?php esc_html_e( 'Esporta o importa lo stato dei campi tradotti (post, tassonomie e menu) per sincronizzare ambienti diversi o ripristinare backup.', 'fp-multilanguage' ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-inline-form">
                        <?php wp_nonce_field( 'fpml_export_state' ); ?>
                        <input type="hidden" name="action" value="fpml_export_state" />
                        <label>
                                <span class="screen-reader-text"><?php esc_html_e( 'Formato export stato traduzioni', 'fp-multilanguage' ); ?></span>
                                <select name="format">
                                        <option value="json"><?php esc_html_e( 'JSON', 'fp-multilanguage' ); ?></option>
                                        <option value="csv"><?php esc_html_e( 'CSV', 'fp-multilanguage' ); ?></option>
                                </select>
                        </label>
                        <?php submit_button( __( 'Esporta stato', 'fp-multilanguage' ), 'secondary', 'submit', false ); ?>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-block-form">
                        <?php wp_nonce_field( 'fpml_import_state' ); ?>
                        <input type="hidden" name="action" value="fpml_import_state" />
                        <p class="fpml-field-description"><?php esc_html_e( 'Incolla qui il contenuto JSON o CSV esportato in precedenza per riallineare gli stati di revisione.', 'fp-multilanguage' ); ?></p>
                        <p>
                                <label for="fpml-import-state-format"><?php esc_html_e( 'Formato sorgente', 'fp-multilanguage' ); ?></label>
                                <select id="fpml-import-state-format" name="format">
                                        <option value="json"><?php esc_html_e( 'JSON', 'fp-multilanguage' ); ?></option>
                                        <option value="csv"><?php esc_html_e( 'CSV', 'fp-multilanguage' ); ?></option>
                                </select>
                        </p>
                        <p>
                                <label for="fpml-import-state-payload" class="screen-reader-text"><?php esc_html_e( 'Payload stato traduzioni', 'fp-multilanguage' ); ?></label>
                                <textarea id="fpml-import-state-payload" name="payload" rows="6"></textarea>
                        </p>
                        <?php submit_button( __( 'Importa stato traduzioni', 'fp-multilanguage' ) ); ?>
                </form>
        </div>

        <div>
                <h2><?php esc_html_e( 'Log diagnostici', 'fp-multilanguage' ); ?></h2>
                <p><?php esc_html_e( 'Scarica o carica i log per analisi offline: gli eventi importati vengono aggiunti mantenendo l’anonimizzazione se attiva.', 'fp-multilanguage' ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-inline-form">
                        <?php wp_nonce_field( 'fpml_export_logs' ); ?>
                        <input type="hidden" name="action" value="fpml_export_logs" />
                        <label>
                                <span class="screen-reader-text"><?php esc_html_e( 'Formato export log', 'fp-multilanguage' ); ?></span>
                                <select name="format">
                                        <option value="json"><?php esc_html_e( 'JSON', 'fp-multilanguage' ); ?></option>
                                        <option value="csv"><?php esc_html_e( 'CSV', 'fp-multilanguage' ); ?></option>
                                </select>
                        </label>
                        <?php submit_button( __( 'Esporta log', 'fp-multilanguage' ), 'secondary', 'submit', false ); ?>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-block-form">
                        <?php wp_nonce_field( 'fpml_import_logs' ); ?>
                        <input type="hidden" name="action" value="fpml_import_logs" />
                        <p class="fpml-field-description"><?php esc_html_e( 'Incolla un export JSON o CSV per unire i log con l’istanza corrente (massimo 200 elementi).', 'fp-multilanguage' ); ?></p>
                        <p>
                                <label for="fpml-import-logs-format"><?php esc_html_e( 'Formato sorgente', 'fp-multilanguage' ); ?></label>
                                <select id="fpml-import-logs-format" name="format">
                                        <option value="json"><?php esc_html_e( 'JSON', 'fp-multilanguage' ); ?></option>
                                        <option value="csv"><?php esc_html_e( 'CSV', 'fp-multilanguage' ); ?></option>
                                </select>
                        </p>
                        <p>
                                <label for="fpml-import-logs-payload" class="screen-reader-text"><?php esc_html_e( 'Payload log', 'fp-multilanguage' ); ?></label>
                                <textarea id="fpml-import-logs-payload" name="payload" rows="6"></textarea>
                        </p>
                        <?php submit_button( __( 'Importa log', 'fp-multilanguage' ) ); ?>
                </form>
        </div>
</div>

<div class="fpml-sandbox-panel">
        <h2><?php esc_html_e( 'Modalità sandbox', 'fp-multilanguage' ); ?></h2>
        <p><?php esc_html_e( 'Quando la modalità sandbox è attiva, i job vengono processati senza salvare le modifiche: qui trovi l’anteprima delle ultime traduzioni simulate con caratteri, costo stimato e collegamenti utili.', 'fp-multilanguage' ); ?></p>

        <?php if ( ! empty( $previews ) ) : ?>
                <table class="widefat fixed fpml-table fpml-sandbox-table">
                        <thead>
                                <tr>
                                        <th scope="col"><?php esc_html_e( 'Data', 'fp-multilanguage' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Oggetto', 'fp-multilanguage' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Campo', 'fp-multilanguage' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Provider', 'fp-multilanguage' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Caratteri / Parole', 'fp-multilanguage' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Costo stimato', 'fp-multilanguage' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Estratto sorgente', 'fp-multilanguage' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Estratto tradotto', 'fp-multilanguage' ); ?></th>
                                </tr>
                        </thead>
                        <tbody>
                                <?php foreach ( $previews as $preview ) : ?>
                                        <tr>
                                                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $preview['timestamp'] ) ); ?></td>
                                                <td>
                                                        <?php
                                                        $object_label = sprintf( '#%d', isset( $preview['object_id'] ) ? absint( $preview['object_id'] ) : 0 );
                                                        if ( ! empty( $preview['translation_url'] ) ) {
                                                                printf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $preview['translation_url'] ), esc_html( $object_label ) );
                                                        } else {
                                                                echo esc_html( $object_label );
                                                        }
                                                        ?>
                                                </td>
                                                <td><?php echo esc_html( isset( $preview['field'] ) ? $preview['field'] : '' ); ?></td>
                                                <td><?php echo esc_html( isset( $preview['provider'] ) ? $preview['provider'] : '' ); ?></td>
                                                <td>
                                                        <?php
                                                        $chars = isset( $preview['characters'] ) ? absint( $preview['characters'] ) : 0;
                                                        $words = isset( $preview['word_count'] ) ? absint( $preview['word_count'] ) : 0;
                                                        printf( '%1$s / %2$s', esc_html( number_format_i18n( $chars ) ), esc_html( number_format_i18n( $words ) ) );
                                                        ?>
                                                </td>
                                                <td>
                                                        <?php
                                                        $cost = isset( $preview['estimated_cost'] ) ? (float) $preview['estimated_cost'] : 0.0;
                                                        echo esc_html( wp_strip_all_tags( sprintf( '€ %s', number_format_i18n( $cost, 2 ) ) ) );
                                                        ?>
                                                </td>
                                                <td><?php echo esc_html( isset( $preview['source_excerpt'] ) ? $preview['source_excerpt'] : '' ); ?></td>
                                                <td><?php echo esc_html( isset( $preview['translated_excerpt'] ) ? $preview['translated_excerpt'] : '' ); ?></td>
                                        </tr>
                                <?php endforeach; ?>
                        </tbody>
                </table>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fpml-inline-form">
                        <?php wp_nonce_field( 'fpml_clear_sandbox' ); ?>
                        <input type="hidden" name="action" value="fpml_clear_sandbox" />
                        <?php submit_button( __( 'Svuota anteprime sandbox', 'fp-multilanguage' ), 'secondary', 'submit', false ); ?>
                </form>
        <?php else : ?>
                <p><?php esc_html_e( 'Nessuna anteprima disponibile: attiva la modalità sandbox e avvia la coda per generare simulazioni.', 'fp-multilanguage' ); ?></p>
        <?php endif; ?>

        <p class="fpml-field-description"><?php esc_html_e( 'Esempi di file di export sono disponibili nella cartella docs/ del plugin.', 'fp-multilanguage' ); ?></p>
</div>
