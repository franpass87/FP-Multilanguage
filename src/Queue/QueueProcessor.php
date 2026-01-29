<?php
/**
 * Queue processor - Handles main queue processing logic.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Processes translation queue jobs.
 *
 * @since 0.10.0
 */
class QueueProcessor {
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
     * Batch manager instance.
     *
     * @var BatchManager
     */
    protected $batch_manager;

    /**
     * Whether the processor is running in assisted mode.
     *
     * @var bool
     */
    protected $assisted_mode = false;

    /**
     * Constructor.
     *
     * @param \FPML_Queue      $queue         Queue instance.
     * @param \FPML_Settings   $settings      Settings instance.
     * @param \FPML_Logger     $logger        Logger instance.
     * @param BatchManager     $batch_manager Batch manager instance.
     * @param bool             $assisted_mode Assisted mode flag.
     */
    public function __construct( $queue, $settings, $logger, BatchManager $batch_manager, $assisted_mode = false ) {
        $this->queue = $queue;
        $this->settings = $settings;
        $this->logger = $logger;
        $this->batch_manager = $batch_manager;
        $this->assisted_mode = $assisted_mode;
    }

    /**
     * Process the translation queue.
     *
     * @since 0.2.0
     *
     * @return array|\WP_Error
     */
    public function run_queue() {
        if ( $this->assisted_mode ) {
            return new \WP_Error( '\FPML_assisted_mode', __( 'La coda interna è disabilitata in modalità assistita.', 'fp-multilanguage' ) );
        }

        if ( ! $this->batch_manager->acquire_lock() ) {
            return new \WP_Error( '\FPML_processor_locked', __( 'La coda è già in esecuzione.', 'fp-multilanguage' ) );
        }

        $summary = array(
            'claimed'   => 0,
            'processed' => 0,
            'skipped'   => 0,
            'errors'    => 0,
        );

        $start_time = microtime( true );
        $batch_size = $this->settings ? (int) $this->settings->get( 'batch_size', 5 ) : 5;
        $batch_size = max( 1, min( 100, $batch_size ) );

        try {
            $jobs = $this->queue->claim_batch( $batch_size );
            $summary['claimed'] = is_array( $jobs ) ? count( $jobs ) : 0;
            
            $this->batch_manager->reset_batch_characters();

            if ( empty( $jobs ) ) {
                return $summary;
            }

            $total_jobs = count( $jobs );

            for ( $index = 0; $index < $total_jobs; $index++ ) {
                $job = $jobs[ $index ];

                if ( $this->batch_manager->should_skip_job_due_to_limit() ) {
                    $this->queue->update_state( $job->id, 'pending' );
                    unset( $jobs[ $index ] );
                    continue;
                }

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
                    unset( $jobs[ $index ] );
                    continue;
                }

                if ( 'skipped' === $result ) {
                    $this->queue->update_state( $job->id, 'skipped' );
                    $summary['skipped']++;
                    $this->batch_manager->add_job_characters( $this->batch_manager->get_current_job_characters() );
                    unset( $jobs[ $index ] );

                    if ( $this->batch_manager->should_skip_job_due_to_limit() ) {
                        for ( $j = $index + 1; $j < $total_jobs; $j++ ) {
                            $this->queue->update_state( $jobs[ $j ]->id, 'pending' );
                            unset( $jobs[ $j ] );
                        }
                        break;
                    }

                    continue;
                }

                $this->queue->update_state( $job->id, 'done' );
                $summary['processed']++;
                $this->batch_manager->add_job_characters( $this->batch_manager->get_current_job_characters() );
                unset( $jobs[ $index ] );

                if ( $this->batch_manager->should_skip_job_due_to_limit() ) {
                    for ( $j = $index + 1; $j < $total_jobs; $j++ ) {
                        $this->queue->update_state( $jobs[ $j ]->id, 'pending' );
                        unset( $jobs[ $j ] );
                    }
                    break;
                }
            }

            unset( $jobs );
        } finally {
            $duration = microtime( true ) - $start_time;
            $this->logger->log(
                'info',
                sprintf(
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
            $this->batch_manager->release_lock();
        }

        return $summary;
    }

    /**
     * Process an individual job.
     *
     * @since 0.2.0
     *
     * @param object $job Queue record.
     *
     * @return true|\WP_Error|string
     */
    protected function process_job( $job ) {
        if ( empty( $job->object_type ) ) {
            return new \WP_Error( '\FPML_job_invalid', __( 'Job non valido.', 'fp-multilanguage' ) );
        }

        $this->batch_manager->reset_job_characters();

        // This will delegate to Processor class methods
        // For now, return skipped as placeholder
        switch ( $job->object_type ) {
            case 'post':
            case 'term':
            case 'menu':
            case 'comment':
            case 'widget':
                // These will be handled by Processor class
                return 'skipped';
            case 'string':
                return 'skipped';
        }

        return new \WP_Error( '\FPML_job_type_unsupported', sprintf( __( 'Tipo di job %s non supportato.', 'fp-multilanguage' ), $job->object_type ) );
    }
}
















