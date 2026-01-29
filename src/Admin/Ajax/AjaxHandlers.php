<?php
/**
 * Admin AJAX handlers - Handles all AJAX requests for admin interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin\Ajax;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Admin\BulkOperations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all AJAX requests for admin interface.
 *
 * @since 0.10.0
 */
class AjaxHandlers {
    /**
     * Handle refresh nonce AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_refresh_nonce() {
        check_ajax_referer( 'fpml_refresh_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }
        $new_nonce = wp_create_nonce( 'wp_rest' );
        wp_send_json_success( array( 'nonce' => $new_nonce ) );
    }

    /**
     * Handle reindex batch AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_reindex_batch_ajax() {
        check_ajax_referer( 'fpml_reindex_batch', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        $step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0;

        try {
            $plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
            if ( $plugin->is_assisted_mode() ) {
                wp_send_json_error( array( 
                    'message' => __( 'Modalità assistita attiva: il reindex automatico è disabilitato.', 'fp-multilanguage' ) 
                ) );
            }

            $indexer = Container::get( 'content_indexer' );
            if ( ! $indexer ) {
                $indexer = function_exists( 'fpml_get_content_indexer' ) ? fpml_get_content_indexer() : ( function_exists( 'fpml_get_content_indexer' ) ? fpml_get_content_indexer() : \FPML_Content_Indexer::instance() );
            }

            $result = $indexer->reindex_batch( $step );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code()
                ) );
            }

            wp_send_json_success( array(
                'success' => true,
                'complete' => isset( $result['complete'] ) ? $result['complete'] : false,
                'step' => isset( $result['step'] ) ? $result['step'] : $step,
                'total_steps' => isset( $result['total_steps'] ) ? $result['total_steps'] : 0,
                'progress_percent' => isset( $result['progress_percent'] ) ? $result['progress_percent'] : 0,
                'current_task' => isset( $result['current_task'] ) ? $result['current_task'] : __( 'Elaborazione...', 'fp-multilanguage' ),
                'summary' => isset( $result['summary'] ) ? $result['summary'] : array(),
                'message' => isset( $result['message'] ) ? $result['message'] : __( 'Batch completato.', 'fp-multilanguage' )
            ) );

        } catch ( \Exception $e ) {
            \FP\Multilanguage\Logger::error( 'AJAX reindex error', array( 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ) );
            wp_send_json_error( array( 
                'message' => __( 'Errore durante il reindex: ', 'fp-multilanguage' ) . $e->getMessage() 
            ) );
        }
    }

    /**
     * Handle cleanup orphaned pairs AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_cleanup_orphaned_pairs() {
        check_ajax_referer( 'fpml_cleanup_orphaned', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        try {
            $cleaned_count = 0;
            $post_types = get_post_types( array( 'public' => true ), 'names' );
            
            foreach ( $post_types as $post_type ) {
                $query = new \WP_Query( array(
                    'post_type'      => $post_type,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_fpml_pair_id',
                            'compare' => 'EXISTS'
                        )
                    )
                ) );

                foreach ( $query->posts as $post_id ) {
                    // Backward compatibility: check legacy _fpml_pair_id for 'en'
                    $pair_id = fpml_get_translation_id( $post_id, 'en' );
                    if ( ! $pair_id ) {
                        $pair_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
                    }
                    
                    if ( $pair_id ) {
                        $paired_post = get_post( $pair_id );
                        
                        if ( ! $paired_post || $paired_post->post_status === 'trash' ) {
                            delete_post_meta( $post_id, '_fpml_pair_id' );
                            $cleaned_count++;
                            \FP\Multilanguage\Logger::info( 'Cleaned orphaned pair', array( 'post_id' => $post_id, 'pair_id' => $pair_id ) );
                        }
                    }
                }
            }

            wp_send_json_success( array(
                'message' => sprintf(
                    __( 'Pulizia completata: %d meta orfani rimossi.', 'fp-multilanguage' ), 
                    $cleaned_count 
                ),
                'cleaned_count' => $cleaned_count
            ) );

        } catch ( \Exception $e ) {
            \FP\Multilanguage\Logger::error( 'Cleanup error', array( 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ) );
            wp_send_json_error( array( 
                'message' => __( 'Errore durante la pulizia: ', 'fp-multilanguage' ) . $e->getMessage() 
            ) );
        }
    }

    /**
     * Handle trigger detection AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_trigger_detection() {
        check_ajax_referer( 'fpml_trigger_detection', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }
        
        wp_send_json_success( array( 'message' => __( 'Rilevamento completato.', 'fp-multilanguage' ) ) );
    }

    /**
     * Handle bulk translate AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_bulk_translate() {
        check_ajax_referer( 'fpml_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }
        
        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
        
        if ( empty( $post_ids ) ) {
            wp_send_json_error( array( 'message' => 'Nessun post selezionato' ) );
        }
        
        $results = BulkOperations::translate_posts( $post_ids );
        wp_send_json_success( $results );
    }

    /**
     * Handle bulk regenerate AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_bulk_regenerate() {
        check_ajax_referer( 'fpml_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }
        
        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
        
        if ( empty( $post_ids ) ) {
            wp_send_json_error( array( 'message' => 'Nessun post selezionato' ) );
        }
        
        $results = BulkOperations::regenerate_translations( $post_ids );
        wp_send_json_success( $results );
    }

    /**
     * Handle bulk sync AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_bulk_sync() {
        check_ajax_referer( 'fpml_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }
        
        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
        
        if ( empty( $post_ids ) ) {
            wp_send_json_error( array( 'message' => 'Nessun post selezionato' ) );
        }
        
        $results = BulkOperations::sync_translations( $post_ids );
        wp_send_json_success( $results );
    }

    /**
     * Handle translate single post AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_translate_single() {
        check_ajax_referer( 'fpml_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'ID post non valido' ) );
        }
        
        $results = BulkOperations::translate_posts( array( $post_id ) );
        wp_send_json_success( $results );
    }

    /**
     * Handle translate site part AJAX.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_translate_site_part() {
        check_ajax_referer( 'fpml_translate_site_part', '_wpnonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        $part = isset( $_POST['part'] ) ? sanitize_text_field( $_POST['part'] ) : '';
        
        if ( empty( $part ) ) {
            wp_send_json_error( array( 'message' => __( 'Parte del sito non specificata.', 'fp-multilanguage' ) ) );
        }
        
        try {
            $translator = new \FP\Multilanguage\Admin\SitePartTranslator();
            $result = $translator->translate( $part );
            
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }
            
            wp_send_json_success( array( 'message' => $result['message'] ) );
        } catch ( \Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }
}














