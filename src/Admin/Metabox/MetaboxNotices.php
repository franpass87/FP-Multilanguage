<?php
/**
 * Metabox notices - Handles admin notices for translation metabox.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin\Metabox;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages admin notices for the translation metabox.
 *
 * @since 0.10.0
 */
class MetaboxNotices {
    /**
     * Set flag to show notice after save.
     *
     * @since 0.9.4
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     *
     * @return void
     */
    public function set_translate_notice_flag( int $post_id, \WP_Post $post ): void {
        if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
            return;
        }

        if ( get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
            return;
        }

        set_transient( 'fpml_show_translate_notice_' . $post_id, true, 60 );
    }

    /**
     * Show admin notice with "Translate now" button after save.
     *
     * @since 0.9.4
     *
     * @return void
     */
    public function show_translate_notice_after_save(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, array( 'post', 'page', 'edit-post', 'edit-page' ), true ) ) {
            return;
        }

        $post_id = 0;
        if ( isset( $_GET['post'] ) ) {
            $post_id = absint( $_GET['post'] );
        } elseif ( isset( $_GET['post_id'] ) ) {
            $post_id = absint( $_GET['post_id'] );
        } elseif ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
            $post_id = $GLOBALS['post']->ID;
        }

        if ( ! $post_id ) {
            return;
        }

        $show_notice = get_transient( 'fpml_show_translate_notice_' . $post_id );
        if ( ! $show_notice ) {
            return;
        }

        delete_transient( 'fpml_show_translate_notice_' . $post_id );

        if ( get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
            return;
        }

        $translation_id = get_post_meta( $post_id, '_fpml_pair_id', true );
        $has_translation = ! empty( $translation_id );

        ?>
        <div class="notice notice-success is-dismissible fpml-translate-notice" style="border-left-color: #00a0d2;">
            <div style="display: flex; align-items: center; gap: 15px; padding: 12px 0;">
                <div style="flex: 1;">
                    <p style="margin: 0; font-size: 14px;">
                        <strong>🌍 <?php esc_html_e( 'Traduzione Disponibile', 'fp-multilanguage' ); ?></strong><br>
                        <?php if ( $has_translation ) : ?>
                            <?php esc_html_e( 'La traduzione inglese esiste. Clicca per aggiornarla o visualizzarla.', 'fp-multilanguage' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Crea la versione inglese di questo contenuto. La traduzione sarà disponibile su /en/', 'fp-multilanguage' ); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <button type="button" class="button button-primary button-large fpml-force-translate-notice" data-post-id="<?php echo esc_attr( $post_id ); ?>" style="min-width: 150px;">
                        <?php if ( $has_translation ) : ?>
                            🔄 <?php esc_html_e( 'Ritraduci ORA', 'fp-multilanguage' ); ?>
                        <?php else : ?>
                            🚀 <?php esc_html_e( 'Traduci ORA', 'fp-multilanguage' ); ?>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        $this->render_notice_script();
    }

    /**
     * Render JavaScript for notice button.
     *
     * @since 0.10.0
     * @return void
     */
    protected function render_notice_script() {
        $nonce = wp_create_nonce( 'fpml_force_translate' );
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function getFreshNonce(callback) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fpml_get_translate_nonce'
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.nonce) {
                            callback(response.data.nonce);
                        } else {
                            callback('<?php echo esc_js( $nonce ); ?>');
                        }
                    },
                    error: function() {
                        callback('<?php echo esc_js( $nonce ); ?>');
                    }
                });
            }
            
            function doTranslateNotice($btn, postId, nonce, attempt) {
                const targetLang = $btn.data('target-lang') || 'en';
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fpml_force_translate_now',
                        post_id: postId,
                        target_lang: targetLang,
                        _wpnonce: nonce
                    },
                    timeout: 300000,
                    success: function(response) {
                        if (response.success) {
                            const estimatedTime = response.data.estimated_time || 2;
                            if (typeof FPMLToast !== 'undefined') {
                                FPMLToast.success('✅ <?php echo esc_js( __( 'Traduzione avviata!', 'fp-multilanguage' ) ); ?> Tempo stimato: ~' + estimatedTime + ' min. <?php echo esc_js( __( 'Pagina ricaricata tra 3 secondi...', 'fp-multilanguage' ) ); ?>', 5000);
                            } else {
                                alert(response.data.message + '\n\n<?php echo esc_js( __( 'Tempo stimato:', 'fp-multilanguage' ) ); ?> ~' + estimatedTime + ' <?php echo esc_js( __( 'minuti', 'fp-multilanguage' ) ); ?>.\n\n<?php echo esc_js( __( 'Pagina ricaricata automaticamente...', 'fp-multilanguage' ) ); ?>');
                            }
                            setTimeout(() => {
                                location.reload();
                            }, 3000);
                        } else {
                            if (attempt === 1 && response.data && response.data.message && 
                                (response.data.message.indexOf('nonce') !== -1 || response.data.message.indexOf('sicurezza') !== -1)) {
                                $btn.html('⏳ Retry con nuovo nonce...');
                                setTimeout(() => {
                                    getFreshNonce(function(newNonce) {
                                        doTranslateNotice($btn, postId, newNonce, 2);
                                    });
                                }, 500);
                                return;
                            }
                            
                            if (typeof FPMLToast !== 'undefined') {
                                FPMLToast.error('❌ ' + response.data.message);
                            } else {
                                alert(response.data.message);
                            }
                            $btn.prop('disabled', false).html('🔄 <?php echo esc_js( __( 'Ritraduci ORA', 'fp-multilanguage' ) ); ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        if (attempt === 1 && xhr.responseJSON && xhr.responseJSON.data && 
                            xhr.responseJSON.data.message && 
                            (xhr.responseJSON.data.message.indexOf('nonce') !== -1 || xhr.responseJSON.data.message.indexOf('sicurezza') !== -1)) {
                            $btn.html('⏳ Retry con nuovo nonce...');
                            setTimeout(() => {
                                getFreshNonce(function(newNonce) {
                                    doTranslateNotice($btn, postId, newNonce, 2);
                                });
                            }, 500);
                            return;
                        }
                        
                        let errorMsg = 'Errore di connessione. Riprova.';
                        if (status === 'timeout') {
                            errorMsg = 'Timeout: la traduzione richiede più tempo del previsto. Controlla i log.';
                        } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            errorMsg = xhr.responseJSON.data.message;
                        } else if (error) {
                            errorMsg = 'Errore: ' + error;
                        }
                        
                        if (typeof FPMLToast !== 'undefined') {
                            FPMLToast.error('❌ ' + errorMsg);
                        } else {
                            alert(errorMsg);
                        }
                        $btn.prop('disabled', false).html('🔄 <?php echo esc_js( __( 'Ritraduci ORA', 'fp-multilanguage' ) ); ?>');
                    }
                });
            }
            
            $('.fpml-force-translate-notice').on('click', function() {
                const $btn = $(this);
                const postId = $btn.data('post-id');
                const targetLang = $btn.data('target-lang') || 'en';
                
                const langName = targetLang === 'en' ? 'Inglese' : (targetLang === 'de' ? 'Tedesco' : (targetLang === 'fr' ? 'Francese' : (targetLang === 'es' ? 'Spagnolo' : targetLang)));
                if (!confirm('<?php echo esc_js( __( 'Creare/aggiornare la traduzione', 'fp-multilanguage' ) ); ?> ' + langName + ' ORA?')) {
                    return;
                }

                $btn.prop('disabled', true).html('⏳ <?php echo esc_js( __( 'Traduzione in corso...', 'fp-multilanguage' ) ); ?>');

                getFreshNonce(function(nonce) {
                    doTranslateNotice($btn, postId, nonce, 1);
                });
            });
        });
        </script>
        <?php
    }
}
















