<?php
/**
 * Queue processor responsible for orchestrating incremental translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Execute translation jobs in batches while respecting provider limits.
 *
 * @since 0.2.0
 */
class FPML_Processor {
        /**
         * Singleton instance.
         *
         * @var FPML_Processor|null
         */
        protected static $instance = null;

        /**
         * Lock transient key.
         *
         * @var string
         */
        protected $lock_key = 'fpml_processor_lock';

        /**
         * Lock time to live in seconds.
         *
         * @var int
         */
        protected $lock_ttl = 120;

        /**
         * Cached queue handler.
         *
         * @var FPML_Queue
         */
        protected $queue;

        /**
         * Cached settings instance.
         *
         * @var FPML_Settings
         */
        protected $settings;

        /**
         * Cached logger instance.
         *
         * @var FPML_Logger
         */
        protected $logger;

        /**
         * Cached plugin instance.
         *
         * @var FPML_Plugin|null
         */
        protected $plugin = null;

        /**
         * Cached translator instance.
         *
         * @var FPML_TranslatorInterface|null
         */
        protected $translator = null;

        /**
         * Whether the processor is running in assisted mode.
         *
         * @var bool
         */
        protected $assisted_mode = false;

        /**
         * Cached list of excluded shortcodes.
         *
         * @since 0.2.1
         *
         * @var array|null
         */
        protected $excluded_shortcodes = null;

        /**
         * Characters processed in the current batch.
         *
         * @var int
         */
        protected $current_batch_characters = 0;

        /**
         * Characters processed while handling the current job.
         *
         * @var int
         */
        protected $current_job_characters = 0;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Processor
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
                $plugin       = class_exists( 'FPML_Plugin' ) ? FPML_Plugin::instance() : null;
                $this->plugin = $plugin;

                if ( $plugin && method_exists( $plugin, 'is_assisted_mode' ) ) {
                        $this->assisted_mode = $plugin->is_assisted_mode();
                }

                $this->queue    = FPML_Queue::instance();
                $this->settings = FPML_Settings::instance();
                $this->logger   = FPML_Logger::instance();

                if ( $this->assisted_mode ) {
                        return;
                }

                add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );
                add_action( 'init', array( $this, 'maybe_schedule_events' ) );
                add_action( 'fpml_run_queue', array( $this, 'run_queue' ) );
                add_action( 'fpml_retry_failed', array( $this, 'retry_failed_jobs' ) );
                add_action( 'fpml_resync_outdated', array( $this, 'resync_outdated_jobs' ) );
                add_action( 'fpml_cleanup_queue', array( $this, 'handle_scheduled_cleanup' ) );
                add_action( 'update_option_' . FPML_Settings::OPTION_KEY, array( $this, 'reschedule_events' ), 10, 2 );
        }

        /**
         * Register custom cron intervals.
         *
         * @since 0.2.0
         *
         * @param array $schedules WP cron schedules.
         *
         * @return array
         */
        public function register_schedules( $schedules ) {
                $schedules['fpml_five_minutes'] = array(
                        'interval' => 5 * MINUTE_IN_SECONDS,
                        'display'  => __( 'Ogni 5 minuti (FP Multilanguage)', 'fp-multilanguage' ),
                );

                $schedules['fpml_fifteen_minutes'] = array(
                        'interval' => 15 * MINUTE_IN_SECONDS,
                        'display'  => __( 'Ogni 15 minuti (FP Multilanguage)', 'fp-multilanguage' ),
                );

                return $schedules;
        }

        /**
         * Determine schedule slug based on saved frequency.
         *
         * @since 0.2.0
         *
         * @return string
         */
        protected function get_schedule_from_settings() {
                $frequency = $this->settings ? $this->settings->get( 'cron_frequency', '15min' ) : '15min';

                if ( '5min' === $frequency ) {
                        return 'fpml_five_minutes';
                }

                if ( 'hourly' === $frequency ) {
                        return 'hourly';
                }

                return 'fpml_fifteen_minutes';
        }

        /**
         * Register cron hooks if they are not scheduled yet.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function maybe_schedule_events() {
                if ( $this->assisted_mode ) {
                        return;
                }

                if ( ! wp_next_scheduled( 'fpml_run_queue' ) ) {
                        wp_schedule_event( time() + MINUTE_IN_SECONDS, $this->get_schedule_from_settings(), 'fpml_run_queue' );
                }

                if ( ! wp_next_scheduled( 'fpml_retry_failed' ) ) {
                        wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', 'fpml_retry_failed' );
                }

                if ( ! wp_next_scheduled( 'fpml_resync_outdated' ) ) {
                        wp_schedule_event( time() + ( 10 * MINUTE_IN_SECONDS ), 'hourly', 'fpml_resync_outdated' );
                }

                $retention = $this->settings ? (int) $this->settings->get( 'queue_retention_days', 0 ) : 0;

                if ( $retention > 0 ) {
                        if ( ! wp_next_scheduled( 'fpml_cleanup_queue' ) ) {
                                wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'fpml_cleanup_queue' );
                        }
                } else {
                        $this->clear_scheduled_event( 'fpml_cleanup_queue' );
                }
        }

        /**
         * Reschedule cron events when settings change.
         *
         * @since 0.2.0
         *
         * @param mixed $old_value Previous settings.
         * @param mixed $value     New settings.
         *
         * @return void
         */
        public function reschedule_events( $old_value, $value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( $this->assisted_mode ) {
                        return;
                }

                $this->clear_scheduled_event( 'fpml_run_queue' );
                $this->clear_scheduled_event( 'fpml_cleanup_queue' );
                $this->maybe_schedule_events();
        }

        /**
         * Unschedule all instances of a hook.
         *
         * @since 0.2.0
         *
         * @param string $hook Hook name.
         *
         * @return void
         */
        protected function clear_scheduled_event( $hook ) {
                $timestamp = wp_next_scheduled( $hook );

                while ( false !== $timestamp ) {
                        wp_unschedule_event( $timestamp, $hook );
                        $timestamp = wp_next_scheduled( $hook );
                }
        }

        /**
         * Acquire processor lock to avoid concurrent execution.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        protected function acquire_lock() {
                $ttl = (int) apply_filters( 'fpml_processor_lock_ttl', $this->lock_ttl );

                if ( get_transient( $this->lock_key ) ) {
                        return false;
                }

                set_transient( $this->lock_key, gmdate( 'Y-m-d H:i:s' ), $ttl );

                return true;
        }

        /**
         * Release the processor lock.
         *
         * @since 0.2.0
         *
         * @return void
         */
        protected function release_lock() {
                delete_transient( $this->lock_key );
        }

        /**
         * Check whether the processor is currently locked.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        public function is_locked() {
                return (bool) get_transient( $this->lock_key );
        }

        /**
         * Force release of the processor lock.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function force_release_lock() {
                if ( $this->assisted_mode ) {
                        return;
                }

                $this->release_lock();
        }

        /**
         * Run a batch of jobs from the queue.
         *
         * @since 0.2.0
         *
         * @return array|WP_Error Summary or error when locked.
         */
        public function run_queue() {
                if ( $this->assisted_mode ) {
                        return new WP_Error( 'fpml_assisted_mode', __( 'La coda interna è disabilitata in modalità assistita.', 'fp-multilanguage' ) );
                }

                if ( ! $this->acquire_lock() ) {
                        return new WP_Error( 'fpml_processor_locked', __( 'La coda è già in esecuzione.', 'fp-multilanguage' ) );
                }

                $summary = array(
                        'claimed'   => 0,
                        'processed' => 0,
                        'skipped'   => 0,
                        'errors'    => 0,
                );

                $start_time          = microtime( true );
                $batch_size          = $this->settings ? (int) $this->settings->get( 'batch_size', 5 ) : 5;
                $max_chars_per_batch = $this->settings ? (int) $this->settings->get( 'max_chars_per_batch', 20000 ) : 20000;
                $max_chars_per_batch = max( 0, $max_chars_per_batch );

                try {
                        $jobs = $this->queue->claim_batch( $batch_size );

                        $summary['claimed'] = is_array( $jobs ) ? count( $jobs ) : 0;
                        $this->current_batch_characters = 0;

                        if ( empty( $jobs ) ) {
                                return $summary;
                        }

                        $total_jobs = count( $jobs );

                        for ( $index = 0; $index < $total_jobs; $index++ ) {
                                $job = $jobs[ $index ];

                                if ( $max_chars_per_batch > 0 && $this->current_batch_characters >= $max_chars_per_batch ) {
                                        $this->queue->update_state( $job->id, 'pending' );
                                        continue;
                                }

                                $this->current_job_characters = 0;
                                $result = $this->process_job( $job );

                                if ( is_wp_error( $result ) ) {
                                        $this->queue->update_state( $job->id, 'error', $result->get_error_message() );
                                        $this->logger->log(
                                                'error',
                                                sprintf( 'Errore traduzione %s #%d: %s', $job->object_type, $job->object_id, $result->get_error_message() ),
                                                array(
                                                        'job_id'      => (int) $job->id,
                                                        'object_type' => $job->object_type,
                                                        'field'       => $job->field,
                                                )
                                        );
                                        $summary['errors']++;
                                        continue;
                                }

                                if ( 'skipped' === $result ) {
                                        $this->queue->update_state( $job->id, 'skipped' );
                                        $summary['skipped']++;
                                        $this->current_batch_characters += $this->current_job_characters;

                                        if ( $max_chars_per_batch > 0 && $this->current_batch_characters >= $max_chars_per_batch ) {
                                                for ( $j = $index + 1; $j < $total_jobs; $j++ ) {
                                                        $this->queue->update_state( $jobs[ $j ]->id, 'pending' );
                                                }

                                                break;
                                        }

                                        continue;
                                }

                                $this->queue->update_state( $job->id, 'done' );
                                $summary['processed']++;

                                $this->current_batch_characters += $this->current_job_characters;

                                if ( $max_chars_per_batch > 0 && $this->current_batch_characters >= $max_chars_per_batch ) {
                                        for ( $j = $index + 1; $j < $total_jobs; $j++ ) {
                                                $this->queue->update_state( $jobs[ $j ]->id, 'pending' );
                                        }

                                        break;
                                }
                        }
                } finally {
                        $duration = microtime( true ) - $start_time;
                        $this->logger->log(
                                'info',
                                sprintf(
                                        /* translators: %s: processing duration in seconds */
                                        __( 'Batch coda completato in %s secondi', 'fp-multilanguage' ),
                                        number_format_i18n( $duration, 2 )
                                ),
                                array(
                                        'jobs'      => $summary['claimed'],
                                        'processed' => $summary['processed'],
                                        'skipped'   => $summary['skipped'],
                                        'errors'    => $summary['errors'],
                                )
                        );
                        $this->maybe_cleanup_queue();
                        $this->notify_admin_if_enabled( $summary );
                        $this->release_lock();
                }

                return $summary;
        }

        /**
         * Send email notification to admin if enabled.
         *
         * @since 0.4.0
         *
         * @param array $summary Batch summary.
         *
         * @return void
         */
        protected function notify_admin_if_enabled( $summary ) {
                if ( ! $this->settings || ! $this->settings->get( 'enable_email_notifications', false ) ) {
                        return;
                }

                // Only notify if there were actual jobs processed
                if ( empty( $summary['claimed'] ) ) {
                        return;
                }

                $admin_email = get_option( 'admin_email' );
                if ( empty( $admin_email ) ) {
                        return;
                }

                $subject = sprintf(
                        /* translators: %s site name */
                        __( '[%s] Batch traduzioni completato', 'fp-multilanguage' ),
                        get_bloginfo( 'name' )
                );

                $duration = isset( $summary['duration'] ) ? $summary['duration'] : 0;

                $message = sprintf(
                        __( "Ciao,\n\nIl batch di traduzioni è stato completato:\n\n✅ Processati: %d\n❌ Errori: %d\n⏭️  Saltati: %d\n⏱️  Durata: %.2fs\n\nVai al pannello: %s\n\n---\nQuesto è un messaggio automatico di FP Multilanguage", 'fp-multilanguage' ),
                        $summary['processed'] ?? 0,
                        $summary['errors'] ?? 0,
                        $summary['skipped'] ?? 0,
                        $duration,
                        admin_url( 'admin.php?page=fpml-settings&tab=diagnostics' )
                );

                wp_mail( $admin_email, $subject, $message );
        }

        /**
         * Retry jobs previously marked as error.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function retry_failed_jobs() {
                if ( $this->assisted_mode ) {
                        return;
                }

                $jobs = $this->queue->get_by_state( array( 'error' ), 50 );

                foreach ( $jobs as $job ) {
                        if ( isset( $job->retries ) && (int) $job->retries >= 5 ) {
                                continue;
                        }

                        $this->queue->update_state( $job->id, 'pending' );
                }
        }

        /**
         * Move outdated jobs back to pending.
         *
         * @since 0.2.0
         *
         * @return int Number of jobs rescheduled.
         */
        public function resync_outdated_jobs() {
                if ( $this->assisted_mode ) {
                        return 0;
                }

                $updated = 0;
                $batch   = 200;

                do {
                        $jobs = $this->queue->get_by_state( array( 'outdated' ), $batch );

                        if ( empty( $jobs ) ) {
                                break;
                        }

                        foreach ( $jobs as $job ) {
                                if ( $this->queue->update_state( $job->id, 'pending' ) ) {
                                        $updated++;
                                }
                        }
                } while ( count( $jobs ) === $batch );

                return $updated;
        }

        /**
         * Process an individual job.
         *
         * @since 0.2.0
         *
         * @param object $job Queue record.
         *
         * @return true|WP_Error|string
         */
        protected function process_job( $job ) {
                if ( empty( $job->object_type ) ) {
                        return new WP_Error( 'fpml_job_invalid', __( 'Job non valido.', 'fp-multilanguage' ) );
                }

                $this->current_job_characters = 0;

                switch ( $job->object_type ) {
                        case 'post':
                                return $this->process_post_job( $job );

                        case 'term':
                                return $this->process_term_job( $job );

                        case 'menu':
                                return $this->process_menu_job( $job );

                        case 'string':
                                /**
                                 * Stub per fasi successive.
                                 */
                                return 'skipped';
                }

                return new WP_Error( 'fpml_job_type_unsupported', sprintf( __( 'Tipo di job %s non supportato.', 'fp-multilanguage' ), $job->object_type ) );
        }

        /**
         * Perform queue cleanup when retention is configured.
         *
         * @since 0.3.1
         *
         * @return void
         */
        protected function maybe_cleanup_queue() {
                if ( $this->assisted_mode || ! $this->settings ) {
                        return;
                }

                $retention = (int) $this->settings->get( 'queue_retention_days', 0 );

                if ( $retention <= 0 ) {
                        return;
                }

                $states = array();

                if ( $this->plugin && method_exists( $this->plugin, 'get_queue_cleanup_states' ) ) {
                        $states = $this->plugin->get_queue_cleanup_states();
                }

                if ( empty( $states ) ) {
                        return;
                }

                $result = $this->queue->cleanup_old_jobs( $states, $retention, 'updated_at' );

                if ( is_wp_error( $result ) ) {
                        $this->logger->log(
                                'error',
                                sprintf(
                                        /* translators: 1: error message */
                                        __( 'Pulizia coda non riuscita: %s', 'fp-multilanguage' ),
                                        $result->get_error_message()
                                ),
                                array(
                                        'states'    => implode( ',', $states ),
                                        'retention' => $retention,
                                )
                        );

                        return;
                }

                $deleted = (int) $result;

                if ( $deleted > 0 ) {
                        $this->logger->log(
                                'info',
                                sprintf(
                                        /* translators: 1: number of deleted jobs, 2: retention days */
                                        __( 'Pulizia coda completata: %1$d job rimossi oltre %2$d giorni.', 'fp-multilanguage' ),
                                        $deleted,
                                        $retention
                                ),
                                array(
                                        'states'    => implode( ',', $states ),
                                        'retention' => $retention,
                                )
                        );
                }
        }

        /**
         * Triggered by the scheduled cleanup event.
         *
         * @since 0.3.1
         *
         * @return void
         */
        public function handle_scheduled_cleanup() {
                $this->maybe_cleanup_queue();
        }

        /**
         * Retrieve translator instance based on settings.
         *
         * @since 0.2.0
         *
         * @return FPML_TranslatorInterface|WP_Error
         */
        protected function get_translator() {
                if ( $this->translator instanceof FPML_TranslatorInterface ) {
                        if ( $this->translator->is_configured() ) {
                                return $this->translator;
                        }
                }

                $provider = $this->settings ? $this->settings->get( 'provider', '' ) : '';

                switch ( $provider ) {
                        case 'openai':
                                $translator = new FPML_Provider_OpenAI();
                                break;
                        case 'deepl':
                                $translator = new FPML_Provider_DeepL();
                                break;
                        case 'google':
                                $translator = new FPML_Provider_Google();
                                break;
                        case 'libretranslate':
                                $translator = new FPML_Provider_LibreTranslate();
                                break;
                        default:
                                return new WP_Error( 'fpml_provider_missing', __( 'Nessun provider configurato.', 'fp-multilanguage' ) );
                }

                if ( ! $translator->is_configured() ) {
                        return new WP_Error( 'fpml_provider_not_configured', __( 'Il provider selezionato non è configurato.', 'fp-multilanguage' ) );
                }

                $this->translator = $translator;

                return $this->translator;
        }

        /**
         * Expose the configured translator instance.
         *
         * @since 0.2.0
         *
         * @return FPML_TranslatorInterface|WP_Error
         */
        public function get_translator_instance() {
                return $this->get_translator();
        }

        /**
         * Retrieve the sanitized list of shortcodes excluded from translation.
         *
         * @since 0.2.1
         *
         * @return array
         */
protected function get_excluded_shortcodes() {
if ( null !== $this->excluded_shortcodes ) {
return $this->excluded_shortcodes;
}

$raw = $this->settings ? $this->settings->get( 'excluded_shortcodes', '' ) : '';
$defaults = array( 'vc_row', 'vc_column', 'vc_section', 'vc_tabs', 'vc_accordion', 'vc_tta_accordion', 'vc_tta_tabs' );

if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
$this->excluded_shortcodes = $defaults;

return $this->excluded_shortcodes;
}

$parts = preg_split( '/[\s,]+/', $raw );
$clean = array();

foreach ( (array) $parts as $part ) {
$part = strtolower( trim( preg_replace( '/[^a-z0-9_-]/i', '', (string) $part ) ) );

if ( '' !== $part ) {
$clean[] = $part;
}
}

if ( empty( $clean ) ) {
$clean = $defaults;
}

$this->excluded_shortcodes = array_values( array_unique( $clean ) );

                return $this->excluded_shortcodes;
        }

        /**
         * Process menu item translation jobs.
         *
         * @since 0.3.0
         *
         * @param object $job Job entry.
         *
         * @return true|WP_Error|string
         */
        protected function process_menu_job( $job ) {
                $source_id = isset( $job->object_id ) ? (int) $job->object_id : 0;

                if ( ! $source_id ) {
                        return new WP_Error( 'fpml_job_menu_missing', __( 'ID voce di menu mancante.', 'fp-multilanguage' ) );
                }

                $source_item = get_post( $source_id );

                if ( ! ( $source_item instanceof WP_Post ) || 'nav_menu_item' !== $source_item->post_type ) {
                        return new WP_Error( 'fpml_job_menu_invalid', __( 'La voce di menu sorgente non è disponibile.', 'fp-multilanguage' ) );
                }

                if ( get_post_meta( $source_item->ID, '_fpml_is_translation', true ) ) {
                        return new WP_Error( 'fpml_job_menu_loop', __( 'Il job punta a una voce di menu già tradotta.', 'fp-multilanguage' ) );
                }

                $field = isset( $job->field ) ? sanitize_key( $job->field ) : 'title';

                if ( 'title' !== $field ) {
                        return new WP_Error( 'fpml_job_menu_field_unsupported', __( 'Campo voce di menu non gestito.', 'fp-multilanguage' ) );
                }

                $target_id = (int) get_post_meta( $source_item->ID, '_fpml_pair_id', true );

                if ( ! $target_id ) {
                        return new WP_Error( 'fpml_job_menu_target_missing', __( 'Nessuna voce di menu tradotta collegata.', 'fp-multilanguage' ) );
                }

                $target_item = get_post( $target_id );

                if ( ! ( $target_item instanceof WP_Post ) || 'nav_menu_item' !== $target_item->post_type ) {
                        return new WP_Error( 'fpml_job_menu_target_invalid', __( 'La voce di menu di destinazione non esiste.', 'fp-multilanguage' ) );
                }

                $manual_label = (string) get_post_meta( $source_item->ID, '_menu_item_title', true );
                $source_label = '' !== $manual_label ? $manual_label : (string) $source_item->post_title;

                if ( 'custom' !== $source_item->type && '' === trim( $manual_label ) ) {
                        $this->update_menu_item_status_flag( $target_item->ID, 'title', 'synced' );

                        return 'skipped';
                }

                $target_manual = (string) get_post_meta( $target_item->ID, '_menu_item_title', true );
                $target_label  = '' !== $target_manual ? $target_manual : (string) $target_item->post_title;

                if ( '' === trim( $source_label ) ) {
                        $this->update_menu_item_status_flag( $target_item->ID, 'title', 'synced' );

                        return 'skipped';
                }

                $context = array(
                        'field'               => 'menu:title',
                        'domain'              => 'menu',
                        'target_value'        => $target_label,
                        'excluded_shortcodes' => $this->get_excluded_shortcodes(),
                );

                $translated = $this->translate_value_recursive( $source_label, $context );

                if ( is_wp_error( $translated ) ) {
                        return $translated;
                }

                $translated = sanitize_text_field( $translated );

                if ( $translated === $target_label ) {
                        $this->update_menu_item_status_flag( $target_item->ID, 'title', 'synced' );

                        return 'skipped';
                }

                $result = wp_update_post(
                        array(
                                'ID'         => $target_item->ID,
                                'post_title' => $translated,
                        ),
                        true
                );

                if ( is_wp_error( $result ) ) {
                        return $result;
                }

                update_post_meta( $target_item->ID, '_menu_item_title', $translated );

                $this->update_menu_item_status_flag( $target_item->ID, 'title', 'synced' );

                /**
                 * Allow third parties to react to menu label translations.
                 *
                 * @since 0.3.0
                 *
                 * @param WP_Post $target_item Target menu item.
                 * @param string  $field       Field identifier.
                 * @param string  $value       New translated value.
                 * @param object  $job         Job entry.
                 */
                do_action( 'fpml_menu_item_translated', $target_item, 'title', $translated, $job );

                return true;
        }

        /**
         * Process term-specific jobs.
         *
         * @since 0.3.0
	 *
	 * @param object $job Job entry.
	 *
	 * @return true|WP_Error|string
	 */
	protected function process_term_job( $job ) {
		$term_id = isset( $job->object_id ) ? (int) $job->object_id : 0;

		if ( ! $term_id ) {
			return new WP_Error( 'fpml_job_term_missing', __( 'ID termine mancante.', 'fp-multilanguage' ) );
		}

		$field_raw = isset( $job->field ) ? (string) $job->field : 'name';
		$taxonomy  = '';
		$field     = $field_raw;

		if ( false !== strpos( $field_raw, ':' ) ) {
			list( $taxonomy, $field ) = array_pad( explode( ':', $field_raw, 2 ), 2, '' );
		}

		$taxonomy = sanitize_key( $taxonomy );
		$field    = sanitize_key( $field );

		if ( '' === $field ) {
			return new WP_Error( 'fpml_job_term_field_invalid', __( 'Campo termine non valido.', 'fp-multilanguage' ) );
		}

		$term = $taxonomy ? get_term( $term_id, $taxonomy ) : get_term( $term_id );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'fpml_job_term_invalid', __( 'Il termine sorgente non è disponibile.', 'fp-multilanguage' ) );
		}

		if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
			return new WP_Error( 'fpml_job_term_loop', __( 'Il job punta a un termine già tradotto.', 'fp-multilanguage' ) );
		}

		$taxonomy = sanitize_key( $term->taxonomy );

		$target_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );

		if ( ! $target_id ) {
			return new WP_Error( 'fpml_job_term_target_missing', __( 'Nessuna traduzione collegata disponibile.', 'fp-multilanguage' ) );
		}

		$target_term = $taxonomy ? get_term( $target_id, $taxonomy ) : get_term( $target_id );

		if ( ! $target_term || is_wp_error( $target_term ) ) {
			return new WP_Error( 'fpml_job_term_target_invalid', __( 'Il termine di destinazione non esiste.', 'fp-multilanguage' ) );
		}

		switch ( $field ) {
			case 'name':
				$source_value = (string) $term->name;
				$target_value = (string) $target_term->name;
				$sanitize     = 'sanitize_text_field';
				break;
			case 'description':
				$source_value = (string) $term->description;
				$target_value = (string) $target_term->description;
				$sanitize     = 'wp_kses_post';
				break;
			default:
				return new WP_Error( 'fpml_job_term_field_unsupported', __( 'Campo termine non gestito.', 'fp-multilanguage' ) );
		}

		if ( '' === $source_value && '' === $target_value ) {
			$this->update_term_status_flag( $target_term->term_id, $field, 'synced' );
			FPML_Language::instance()->set_term_pair( $term->term_id, $target_term->term_id );

			return 'skipped';
		}

		$context = array(
			'field'               => 'term:' . $field,
			'target_value'        => $target_value,
			'excluded_shortcodes' => $this->get_excluded_shortcodes(),
		);

		$translated = $this->translate_value_recursive( $source_value, $context );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		$translated = call_user_func( $sanitize, $translated );

		if ( $translated === $target_value ) {
			$this->update_term_status_flag( $target_term->term_id, $field, 'synced' );
			FPML_Language::instance()->set_term_pair( $term->term_id, $target_term->term_id );

			return 'skipped';
		}

		$args = array();

		if ( 'name' === $field ) {
			$args['name'] = $translated;
		} else {
			$args['description'] = $translated;
		}

		$result = wp_update_term( $target_term->term_id, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->update_term_status_flag( $target_term->term_id, $field, 'synced' );
		FPML_Language::instance()->set_term_pair( $term->term_id, $target_term->term_id );

		/**
		 * Allow third parties to react to term translations.
		 *
		 * @since 0.3.0
		 *
		 * @param WP_Term $target_term Translated term.
		 * @param string  $field       Field identifier.
		 * @param string  $value       New value.
		 * @param object  $job         Job entry.
		 */
		do_action( 'fpml_term_translated', $target_term, $field, $translated, $job );

		return true;
	}

        /**
         * Process post-related job.
         *
         * @since 0.2.0
         *
         * @param object $job Job entry.
         *
         * @return true|WP_Error|string
         */
        protected function process_post_job( $job ) {
                $post_id = isset( $job->object_id ) ? (int) $job->object_id : 0;

                if ( ! $post_id ) {
                        return new WP_Error( 'fpml_job_post_missing', __( 'ID post mancante.', 'fp-multilanguage' ) );
                }

                $source_post = get_post( $post_id );

                if ( ! $source_post || 'trash' === $source_post->post_status ) {
                        return new WP_Error( 'fpml_job_post_invalid', __( 'Il contenuto sorgente non è disponibile.', 'fp-multilanguage' ) );
                }

                if ( get_post_meta( $source_post->ID, '_fpml_is_translation', true ) ) {
                        return new WP_Error( 'fpml_job_post_loop', __( 'Il job punta a un contenuto già tradotto.', 'fp-multilanguage' ) );
                }

                $target_id = (int) get_post_meta( $source_post->ID, '_fpml_pair_id', true );

                if ( ! $target_id ) {
                        return new WP_Error( 'fpml_job_post_target_missing', __( 'Nessuna traduzione collegata disponibile.', 'fp-multilanguage' ) );
                }

                $target_post = get_post( $target_id );

                if ( ! $target_post ) {
                        return new WP_Error( 'fpml_job_post_target_invalid', __( 'Il post di destinazione non esiste.', 'fp-multilanguage' ) );
                }

                $field = isset( $job->field ) ? sanitize_text_field( $job->field ) : 'post_content';

                if ( 0 === strpos( $field, 'meta:' ) ) {
                        $meta_key    = substr( $field, 5 );
                        $source_raw  = get_post_meta( $source_post->ID, $meta_key, true );
                        $target_raw  = get_post_meta( $target_post->ID, $meta_key, true );
                        $source_meta = maybe_unserialize( $source_raw );
                        $target_meta = maybe_unserialize( $target_raw );

                        $context = array(
                                'post_id'             => $source_post->ID,
                                'meta_key'            => $meta_key,
                                'field'               => $field,
                                'domain'              => 'meta',
                                'target_value'        => $target_meta,
                                'excluded_shortcodes' => $this->get_excluded_shortcodes(),
                        );

                        if ( '_product_attributes' === $meta_key ) {
                                $translated_value = $this->translate_product_attributes_meta( $source_meta, $target_meta );
                        } else {
                                $translated_value = $this->translate_value_recursive( $source_meta, $context );
                        }

                        if ( is_wp_error( $translated_value ) ) {
                                return $translated_value;
                        }

                        if ( '_wp_attachment_image_alt' === $meta_key && ! is_string( $translated_value ) ) {
                                if ( is_scalar( $translated_value ) || null === $translated_value ) {
                                        $translated_value = (string) $translated_value;
                                } else {
                                        $translated_value = $this->stringify_value_for_preview( $translated_value );
                                }
                        }

                        if ( $this->settings && $this->settings->get( 'sandbox_mode', false ) ) {
                                $source_preview     = $this->stringify_value_for_preview( $source_meta );
                                $translated_preview = $this->stringify_value_for_preview( $translated_value );
                                $payload_text       = $source_preview;
                                $characters         = function_exists( 'mb_strlen' ) ? mb_strlen( $payload_text, 'UTF-8' ) : strlen( $payload_text );
                                $plain_text         = wp_strip_all_tags( $translated_preview );
                                $plain_text         = preg_replace( '/\s+/u', ' ', $plain_text );
                                $plain_text         = trim( $plain_text );
                                $word_count         = '' === $plain_text ? 0 : count( preg_split( '/\s+/u', $plain_text ) );
                                $provider           = $this->translator instanceof FPML_TranslatorInterface ? $this->translator->get_slug() : '';
                                $cost               = $this->translator instanceof FPML_TranslatorInterface ? $this->translator->estimate_cost( $payload_text ) : 0.0;

                                FPML_Export_Import::instance()->record_sandbox_preview(
                                        array(
                                                'object_type'        => 'post',
                                                'object_id'          => $target_post->ID,
                                                'field'              => $field,
                                                'characters'         => $characters,
                                                'word_count'         => $word_count,
                                                'estimated_cost'     => $cost,
                                                'source_excerpt'     => $source_preview,
                                                'translated_excerpt' => $translated_preview,
                                                'job_id'             => isset( $job->id ) ? (int) $job->id : 0,
                                                'provider'           => $provider,
                                                'source_url'         => get_permalink( $source_post->ID ),
                                                'translation_url'    => get_permalink( $target_post->ID ),
                                        )
                                );

                                $this->logger->log(
                                        'info',
                                        sprintf( 'Sandbox: anteprima generata per il meta %1$s del post #%2$d.', $meta_key, $target_post->ID ),
                                        array(
                                                'job_id'      => isset( $job->id ) ? (int) $job->id : 0,
                                                'object_type' => 'post',
                                                'field'       => $field,
                                                'characters'  => $characters,
                                                'estimated'   => $cost,
                                        )
                                );

                                return 'skipped';
                        }

                        update_post_meta( $target_post->ID, $meta_key, $translated_value );

                        do_action( 'fpml_post_translated', $target_post, $field, $translated_value, $job );

                        return true;
                }

                $source_value = $this->get_post_field_value( $source_post, $field );
                $target_value = $this->get_post_field_value( $target_post, $field );

                if ( '' === $source_value && '' === $target_value ) {
                        return 'skipped';
                }

                $diff = FPML_Content_Diff::instance()->calculate_diff(
                        $source_value,
                        $target_value,
                        array(
                                'excluded_shortcodes' => $this->get_excluded_shortcodes(),
                        )
                );

                $chunks = isset( $diff['segments'] ) ? $diff['segments'] : array();

                if ( empty( $chunks ) ) {
                        return true;
                }

                $translations = $this->translate_segments( $chunks, $field );

                if ( is_wp_error( $translations ) ) {
                        return $translations;
                }

                $new_value = FPML_Content_Diff::instance()->rebuild(
                        $diff['source_tokens'],
                        $diff['target_map'],
                        $translations,
                        isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                );

                if ( $this->settings && $this->settings->get( 'sandbox_mode', false ) ) {
                        $payload_text = implode( "\n\n", array_map( 'strval', $chunks ) );
                        $characters   = function_exists( 'mb_strlen' ) ? mb_strlen( $payload_text, 'UTF-8' ) : strlen( $payload_text );
                        $plain_text   = wp_strip_all_tags( $new_value );
                        $plain_text   = preg_replace( '/\s+/u', ' ', $plain_text );
                        $plain_text   = trim( $plain_text );
                        $word_count   = '' === $plain_text ? 0 : count( preg_split( '/\s+/u', $plain_text ) );
                        $provider     = $this->translator instanceof FPML_TranslatorInterface ? $this->translator->get_slug() : '';
                        $cost         = $this->translator instanceof FPML_TranslatorInterface ? $this->translator->estimate_cost( $payload_text ) : 0.0;

                        FPML_Export_Import::instance()->record_sandbox_preview(
                                array(
                                        'object_type'        => 'post',
                                        'object_id'          => $target_post->ID,
                                        'field'              => $field,
                                        'characters'         => $characters,
                                        'word_count'         => $word_count,
                                        'estimated_cost'     => $cost,
                                        'source_excerpt'     => $source_value,
                                        'translated_excerpt' => $new_value,
                                        'job_id'             => isset( $job->id ) ? (int) $job->id : 0,
                                        'provider'           => $provider,
                                        'source_url'         => get_permalink( $source_post->ID ),
                                        'translation_url'    => get_permalink( $target_post->ID ),
                                )
                        );

                        $this->logger->log(
                                'info',
                                sprintf( 'Sandbox: anteprima generata per il post #%d (%s).', $target_post->ID, $field ),
                                array(
                                        'job_id'      => isset( $job->id ) ? (int) $job->id : 0,
                                        'object_type' => 'post',
                                        'field'       => $field,
                                        'characters'  => $characters,
                                        'estimated'   => $cost,
                                )
                        );

                        return 'skipped';
                }

                $this->save_post_field_value( $target_post, $field, $new_value );

                /**
                 * Allow third parties to hook after a successful post field translation.
                 *
                 * @since 0.2.0
                 *
                 * @param WP_Post $target_post Post updated.
                 * @param string  $field       Field identifier.
                 * @param string  $new_value   Translated value.
                 * @param object  $job         Queue job entry.
                 */
                do_action( 'fpml_post_translated', $target_post, $field, $new_value, $job );

                return true;
        }

        /**
         * Retrieve the value for a post field or meta.
         *
         * @since 0.2.0
         *
         * @param WP_Post $post  Post object.
         * @param string  $field Field identifier.
         *
         * @return string
         */
        protected function get_post_field_value( $post, $field ) {
                switch ( $field ) {
                        case 'post_title':
                                return (string) $post->post_title;
                        case 'post_excerpt':
                                return (string) $post->post_excerpt;
                        case 'post_content':
                                return (string) $post->post_content;
                        case 'slug':
                                $slug = (string) $post->post_name;

                                if ( '' === $slug ) {
                                        $slug = sanitize_title( $post->post_title );
                                }

                                $slug = str_replace( array( '-', '_' ), ' ', $slug );

                                return trim( $slug );
                }

                if ( 0 === strpos( $field, 'meta:' ) ) {
                        $meta_key = substr( $field, 5 );
                        $value    = get_post_meta( $post->ID, $meta_key, true );

                        if ( is_array( $value ) ) {
                                $value = wp_json_encode( $value );
                        }

                        return (string) $value;
                }

                return (string) apply_filters( 'fpml_get_post_field_value', '', $post, $field );
        }

        /**
         * Persist translated value to the destination post.
         *
         * @since 0.2.0
         *
         * @param WP_Post $post      Target post.
         * @param string  $field     Field identifier.
         * @param string  $new_value Translated value.
         *
         * @return void
         */
        protected function save_post_field_value( $post, $field, $new_value ) {
                $new_value = apply_filters( 'fpml_pre_save_translation', $new_value, $post, $field );

                switch ( $field ) {
                        case 'post_title':
                                wp_update_post(
                                        array(
                                                'ID'         => $post->ID,
                                                'post_title' => sanitize_text_field( $new_value ),
                                        )
                                );
                                return;
                        case 'post_excerpt':
                                wp_update_post(
                                        array(
                                                'ID'           => $post->ID,
                                                'post_excerpt' => wp_kses_post( $new_value ),
                                        )
                                );
                                return;
                        case 'post_content':
                                wp_update_post(
                                        array(
                                                'ID'           => $post->ID,
                                                'post_content' => wp_kses_post( $new_value ),
                                        )
                                );
                                return;
                        case 'slug':
                                FPML_SEO::instance()->handle_slug_translation( $post, $new_value );
                                return;
                }

                if ( 0 === strpos( $field, 'meta:' ) ) {
                        $meta_key = substr( $field, 5 );
                        update_post_meta( $post->ID, $meta_key, wp_kses_post( $new_value ) );
                        return;
                }

                /**
                 * Let developers handle custom save strategies.
                 *
                 * @since 0.2.0
                 */
                do_action( 'fpml_save_post_field_value', $post, $field, $new_value );
        }

        /**
         * Update translation status flag for a menu item.
         *
         * @since 0.3.0
         *
         * @param int    $item_id Menu item ID.
         * @param string $field   Field identifier.
         * @param string $status  Status slug.
         *
         * @return void
         */
        protected function update_menu_item_status_flag( $item_id, $field, $status ) {
                $meta_key = '_fpml_status_' . sanitize_key( str_replace( ':', '_', $field ) );

                update_post_meta( $item_id, $meta_key, sanitize_key( $status ) );
        }

        /**
         * Update translation status flag for a term.
         *
         * @since 0.3.0
         *
         * @param int    $term_id Term ID.
         * @param string $field   Field identifier.
         * @param string $status  Status slug.
         *
         * @return void
         */
        protected function update_term_status_flag( $term_id, $field, $status ) {
                $meta_key = '_fpml_status_' . sanitize_key( $field );

                update_term_meta( $term_id, $meta_key, sanitize_key( $status ) );
        }

        /**
         * Translate text segments batching them according to provider limits.
         *
         * @since 0.2.0
         *
         * @param array  $segments Map of index => text.
         * @param string $field    Field identifier for domain detection.
         *
         * @return array|WP_Error
         */
        protected function translate_segments( $segments, $field ) {
                $translator = $this->get_translator();

                if ( is_wp_error( $translator ) ) {
                        return $translator;
                }

                $max_chars = $this->settings ? (int) $this->settings->get( 'max_chars', 4500 ) : 4500;
                $max_chars = max( 500, $max_chars );
                $domain    = $this->resolve_domain_for_field( $field );

                $chunks = array();
                $buffer = '';
                $buffer_indexes = array();

                foreach ( $segments as $index => $segment ) {
                        $segment = (string) $segment;

                        if ( '' === trim( $segment ) ) {
                                continue;
                        }

                        $candidate = '' === $buffer ? $segment : $buffer . "\n\n" . $segment;

                        if ( strlen( $candidate ) > $max_chars && '' !== $buffer ) {
                                $chunks[] = array(
                                        'text'    => $buffer,
                                        'indices' => $buffer_indexes,
                                        'length'  => function_exists( 'mb_strlen' ) ? mb_strlen( $buffer, 'UTF-8' ) : strlen( $buffer ),
                                );

                                $buffer         = $segment;
                                $buffer_indexes = array( $index );
                                continue;
                        }

                        $buffer         = $candidate;
                        $buffer_indexes[] = $index;
                }

                if ( '' !== $buffer && ! empty( $buffer_indexes ) ) {
                        $chunks[] = array(
                                'text'    => $buffer,
                                'indices' => $buffer_indexes,
                                'length'  => function_exists( 'mb_strlen' ) ? mb_strlen( $buffer, 'UTF-8' ) : strlen( $buffer ),
                        );
                }

                $translations      = array();
                $attempt           = 0;
                $shortcodes        = $this->get_excluded_shortcodes();
                $chunk_placeholders = array();

                if ( ! empty( $shortcodes ) ) {
                        foreach ( $chunks as $chunk_index => $chunk_data ) {
                                list( $masked_text, $map ) = FPML_Content_Diff::instance()->prepare_text_for_provider( $chunk_data['text'], $shortcodes );
                                $chunks[ $chunk_index ]['text'] = $masked_text;

                                if ( ! empty( $map ) ) {
                                        $chunk_placeholders[ $chunk_index ] = $map;
                                }
                        }
                }

                foreach ( $chunks as $chunk_index => $chunk ) {
                        $attempt++;
                        $response = $this->attempt_translation_with_backoff( $translator, $chunk['text'], $domain, $attempt );

                        if ( is_wp_error( $response ) ) {
                                return $response;
                        }

                        if ( isset( $chunk_placeholders[ $chunk_index ] ) && ! empty( $chunk_placeholders[ $chunk_index ] ) ) {
                                $response = FPML_Content_Diff::instance()->restore_placeholders( $response, $chunk_placeholders[ $chunk_index ] );
                        }

                        $pieces = preg_split( "/\n\n/", $response );

                        foreach ( $chunk['indices'] as $position => $index ) {
                                $translations[ $index ] = isset( $pieces[ $position ] ) ? $pieces[ $position ] : '';
                        }
                }

                if ( ! empty( $chunks ) ) {
                        $total_characters = 0;

                        foreach ( $chunks as $chunk_data ) {
                                if ( isset( $chunk_data['length'] ) ) {
                                        $total_characters += (int) $chunk_data['length'];
                                }
                        }

                        if ( $total_characters > 0 ) {
                                $this->current_job_characters += $total_characters;
                        }
                }

                return $translations;
        }

        /**
         * Convert structured values into a printable string for sandbox previews.
         *
         * @since 0.2.1
         *
         * @param mixed $value Value to stringify.
         *
         * @return string
         */
        protected function stringify_value_for_preview( $value ) {
                if ( is_string( $value ) ) {
                        return $value;
                }

                if ( is_scalar( $value ) || null === $value ) {
                        return (string) $value;
                }

                $encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

                return is_string( $encoded ) ? $encoded : '';
        }

        /**
         * Translate WooCommerce product attribute meta.
         *
         * @since 0.3.0
         *
         * @param mixed $attributes        Source attributes.
         * @param mixed $target_attributes Target attributes.
         *
         * @return mixed|WP_Error
         */
        protected function translate_product_attributes_meta( $attributes, $target_attributes ) {
                if ( ! is_array( $attributes ) ) {
                        return $this->translate_value_recursive(
                                $attributes,
                                array(
                                        'field'               => 'meta:_product_attributes',
                                        'target_value'        => $target_attributes,
                                        'excluded_shortcodes' => $this->get_excluded_shortcodes(),
                                )
                        );
                }

                $result       = array();
                $target_array = is_array( $target_attributes ) ? $target_attributes : array();
                $shortcodes   = $this->get_excluded_shortcodes();

                foreach ( $attributes as $key => $attribute ) {
                        $current_target = is_array( $target_array ) && array_key_exists( $key, $target_array ) ? $target_array[ $key ] : array();

                        if ( ! is_array( $attribute ) ) {
                                $translated_item = $this->translate_value_recursive(
                                        $attribute,
                                        array(
                                                'field'               => 'meta:_product_attributes',
                                                'target_value'        => $current_target,
                                                'excluded_shortcodes' => $shortcodes,
                                        )
                                );

                                if ( is_wp_error( $translated_item ) ) {
                                        return $translated_item;
                                }

                                $result[ $key ] = $translated_item;
                                continue;
                        }

                        $translated_attribute = $attribute;
                        $is_taxonomy          = false;

                        if ( array_key_exists( 'is_taxonomy', $attribute ) ) {
                                $raw_taxonomy = $attribute['is_taxonomy'];

                                if ( is_bool( $raw_taxonomy ) ) {
                                        $is_taxonomy = $raw_taxonomy;
                                } else {
                                        $is_taxonomy = in_array( strtolower( (string) $raw_taxonomy ), array( '1', 'yes', 'true' ), true );
                                }
                        }

                        if ( ! $is_taxonomy ) {
                                if ( array_key_exists( 'name', $attribute ) ) {
                                        $name_context = array(
                                                'field'               => 'meta:_product_attributes.name',
                                                'target_value'        => is_array( $current_target ) && array_key_exists( 'name', $current_target ) ? $current_target['name'] : '',
                                                'excluded_shortcodes' => $shortcodes,
                                        );

                                        $translated_name = $this->translate_value_recursive( $attribute['name'], $name_context );

                                        if ( is_wp_error( $translated_name ) ) {
                                                return $translated_name;
                                        }

                                        $translated_attribute['name'] = $translated_name;
                                }

                                if ( array_key_exists( 'value', $attribute ) ) {
                                        $value_context = array(
                                                'field'               => 'meta:_product_attributes.value',
                                                'target_value'        => is_array( $current_target ) && array_key_exists( 'value', $current_target ) ? $current_target['value'] : '',
                                                'excluded_shortcodes' => $shortcodes,
                                        );

                                        $translated_value = $this->translate_value_recursive( $attribute['value'], $value_context );

                                        if ( is_wp_error( $translated_value ) ) {
                                                return $translated_value;
                                        }

                                        $translated_attribute['value'] = $translated_value;
                                }
                        } else {
                                if ( is_array( $current_target ) ) {
                                        if ( array_key_exists( 'name', $current_target ) ) {
                                                $translated_attribute['name'] = $current_target['name'];
                                        }

                                        if ( array_key_exists( 'value', $current_target ) ) {
                                                $translated_attribute['value'] = $current_target['value'];
                                        }
                                }
                        }

                        $result[ $key ] = $translated_attribute;
                }

                return $result;
        }

/**
 * Translate a structured value recursively while preserving its shape.
 *
 * @since 0.2.1
 *
 * @param mixed $value   Value to translate.
 * @param array $context Translation context (meta key, target value, field, etc.).
 *
 * @return mixed|WP_Error
 */
private function translate_value_recursive( $value, array $context ) {
                if ( ! isset( $context['excluded_shortcodes'] ) ) {
                        $context['excluded_shortcodes'] = $this->get_excluded_shortcodes();
                }

                if ( is_string( $value ) ) {
                        $string = (string) $value;

                        if ( '' === trim( $string ) ) {
                                return $string;
                        }

                        $target_string = '';

                        if ( isset( $context['target_value'] ) && is_string( $context['target_value'] ) ) {
                                $target_string = (string) $context['target_value'];
                        }

                        $diff = FPML_Content_Diff::instance()->calculate_diff(
                                $string,
                                $target_string,
                                array(
                                        'excluded_shortcodes' => $context['excluded_shortcodes'],
                                )
                        );

                        $segments = isset( $diff['segments'] ) ? $diff['segments'] : array();

                        if ( empty( $segments ) ) {
                                if ( '' !== $target_string ) {
                                        return FPML_Content_Diff::instance()->restore_placeholders(
                                                $target_string,
                                                isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                                        );
                                }

                                return FPML_Content_Diff::instance()->restore_placeholders(
                                        $string,
                                        isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                                );
                        }

                        $field        = isset( $context['field'] ) ? $context['field'] : 'meta';
                        $translations = $this->translate_segments( $segments, $field );

                        if ( is_wp_error( $translations ) ) {
                                return $translations;
                        }

                        return FPML_Content_Diff::instance()->rebuild(
                                $diff['source_tokens'],
                                $diff['target_map'],
                                $translations,
                                isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                        );
                }

                if ( is_array( $value ) ) {
                        $result        = array();
                        $target_source = isset( $context['target_value'] ) && is_array( $context['target_value'] ) ? $context['target_value'] : array();

                        foreach ( $value as $key => $item ) {
                                $child_context                 = $context;
                                $child_context['target_value'] = is_array( $target_source ) && array_key_exists( $key, $target_source ) ? $target_source[ $key ] : null;
                                $result[ $key ]                = $this->translate_value_recursive( $item, $child_context );

                                if ( is_wp_error( $result[ $key ] ) ) {
                                        return $result[ $key ];
                                }
                        }

                        return $result;
                }

                if ( is_object( $value ) ) {
                        $properties    = get_object_vars( $value );
                        $target_object = isset( $context['target_value'] ) && is_object( $context['target_value'] ) ? get_object_vars( $context['target_value'] ) : array();
                        $class         = get_class( $value );
                        $clone         = 'stdClass' === $class ? new stdClass() : clone $value;

                        foreach ( $properties as $prop => $prop_value ) {
                                $child_context                 = $context;
                                $child_context['target_value'] = isset( $target_object[ $prop ] ) ? $target_object[ $prop ] : null;
                                $translated_prop               = $this->translate_value_recursive( $prop_value, $child_context );

                                if ( is_wp_error( $translated_prop ) ) {
                                        return $translated_prop;
                                }

                                $clone->$prop = $translated_prop;
                        }

                        return $clone;
                }

                return $value;
        }

        /**
         * Attempt translation with retry/backoff strategy.
         *
         * @since 0.2.0
         *
         * @param FPML_TranslatorInterface $translator Provider instance.
         * @param string                   $text       Payload to translate.
         * @param string                   $domain     Context domain.
         * @param int                      $attempt    Attempt counter.
         *
         * @return string|WP_Error
         */
        protected function attempt_translation_with_backoff( $translator, $text, $domain, $attempt ) {
                $max_attempts = 4;
                $delay        = 1;

                for ( $i = 0; $i < $max_attempts; $i++ ) {
                        $result = $translator->translate( $text, 'it', 'en', $domain );

                        if ( ! is_wp_error( $result ) ) {
                                return (string) $result;
                        }

                        $delay = min( 30, $delay * 2 );
                        $sleep = $delay + wp_rand( 0, 1000 ) / 1000;

                        if ( $i === $max_attempts - 1 ) {
                                return $result;
                        }

                        /**
                         * Allow overriding the retry delay.
                         *
                         * @since 0.2.0
                         *
                         * @param float  $sleep Delay in seconds.
                         * @param string $domain Translation domain.
                         */
                        $sleep = apply_filters( 'fpml_translation_retry_delay', $sleep, $domain );

                        usleep( (int) ( $sleep * 1000000 ) );
                }

                return new WP_Error( 'fpml_translation_failed', __( 'Traduzione non riuscita dopo vari tentativi.', 'fp-multilanguage' ) );
        }

        /**
         * Determine translation domain based on field name.
         *
         * @since 0.2.0
         *
         * @param string $field Field identifier.
         *
         * @return string
         */
        protected function resolve_domain_for_field( $field ) {
                $field = (string) $field;

                if ( false !== strpos( $field, 'seo' ) || false !== strpos( $field, 'og:' ) || false !== strpos( $field, 'twitter:' ) ) {
                        return 'seo';
                }

                if ( 'slug' === $field ) {
                        return 'seo';
                }

                if ( 'post_title' === $field ) {
                        return 'marketing';
                }

                return 'general';
        }
}
