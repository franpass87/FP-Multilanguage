<?php
/**
 * Admin class - Temporary cleanup handler only
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Temporary admin class for cleanup functionality
 */
class FPML_Admin_Cleanup {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_fpml_cleanup_orphaned_pairs', array( $this, 'handle_cleanup_orphaned_pairs' ) );
    }

    /**
     * Handle AJAX cleanup of orphaned translation pairs.
     * 
     * This method cleans up orphaned _fpml_pair_id meta when translations
     * have been deleted but the source posts still reference them.
     *
     * @since 0.4.3
     *
     * @return void
     */
    public function handle_cleanup_orphaned_pairs() {
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        try {
            $cleaned_count = 0;
            $post_types = get_post_types( array( 'public' => true ), 'names' );
            
            foreach ( $post_types as $post_type ) {
                $query = new WP_Query( array(
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
                    $pair_id = get_post_meta( $post_id, '_fpml_pair_id', true );
                    
                    if ( $pair_id ) {
                        // Check if the paired post still exists
                        $paired_post = get_post( $pair_id );
                        
                        if ( ! $paired_post || $paired_post->post_status === 'trash' ) {
                            // Orphaned pair - clean it up
                            delete_post_meta( $post_id, '_fpml_pair_id' );
                            $cleaned_count++;
                            
                            error_log( "FPML: Cleaned orphaned pair for post #$post_id (missing pair #$pair_id)" );
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

        } catch ( Exception $e ) {
            error_log( 'FPML cleanup error: ' . $e->getMessage() );
            wp_send_json_error( array( 
                'message' => __( 'Errore durante la pulizia: ', 'fp-multilanguage' ) . $e->getMessage() 
            ) );
        }
    }
}

// Initialize the cleanup handler
new FPML_Admin_Cleanup();
