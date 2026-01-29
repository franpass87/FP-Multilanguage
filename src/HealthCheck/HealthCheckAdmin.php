<?php
/**
 * Health Check Admin - Handles admin UI for health check.
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
 * Handles admin UI for health check.
 *
 * @since 0.10.0
 */
class HealthCheckAdmin {
	/**
	 * Alert manager instance.
	 *
	 * @var AlertManager
	 */
	protected AlertManager $alert_manager;

	/**
	 * Constructor.
	 *
	 * @param AlertManager $alert_manager Alert manager instance.
	 */
	public function __construct( AlertManager $alert_manager ) {
		$this->alert_manager = $alert_manager;
	}

	/**
	 * Mostra notice admin se ci sono problemi.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function show_health_notices(): void {
		$alerts = $this->alert_manager->get_active_alerts();

		if ( empty( $alerts ) ) {
			return;
		}

		$critical_alerts = array_filter( $alerts, array( $this, 'is_critical' ) );

		if ( ! empty( $critical_alerts ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'FP Multilanguage - Problemi critici rilevati', 'fp-multilanguage' ); ?></strong>
				</p>
				<ul>
					<?php foreach ( $critical_alerts as $alert ) : ?>
						<li><?php echo esc_html( $alert['message'] ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-multilanguage&tab=diagnostics' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Vai alla diagnostica', 'fp-multilanguage' ); ?>
					</a>
				</p>
			</div>
			<?php
		} else {
			$warning_alerts = array_filter( $alerts, array( $this, 'is_warning' ) );

			if ( ! empty( $warning_alerts ) ) {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'FP Multilanguage - Avvisi', 'fp-multilanguage' ); ?></strong>
					</p>
					<ul>
						<?php foreach ( $warning_alerts as $alert ) : ?>
							<li><?php echo esc_html( $alert['message'] ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-multilanguage&tab=diagnostics' ) ); ?>" class="button">
							<?php esc_html_e( 'Vai alla diagnostica', 'fp-multilanguage' ); ?>
						</a>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Check if alert is critical.
	 *
	 * @param array $alert Alert data.
	 * @return bool
	 */
	protected function is_critical( array $alert ): bool {
		return 'critical' === $alert['severity'];
	}

	/**
	 * Check if alert is warning.
	 *
	 * @param array $alert Alert data.
	 * @return bool
	 */
	protected function is_warning( array $alert ): bool {
		return 'warning' === $alert['severity'];
	}
}
















