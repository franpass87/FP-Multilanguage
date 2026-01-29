<?php
/**
 * Health Check Notifier - Sends notifications for critical issues.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\HealthCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends notifications for critical issues.
 *
 * @since 0.10.0
 */
class Notifier {
	/**
	 * Invia notifiche se ci sono problemi critici.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array.
	 *
	 * @return void
	 */
	public function maybe_send_notifications( array $report ): void {
		if ( 'critical' !== $report['status'] ) {
			return;
		}

		// Verifica se abbiamo già notificato nelle ultime 24 ore.
		$last_notification = get_option( '\FPML_last_critical_notification', 0 );

		if ( $last_notification && ( current_time( 'timestamp', true ) - $last_notification < DAY_IN_SECONDS ) ) {
			return;
		}

		// Invia email all'admin.
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_option( 'blogname' );

		$subject = sprintf(
			/* translators: %s: nome del sito */
			__( '[%s] FP Multilanguage: Problemi critici rilevati', 'fp-multilanguage' ),
			$site_name
		);

		$message = __( 'Sono stati rilevati problemi critici nel sistema di traduzione:', 'fp-multilanguage' ) . "\n\n";

		foreach ( $report['issues'] as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				$message .= '- ' . $issue['message'] . "\n";
			}
		}

		$message .= "\n" . sprintf(
			/* translators: %s: URL admin */
			__( 'Vai alla dashboard: %s', 'fp-multilanguage' ),
			admin_url( 'admin.php?page=fp-multilanguage&tab=diagnostics' )
		);

		wp_mail( $admin_email, $subject, $message );

		// Salva timestamp dell'ultima notifica.
		update_option( '\FPML_last_critical_notification', current_time( 'timestamp', true ), false );
	}
}
















