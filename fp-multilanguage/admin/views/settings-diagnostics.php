<?php
/**
 * Diagnostics view.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

$options = isset( $options ) ? $options : array();
$plugin  = isset( $plugin ) && $plugin instanceof FPML_Plugin ? $plugin : FPML_Plugin::instance();

$snapshot = isset( $diagnostics_snapshot ) && is_array( $diagnostics_snapshot ) ? $diagnostics_snapshot : $plugin->get_diagnostics_snapshot();

$assisted_mode  = ! empty( $snapshot['assisted_mode'] );
$assisted_reason = isset( $snapshot['assisted_reason'] ) ? $snapshot['assisted_reason'] : '';
$assisted_message = isset( $snapshot['message'] ) ? $snapshot['message'] : '';

if ( $assisted_mode ) {
        $reason_map = array(
                'wpml'      => 'WPML',
                'polylang'  => 'Polylang',
        );
        $label      = isset( $reason_map[ $assisted_reason ] ) ? $reason_map[ $assisted_reason ] : '';
        $suffix     = '' !== $label ? ' (' . $label . ')' : '';

        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html( $assisted_message ? $assisted_message : __( 'Modalità assistita attiva: le metriche della coda interna non sono disponibili.', 'fp-multilanguage' ) ) . esc_html( $suffix ) . '</p>';
        echo '</div>';

        return;
}

$queue_counts    = isset( $snapshot['queue_counts'] ) && is_array( $snapshot['queue_counts'] ) ? $snapshot['queue_counts'] : array();
$kpi             = isset( $snapshot['kpi'] ) && is_array( $snapshot['kpi'] ) ? $snapshot['kpi'] : array();
$estimate        = isset( $snapshot['estimate'] ) && is_array( $snapshot['estimate'] ) ? $snapshot['estimate'] : array();
$estimate_error  = isset( $snapshot['estimate_error'] ) ? $snapshot['estimate_error'] : '';
$translator_data = isset( $snapshot['translator_status'] ) ? $snapshot['translator_status'] : array();
$events          = isset( $snapshot['events'] ) && is_array( $snapshot['events'] ) ? $snapshot['events'] : array();
$log_stats       = isset( $snapshot['log_stats'] ) && is_array( $snapshot['log_stats'] ) ? $snapshot['log_stats'] : array();
$logs            = isset( $snapshot['logs'] ) && is_array( $snapshot['logs'] ) ? array_slice( $snapshot['logs'], 0, 10 ) : array();
$recent_errors   = isset( $snapshot['recent_errors'] ) && is_array( $snapshot['recent_errors'] ) ? $snapshot['recent_errors'] : array();
$batch_average   = isset( $snapshot['batch_average'] ) && is_array( $snapshot['batch_average'] ) ? $snapshot['batch_average'] : array();
$lock_active     = ! empty( $snapshot['lock_active'] );
$cron_disabled   = ! empty( $snapshot['cron_disabled'] );
$queue_age       = isset( $snapshot['queue_age'] ) && is_array( $snapshot['queue_age'] ) ? $snapshot['queue_age'] : array();
$age_pending     = isset( $queue_age['pending'] ) && is_array( $queue_age['pending'] ) ? $queue_age['pending'] : array();
$age_completed   = isset( $queue_age['completed'] ) && is_array( $queue_age['completed'] ) ? $queue_age['completed'] : array();
$retention_days  = isset( $queue_age['retention_days'] ) ? (int) $queue_age['retention_days'] : 0;
$cleanup_states  = isset( $queue_age['cleanup_states'] ) ? (array) $queue_age['cleanup_states'] : array();
$age_pending_text   = ( ! empty( $age_pending['age'] ) && ! empty( $age_pending['datetime_local'] ) ) ? sprintf( __( '%1$s fa (%2$s)', 'fp-multilanguage' ), $age_pending['age'], $age_pending['datetime_local'] ) : __( 'Nessun job in attesa', 'fp-multilanguage' );
$age_completed_text = ( ! empty( $age_completed['age'] ) && ! empty( $age_completed['datetime_local'] ) ) ? sprintf( __( '%1$s fa (%2$s)', 'fp-multilanguage' ), $age_completed['age'], $age_completed['datetime_local'] ) : __( 'Nessun job archiviato', 'fp-multilanguage' );

/* translators: Placeholder tokens {{processed}}, {{claimed}}, {{skipped}} and {{errors}} will be replaced with queue counters. */
$run_queue_success_template = __( 'Batch completato: {{processed}}/{{claimed}} processati, {{skipped}} saltati, {{errors}} errori.', 'fp-multilanguage' );

/* translators: Placeholder tokens {{deleted}}, {{days}} and {{states}} will be replaced with cleanup results. */
$cleanup_success_template = __( 'Pulizia completata: {{deleted}} job rimossi (>{{days}} giorni, stati: {{states}}).', 'fp-multilanguage' );

/* translators: Placeholder tokens {{posts_scanned}}, {{terms_scanned}} and {{menus_synced}} will be replaced with reindex stats. */
$reindex_success_template = __( 'Reindex completato: {{posts_scanned}} post, {{terms_scanned}} termini, {{menus_synced}} menu.', 'fp-multilanguage' );

$pending_states = array( 'pending', 'translating', 'outdated' );
$pending_jobs   = 0;
foreach ( $pending_states as $pending_state ) {
        $pending_jobs += isset( $queue_counts[ $pending_state ] ) ? (int) $queue_counts[ $pending_state ] : 0;
}

$done_jobs    = isset( $queue_counts['done'] ) ? (int) $queue_counts['done'] : 0;
$skipped_jobs = isset( $queue_counts['skipped'] ) ? (int) $queue_counts['skipped'] : 0;
$error_jobs   = isset( $queue_counts['error'] ) ? (int) $queue_counts['error'] : 0;
$terms_translated = isset( $kpi['terms_translated'] ) ? (int) $kpi['terms_translated'] : 0;
$menu_labels_translated = isset( $kpi['menu_labels_translated'] ) ? (int) $kpi['menu_labels_translated'] : 0;

$characters   = isset( $estimate['characters'] ) ? (int) $estimate['characters'] : 0;
$words        = isset( $estimate['word_count'] ) ? (int) $estimate['word_count'] : 0;
$cost         = isset( $estimate['estimated_cost'] ) ? (float) $estimate['estimated_cost'] : 0.0;
$jobs_scanned = isset( $estimate['jobs_scanned'] ) ? (int) $estimate['jobs_scanned'] : 0;

$average_duration = isset( $batch_average['duration'] ) ? (float) $batch_average['duration'] : 0.0;
$average_jobs     = isset( $batch_average['jobs'] ) ? (float) $batch_average['jobs'] : 0.0;

$provider_labels = array(
        'openai'         => __( 'OpenAI', 'fp-multilanguage' ),
        'google'         => __( 'Google Cloud Translation', 'fp-multilanguage' ),
);

$provider_slug  = isset( $translator_data['provider'] ) ? $translator_data['provider'] : '';
$provider_name  = isset( $provider_labels[ $provider_slug ] ) ? $provider_labels[ $provider_slug ] : ( '' !== $provider_slug ? ucfirst( $provider_slug ) : __( 'Nessun provider', 'fp-multilanguage' ) );
$provider_ready = ! empty( $translator_data['configured'] );
$provider_error = isset( $translator_data['error'] ) ? $translator_data['error'] : '';

$rest_nonce        = wp_create_nonce( 'wp_rest' );
$run_endpoint      = esc_url( rest_url( 'fpml/v1/queue/run' ) );
$test_endpoint     = esc_url( rest_url( 'fpml/v1/test-provider' ) );
$reindex_endpoint  = esc_url( rest_url( 'fpml/v1/reindex' ) );
$cleanup_endpoint  = esc_url( rest_url( 'fpml/v1/queue/cleanup' ) );
$refresh_endpoint  = esc_url( rest_url( 'fpml/v1/refresh-nonce' ) );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
<?php settings_fields( 'fpml_settings_group' ); ?>
<table class="form-table" role="presentation">
        <tbody>
                <tr>
                        <th scope="row"><?php esc_html_e( 'Anonimizza log', 'fp-multilanguage' ); ?></th>
                        <td>
                                <label>
                                        <input type="checkbox" name="<?php echo esc_attr( FPML_Settings::OPTION_KEY ); ?>[anonymize_logs]" value="1" <?php checked( $options['anonymize_logs'], true ); ?> />
                                        <?php esc_html_e( 'Rimuovi dati personali dai log e dai report costi.', 'fp-multilanguage' ); ?>
                                </label>
                        </td>
                </tr>
        </tbody>
</table>
<p><?php esc_html_e( "Configura l'anonimizzazione e utilizza gli strumenti sottostanti per monitorare la pipeline.", 'fp-multilanguage' ); ?></p>
<?php submit_button(); ?>
</form>

<div id="fpml-diagnostics-feedback" class="fpml-diagnostics-feedback" role="status" aria-live="polite" data-refresh-endpoint="<?php echo esc_url( $refresh_endpoint ); ?>"></div>

<div class="fpml-diagnostics-grid">
        <div class="fpml-diagnostics-card">
                <h2><?php esc_html_e( 'Stato della coda', 'fp-multilanguage' ); ?></h2>
                <table class="widefat striped fpml-diagnostics-table">
                        <tbody>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Da tradurre', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $pending_jobs ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Tradotti', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $done_jobs ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Termini tradotti', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $terms_translated ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Label menu tradotte', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $menu_labels_translated ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Saltati', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $skipped_jobs ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Errori', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $error_jobs ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Job in coda più vecchio', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( $age_pending_text ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Job completato più vecchio conservato', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( $age_completed_text ); ?></td>
                                </tr>
                        </tbody>
                </table>
                <?php if ( $retention_days > 0 && ! empty( $cleanup_states ) ) : ?>
                        <p class="description"><?php echo esc_html( sprintf( __( 'Retention automatica: %1$d giorni per gli stati %2$s.', 'fp-multilanguage' ), $retention_days, implode( ', ', array_map( 'sanitize_text_field', $cleanup_states ) ) ) ); ?></p>
                <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Retention automatica disattivata: i job completati restano nel database finché non vengono rimossi manualmente.', 'fp-multilanguage' ); ?></p>
                <?php endif; ?>
                <p>
                        <?php
                        printf(
                                /* translators: 1: seconds, 2: jobs */
                                esc_html__( 'Durata media batch: %1$.2fs — Job per batch: %2$.1f', 'fp-multilanguage' ),
                                $average_duration,
                                $average_jobs
                        );
                        ?>
                </p>
                <p class="fpml-diagnostics-actions">
                        <button
                                type="button"
                                class="button button-secondary"
                                data-fpml-action="run-queue"
                                data-endpoint="<?php echo esc_url( $run_endpoint ); ?>"
                                data-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
                                data-success-message="<?php echo esc_attr__( 'Batch eseguito. Aggiorna la pagina per aggiornare le metriche.', 'fp-multilanguage' ); ?>"
                                data-success-template="<?php echo esc_attr( $run_queue_success_template ); ?>"
                        ><?php esc_html_e( 'Esegui batch ora', 'fp-multilanguage' ); ?></button>
                        <button
                                type="button"
                                class="button button-secondary"
                                data-fpml-action="cleanup"
                                data-endpoint="<?php echo esc_url( $cleanup_endpoint ); ?>"
                                data-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
                                data-working-message="<?php echo esc_attr__( 'Pulizia in corso…', 'fp-multilanguage' ); ?>"
                                data-success-message="<?php echo esc_attr( $cleanup_success_template ); ?>"
                                data-success-template="<?php echo esc_attr( $cleanup_success_template ); ?>"
                        ><?php esc_html_e( 'Pulisci coda', 'fp-multilanguage' ); ?></button>
                        <button
                                type="button"
                                class="button"
                                data-fpml-action="reindex"
                                data-endpoint="<?php echo esc_url( $reindex_endpoint ); ?>"
                                data-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
                                data-success-message="<?php echo esc_attr__( 'Reindex completato. Controlla la coda per nuovi job.', 'fp-multilanguage' ); ?>"
                                data-success-template="<?php echo esc_attr( $reindex_success_template ); ?>"
                        ><?php esc_html_e( 'Forza reindex', 'fp-multilanguage' ); ?></button>
                </p>
                <?php if ( $lock_active ) : ?>
                        <p class="fpml-diagnostics-warning"><?php esc_html_e( 'Attenzione: il lock del processor è attivo. Attendi la fine del batch o usa WP-CLI per forzare il reset.', 'fp-multilanguage' ); ?></p>
                <?php endif; ?>
                <?php if ( $cron_disabled ) : ?>
                        <p class="fpml-diagnostics-warning"><?php esc_html_e( 'WP-Cron è disabilitato: assicurati che il cron di sistema sia operativo.', 'fp-multilanguage' ); ?></p>
                <?php endif; ?>
        </div>

        <div class="fpml-diagnostics-card">
                <h2><?php esc_html_e( 'Stima costi e parole', 'fp-multilanguage' ); ?></h2>
                <?php if ( $estimate_error ) : ?>
                        <p class="fpml-diagnostics-warning"><?php echo esc_html( $estimate_error ); ?></p>
                <?php endif; ?>
                <table class="widefat striped fpml-diagnostics-table">
                        <tbody>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Caratteri in coda', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $characters ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Parole da tradurre', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $words ) ); ?></td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Costo stimato', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $cost, 4 ) ); ?> €</td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Job analizzati', 'fp-multilanguage' ); ?></th>
                                        <td><?php echo esc_html( number_format_i18n( $jobs_scanned ) ); ?></td>
                                </tr>
                        </tbody>
                </table>
                <p class="description"><?php esc_html_e( 'Il calcolo analizza un campione di job pendenti e utilizza la tariffa configurata per il provider attivo.', 'fp-multilanguage' ); ?></p>
        </div>

        <div class="fpml-diagnostics-card">
                <h2><?php esc_html_e( 'Provider di traduzione', 'fp-multilanguage' ); ?></h2>
                <p>
                        <strong><?php esc_html_e( 'Provider attivo:', 'fp-multilanguage' ); ?></strong>
                        <?php echo esc_html( $provider_name ); ?>
                </p>
                <?php if ( $provider_ready ) : ?>
                        <p class="fpml-diagnostics-success"><?php esc_html_e( 'Configurazione valida. Esegui un test per verificarne la latenza.', 'fp-multilanguage' ); ?></p>
                <?php else : ?>
                        <p class="fpml-diagnostics-warning"><?php esc_html_e( 'Il provider selezionato non è pronto. Controlla chiavi API e impostazioni.', 'fp-multilanguage' ); ?></p>
                        <?php if ( $provider_error ) : ?>
                                <p class="fpml-diagnostics-warning"><?php echo esc_html( $provider_error ); ?></p>
                        <?php endif; ?>
                <?php endif; ?>
                <p class="fpml-diagnostics-actions">
                        <button type="button" class="button button-primary" data-fpml-action="test-provider" data-endpoint="<?php echo esc_url( $test_endpoint ); ?>" data-nonce="<?php echo esc_attr( $rest_nonce ); ?>" data-success-message="<?php echo esc_attr__( 'Test completato.', 'fp-multilanguage' ); ?>"><?php esc_html_e( 'Test provider', 'fp-multilanguage' ); ?></button>
                </p>
                <div class="fpml-provider-result" data-fpml-provider-result></div>
        </div>

        <div class="fpml-diagnostics-card">
                <h2><?php esc_html_e( 'Eventi WP-Cron', 'fp-multilanguage' ); ?></h2>
                <ul class="fpml-diagnostics-list">
                        <?php foreach ( $events as $hook => $timestamp ) : ?>
                                <li>
                                        <strong><?php echo esc_html( $hook ); ?>:</strong>
                                        <?php
                                        if ( $timestamp ) {
                                                echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
                                        } else {
                                                esc_html_e( 'Non pianificato', 'fp-multilanguage' );
                                        }
                                        ?>
                                </li>
                        <?php endforeach; ?>
                </ul>
                <p class="description"><?php esc_html_e( 'Se gli eventi non risultano pianificati, verifica che il cron di sistema stia richiamando WordPress.', 'fp-multilanguage' ); ?></p>
        </div>

        <div class="fpml-diagnostics-card">
                <h2><?php esc_html_e( 'Errori recenti', 'fp-multilanguage' ); ?></h2>
                <?php if ( empty( $recent_errors ) ) : ?>
                        <p><?php esc_html_e( 'Nessun errore registrato negli ultimi log.', 'fp-multilanguage' ); ?></p>
                <?php else : ?>
                        <ul class="fpml-diagnostics-list">
                                <?php
                                foreach ( $recent_errors as $error_entry ) {
                                        $error_message = isset( $error_entry['message'] ) ? $error_entry['message'] : '';
                                        $error_time    = isset( $error_entry['timestamp'] ) ? $error_entry['timestamp'] : '';
                                        $formatted     = $error_time ? get_date_from_gmt( $error_time, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
                                        ?>
                                        <li>
                                                <strong><?php echo esc_html( $formatted ); ?></strong>
                                                <span><?php echo esc_html( $error_message ); ?></span>
                                        </li>
                                        <?php
                                }
                                ?>
                        </ul>
                <?php endif; ?>
        </div>

        <div class="fpml-diagnostics-card fpml-diagnostics-card--logs">
                <h2><?php esc_html_e( 'Ultimi log', 'fp-multilanguage' ); ?></h2>
                <p>
                        <?php
                        printf(
                                /* translators: 1: info logs count, 2: warning logs count, 3: error logs count */
                                esc_html__( 'Info: %1$d · Warning: %2$d · Errori: %3$d', 'fp-multilanguage' ),
                                isset( $log_stats['info'] ) ? (int) $log_stats['info'] : 0,
                                isset( $log_stats['warn'] ) ? (int) $log_stats['warn'] : 0,
                                isset( $log_stats['error'] ) ? (int) $log_stats['error'] : 0
                        );
                        ?>
                </p>
                <?php if ( empty( $logs ) ) : ?>
                        <p><?php esc_html_e( 'Nessun log disponibile.', 'fp-multilanguage' ); ?></p>
                <?php else : ?>
                        <table class="widefat striped fpml-diagnostics-table">
                                <thead>
                                        <tr>
                                                <th scope="col"><?php esc_html_e( 'Data', 'fp-multilanguage' ); ?></th>
                                                <th scope="col"><?php esc_html_e( 'Livello', 'fp-multilanguage' ); ?></th>
                                                <th scope="col"><?php esc_html_e( 'Messaggio', 'fp-multilanguage' ); ?></th>
                                        </tr>
                                </thead>
                                <tbody>
                                        <?php
                                        foreach ( $logs as $entry ) {
                                                $timestamp = isset( $entry['timestamp'] ) ? $entry['timestamp'] : '';
                                                $level     = isset( $entry['level'] ) ? $entry['level'] : 'info';
                                                $message   = isset( $entry['message'] ) ? $entry['message'] : '';
                                                $date      = $timestamp ? get_date_from_gmt( $timestamp, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
                                                ?>
                                                <tr>
                                                        <td><?php echo esc_html( $date ); ?></td>
                                                        <td><?php echo esc_html( ucfirst( $level ) ); ?></td>
                                                        <td><?php echo esc_html( $message ); ?></td>
                                                </tr>
                                                <?php
                                        }
                                        ?>
                                </tbody>
                        </table>
                <?php endif; ?>
        </div>
</div>
