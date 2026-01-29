<?php
/**
 * SEO GSC Renderer - Renders Google Search Console comparison in metabox.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders Google Search Console comparison in metabox.
 *
 * @since 0.10.0
 */
class GscRenderer {
	/**
	 * Render GSC metrics comparison in translation metabox.
	 *
	 * @since 0.10.0
	 *
	 * @param int      $post_id     Current post ID.
	 * @param int|null $english_id  English post ID.
	 *
	 * @return void
	 */
	public function render_gsc_comparison( int $post_id, ?int $english_id ): void {
		if ( ! $english_id ) {
			return;
		}

		// Only if GscData class exists
		if ( ! class_exists( '\FP\SEO\Integrations\GscData' ) ) {
			return;
		}

		$gsc = new \FP\SEO\Integrations\GscData();

		// Get metrics for both versions
		$it_metrics = $gsc->get_post_metrics( $post_id );
		$en_metrics = $gsc->get_post_metrics( $english_id );

		if ( ! $it_metrics && ! $en_metrics ) {
			return; // No data available
		}

		?>
		<div class="fpml-seo-gsc-comparison" style="margin: 12px 0; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
			<h4 style="margin: 0 0 10px; font-size: 13px; font-weight: 600; color: #374151;">
				📊 Google Search Console (28 giorni)
			</h4>
			
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
				<!-- IT Version -->
				<div style="padding: 10px; background: #fff; border-radius: 4px;">
					<div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 6px;">
						🇮🇹 Italiano
					</div>
					<?php if ( $it_metrics ) : ?>
						<div style="font-size: 11px; color: #111827; line-height: 1.6;">
							<strong><?php echo esc_html( number_format_i18n( $it_metrics['clicks'] ) ); ?></strong> click<br>
							<strong><?php echo esc_html( number_format_i18n( $it_metrics['impressions'] ) ); ?></strong> impression<br>
							CTR: <strong><?php echo esc_html( $it_metrics['ctr'] ); ?>%</strong><br>
							Pos: <strong><?php echo esc_html( $it_metrics['position'] ); ?></strong>
						</div>
					<?php else : ?>
						<div style="font-size: 11px; color: #9ca3af;">
							Nessun dato
						</div>
					<?php endif; ?>
				</div>

				<!-- EN Version -->
				<div style="padding: 10px; background: #fff; border-radius: 4px;">
					<div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 6px;">
						🇬🇧 English
					</div>
					<?php if ( $en_metrics ) : ?>
						<div style="font-size: 11px; color: #111827; line-height: 1.6;">
							<strong><?php echo esc_html( number_format_i18n( $en_metrics['clicks'] ) ); ?></strong> click<br>
							<strong><?php echo esc_html( number_format_i18n( $en_metrics['impressions'] ) ); ?></strong> impression<br>
							CTR: <strong><?php echo esc_html( $en_metrics['ctr'] ); ?>%</strong><br>
							Pos: <strong><?php echo esc_html( $en_metrics['position'] ); ?></strong>
						</div>
					<?php else : ?>
						<div style="font-size: 11px; color: #9ca3af;">
							Nessun dato
						</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $it_metrics && $en_metrics ) : ?>
				<!-- Performance Comparison -->
				<?php
				$clicks_diff = $en_metrics['clicks'] - $it_metrics['clicks'];
				$clicks_icon = $clicks_diff > 0 ? '📈' : ( $clicks_diff < 0 ? '📉' : '➡️' );
				$clicks_color = $clicks_diff > 0 ? '#059669' : ( $clicks_diff < 0 ? '#dc2626' : '#6b7280' );
				?>
				<div style="margin-top: 10px; padding: 8px; background: #fff; border-radius: 4px; font-size: 11px;">
					<strong style="color: #374151;">Differenza EN vs IT:</strong>
					<span style="color: <?php echo esc_attr( $clicks_color ); ?>; font-weight: 600; margin-left: 6px;">
						<?php echo esc_html( $clicks_icon ); ?> 
						<?php echo esc_html( $clicks_diff > 0 ? '+' . $clicks_diff : $clicks_diff ); ?> click
					</span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
















