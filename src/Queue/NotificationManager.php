<?php
/**
 * Notification manager - Handles email notifications for queue processing.
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
 * Manages email notifications for queue processing.
 *
 * @since 0.10.0
 */
class NotificationManager {
    /**
     * Cached settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Constructor.
     *
     * @param \FPML_Settings $settings Settings instance.
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
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
    public function notify_admin_if_enabled( $summary ) {
        if ( ! $this->settings || ! $this->settings->get( 'enable_email_notifications', false ) ) {
            return;
        }

        if ( empty( $summary['claimed'] ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        if ( empty( $admin_email ) ) {
            return;
        }

        $subject = sprintf(
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
}
















