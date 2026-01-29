<?php
/**
 * Metabox assets - Handles enqueue of scripts and styles for translation metabox.
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
 * Manages assets (scripts and styles) for the translation metabox.
 *
 * @since 0.10.0
 */
class MetaboxAssets {
    /**
     * Enqueue scripts for post editor.
     *
     * @param string $hook Current admin page hook.
     *
     * @return void
     */
    public function enqueue_scripts( string $hook ): void {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        wp_add_inline_script(
            'jquery',
            $this->get_translation_script()
        );
    }

    /**
     * Get translation JavaScript code.
     *
     * @since 0.10.0
     * @return string
     */
    protected function get_translation_script(): string {
        $nonce = wp_create_nonce( 'fpml_force_translate' );
        
        return "
        jQuery(document).ready(function($) {
            // Salva automaticamente il provider di traduzione quando viene cambiato
            $('#fpml_translation_provider').on('change', function() {
                const \$select = $(this);
                const provider = \$select.val();
                const postId = $('#post_ID').val();
                
                if (!postId) {
                    return; // Post non ancora salvato
                }
                
                // Salva il provider via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fpml_save_translation_provider',
                        post_id: postId,
                        provider: provider,
                        _wpnonce: '" . esc_js( wp_create_nonce( 'fpml_save_translation_provider_' . get_current_user_id() ) ) . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Mostra un messaggio temporaneo
                            const \$notice = $('<div class=\"notice notice-success is-dismissible inline\" style=\"margin: 10px 0;\"><p>' + response.data.message + '</p></div>');
                            \$select.closest('.fpml-provider-selector').after(\$notice);
                            setTimeout(function() {
                                \$notice.fadeOut(function() { $(this).remove(); });
                            }, 3000);
                        }
                    }
                });
            });
            
            $('.fpml-force-translate').on('click', function() {
                const \$btn = $(this);
                const postId = \$btn.data('post-id');
                const targetLang = \$btn.data('target-lang') || 'en';
                
                const langName = targetLang === 'en' ? 'Inglese' : (targetLang === 'de' ? 'Tedesco' : (targetLang === 'fr' ? 'Francese' : (targetLang === 'es' ? 'Spagnolo' : targetLang)));
                if (!confirm('" . esc_js( __( 'Creare/aggiornare la traduzione', 'fp-multilanguage' ) ) . " ' + langName + ' ORA?')) {
                    return;
                }

                \$btn.prop('disabled', true).html('💾 Salvataggio...');
                
                // Salva il post prima di tradurre (come bozza, non pubblicare)
                function savePostBeforeTranslate() {
                    // Metodo 1: Gutenberg Editor (Redux API)
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.dispatch) {
                        try {
                            const editorStore = wp.data.select('core/editor');
                            if (editorStore && typeof editorStore.isEditedPostDirty === 'function' && editorStore.isEditedPostDirty()) {
                                // Ottieni lo status corrente
                                const currentStatus = editorStore.getCurrentPost().status || 'draft';
                                
                                // Se il post è pubblicato, salva come bozza temporaneamente
                                // Altrimenti mantieni lo status corrente
                                if (currentStatus === 'publish' || currentStatus === 'future') {
                                    // Salva come bozza usando autosave (non cambia lo status pubblicato)
                                    wp.data.dispatch('core/editor').autosave();
                                } else {
                                    // Salva mantenendo lo status corrente (bozza, pending, ecc.)
                                    wp.data.dispatch('core/editor').savePost();
                                }
                                
                                // Aspetta che il salvataggio sia completato
                                return new Promise((resolve) => {
                                    let attempts = 0;
                                    const maxAttempts = 50; // 5 secondi max
                                    const checkSave = setInterval(() => {
                                        attempts++;
                                        if (!editorStore.isEditedPostDirty() || attempts >= maxAttempts) {
                                            clearInterval(checkSave);
                                            resolve();
                                        }
                                    }, 100);
                                });
                            }
                        } catch(e) {
                            // Gutenberg non disponibile, continua con altri metodi
                        }
                    }
                    
                    // Metodo 2: Editor Classico (TinyMCE) - usa autosave
                    if (typeof wp !== 'undefined' && wp.autosave && wp.autosave.server) {
                        try {
                            if (typeof wp.autosave.server.postChanged === 'function' && wp.autosave.server.postChanged()) {
                                wp.autosave.server.triggerSave();
                                return new Promise((resolve) => {
                                    setTimeout(resolve, 2000);
                                });
                            }
                        } catch(e) {
                            // Autosave non disponibile, continua
                        }
                    }
                    
                    // Metodo 3: Editor Classico - salva come bozza usando il pulsante Salva bozza
                    const \$saveDraftBtn = $('#save-post');
                    if (\$saveDraftBtn.length > 0) {
                        // Clicca il pulsante Salva bozza se esiste
                        return new Promise((resolve) => {
                            // Verifica se ci sono modifiche
                            const hasChanges = typeof wp !== 'undefined' && wp.autosave && wp.autosave.server && wp.autosave.server.postChanged();
                            
                            if (hasChanges) {
                                // Trigger del click sul pulsante Salva bozza
                                \$saveDraftBtn.trigger('click');
                                
                                // Aspetta che il salvataggio sia completato
                                setTimeout(resolve, 2000);
                            } else {
                                resolve();
                            }
                        });
                    }
                    
                    // Metodo 4: Editor Classico - fallback con autosave
                    if (typeof wp !== 'undefined' && wp.autosave && wp.autosave.server) {
                        try {
                            if (typeof wp.autosave.server.postChanged === 'function' && wp.autosave.server.postChanged()) {
                                // Autosave salva sempre come bozza
                                wp.autosave.server.triggerSave();
                                return new Promise((resolve) => {
                                    setTimeout(resolve, 2000);
                                });
                            }
                        } catch(e) {
                            // Autosave non disponibile, continua
                        }
                    }
                    
                    // Se non c'è modo di salvare o non ci sono modifiche, procedi comunque
                    return Promise.resolve();
                }
                
                let \$progressContainer = \$btn.parent().find('.fpml-translation-progress');
                if (\$progressContainer.length === 0) {
                    \$progressContainer = \$('<div class=\"fpml-translation-progress\" style=\"margin-top: 10px; display: block;\"><div class=\"fpml-progress-bar-container\"><div class=\"fpml-progress-bar-fill\" style=\"width: 0%;\"></div></div><div class=\"fpml-progress-text\" style=\"font-size: 12px; color: #666; margin-top: 5px;\">Preparazione traduzione...</div></div>');
                    \$btn.after(\$progressContainer);
                } else {
                    \$progressContainer.show();
                }
                
                let progress = 0;
                const progressInterval = setInterval(function() {
                    progress += Math.random() * 10;
                    if (progress > 90) progress = 90;
                    \$progressContainer.find('.fpml-progress-bar-fill').css('width', progress + '%');
                }, 500);

                // Salva il post e poi procedi con la traduzione
                savePostBeforeTranslate().then(() => {
                    \$btn.html('⏳ Traduzione in corso...');
                    doTranslate(1);
                });

                function doTranslate(attempt = 1) {
                    let nonce = $('#fpml_translate_nonce').val();
                    
                    if (!nonce || nonce === '') {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'fpml_get_translate_nonce'
                            },
                            success: function(response) {
                                if (response.success && response.data && response.data.nonce) {
                                    nonce = response.data.nonce;
                                    $('#fpml_translate_nonce').val(nonce);
                                    executeTranslate(nonce, attempt);
                                } else {
                                    nonce = '" . esc_js( $nonce ) . "';
                                    executeTranslate(nonce, attempt);
                                }
                            },
                            error: function() {
                                nonce = '" . esc_js( $nonce ) . "';
                                executeTranslate(nonce, attempt);
                            }
                        });
                    } else {
                        executeTranslate(nonce, attempt);
                    }
                }
                
                function executeTranslate(nonce, attempt) {
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
                            clearInterval(progressInterval);
                            
                            if (response.success) {
                                \$progressContainer.find('.fpml-progress-bar-fill').css('width', '100%');
                                \$progressContainer.find('.fpml-progress-text').text('✅ Traduzione completata! Ricaricamento pagina...');
                                
                                const estimatedTime = response.data.estimated_time || 2;
                                
                                if (typeof FPMLToast !== 'undefined') {
                                    FPMLToast.success('✅ Traduzione avviata! Tempo stimato: ~' + estimatedTime + ' min. Pagina ricaricata tra 3 secondi...', 5000);
                                } else {
                                    alert(response.data.message + '\\n\\nTempo stimato: ~' + estimatedTime + ' minuti.\\n\\nPagina ricaricata automaticamente...');
                                }
                                
                                setTimeout(() => {
                                    location.reload();
                                }, 3000);
                            } else {
                                if (attempt === 1 && response.data && response.data.message && 
                                    (response.data.message.indexOf('nonce') !== -1 || response.data.message.indexOf('sicurezza') !== -1)) {
                                    \$btn.html('⏳ Retry con nuovo nonce...');
                                    setTimeout(() => {
                                        doTranslate(2);
                                    }, 500);
                                    return;
                                }
                                
                                if (typeof FPMLToast !== 'undefined') {
                                    FPMLToast.error('❌ ' + response.data.message);
                                } else {
                                    alert(response.data.message);
                                }
                                \$btn.prop('disabled', false).html('🔄 Ritraduci ORA');
                            }
                        },
                        error: function(xhr, status, error) {
                            clearInterval(progressInterval);
                            \$progressContainer.hide();
                            
                            if (attempt === 1 && xhr.responseJSON && xhr.responseJSON.data && 
                                xhr.responseJSON.data.message && 
                                (xhr.responseJSON.data.message.indexOf('nonce') !== -1 || xhr.responseJSON.data.message.indexOf('sicurezza') !== -1)) {
                                \$btn.html('⏳ Retry con nuovo nonce...');
                                setTimeout(() => {
                                    doTranslate(2);
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
                            \$btn.prop('disabled', false).html('🔄 Ritraduci ORA');
                        }
                    });
                }
            });
        });
        ";
    }
}
















