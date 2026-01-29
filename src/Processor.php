<?php
/**
 * Queue processor responsible for orchestrating incremental translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Queue\QueueProcessor;
use FP\Multilanguage\Queue\BatchManager;
use FP\Multilanguage\Queue\RetryManager;
use FP\Multilanguage\Queue\CleanupManager;
use FP\Multilanguage\Queue\NotificationManager;
use FP\Multilanguage\Queue\CronScheduler;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Execute translation jobs in batches while respecting provider limits.
 *
 * @since 0.2.0
 */
class Processor {
        /**
         * Singleton instance.
         *
         * @var \FPML_Processor|null
         */
        protected static $instance = null;

        /**
         * Lock transient key.
         *
         * @var string
         */
        protected $lock_key = '\FPML_processor_lock';

        /**
         * Lock time to live in seconds.
         *
         * @var int
         */
        protected $lock_ttl = 120;

        /**
         * Cached queue handler.
         *
         * @var \FPML_Queue
         */
        protected $queue;

        /**
         * Cached settings instance.
         *
         * @var \FPML_Settings
         */
        protected $settings;

        /**
         * Cached logger instance.
         *
         * @var \FPML_Logger
         */
        protected $logger;

        /**
         * Cached plugin instance.
         *
         * @var \FPML_Plugin|null
         */
        protected $plugin = null;

        /**
         * Cached translator instance.
         *
         * @var \FPML_TranslatorInterface|null
         */
        protected $translator = null;

        /**
         * Whether the processor is running in assisted mode.
         *
         * @var bool
         */
        protected $assisted_mode = false;

        /**
         * Queue processor instance.
         *
         * @since 0.10.0
         *
         * @var QueueProcessor
         */
        protected $queue_processor;

        /**
         * Batch manager instance.
         *
         * @since 0.10.0
         *
         * @var BatchManager
         */
        protected $batch_manager;

        /**
         * Retry manager instance.
         *
         * @since 0.10.0
         *
         * @var RetryManager
         */
        protected $retry_manager;

        /**
         * Cleanup manager instance.
         *
         * @since 0.10.0
         *
         * @var CleanupManager
         */
        protected $cleanup_manager;

        /**
         * Notification manager instance.
         *
         * @since 0.10.0
         *
         * @var NotificationManager
         */
        protected $notification_manager;

        /**
         * Cron scheduler instance.
         *
         * @since 0.10.0
         *
         * @var CronScheduler
         */
        protected $cron_scheduler;

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
         * @return self
         */
        public static function instance(): self {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        protected function __construct() {
                $this->assisted_mode = false;

                $this->queue    = fpml_get_queue();
                // Try new container first, fallback to compatibility layer
                $container = $this->getContainer();
                $this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
                $this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : fpml_get_logger();

                // Initialize modules
                $this->batch_manager = new BatchManager( $this->settings );
                $this->retry_manager = new RetryManager( $this->queue, $this->assisted_mode );
                $this->cleanup_manager = new CleanupManager( $this->queue, $this->settings, $this->assisted_mode );
                $this->notification_manager = new NotificationManager( $this->settings );
                $this->cron_scheduler = new CronScheduler( $this->settings, $this->assisted_mode );
                $this->queue_processor = new QueueProcessor( $this->queue, $this->settings, $this->logger, $this->batch_manager, $this->assisted_mode );

                if ( $this->assisted_mode ) {
                        return;
                }

                add_filter( 'cron_schedules', array( $this->cron_scheduler, 'register_schedules' ) );
        }

        /**
         * Get container instance (new Kernel container if available, null otherwise).
         *
         * @return \FP\Multilanguage\Kernel\Container|null
         */
        protected function getContainer() {
                if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
                        $kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
                        if ( $kernel ) {
                                return $kernel->getContainer();
                        }
                }
                return null;
                add_action( 'init', array( $this->cron_scheduler, 'maybe_schedule_events' ) );
                add_action( '\FPML_run_queue', array( $this->queue_processor, 'run_queue' ) );
                add_action( '\FPML_retry_failed', array( $this->retry_manager, 'retry_failed_jobs' ) );
                add_action( '\FPML_resync_outdated', array( $this->cleanup_manager, 'resync_outdated_jobs' ) );
                add_action( '\FPML_cleanup_queue', array( $this->cleanup_manager, 'handle_scheduled_cleanup' ) );
                add_action( 'update_option_' . \FPML_Settings::OPTION_KEY, array( $this->cron_scheduler, 'reschedule_events' ), 10, 2 );
                
                if ( $this->settings && $this->settings->get( 'manual_translation_mode', false ) ) {
                        $this->cron_scheduler->clear_scheduled_event( '\FPML_run_queue' );
                }
        }

        /**
         * Register custom cron intervals.
         *
         * @since 0.2.0
         *
         * @param array<string, array{interval: int, display: string}> $schedules WP cron schedules.
         *
         * @return array<string, array{interval: int, display: string}>
         */
        public function register_schedules( array $schedules ): array {
                return $this->cron_scheduler->register_schedules( $schedules );
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
                        return '\FPML_five_minutes';
                }

                if ( 'hourly' === $frequency ) {
                        return 'hourly';
                }

                return '\FPML_fifteen_minutes';
        }

        /**
         * Register cron hooks if they are not scheduled yet.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function maybe_schedule_events(): void {
                $this->cron_scheduler->maybe_schedule_events();
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
                $this->cron_scheduler->reschedule_events( $old_value, $value );
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
                $this->cron_scheduler->clear_scheduled_event( $hook );
        }

        /**
         * Acquire processor lock to avoid concurrent execution.
         *
         * @since 0.2.0
         *
         * @return bool
         */
	protected function acquire_lock() {
		return $this->batch_manager->acquire_lock();
	}

        /**
         * Release the processor lock.
         *
         * @since 0.2.0
         *
         * @return void
         */
        protected function release_lock() {
		$this->batch_manager->release_lock();
	}

        /**
         * Check whether the processor is currently locked.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        /**
         * Check if processor is locked.
         *
         * @return bool
         */
        public function is_locked(): bool {
		return $this->batch_manager->is_locked();
	}

        /**
         * Force release of the processor lock.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function force_release_lock(): void {
		$this->batch_manager->force_release_lock();
	}

        /**
         * Run a batch of jobs from the queue.
         *
         * @since 0.2.0
         *
         * @return array{claimed: int, processed: int, skipped: int, errors: int}|\WP_Error Summary or error when locked.
         */
        public function run_queue(): array|\WP_Error {
                return $this->queue_processor->run_queue();
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
                $this->notification_manager->notify_admin_if_enabled( $summary );
        }


        /**
         * Retry jobs previously marked as error.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function retry_failed_jobs() {
                $this->retry_manager->retry_failed_jobs();
        }

        /**
         * Move outdated jobs back to pending.
         *
         * @since 0.2.0
         *
         * @return int Number of jobs rescheduled.
         */
        public function resync_outdated_jobs(): int {
                return $this->cleanup_manager->resync_outdated_jobs();
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
                        return new \WP_Error( '\FPML_job_invalid', __( 'Job non valido.', 'fp-multilanguage' ) );
                }

                $this->current_job_characters = 0;

                switch ( $job->object_type ) {
                        case 'post':
                                return $this->process_post_job( $job );

                        case 'term':
                                return $this->process_term_job( $job );

                        case 'menu':
                                return $this->process_menu_job( $job );

                        case 'comment':
                                return $this->process_comment_job( $job );

                        case 'widget':
                                return $this->process_widget_job( $job );

                        case 'string':
                                /**
                                 * Stub per fasi successive.
                                 */
                                return 'skipped';
                }

                return new \WP_Error( '\FPML_job_type_unsupported', sprintf( __( 'Tipo di job %s non supportato.', 'fp-multilanguage' ), $job->object_type ) );
        }

        /**
         * Perform queue cleanup when retention is configured.
         *
         * @since 0.3.1
         *
         * @return void
         */
        protected function maybe_cleanup_queue() {
                $this->cleanup_manager->maybe_cleanup_queue();
        }

        /**
         * Triggered by the scheduled cleanup event.
         *
         * @since 0.3.1
         *
         * @return void
         */
        public function handle_scheduled_cleanup() {
                $this->cleanup_manager->handle_scheduled_cleanup();
        }

        /**
         * Retrieve translator instance based on settings.
         *
         * @since 0.2.0
         *
         * @return \FPML_TranslatorInterface|WP_Error
         */
        protected function get_translator() {
                if ( $this->translator instanceof \FPML_TranslatorInterface ) {
                        if ( $this->translator->is_configured() ) {
                                return $this->translator;
                        }
                }

	$provider = $this->settings ? $this->settings->get( 'provider', '' ) : '';

	switch ( $provider ) {
		case 'openai':
			$translator = new \FPML_Provider_OpenAI();
			break;
		case 'google':
			$translator = new \FPML_Provider_Google();
			break;
		default:
			return new \WP_Error( '\FPML_provider_missing', __( 'Nessun provider configurato.', 'fp-multilanguage' ) );
	}

                if ( ! $translator->is_configured() ) {
                        return new \WP_Error( '\FPML_provider_not_configured', __( 'Il provider selezionato non è configurato.', 'fp-multilanguage' ) );
                }

                $this->translator = $translator;

                return $this->translator;
        }

        /**
         * Expose the configured translator instance.
         *
         * @since 0.2.0
         *
         * @return \FPML_TranslatorInterface|WP_Error
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
	$defaults = array( 'vc_row', 'vc_column', 'vc_section' );

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
                        return new \WP_Error( '\FPML_job_menu_missing', __( 'ID voce di menu mancante.', 'fp-multilanguage' ) );
                }

                $source_item = get_post( $source_id );

                if ( ! ( $source_item instanceof WP_Post ) || 'nav_menu_item' !== $source_item->post_type ) {
                        return new \WP_Error( '\FPML_job_menu_invalid', __( 'La voce di menu sorgente non è disponibile.', 'fp-multilanguage' ) );
                }

                if ( get_post_meta( $source_item->ID, '_fpml_is_translation', true ) ) {
                        return new \WP_Error( '\FPML_job_menu_loop', __( 'Il job punta a una voce di menu già tradotta.', 'fp-multilanguage' ) );
                }

                $field = isset( $job->field ) ? sanitize_key( $job->field ) : 'title';

                if ( 'title' !== $field ) {
                        return new \WP_Error( '\FPML_job_menu_field_unsupported', __( 'Campo voce di menu non gestito.', 'fp-multilanguage' ) );
                }

                // Get translation ID - try to determine target language from job or use first enabled language
                $language_manager = fpml_get_language_manager();
                if ( ! $language_manager ) {
                        return new \WP_Error( '\FPML_job_menu_language_manager_missing', __( 'Language manager non disponibile.', 'fp-multilanguage' ) );
                }
                $enabled_languages = $language_manager->get_enabled_languages();
                
                $target_id = 0;
                $target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
                
                // Try to get translation for the target language using helper function
                $target_id = fpml_get_translation_id( $source_item->ID, $target_lang );
                
                // Backward compatibility: check legacy _fpml_pair_id
                if ( ! $target_id && 'en' === $target_lang ) {
                        $target_id = (int) get_post_meta( $source_item->ID, '_fpml_pair_id', true );
                }

                if ( ! $target_id ) {
                        return new \WP_Error( '\FPML_job_menu_target_missing', __( 'Nessuna voce di menu tradotta collegata.', 'fp-multilanguage' ) );
                }

                $target_item = get_post( $target_id );

                if ( ! ( $target_item instanceof WP_Post ) || 'nav_menu_item' !== $target_item->post_type ) {
                        return new \WP_Error( '\FPML_job_menu_target_invalid', __( 'La voce di menu di destinazione non esiste.', 'fp-multilanguage' ) );
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

                $result = $this->safe_update_post(
                        array(
                                'ID'         => $target_item->ID,
                                'post_title' => $translated,
                        )
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
                do_action( '\FPML_menu_item_translated', $target_item, 'title', $translated, $job );

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
			return new \WP_Error( '\FPML_job_term_missing', __( 'ID termine mancante.', 'fp-multilanguage' ) );
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
			return new \WP_Error( '\FPML_job_term_field_invalid', __( 'Campo termine non valido.', 'fp-multilanguage' ) );
		}

		$term = $taxonomy ? get_term( $term_id, $taxonomy ) : get_term( $term_id );

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( '\FPML_job_term_invalid', __( 'Il termine sorgente non è disponibile.', 'fp-multilanguage' ) );
		}

		if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
			return new \WP_Error( '\FPML_job_term_loop', __( 'Il job punta a un termine già tradotto.', 'fp-multilanguage' ) );
		}

		$taxonomy = sanitize_key( $term->taxonomy );

		// Get translation ID for terms - try first enabled language
		$language_manager = fpml_get_language_manager();
		if ( ! $language_manager ) {
			return new \WP_Error( '\FPML_job_term_language_manager_missing', __( 'Language manager non disponibile.', 'fp-multilanguage' ) );
		}
		$enabled_languages = $language_manager->get_enabled_languages();
		$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
		
		// Try language-specific meta key
		$meta_key = '_fpml_pair_id_' . $target_lang;
		$target_id = (int) get_term_meta( $term->term_id, $meta_key, true );
		
		// Backward compatibility: check legacy _fpml_pair_id
		if ( ! $target_id && 'en' === $target_lang ) {
			$target_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );
		}

		if ( ! $target_id ) {
			return new \WP_Error( '\FPML_job_term_target_missing', __( 'Nessuna traduzione collegata disponibile.', 'fp-multilanguage' ) );
		}

		$target_term = $taxonomy ? get_term( $target_id, $taxonomy ) : get_term( $target_id );

		if ( ! $target_term || is_wp_error( $target_term ) ) {
			return new \WP_Error( '\FPML_job_term_target_invalid', __( 'Il termine di destinazione non esiste.', 'fp-multilanguage' ) );
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
				return new \WP_Error( '\FPML_job_term_field_unsupported', __( 'Campo termine non gestito.', 'fp-multilanguage' ) );
		}

		// Se entrambi i valori sono vuoti, salta
		if ( '' === $source_value && '' === $target_value ) {
			$this->update_term_status_flag( $target_term->term_id, $field, 'synced' );
			if ( class_exists( '\FPML_Language' ) ) {
				$language = ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() );
				if ( $language && method_exists( $language, 'set_term_pair' ) ) {
					$language->set_term_pair( $term->term_id, $target_term->term_id );
				}
			}

			return 'skipped';
		}
		
		// Per i nomi dei termini, se il sorgente e target sono identici E il sorgente non è vuoto,
		// procedi comunque con la traduzione (potrebbe essere che il termine non sia ancora stato tradotto)
		// Questo è importante per termini come "Senza categoria" che devono essere tradotti
		$force_translation = ( 'name' === $field && $source_value === $target_value && '' !== $source_value );

		$context = array(
			'field'               => 'term:' . $field,
			'target_value'        => $force_translation ? '' : $target_value, // Se forziamo, usa stringa vuota per evitare che il diff salti
			'excluded_shortcodes' => $this->get_excluded_shortcodes(),
		);

		$translated = $this->translate_value_recursive( $source_value, $context );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		$translated = call_user_func( $sanitize, $translated );

		// Se il valore tradotto è uguale al target E non stiamo forzando, salta
		if ( ! $force_translation && $translated === $target_value ) {
			$this->update_term_status_flag( $target_term->term_id, $field, 'synced' );
			( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() )->set_term_pair( $term->term_id, $target_term->term_id );

			return 'skipped';
		}

		$args = array();

		if ( 'name' === $field ) {
			$args['name'] = $translated;
		} else {
			$args['description'] = $translated;
		}

		$result = \fpml_safe_update_term( $target_term->term_id, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->update_term_status_flag( $target_term->term_id, $field, 'synced' );
		if ( class_exists( '\FPML_Language' ) ) {
			$language = ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() );
			if ( $language && method_exists( $language, 'set_term_pair' ) ) {
				$language->set_term_pair( $term->term_id, $target_term->term_id );
			}
		}

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
		do_action( '\FPML_term_translated', $target_term, $field, $translated, $job );

		return true;
	}

	/**
	 * Process a comment translation job.
	 *
	 * @since 0.9.1
	 *
	 * @param object $job Job entry.
	 *
	 * @return true|WP_Error|string
	 */
	protected function process_comment_job( $job ) {
		$comment_id = isset( $job->object_id ) ? (int) $job->object_id : 0;

		if ( ! $comment_id ) {
			return new \WP_Error( '\FPML_job_comment_missing', __( 'ID commento mancante.', 'fp-multilanguage' ) );
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return new \WP_Error( '\FPML_job_comment_invalid', __( 'Il commento sorgente non è disponibile.', 'fp-multilanguage' ) );
		}

		if ( get_comment_meta( $comment_id, '_fpml_is_translation', true ) ) {
			return new \WP_Error( '\FPML_job_comment_loop', __( 'Il job punta a un commento già tradotto.', 'fp-multilanguage' ) );
		}

		$field = isset( $job->field ) ? sanitize_key( $job->field ) : 'comment_content';

		if ( 'comment_content' !== $field ) {
			return new \WP_Error( '\FPML_job_comment_field_unsupported', __( 'Campo commento non gestito.', 'fp-multilanguage' ) );
		}

		$target_comment_id = (int) get_comment_meta( $comment_id, '_fpml_pair_id', true );

		if ( ! $target_comment_id ) {
			return new \WP_Error( '\FPML_job_comment_target_missing', __( 'Nessun commento tradotto collegato.', 'fp-multilanguage' ) );
		}

		$target_comment = get_comment( $target_comment_id );

		if ( ! $target_comment ) {
			return new \WP_Error( '\FPML_job_comment_target_invalid', __( 'Il commento di destinazione non esiste.', 'fp-multilanguage' ) );
		}

		$source_value = (string) $comment->comment_content;
		$target_value = (string) $target_comment->comment_content;

		if ( '' === $source_value && '' === $target_value ) {
			return 'skipped';
		}

		$context = array(
			'field'               => 'comment_content',
			'target_value'        => $target_value,
			'excluded_shortcodes' => $this->get_excluded_shortcodes(),
		);

		$translated = $this->translate_value_recursive( $source_value, $context );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		$translated = wp_kses_post( $translated );

		if ( $translated === $target_value ) {
			return 'skipped';
		}

		$result = wp_update_comment(
			array(
				'comment_ID'      => $target_comment_id,
				'comment_content' => $translated,
			)
		);

		if ( ! $result ) {
			return new \WP_Error( '\FPML_job_comment_update_failed', __( 'Impossibile aggiornare il commento tradotto.', 'fp-multilanguage' ) );
		}

		/**
		 * Allow third parties to react to comment translations.
		 *
		 * @since 0.9.1
		 *
		 * @param WP_Comment $target_comment Translated comment.
		 * @param string     $field         Field identifier.
		 * @param string     $translated     Translated value.
		 * @param object     $job           Job entry.
		 */
		do_action( '\FPML_comment_translated', $target_comment, $field, $translated, $job );

		return true;
	}

	/**
	 * Process a widget translation job.
	 *
	 * @since 0.9.1
	 *
	 * @param object $job Job entry.
	 *
	 * @return true|WP_Error|string
	 */
	protected function process_widget_job( $job ) {
		$widget_id = isset( $job->object_id ) ? (string) $job->object_id : '';

		if ( '' === $widget_id ) {
			return new \WP_Error( '\FPML_job_widget_missing', __( 'ID widget mancante.', 'fp-multilanguage' ) );
		}

		$field = isset( $job->field ) ? sanitize_key( $job->field ) : 'text';

		// Parse widget ID (format: widget_id_base_number)
		$parts = explode( '_', $widget_id, 2 );
		if ( count( $parts ) < 2 ) {
			return new \WP_Error( '\FPML_job_widget_invalid', __( 'ID widget non valido.', 'fp-multilanguage' ) );
		}

		$widget_number = array_pop( $parts );
		$widget_id_base = implode( '_', $parts );

		// Ottieni valore italiano
		$option_key = "fpml_widget_{$widget_id_base}_{$widget_number}_{$field}_it";
		$source_value = get_option( $option_key, '' );

		if ( '' === $source_value ) {
			return 'skipped';
		}

		// Ottieni valore inglese esistente
		$option_key_en = "fpml_widget_{$widget_id_base}_{$widget_number}_{$field}_en";
		$target_value = get_option( $option_key_en, '' );

		$context = array(
			'field'               => 'widget:' . $field,
			'target_value'        => $target_value,
			'excluded_shortcodes' => $this->get_excluded_shortcodes(),
		);

		$translated = $this->translate_value_recursive( $source_value, $context );

		if ( is_wp_error( $translated ) ) {
			return $translated;
		}

		$translated = wp_kses_post( $translated );

		if ( $translated === $target_value ) {
			return 'skipped';
		}

		// Salva traduzione
		update_option( $option_key_en, $translated, false );

		// Aggiorna widget instance
		$widget_option = get_option( "widget_{$widget_id_base}", array() );
		if ( isset( $widget_option[ $widget_number ] ) ) {
			$widget_option[ $widget_number ][ $field ] = $translated;
			update_option( "widget_{$widget_id_base}", $widget_option );
		}

		/**
		 * Allow third parties to react to widget translations.
		 *
		 * @since 0.9.1
		 *
		 * @param string $widget_id Widget identifier.
		 * @param string $field     Field identifier.
		 * @param string $translated Translated value.
		 * @param object $job       Job entry.
		 */
		do_action( '\FPML_widget_translated', $widget_id, $field, $translated, $job );

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
                        return new \WP_Error( '\FPML_job_post_missing', __( 'ID post mancante.', 'fp-multilanguage' ) );
                }

                $source_post = get_post( $post_id );

                if ( ! $source_post || 'trash' === $source_post->post_status ) {
                        return new \WP_Error( '\FPML_job_post_invalid', __( 'Il contenuto sorgente non è disponibile.', 'fp-multilanguage' ) );
                }

                if ( get_post_meta( $source_post->ID, '_fpml_is_translation', true ) ) {
                        return new \WP_Error( '\FPML_job_post_loop', __( 'Il job punta a un contenuto già tradotto.', 'fp-multilanguage' ) );
                }

                // Get translation ID - check if explicitly provided in job first
                $target_id = 0;
                $target_lang = null;
                
                // If target_id is explicitly provided in the job (e.g., from translate_post_directly), use it
                if ( isset( $job->target_id ) && $job->target_id > 0 ) {
                        $target_id = (int) $job->target_id;
                        // Determine language from target post meta
                        $target_lang = get_post_meta( $target_id, '_fpml_language', true );
                        if ( empty( $target_lang ) ) {
                                $target_lang = 'en'; // Fallback default
                        }
                } else {
                        // Try to determine target language from all enabled languages
                        // Since jobs are enqueued for source_post but we need to find which translation to update,
                        // we check all enabled languages and find translations that need this field updated
                        $language_manager = fpml_get_language_manager();
                        if ( ! $language_manager ) {
                                return new \WP_Error( '\FPML_job_post_language_manager_missing', __( 'Language manager non disponibile.', 'fp-multilanguage' ) );
                        }
                        $enabled_languages = $language_manager->get_enabled_languages();
                        
                        // Try to find which translation needs this field updated by checking status flags
                        // This handles the case where we have multiple translations (EN, DE, FR, etc.)
                        $field_key = str_replace( ':', '_', $job->field );
                        $status_key = '_fpml_status_' . $field_key;
                        
                        foreach ( $enabled_languages as $lang ) {
                                $translation_id = fpml_get_translation_id( $source_post->ID, $lang );
                                if ( $translation_id ) {
                                        $translation_post = get_post( $translation_id );
                                        if ( $translation_post && get_post_meta( $translation_id, '_fpml_is_translation', true ) ) {
                                                // Check if this translation has status flag indicating it needs this field
                                                $status = get_post_meta( $translation_id, $status_key, true );
                                                $translation_status = get_post_meta( $translation_id, '_fpml_translation_status', true );
                                                
                                                if ( 'needs_update' === $status || 'pending' === $translation_status ) {
                                                        $target_id = $translation_id;
                                                        $target_lang = $lang;
                                                        break;
                                                }
                                        }
                                }
                        }
                        
                        // Fallback: if no specific translation found with needs_update status, use first enabled language
                        if ( ! $target_id ) {
                                $target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
                                $target_id = fpml_get_translation_id( $source_post->ID, $target_lang );
                                
                                // Backward compatibility: check legacy _fpml_pair_id
                                if ( ! $target_id && 'en' === $target_lang ) {
                                        $target_id = (int) get_post_meta( $source_post->ID, '_fpml_pair_id', true );
                                }
                        }
                }

                if ( ! $target_id ) {
                        return new \WP_Error( '\FPML_job_post_target_missing', __( 'Nessuna traduzione collegata disponibile.', 'fp-multilanguage' ) );
                }

                $target_post = get_post( $target_id );

                if ( ! $target_post ) {
                        return new \WP_Error( '\FPML_job_post_target_invalid', __( 'Il post di destinazione non esiste.', 'fp-multilanguage' ) );
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
			$this->update_post_status_flag( $target_post->ID, $field, 'failed' );
			$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

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
				
				// Handle PCRE errors
				if ( null === $plain_text ) {
					$plain_text = wp_strip_all_tags( $translated_preview );
				}
				
				$plain_text         = trim( $plain_text );
				$words              = preg_split( '/\s+/u', $plain_text );
				$word_count         = ( '' === $plain_text || false === $words ) ? 0 : count( $words );
                                $provider           = $this->translator instanceof \FPML_TranslatorInterface ? $this->translator->get_slug() : '';
                                $cost               = $this->translator instanceof \FPML_TranslatorInterface ? $this->translator->estimate_cost( $payload_text ) : 0.0;

                                if ( class_exists( '\FPML_Export_Import' ) ) {
                                        $export_import = function_exists( 'fpml_get_export_import' ) ? fpml_get_export_import() : ( function_exists( 'fpml_get_export_import' ) ? fpml_get_export_import() : \FPML_Export_Import::instance() );
                                        if ( $export_import && method_exists( $export_import, 'record_sandbox_preview' ) ) {
                                                $export_import->record_sandbox_preview(
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
                                        }
                                }

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

			$this->update_post_status_flag( $target_post->ID, $field, 'synced' );
			$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

			return 'skipped';
                        }

                        update_post_meta( $target_post->ID, $meta_key, $translated_value );

		// Version saving temporarily disabled - TODO: fix parameter mismatch
		// if ( class_exists( '\FP\Multilanguage\Versioning\TranslationVersioning' ) ) {
		// 	\FP\Multilanguage\Versioning\TranslationVersioning::instance()->save_version(...);
		// }

		$this->update_post_status_flag( $target_post->ID, $field, 'synced' );

                        do_action( '\FPML_post_translated', $target_post, $field, $translated_value, $job );

		$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

                        return true;
                }

                $source_value = $this->get_post_field_value( $source_post, $field );
                $target_value = $this->get_post_field_value( $target_post, $field );

	if ( '' === $source_value && '' === $target_value ) {
		$this->update_post_status_flag( $target_post->ID, $field, 'synced' );
		$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

		return 'skipped';
	}

                // Per slug e titolo, forza sempre la traduzione completa (non usare diff)
                $force_full_translation = false;
                if ( 'slug' === $field || 'post_title' === $field ) {
                        $force_full_translation = true;
                } else {
                        // Se il contenuto sorgente è molto più lungo del target, forza la traduzione
                        // Questo gestisce il caso in cui il target ha solo un placeholder o contenuto minimo
                        $source_length = strlen( $source_value );
                        $target_length = strlen( $target_value );
                        
                        // Se il sorgente è significativamente più lungo (> 3x) o il target è molto corto (< 100 caratteri), forza la traduzione completa
                        if ( $source_length > 500 && ( $target_length < 100 || ( $target_length > 0 && $source_length > ( $target_length * 3 ) ) ) ) {
                                // Il target probabilmente contiene solo un placeholder o contenuto minimo
                                // Traduci direttamente tutto il contenuto sorgente senza usare il diff
                                $force_full_translation = true;
                        }
                }

                if ( $force_full_translation ) {
                        // Traduci direttamente tutto il contenuto sorgente
                        $translator = $this->get_translator();
                        if ( is_wp_error( $translator ) ) {
                                $this->update_post_status_flag( $target_post->ID, $field, 'failed' );
                                $this->maybe_update_translation_status( $source_post->ID, $target_post->ID );
                                return $translator;
                        }
                        
                        $domain = $this->resolve_domain_for_field( $field );
                        $new_value = $this->attempt_translation_with_backoff( $translator, $source_value, $domain, 1 );
                        
                        if ( is_wp_error( $new_value ) ) {
                                $this->update_post_status_flag( $target_post->ID, $field, 'failed' );
                                $this->maybe_update_translation_status( $source_post->ID, $target_post->ID );
                                return $new_value;
                        }
                        
                        // Salva direttamente il valore tradotto
                        $this->save_post_field_value( $target_post, $field, (string) $new_value );
                        $this->update_post_status_flag( $target_post->ID, $field, 'synced' );
                        $this->maybe_update_translation_status( $source_post->ID, $target_post->ID );
                        
                        do_action( '\FPML_post_translated', $target_post, $field, $new_value, $job );
                        
                        return true;
                }

                $diff = null;
                if ( class_exists( '\FPML_Content_Diff' ) ) {
                        $content_diff = function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() );
                        if ( $content_diff && method_exists( $content_diff, 'calculate_diff' ) ) {
                                $diff = $content_diff->calculate_diff(
                                        $source_value,
                                        $target_value,
                                        array(
                                                'excluded_shortcodes' => $this->get_excluded_shortcodes(),
                                        )
                                );
                        }
                }
                if ( ! $diff || ! is_array( $diff ) ) {
                        // Fallback se Content_Diff non è disponibile
                        $diff = array(
                                'segments' => array(),
                                'source_tokens' => array(),
                                'target_map' => array(),
                        );
                }

                $chunks = isset( $diff['segments'] ) ? $diff['segments'] : array();

	if ( empty( $chunks ) ) {
		$this->update_post_status_flag( $target_post->ID, $field, 'synced' );
		$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

		return true;
	}

                $translations = $this->translate_segments( $chunks, $field );

	if ( is_wp_error( $translations ) ) {
		$this->update_post_status_flag( $target_post->ID, $field, 'failed' );
		$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

		return $translations;
	}

                $new_value = null;
                if ( class_exists( '\FPML_Content_Diff' ) && isset( $diff['source_tokens'] ) && isset( $diff['target_map'] ) ) {
                        $content_diff = function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() );
                        if ( $content_diff && method_exists( $content_diff, 'rebuild' ) ) {
                                $new_value = $content_diff->rebuild(
                                        $diff['source_tokens'],
                                        $diff['target_map'],
                                        $translations,
                                        isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                                );
                        }
                }
                if ( null === $new_value ) {
                        // Fallback se Content_Diff non è disponibile
                        $new_value = $source_value;
                }

	if ( $this->settings && $this->settings->get( 'sandbox_mode', false ) ) {
                        $payload_text = implode( "\n\n", array_map( 'strval', $chunks ) );
			$characters   = function_exists( 'mb_strlen' ) ? mb_strlen( $payload_text, 'UTF-8' ) : strlen( $payload_text );
			$plain_text   = wp_strip_all_tags( $new_value );
			$plain_text   = preg_replace( '/\s+/u', ' ', $plain_text );
			
			// Handle PCRE errors
			if ( null === $plain_text ) {
				$plain_text = wp_strip_all_tags( $new_value );
			}
			
			$plain_text   = trim( $plain_text );
			$words        = preg_split( '/\s+/u', $plain_text );
			$word_count   = ( '' === $plain_text || false === $words ) ? 0 : count( $words );
                        $provider     = $this->translator instanceof \FPML_TranslatorInterface ? $this->translator->get_slug() : '';
                        $cost         = $this->translator instanceof \FPML_TranslatorInterface ? $this->translator->estimate_cost( $payload_text ) : 0.0;

                        if ( class_exists( '\FPML_Export_Import' ) ) {
                                $export_import = function_exists( 'fpml_get_export_import' ) ? fpml_get_export_import() : ( function_exists( 'fpml_get_export_import' ) ? fpml_get_export_import() : \FPML_Export_Import::instance() );
                                if ( $export_import && method_exists( $export_import, 'record_sandbox_preview' ) ) {
                                        $export_import->record_sandbox_preview(
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
                                }
                        }

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

		$this->update_post_status_flag( $target_post->ID, $field, 'synced' );
		$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

		return 'skipped';
                }

                $this->save_post_field_value( $target_post, $field, $new_value );

	$this->update_post_status_flag( $target_post->ID, $field, 'synced' );

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
                do_action( '\FPML_post_translated', $target_post, $field, $new_value, $job );

	$this->maybe_update_translation_status( $source_post->ID, $target_post->ID );

                return true;
        }

        /**
         * Traduce direttamente un post senza usare la coda (traduzione immediata).
         *
         * @since 0.9.1
         *
         * @param WP_Post $source_post Post sorgente italiano.
         * @param WP_Post $target_post Post destinazione inglese.
         *
         * @return array Risultato con statistiche: { 'translated' => int, 'skipped' => int, 'errors' => int }
         */
        public function translate_post_directly( $source_post, $target_post ) {
                $result = array(
                        'translated' => 0,
                        'skipped'    => 0,
                        'errors'     => 0,
                );

                // Campi principali da tradurre (incluso slug)
                $fields = array( 'post_title', 'post_excerpt', 'post_content', 'slug' );

                // Aggiungi meta fields se configurati
                $meta_whitelist = $this->settings ? $this->settings->get( 'meta_whitelist', '' ) : '';
                if ( ! empty( $meta_whitelist ) && is_string( $meta_whitelist ) ) {
                        $meta_keys = preg_split( '/[\n,]+/', $meta_whitelist );
                        if ( is_array( $meta_keys ) ) {
                                foreach ( $meta_keys as $meta_key ) {
                                        $meta_key = trim( $meta_key );
                                        if ( ! empty( $meta_key ) ) {
                                                // Verifica che il meta esista nel post sorgente
                                                $value = get_post_meta( $source_post->ID, $meta_key, true );
                                                if ( ! empty( $value ) ) {
                                                        $fields[] = 'meta:' . $meta_key;
                                                }
                                        }
                                }
                        }
                }

                // Crea job fittizi per ogni campo (senza salvarli nella coda)
                foreach ( $fields as $field ) {
                        $fake_job = (object) array(
                                'id'         => 0,
                                'object_id'  => $source_post->ID,
                                'object_type' => 'post',
                                'field'      => $field,
                                'state'      => 'translating',
                                'target_id'  => $target_post->ID,
                        );

                        try {
                                $translation_result = $this->process_post_job( $fake_job );

                                if ( is_wp_error( $translation_result ) ) {
                                        $result['errors']++;
                                        // Log error but continue with next field
                                        if ( $this->logger ) {
                                                $this->logger->log( 'warning', sprintf( 'Translation error for field %s: %s', $field, $translation_result->get_error_message() ) );
                                        }
                                } elseif ( 'skipped' === $translation_result ) {
                                        $result['skipped']++;
                                } else {
                                        $result['translated']++;
                                }
                        } catch ( \Exception $e ) {
                                $result['errors']++;
                                if ( $this->logger ) {
                                        $this->logger->log( 'error', sprintf( 'Exception translating field %s: %s', $field, $e->getMessage() ) );
                                }
                                // Continue with next field
                        } catch ( \Error $e ) {
                                $result['errors']++;
                                if ( $this->logger ) {
                                        $this->logger->log( 'error', sprintf( 'Fatal error translating field %s: %s', $field, $e->getMessage() ) );
                                }
                                // Continue with next field
                        }
                }

                // Traduci anche le taxonomies (categorie, tag, ecc.)
                $this->translate_post_taxonomies( $source_post, $target_post );

                return $result;
        }

        /**
         * Traduce le taxonomies di un post (categorie, tag, ecc.).
         *
         * @since 0.9.1
         *
         * @param WP_Post $source_post Post sorgente italiano.
         * @param WP_Post $target_post Post destinazione inglese.
         *
         * @return void
         */
        protected function translate_post_taxonomies( $source_post, $target_post ) {
                $translation_manager = \FP\Multilanguage\Core\Container::get( 'translation_manager' );
                $job_enqueuer = \FP\Multilanguage\Core\Container::get( 'job_enqueuer' );

                if ( ! $translation_manager || ! $job_enqueuer ) {
                        return;
                }

                // Ottieni taxonomies traducibili
                $taxonomies = get_taxonomies(
                        array(
                                'public' => true,
                        ),
                        'names'
                );

                $custom_taxonomies = get_option( '\FPML_custom_translatable_taxonomies', array() );
                if ( ! empty( $custom_taxonomies ) ) {
                        $taxonomies = array_merge( $taxonomies, $custom_taxonomies );
                }

                $taxonomies = apply_filters( '\FPML_translatable_taxonomies', $taxonomies );

                if ( empty( $taxonomies ) ) {
                        return;
                }

                // Per ogni taxonomy, sincronizza e traduce i termini
                foreach ( $taxonomies as $taxonomy ) {
                        $source_terms = wp_get_post_terms( $source_post->ID, $taxonomy, array( 'fields' => 'ids' ) );

                        if ( empty( $source_terms ) || is_wp_error( $source_terms ) ) {
                                continue;
                        }

                        $translated_term_ids = array();

                        foreach ( $source_terms as $term_id ) {
                                // Crea o ottieni traduzione del termine
                                $target_term_id = $translation_manager->ensure_term_translation( $term_id, $taxonomy );

                                if ( $target_term_id ) {
                                        $target_term = get_term( $target_term_id, $taxonomy );
                                        if ( $target_term && ! is_wp_error( $target_term ) ) {
                                                $translated_term_ids[] = (int) $target_term->term_id;

                                                // Traduci direttamente nome e descrizione del termine
                                                $source_term = get_term( $term_id, $taxonomy );
                                                if ( $source_term && ! is_wp_error( $source_term ) ) {
                                                        // Accoda job per tradurre
                                                        $job_enqueuer->enqueue_term_jobs( $source_term, $target_term );
                                                        
                                                        // Processa immediatamente i job del termine
                                                        $term_jobs = array(
                                                                (object) array(
                                                                        'id'         => 0,
                                                                        'object_id'  => $source_term->term_id,
                                                                        'object_type' => 'term',
                                                                        'field'      => 'name',
                                                                        'state'      => 'translating',
                                                                ),
                                                                (object) array(
                                                                        'id'         => 0,
                                                                        'object_id'  => $source_term->term_id,
                                                                        'object_type' => 'term',
                                                                        'field'      => 'description',
                                                                        'state'      => 'translating',
                                                                ),
                                                        );

                                                        foreach ( $term_jobs as $term_job ) {
                                                                $this->process_term_job( $term_job );
                                                        }
                                                }
                                        }
                                }
                        }

                        // Assegna i termini tradotti al post inglese
                        if ( ! empty( $translated_term_ids ) ) {
                                wp_set_post_terms( $target_post->ID, $translated_term_ids, $taxonomy, false );
                        }
                }
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

                                // Rimuovi il prefisso en- se presente (per post inglesi)
                                $slug = preg_replace( '/^en[-_]/i', '', $slug );

                                // Per la traduzione, converti lo slug in testo leggibile
                                // Sostituisci i trattini con spazi per permettere la traduzione
                                $slug = str_replace( array( '-', '_' ), ' ', $slug );
                                
                                // Se lo slug è vuoto dopo la rimozione del prefisso, usa il titolo
                                if ( '' === trim( $slug ) ) {
                                        $slug = $post->post_title;
                                }

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

                return (string) apply_filters( '\FPML_get_post_field_value', '', $post, $field );
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
                $new_value = apply_filters( '\FPML_pre_save_translation', $new_value, $post, $field );

                // Get provider for versioning
                $provider = 'gpt-5-nano';
                $container = $this->getContainer();
                $settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
                if ( $settings ) {
                        $provider = $settings->get( 'openai_model', 'gpt-5-nano' );
                }

                switch ( $field ) {
                        case 'post_title':
                                $this->safe_update_post(
                                        array(
                                                'ID'         => $post->ID,
                                                'post_title' => sanitize_text_field( $new_value ),
                                        )
                                );
                                // Version saving temporarily disabled - TODO: fix parameter mismatch
                                return;
                        case 'post_excerpt':
                                $this->safe_update_post(
                                        array(
                                                'ID'           => $post->ID,
                                                'post_excerpt' => wp_kses_post( $new_value ),
                                        )
                                );
                                // Version saving temporarily disabled - TODO: fix parameter mismatch
                                return;
                        case 'post_content':
                                // Rimuovi il placeholder HTML se presente
                                $new_value = preg_replace(
                                        '/<!-- FPML Translation Placeholder -->.*?<\/p>\s*<p><em>Original page:.*?<\/em><\/p>/s',
                                        '',
                                        $new_value
                                );
                                // Rimuovi anche versioni semplificate del placeholder
                                $new_value = preg_replace(
                                        '/<p>This page is being translated from Italian\. Content will be updated automatically\.<\/p>/i',
                                        '',
                                        $new_value
                                );
                                $new_value = trim( $new_value );
                                
                                $this->safe_update_post(
                                        array(
                                                'ID'           => $post->ID,
                                                'post_content' => wp_kses_post( $new_value ),
                                        )
                                );
                                // Version saving temporarily disabled - TODO: fix parameter mismatch
                                return;
                        case 'slug':
                                if ( class_exists( '\FPML_SEO' ) ) {
                                        $seo = ( function_exists( 'fpml_get_seo' ) ? fpml_get_seo() : \FPML_SEO::instance() );
                                        if ( $seo && method_exists( $seo, 'handle_slug_translation' ) ) {
                                                $seo->handle_slug_translation( $post, $new_value );
                                        }
                                }
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
                do_action( '\FPML_save_post_field_value', $post, $field, $new_value );
        }

	/**
         * Update translation status flag for a translated post.
         *
         * @since 0.9.0
         *
         * @param int    $post_id Target post ID.
         * @param string $field   Field identifier.
         * @param string $status  Status slug.
         *
         * @return void
         */
        protected function update_post_status_flag( $post_id, $field, $status ) {
                $meta_key = '_fpml_status_' . sanitize_key( str_replace( ':', '_', $field ) );

                update_post_meta( $post_id, $meta_key, sanitize_key( $status ) );
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
         * Update overall translation status for a post pair.
         *
         * @since 0.9.0
         *
         * @param int $source_post_id Source (Italian) post ID.
         * @param int $target_post_id Target (English) post ID.
         *
         * @return void
         */
        protected function maybe_update_translation_status( $source_post_id, $target_post_id ) {
                $queue_pending = false;

                if ( $this->queue && method_exists( $this->queue, 'has_pending_jobs' ) ) {
                        $queue_pending = (bool) $this->queue->has_pending_jobs( $source_post_id );
                }

                if ( $queue_pending ) {
                        update_post_meta( $target_post_id, '_fpml_translation_status', 'pending' );
                        return;
                }

                if ( $this->has_unsynced_post_fields( $target_post_id ) ) {
                        update_post_meta( $target_post_id, '_fpml_translation_status', 'partial' );
                        return;
                }

                update_post_meta( $target_post_id, '_fpml_translation_status', 'completed' );
                update_post_meta( $target_post_id, '_fpml_last_sync', current_time( 'mysql' ) );
        }

        /**
         * Detect whether translated fields still require synchronization.
         *
         * @since 0.9.0
         *
         * @param int $target_post_id Target post ID.
         *
         * @return bool
         */
        protected function has_unsynced_post_fields( $target_post_id ) {
                global $wpdb;

                $allowed = array( 'synced', 'done', 'automatic', 'auto' );
                $placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
                $prepared_args = array_merge(
                        array( $target_post_id, '_fpml_status_%' ),
                        array_map( 'strtolower', $allowed )
                );

                $sql = $wpdb->prepare(
                        "SELECT meta_id FROM {$wpdb->postmeta}
                        WHERE post_id = %d
                        AND meta_key LIKE %s
                        AND (meta_value IS NULL OR meta_value = '' OR LOWER(meta_value) NOT IN ($placeholders))
                        LIMIT 1",
                        $prepared_args
                );

                $unsynced = $wpdb->get_var( $sql );

                return ! empty( $unsynced );
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

                // ✅ QA RACCOMANDAZIONE: Limite esplicito per contenuto totale (10MB)
                $max_total_size = 10 * 1024 * 1024; // 10MB
                $total_size = 0;
                foreach ( $segments as $segment ) {
                        $total_size += function_exists( 'mb_strlen' ) ? mb_strlen( (string) $segment, 'UTF-8' ) : strlen( (string) $segment );
                }
                
                if ( $total_size > $max_total_size ) {
                        \FP\Multilanguage\Logger::warning(
                                'Content too large for translation',
                                array(
                                        'size'        => $total_size,
                                        'max_size'    => $max_total_size,
                                        'field'       => $field,
                                )
                        );
                        return new \WP_Error(
                                '\FPML_content_too_large',
                                sprintf(
                                        __( 'Contenuto troppo grande per la traduzione (%d bytes, massimo %d bytes).', 'fp-multilanguage' ),
                                        $total_size,
                                        $max_total_size
                                )
                        );
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
                                $masked_text = $chunk_data['text'];
                                $map = array();
                                if ( class_exists( '\FPML_Content_Diff' ) ) {
                                        $content_diff = function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() );
                                        if ( $content_diff && method_exists( $content_diff, 'prepare_text_for_provider' ) ) {
                                                list( $masked_text, $map ) = $content_diff->prepare_text_for_provider( $chunk_data['text'], $shortcodes );
                                        }
                                }
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
                                if ( class_exists( '\FPML_Content_Diff' ) ) {
                                        $content_diff = function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() );
                                        if ( $content_diff && method_exists( $content_diff, 'restore_placeholders' ) ) {
                                                $response = $content_diff->restore_placeholders( $response, $chunk_placeholders[ $chunk_index ] );
                                        }
                                }
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

                        $diff = null;
                        if ( class_exists( '\FPML_Content_Diff' ) ) {
                                $content_diff = function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() );
                                if ( $content_diff && method_exists( $content_diff, 'calculate_diff' ) ) {
                                        $diff = $content_diff->calculate_diff(
                                                $string,
                                                $target_string,
                                                array(
                                                        'excluded_shortcodes' => $context['excluded_shortcodes'],
                                                )
                                        );
                                }
                        }
                        if ( ! $diff || ! is_array( $diff ) ) {
                                // Fallback se Content_Diff non è disponibile
                                return $string;
                        }

                        $segments = isset( $diff['segments'] ) ? $diff['segments'] : array();

                        if ( empty( $segments ) ) {
                                $content_diff = class_exists( '\FPML_Content_Diff' ) ? ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() ) : null;
                                if ( $content_diff && method_exists( $content_diff, 'restore_placeholders' ) ) {
                                        if ( '' !== $target_string ) {
                                                return $content_diff->restore_placeholders(
                                                        $target_string,
                                                        isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                                                );
                                        }

                                        return $content_diff->restore_placeholders(
                                                $string,
                                                isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                                        );
                                }
                                // Fallback
                                return '' !== $target_string ? $target_string : $string;
                        }

                        $field        = isset( $context['field'] ) ? $context['field'] : 'meta';
                        $translations = $this->translate_segments( $segments, $field );

                        if ( is_wp_error( $translations ) ) {
                                return $translations;
                        }

                        $content_diff = class_exists( '\FPML_Content_Diff' ) ? ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() ) : null;
                        if ( $content_diff && method_exists( $content_diff, 'rebuild' ) && isset( $diff['source_tokens'] ) && isset( $diff['target_map'] ) ) {
                                return $content_diff->rebuild(
                                        $diff['source_tokens'],
                                        $diff['target_map'],
                                        $translations,
                                        isset( $diff['placeholder_map'] ) ? $diff['placeholder_map'] : array()
                                );
                        }
                        // Fallback se Content_Diff non è disponibile
                        return $string;
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
                        $clone         = 'stdClass' === $class ? new \stdClass() : clone $value;

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
         * @param \FPML_TranslatorInterface $translator Provider instance.
         * @param string                   $text       Payload to translate.
         * @param string                   $domain     Context domain.
         * @param int                      $attempt    Attempt counter.
         *
         * @return string|WP_Error
         */
        protected function attempt_translation_with_backoff( $translator, $text, $domain, $attempt ) {
                $max_attempts = 2; // Reduced from 4 to avoid long blocking
                $delay        = 1;
                $start_time   = time();
                $max_time     = 45; // Maximum 45 seconds for all attempts

                for ( $i = 0; $i < $max_attempts; $i++ ) {
                        // Check if we've exceeded max time
                        if ( ( time() - $start_time ) > $max_time ) {
                                return new \WP_Error( '\FPML_translation_timeout', __( 'Traduzione interrotta per timeout.', 'fp-multilanguage' ) );
                        }

                        try {
                                $result = $translator->translate( $text, 'it', 'en', $domain );
                        } catch ( \Exception $e ) {
                                $result = new \WP_Error( '\FPML_translation_exception', $e->getMessage() );
                        } catch ( \Error $e ) {
                                $result = new \WP_Error( '\FPML_translation_error', $e->getMessage() );
                        }

                        if ( ! is_wp_error( $result ) ) {
                                return (string) $result;
                        }

                        $delay = min( 5, $delay * 2 ); // Reduced max delay from 30s to 5s
                        $sleep = $delay + wp_rand( 0, 500 ) / 1000;

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
                        $sleep = apply_filters( '\FPML_translation_retry_delay', $sleep, $domain );

                        usleep( (int) ( $sleep * 1000000 ) );
                }

                return new \WP_Error( '\FPML_translation_failed', __( 'Traduzione non riuscita dopo vari tentativi.', 'fp-multilanguage' ) );
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

        /**
         * Safely update a post without triggering problematic hooks.
         *
         * @since 0.9.3
         *
         * @param array $post_data Post data array for wp_update_post.
         * @return int|\WP_Error Post ID on success, WP_Error on failure.
         */
        protected function safe_update_post( $post_data ) {
                // Se già in modalità safe, usa direttamente wp_update_post
                if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
                        return wp_update_post( $post_data, true );
                }
                
                global $wp_filter;
                
                // Save current hooks
                $saved_hooks = array();
                $problematic_hooks = array( 'save_post', 'publish_post', 'transition_post_status', 'publish_page', 'on_publish' );
                
                // Remove problematic hooks temporarily
                foreach ( $problematic_hooks as $hook_name ) {
                        if ( isset( $wp_filter[ $hook_name ] ) ) {
                                $saved_hooks[ $hook_name ] = $wp_filter[ $hook_name ];
                                unset( $wp_filter[ $hook_name ] );
                        }
                }
                
                // Set flag to prevent recursion
                $GLOBALS['fpml_updating_translation'] = true;
                
                try {
                        // Update post
                        $result = wp_update_post( $post_data, true );
                } finally {
                        // Always restore hooks, even if there's an error
                        foreach ( $saved_hooks as $hook_name => $hook_data ) {
                                $wp_filter[ $hook_name ] = $hook_data;
                        }
                        
                        // Clear flag
                        unset( $GLOBALS['fpml_updating_translation'] );
                }
                
                return $result;
        }

        /**
         * Safely insert a post without triggering problematic hooks.
         *
         * @since 0.9.3
         *
         * @param array $post_data Post data array for wp_insert_post.
         * @return int|\WP_Error Post ID on success, WP_Error on failure.
         */
        protected function safe_insert_post( $post_data ) {
                // Se già in modalità safe, usa direttamente wp_insert_post
                if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
                        return wp_insert_post( $post_data, true );
                }
                
                global $wp_filter;
                
                // Save current hooks
                $saved_hooks = array();
                $problematic_hooks = array( 'save_post', 'publish_post', 'transition_post_status', 'publish_page', 'on_publish' );
                
                // Remove problematic hooks temporarily
                foreach ( $problematic_hooks as $hook_name ) {
                        if ( isset( $wp_filter[ $hook_name ] ) ) {
                                $saved_hooks[ $hook_name ] = $wp_filter[ $hook_name ];
                                unset( $wp_filter[ $hook_name ] );
                        }
                }
                
                // Set flag to prevent recursion
                $GLOBALS['fpml_updating_translation'] = true;
                
                try {
                        // Insert post
                        $result = wp_insert_post( $post_data, true );
                } finally {
                        // Always restore hooks, even if there's an error
                        foreach ( $saved_hooks as $hook_name => $hook_data ) {
                                $wp_filter[ $hook_name ] = $hook_data;
                        }
                        
                        // Clear flag
                        unset( $GLOBALS['fpml_updating_translation'] );
                }
                
                return $result;
        }
}

