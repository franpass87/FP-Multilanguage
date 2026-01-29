<?php
/**
 * Health Check Alert Manager - Manages active alerts.
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
 * Manages active alerts.
 *
 * @since 0.10.0
 */
class AlertManager {
	/**
	 * Opzione per memorizzare gli alert attivi.
	 */
	const OPTION_ACTIVE_ALERTS = '\FPML_active_health_alerts';

	/**
	 * Aggiorna gli alert attivi.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array.
	 *
	 * @return void
	 */
	public function update_active_alerts( array $report ): void {
		$alerts = array();

		foreach ( $report['issues'] as $issue ) {
			$alerts[ $issue['code'] ] = array(
				'severity'   => $issue['severity'],
				'message'    => $issue['message'],
				'first_seen' => current_time( 'timestamp', true ),
			);
		}

		// Mantieni la data di first_seen per alert già esistenti.
		$existing = get_option( self::OPTION_ACTIVE_ALERTS, array() );

		foreach ( $alerts as $code => $alert ) {
			if ( isset( $existing[ $code ]['first_seen'] ) ) {
				$alerts[ $code ]['first_seen'] = $existing[ $code ]['first_seen'];
			}
		}

		update_option( self::OPTION_ACTIVE_ALERTS, $alerts, false );
	}

	/**
	 * Get active alerts.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_active_alerts(): array {
		return get_option( self::OPTION_ACTIVE_ALERTS, array() );
	}
}
















