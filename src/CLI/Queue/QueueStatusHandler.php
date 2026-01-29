<?php
/**
 * CLI Queue Status Handler - Displays queue status and scheduled events.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\CLI\Queue;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays queue status and scheduled events.
 *
 * @since 0.10.0
 */
class QueueStatusHandler {
	use ContainerAwareTrait;
	/**
	 * Display queue status and scheduled events.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function display_status(): void {
		$queue      = fpml_get_queue();
		$processor  = \FPML_fpml_get_processor();
		$state_data = $queue->get_state_counts();

		$rows   = array();
		$states = array( 'pending', 'translating', 'outdated', 'done', 'error', 'skipped' );

		foreach ( $states as $state ) {
			$rows[] = array(
				'stato'  => $state,
				'totale' => isset( $state_data[ $state ] ) ? (int) $state_data[ $state ] : 0,
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( __( 'Nessun job presente nella coda.', 'fp-multilanguage' ) );
		} else {
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'stato', 'totale' ) );
		}

		$events = array(
			'\FPML_run_queue'       => wp_next_scheduled( '\FPML_run_queue' ),
			'\FPML_retry_failed'    => wp_next_scheduled( '\FPML_retry_failed' ),
			'\FPML_resync_outdated' => wp_next_scheduled( '\FPML_resync_outdated' ),
			'\FPML_cleanup_queue'   => wp_next_scheduled( '\FPML_cleanup_queue' ),
		);

		foreach ( $events as $hook => $timestamp ) {
			if ( $timestamp ) {
				\WP_CLI::line(
					sprintf(
						/* translators: 1: hook name, 2: formatted datetime */
						__( '%1$s: %2$s', 'fp-multilanguage' ),
						$hook,
						date_i18n( 'Y-m-d H:i:s', $timestamp )
					)
				);
			} else {
				\WP_CLI::line(
					sprintf(
						/* translators: %s: hook name */
						__( '%s: non programmato', 'fp-multilanguage' ),
						$hook
					)
				);
			}
		}

		\WP_CLI::line(
			sprintf(
				/* translators: %s: lock status label */
				__( 'Lock processor: %s', 'fp-multilanguage' ),
				$processor->is_locked() ? __( 'attivo', 'fp-multilanguage' ) : __( 'libero', 'fp-multilanguage' )
			)
		);

		$container = $this->getContainer();
		$settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
		$provider_map = array(
			'openai'         => 'OpenAI',
			'google'         => 'Google Cloud Translation',
		);

		$provider_slug = $settings ? $settings->get( 'provider', '' ) : '';
		$provider_name = isset( $provider_map[ $provider_slug ] ) ? $provider_map[ $provider_slug ] : ( '' !== $provider_slug ? ucfirst( $provider_slug ) : __( 'Nessun provider', 'fp-multilanguage' ) );

		$translator_instance = $processor->get_translator_instance();

		if ( is_wp_error( $translator_instance ) ) {
			\WP_CLI::warning(
				sprintf(
					/* translators: 1: provider label, 2: error message */
					__( 'Provider %1$s non configurato: %2$s', 'fp-multilanguage' ),
					$provider_name,
					$translator_instance->get_error_message()
				)
			);
		} else {
			\WP_CLI::line(
				sprintf(
					/* translators: %s: provider label */
					__( 'Provider configurato: %s', 'fp-multilanguage' ),
					$provider_name
				)
			);
		}

		$age_summary = ( function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance() )->get_queue_age_summary();
		$retention   = isset( $age_summary['retention_days'] ) ? (int) $age_summary['retention_days'] : 0;
		$states      = isset( $age_summary['cleanup_states'] ) ? (array) $age_summary['cleanup_states'] : array();

		if ( $retention > 0 && ! empty( $states ) ) {
			\WP_CLI::line(
				sprintf(
					/* translators: 1: days, 2: comma separated states */
					__( 'Retention automatica: %1$d giorni (%2$s)', 'fp-multilanguage' ),
					$retention,
					implode( ',', $states )
				)
			);
		} else {
			\WP_CLI::line( __( 'Retention automatica: disattivata', 'fp-multilanguage' ) );
		}

		if ( ! empty( $age_summary['pending'] ) && isset( $age_summary['pending']['age'] ) ) {
			\WP_CLI::line(
				sprintf(
					/* translators: 1: human readable age, 2: local datetime */
					__( 'Job in attesa più vecchio: %1$s fa (%2$s)', 'fp-multilanguage' ),
					$age_summary['pending']['age'],
					$age_summary['pending']['datetime_local']
				)
			);
		}

		if ( ! empty( $age_summary['completed'] ) && isset( $age_summary['completed']['age'] ) ) {
			\WP_CLI::line(
				sprintf(
					/* translators: 1: human readable age, 2: local datetime */
					__( 'Job completato più vecchio ancora archiviato: %1$s fa (%2$s)', 'fp-multilanguage' ),
					$age_summary['completed']['age'],
					$age_summary['completed']['datetime_local']
				)
			);
		}
	}
}
















