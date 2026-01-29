<?php
/**
 * Metabox AJAX handler - Handles AJAX requests for translation metabox.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin\Metabox;

use FP\Multilanguage\Content\TranslationManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AJAX requests for the translation metabox.
 *
 * @since 0.10.0
 */
class MetaboxAjax {
    /**
     * AJAX handler for force translate action.
     *
     * @return void
     */
    public function ajax_force_translate(): void {
        try {
            $nonce_check = check_ajax_referer( 'fpml_force_translate', '_wpnonce', false );
            if ( ! $nonce_check ) {
                wp_send_json_error( array( 
                    'message' => __( 'Errore di sicurezza: nonce non valido. Ricarica la pagina e riprova.', 'fp-multilanguage' ) 
                ) );
            }

            if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
            }

            $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
            $target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( $_POST['target_lang'] ) : 'en';

            if ( ! $post_id ) {
                wp_send_json_error( array( 'message' => __( 'ID post mancante.', 'fp-multilanguage' ) ) );
            }

            $language_manager = fpml_get_language_manager();
            $enabled_languages = $language_manager->get_enabled_languages();
            if ( ! in_array( $target_lang, $enabled_languages, true ) ) {
                wp_send_json_error( array( 'message' => __( 'Lingua target non valida o non abilitata.', 'fp-multilanguage' ) ) );
            }

            $post = get_post( $post_id );

            if ( ! $post ) {
                wp_send_json_error( array( 'message' => __( 'Post non trovato.', 'fp-multilanguage' ) ) );
            }

            if ( empty( $post->post_title ) && empty( $post->post_content ) ) {
                wp_send_json_error( array( 
                    'message' => __( 'Il post deve avere almeno un titolo o contenuto prima di essere tradotto. Salva il post e riprova.', 'fp-multilanguage' ) 
                ) );
            }

            // Verifica il provider di traduzione scelto
            $translation_provider = get_post_meta( $post->ID, '_fpml_translation_provider', true );
            $wpml_active = defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
            
            // Se WPML è attivo e il provider è 'wpml' o 'auto' (e WPML ha una traduzione), usa WPML
            if ( $wpml_active && ( $translation_provider === 'wpml' || ( $translation_provider === 'auto' && function_exists( 'icl_object_id' ) ) ) ) {
                // Verifica se WPML ha già una traduzione per questa lingua
                $wpml_translation_id = 0;
                if ( function_exists( 'icl_object_id' ) ) {
                    $wpml_translation_id = icl_object_id( $post->ID, $post->post_type, false, $target_lang );
                }
                
                if ( $wpml_translation_id && $wpml_translation_id !== $post->ID ) {
                    // WPML ha già una traduzione, reindirizza l'utente
                    $edit_link = get_edit_post_link( $wpml_translation_id, 'raw' );
                    wp_send_json_error( array( 
                        'message' => sprintf( 
                            __( 'Questo post è configurato per usare WPML. La traduzione esiste già: <a href="%s" target="_blank">Modifica traduzione WPML</a>', 'fp-multilanguage' ), 
                            esc_url( $edit_link ) 
                        ),
                        'wpml_translation_id' => $wpml_translation_id
                    ) );
                } else {
                    // WPML non ha traduzione, suggerisci di usare WPML
                    wp_send_json_error( array( 
                        'message' => __( 'Questo post è configurato per usare WPML. Crea la traduzione tramite WPML invece di FP Multilanguage. Se vuoi usare FP Multilanguage, cambia il provider nel pannello Traduzioni.', 'fp-multilanguage' )
                    ) );
                }
            }

            $manager = fpml_get_translation_manager();
            
            if ( ! $manager ) {
                wp_send_json_error( array( 'message' => __( 'Errore: TranslationManager non disponibile.', 'fp-multilanguage' ) ) );
            }
            
            $existing_id = $manager->get_translation_id( $post->ID, $target_lang );
            if ( $existing_id ) {
                $translation = get_post( $existing_id );
                if ( ! $translation ) {
                    $meta_key = '_fpml_pair_id_' . $target_lang;
                    delete_post_meta( $post->ID, $meta_key );
                    $existing_id = 0;
                }
            }
            
            if ( ! $existing_id || ! $translation ) {
                $translation = $manager->create_post_translation( $post, $target_lang, 'draft' );
                
                if ( ! $translation ) {
                    wp_send_json_error( array( 
                        'message' => __( 'Impossibile creare traduzione. Verifica che il post sia salvato correttamente e riprova.', 'fp-multilanguage' ) 
                    ) );
                }
            }

            $processor = fpml_get_processor();
            
            if ( ! $processor ) {
                wp_send_json_error( array( 'message' => __( 'Errore: Processor non disponibile.', 'fp-multilanguage' ) ) );
            }
            
            $result = $processor->translate_post_directly( $post, $translation );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error(
                    array(
                        'message' => $result->get_error_message(),
                    )
                );
            }
            $translated = isset( $result['translated'] ) ? (int) $result['translated'] : 0;
            $skipped = isset( $result['skipped'] ) ? (int) $result['skipped'] : 0;
            $errors = isset( $result['errors'] ) ? (int) $result['errors'] : 0;
            $processed = $translated;

            update_post_meta( $translation->ID, '_fpml_last_sync', current_time( 'mysql' ) );

            $content_length = mb_strlen( wp_strip_all_tags( $post->post_content ) );
            $title_length   = mb_strlen( $post->post_title );
            $excerpt_length = mb_strlen( wp_strip_all_tags( $post->post_excerpt ) );
            $total_chars    = $content_length + $title_length + $excerpt_length;
            $estimated_time = max( 1, ceil( $total_chars / 1000 ) );

            $message = sprintf(
                __( '✓ Traduzione completata! %d campi tradotti, %d saltati, %d errori.', 'fp-multilanguage' ),
                $translated,
                $skipped,
                $errors
            );

            wp_send_json_success(
                array(
                    'message'        => $message,
                    'translation_id' => $translation->ID,
                    'processed'      => $processed,
                    'translated'     => $translated,
                    'skipped'        => $skipped,
                    'errors'         => $errors,
                    'estimated_time' => 0,
                )
            );
        } catch ( \Exception $e ) {
            wp_send_json_error(
                array(
                    'message' => sprintf( __( 'Errore durante la traduzione: %s', 'fp-multilanguage' ), $e->getMessage() ),
                )
            );
        } catch ( \Error $e ) {
            wp_send_json_error(
                array(
                    'message' => sprintf( __( 'Errore fatale durante la traduzione: %s', 'fp-multilanguage' ), $e->getMessage() ),
                )
            );
        }
    }

    /**
     * AJAX handler per ottenere un nonce fresco per la traduzione.
     *
     * @since 0.9.5
     *
     * @return void
     */
    public function ajax_get_translate_nonce(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        $nonce = wp_create_nonce( 'fpml_force_translate' );

        wp_send_json_success( array( 'nonce' => $nonce ) );
    }
}
















