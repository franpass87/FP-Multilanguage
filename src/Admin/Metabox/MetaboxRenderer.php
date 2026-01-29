<?php
/**
 * Metabox renderer - Handles rendering of translation metabox HTML.
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
 * Renders the translation metabox HTML content.
 *
 * @since 0.10.0
 */
class MetaboxRenderer {
    /**
     * Render meta box content.
     *
     * @param \WP_Post $post Post object.
     *
     * @return void
     */
    public function render_meta_box( \WP_Post $post ): void {
        try {
            $is_translation = get_post_meta( $post->ID, '_fpml_is_translation', true );

            if ( $is_translation ) {
                $this->render_translation_view( $post );
            } else {
                $this->render_source_view( $post );
            }

            wp_nonce_field( 'fpml_force_translate', 'fpml_translate_nonce' );
        } catch ( \Throwable $e ) {
            // Log error but don't break the page
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'FP Multilanguage: Error in MetaboxRenderer::render_meta_box: ' . $e->getMessage() );
                error_log( 'Stack trace: ' . $e->getTraceAsString() );
            }
            // Show user-friendly error message
            echo '<div class="notice notice-error inline"><p>';
            echo esc_html__( 'Errore nel caricamento del pannello Traduzioni. Controlla i log per i dettagli.', 'fp-multilanguage' );
            echo '</p></div>';
        }
    }

    /**
     * Render view for translation posts.
     *
     * @since 0.10.0
     *
     * @param \WP_Post $post Post object.
     * @return void
     */
    protected function render_translation_view( \WP_Post $post ): void {
        $source_id = get_post_meta( $post->ID, '_fpml_pair_source_id', true );
        $target_lang = get_post_meta( $post->ID, '_fpml_target_language', true );
        $language_manager = fpml_get_language_manager();
        $lang_info = $language_manager->get_language_info( $target_lang );
        ?>
        <div class="fpml-metabox">
            <p>
                <strong><?php esc_html_e( 'Questa è una traduzione di:', 'fp-multilanguage' ); ?></strong><br>
                <a href="<?php echo esc_url( get_edit_post_link( $source_id ) ); ?>">
                    <?php echo esc_html( get_the_title( $source_id ) ); ?>
                </a>
            </p>
            <p>
                <strong><?php esc_html_e( 'Lingua:', 'fp-multilanguage' ); ?></strong>
                <?php echo esc_html( $lang_info ? $lang_info['flag'] . ' ' . $lang_info['name'] : $target_lang ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render view for source posts.
     *
     * @since 0.10.0
     *
     * @param \WP_Post $post Post object.
     * @return void
     */
    protected function render_source_view( \WP_Post $post ): void {
        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();
        $translation_manager = fpml_get_translation_manager();
        $queue = fpml_get_queue();

        $in_queue = false;
        if ( $queue ) {
            $queue_jobs = $queue->get_by_object( 'post', $post->ID );
            $in_queue = ! empty( $queue_jobs );
        }
        // Verifica se WPML è attivo
        $wpml_active = defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
        $translation_provider = get_post_meta( $post->ID, '_fpml_translation_provider', true );
        if ( empty( $translation_provider ) ) {
            // Default: FP Multilanguage se WPML non è attivo, altrimenti 'auto' (lascia decidere)
            $translation_provider = $wpml_active ? 'auto' : 'fpml';
        }
        ?>
        <div class="fpml-metabox">
            <?php if ( $wpml_active ) : ?>
                <div class="fpml-provider-selector" style="margin-bottom: 15px; padding: 12px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <label for="fpml_translation_provider" style="display: block; margin-bottom: 8px; font-weight: 600;">
                        <?php esc_html_e( 'Provider di Traduzione:', 'fp-multilanguage' ); ?>
                    </label>
                    <select name="fpml_translation_provider" id="fpml_translation_provider" style="width: 100%; padding: 6px;">
                        <option value="auto" <?php selected( $translation_provider, 'auto' ); ?>>
                            <?php esc_html_e( '🔀 Automatico (usa WPML se disponibile, altrimenti FP Multilanguage)', 'fp-multilanguage' ); ?>
                        </option>
                        <option value="wpml" <?php selected( $translation_provider, 'wpml' ); ?>>
                            <?php esc_html_e( '🌐 WPML (gestione traduzioni con WPML)', 'fp-multilanguage' ); ?>
                        </option>
                        <option value="fpml" <?php selected( $translation_provider, 'fpml' ); ?>>
                            <?php esc_html_e( '🤖 FP Multilanguage (traduzione automatica con AI)', 'fp-multilanguage' ); ?>
                        </option>
                    </select>
                    <p style="margin-top: 8px; margin-bottom: 0; font-size: 12px; color: #646970;">
                        <?php esc_html_e( 'Scegli quale sistema usare per tradurre questo contenuto. La scelta viene salvata automaticamente.', 'fp-multilanguage' ); ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php if ( ! empty( $enabled_languages ) ) : ?>
                <?php foreach ( $enabled_languages as $lang_code ) : ?>
                    <?php
                    if ( ! isset( $available_languages[ $lang_code ] ) ) {
                        continue;
                    }
                    $lang_info = $available_languages[ $lang_code ];
                    $translation_id = $translation_manager->get_translation_id( $post->ID, $lang_code );
                    $translation_post = $translation_id ? get_post( $translation_id ) : null;
                    ?>
                    <div class="fpml-language-section">
                        <?php if ( $translation_post instanceof \WP_Post ) : ?>
                            <?php $this->render_existing_translation( $post, $translation_post, $lang_code, $lang_info ); ?>
                        <?php else : ?>
                            <?php $this->render_missing_translation( $post, $lang_code, $lang_info ); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ( $in_queue ) : ?>
                <div class="fpml-queue-notice" style="margin-top:10px; padding:10px; background:#fff3cd; border-left:4px solid #ffc107;">
                    <strong>⏳ <?php esc_html_e( 'In coda di traduzione', 'fp-multilanguage' ); ?></strong><br>
                    <small><?php esc_html_e( 'Il contenuto sarà tradotto nei prossimi minuti.', 'fp-multilanguage' ); ?></small>
                </div>
            <?php endif; ?>
        </div>

        <?php $this->render_styles(); ?>
        <?php
    }

    /**
     * Render existing translation card.
     *
     * @since 0.10.0
     *
     * @param \WP_Post $post Post object.
     * @param \WP_Post $translation_post Translation post.
     * @param string   $lang_code Language code.
     * @param array    $lang_info Language info.
     * @return void
     */
    protected function render_existing_translation( \WP_Post $post, \WP_Post $translation_post, $lang_code, $lang_info ) {
        $status = $this->determine_translation_status( $translation_post->ID );
        $status_class = 'completed' === $status ? 'fpml-status-completed' : 'fpml-status-pending';
        $status_icon = 'completed' === $status ? '✅' : '⏳';
        $translation_provider = get_post_meta( $post->ID, '_fpml_translation_provider', true );
        $wpml_active = defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
        $use_wpml = $wpml_active && ( $translation_provider === 'wpml' || ( $translation_provider === 'auto' && function_exists( 'icl_object_id' ) ) );
        ?>
        <div class="fpml-status-card <?php echo esc_attr( $status_class ); ?>">
            <div class="fpml-status-icon"><?php echo esc_html( $status_icon ); ?></div>
            <div class="fpml-status-text">
                <strong><?php printf( esc_html__( 'Tradotto in %s', 'fp-multilanguage' ), esc_html( $lang_info['name'] ) ); ?></strong>
                <div class="fpml-status-meta">
                    <?php
                    $view_permalink = get_permalink( $translation_post->ID );
                    if ( class_exists( '\FPML_Language' ) ) {
                        $language = ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() );
                        if ( $language && method_exists( $language, 'filter_translation_permalink' ) ) {
                            $view_permalink = $language->filter_translation_permalink( $view_permalink, $translation_post, true );
                        }
                    }
                    ?>
                    <a href="<?php echo esc_url( $view_permalink ); ?>" target="_blank"><?php esc_html_e( 'Visualizza', 'fp-multilanguage' ); ?></a> |
                    <a href="<?php echo esc_url( get_edit_post_link( $translation_post->ID ) ); ?>"><?php esc_html_e( 'Modifica', 'fp-multilanguage' ); ?></a>
                </div>
            </div>
        </div>

        <div class="fpml-actions">
            <a href="<?php echo esc_url( $view_permalink ); ?>" target="_blank" class="button button-secondary" style="width:100%; text-align:center; margin-bottom:8px;">
                <?php echo esc_html( isset( $lang_info['flag'] ) ? $lang_info['flag'] : '' ); ?> <?php printf( esc_html__( 'Visualizza %s', 'fp-multilanguage' ), esc_html( $lang_info['name'] ) ); ?>
            </a>
            <a href="<?php echo esc_url( get_edit_post_link( $translation_post->ID ) ); ?>" class="button button-secondary" style="width:100%; text-align:center; margin-bottom:8px;">
                ✏️ <?php printf( esc_html__( 'Modifica %s', 'fp-multilanguage' ), esc_html( $lang_info['name'] ) ); ?>
            </a>
            <?php if ( ! $use_wpml ) : ?>
                <button type="button" class="button button-primary fpml-force-translate" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-target-lang="<?php echo esc_attr( $lang_code ); ?>" style="width:100%; text-align:center;">
                    🔄 <?php printf( esc_html__( 'Ritraduci in %s ORA', 'fp-multilanguage' ), esc_html( $lang_info['name'] ) ); ?>
                </button>
            <?php else : ?>
                <div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <p style="margin: 0; font-size: 12px;">
                        <?php esc_html_e( '🌐 Questo post usa WPML. Usa WPML per ritradurre.', 'fp-multilanguage' ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        do_action( 'fpml_translation_metabox_after_actions', $post->ID, $translation_post->ID );
    }

    /**
     * Render missing translation card.
     *
     * @since 0.10.0
     *
     * @param \WP_Post $post Post object.
     * @param string   $lang_code Language code.
     * @param array    $lang_info Language info.
     * @return void
     */
    protected function render_missing_translation( \WP_Post $post, $lang_code, $lang_info ) {
        $translation_provider = get_post_meta( $post->ID, '_fpml_translation_provider', true );
        $wpml_active = defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
        $use_wpml = $wpml_active && ( $translation_provider === 'wpml' || ( $translation_provider === 'auto' && function_exists( 'icl_object_id' ) ) );
        ?>
        <div class="fpml-status-card fpml-status-none">
            <div class="fpml-status-icon">⚪</div>
            <div class="fpml-status-text">
                <strong><?php printf( esc_html__( 'Non Tradotto in %s', 'fp-multilanguage' ), esc_html( isset( $lang_info['name'] ) ? $lang_info['name'] : $lang_code ) ); ?></strong>
                <div class="fpml-status-meta">
                    <?php if ( $use_wpml ) : ?>
                        <?php esc_html_e( 'Usa WPML per creare la traduzione', 'fp-multilanguage' ); ?>
                    <?php else : ?>
                        <?php printf( esc_html__( 'Clicca "Traduci ORA" per creare la versione %s', 'fp-multilanguage' ), esc_html( isset( $lang_info['name'] ) ? $lang_info['name'] : $lang_code ) ); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="fpml-actions">
            <?php if ( $use_wpml ) : ?>
                <div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 8px;">
                    <p style="margin: 0; font-size: 12px;">
                        <?php esc_html_e( '🌐 Questo post è configurato per usare WPML. Crea la traduzione tramite WPML.', 'fp-multilanguage' ); ?>
                    </p>
                </div>
            <?php else : ?>
                <button type="button" class="button button-primary fpml-force-translate" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-target-lang="<?php echo esc_attr( $lang_code ); ?>" style="width:100%; text-align:center;">
                    🚀 <?php printf( esc_html__( 'Traduci in %s ORA', 'fp-multilanguage' ), esc_html( isset( $lang_info['name'] ) ? $lang_info['name'] : $lang_code ) ); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render metabox styles.
     *
     * @since 0.10.0
     * @return void
     */
    protected function render_styles() {
        ?>
        <style>
            .fpml-translation-progress {
                margin-top: 12px;
                padding: 10px;
                background: #f9fafb;
                border-radius: 4px;
                display: none;
            }
            .fpml-progress-bar-container {
                width: 100%;
                height: 8px;
                background: #e5e7eb;
                border-radius: 4px;
                overflow: hidden;
                position: relative;
            }
            .fpml-progress-bar-fill {
                height: 100%;
                background: linear-gradient(90deg, #0ea5e9 0%, #3b82f6 50%, #0ea5e9 100%);
                background-size: 200% 100%;
                border-radius: 4px;
                transition: width 0.3s ease;
                animation: fpml-progress-shimmer 2s infinite;
            }
            @keyframes fpml-progress-shimmer {
                0% { background-position: -200% 0; }
                100% { background-position: 200% 0; }
            }
            .fpml-progress-text {
                font-size: 12px;
                color: #64748b;
                margin-top: 6px;
                text-align: center;
            }
            .fpml-metabox {
                margin: -6px -12px -12px;
            }
            .fpml-status-card {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 15px;
                background: #f6f7f7;
                border-left: 4px solid #ddd;
            }
            .fpml-status-completed {
                border-left-color: #46b450;
                background: #ecf7ed;
            }
            .fpml-status-pending {
                border-left-color: #00a0d2;
                background: #e5f5fa;
            }
            .fpml-status-none {
                border-left-color: #999;
            }
            .fpml-status-icon {
                font-size: 24px;
                line-height: 1;
            }
            .fpml-status-text {
                flex: 1;
            }
            .fpml-status-meta {
                font-size: 12px;
                color: #646970;
                margin-top: 4px;
            }
            .fpml-actions {
                padding: 15px;
            }
        </style>
        <?php
    }

    /**
     * Determine translation status.
     *
     * @since 0.10.0
     *
     * @param int $translation_id Translation post ID.
     * @return string
     */
    protected function determine_translation_status( $translation_id ) {
        global $wpdb;

        $allowed      = array( 'synced', 'done', 'automatic', 'auto' );
        $placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
        $args         = array_merge(
            array( $translation_id, '_fpml_status_%' ),
            array_map( 'strtolower', $allowed )
        );

        $sql = $wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta}
            WHERE post_id = %d
            AND meta_key LIKE %s
            AND (meta_value IS NULL OR meta_value = '' OR LOWER(meta_value) NOT IN ($placeholders))
            LIMIT 1",
            $args
        );

        $unsynced = $wpdb->get_var( $sql );

        return $unsynced ? 'partial' : 'completed';
    }
}
















