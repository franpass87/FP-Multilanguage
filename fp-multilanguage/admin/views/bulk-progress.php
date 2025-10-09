<?php
/**
 * Bulk Translation Progress Page
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$job_id = isset( $_GET['job_id'] ) ? intval( $_GET['job_id'] ) : 0;

if ( ! $job_id ) {
	wp_die( __( 'Invalid job ID.', 'fp-multilanguage' ) );
}

$bulk_manager = FPML_Container::resolve( 'bulk_translation_manager' );
$job = $bulk_manager->get_job_status( $job_id );

if ( ! $job ) {
	wp_die( __( 'Job not found.', 'fp-multilanguage' ) );
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Bulk Translation Progress', 'fp-multilanguage' ); ?></h1>

	<div class="fpml-bulk-progress-container" style="max-width: 800px; margin: 30px 0;">
		
		<!-- Progress Card -->
		<div class="fpml-progress-card" style="background: white; padding: 30px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			
			<!-- Status Badge -->
			<div style="margin-bottom: 20px;">
				<span class="fpml-status-badge fpml-status-<?php echo esc_attr( $job['status'] ); ?>" style="padding: 8px 16px; border-radius: 4px; font-weight: bold; display: inline-block;">
					<?php
					$status_labels = array(
						'pending' => '⏳ ' . __( 'Pending', 'fp-multilanguage' ),
						'processing' => '⚙️ ' . __( 'Processing', 'fp-multilanguage' ),
						'completed' => '✅ ' . __( 'Completed', 'fp-multilanguage' ),
						'failed' => '❌ ' . __( 'Failed', 'fp-multilanguage' ),
					);
					echo esc_html( $status_labels[ $job['status'] ] ?? $job['status'] );
					?>
				</span>
			</div>

			<!-- Progress Bar -->
			<div style="margin-bottom: 30px;">
				<div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
					<span><strong><?php esc_html_e( 'Progress', 'fp-multilanguage' ); ?></strong></span>
					<span id="progress-text"><strong><?php echo esc_html( $job['progress'] ); ?>%</strong></span>
				</div>
				<div style="width: 100%; height: 30px; background: #f0f0f0; border-radius: 4px; overflow: hidden; position: relative;">
					<div id="progress-bar" style="height: 100%; background: linear-gradient(90deg, #0073aa, #00a0d2); width: <?php echo esc_attr( $job['progress'] ); ?>%; transition: width 0.5s ease;">
					</div>
				</div>
			</div>

			<!-- Statistics -->
			<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
				<div class="stat-box" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
					<div style="font-size: 32px; font-weight: bold; color: #0073aa;" id="total-posts">
						<?php echo esc_html( $job['total_posts'] ); ?>
					</div>
					<div style="color: #666; margin-top: 5px;">
						<?php esc_html_e( 'Total Posts', 'fp-multilanguage' ); ?>
					</div>
				</div>

				<div class="stat-box" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
					<div style="font-size: 32px; font-weight: bold; color: #00a32a;" id="processed-posts">
						<?php echo esc_html( $job['processed_posts'] ); ?>
					</div>
					<div style="color: #666; margin-top: 5px;">
						<?php esc_html_e( 'Successful', 'fp-multilanguage' ); ?>
					</div>
				</div>

				<div class="stat-box" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
					<div style="font-size: 32px; font-weight: bold; color: #d63638;" id="failed-posts">
						<?php echo esc_html( $job['failed_posts'] ); ?>
					</div>
					<div style="color: #666; margin-top: 5px;">
						<?php esc_html_e( 'Failed', 'fp-multilanguage' ); ?>
					</div>
				</div>
			</div>

			<!-- Details -->
			<div style="border-top: 1px solid #ddd; padding-top: 20px;">
				<table class="widefat" style="border: none;">
					<tr>
						<th style="width: 150px; text-align: left; padding: 8px 0;"><?php esc_html_e( 'Job ID', 'fp-multilanguage' ); ?></th>
						<td style="padding: 8px 0;"><?php echo esc_html( $job['id'] ); ?></td>
					</tr>
					<tr>
						<th style="text-align: left; padding: 8px 0;"><?php esc_html_e( 'Created', 'fp-multilanguage' ); ?></th>
						<td style="padding: 8px 0;"><?php echo esc_html( $job['created_at'] ); ?></td>
					</tr>
					<?php if ( $job['started_at'] ) : ?>
					<tr>
						<th style="text-align: left; padding: 8px 0;"><?php esc_html_e( 'Started', 'fp-multilanguage' ); ?></th>
						<td style="padding: 8px 0;"><?php echo esc_html( $job['started_at'] ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( $job['completed_at'] ) : ?>
					<tr>
						<th style="text-align: left; padding: 8px 0;"><?php esc_html_e( 'Completed', 'fp-multilanguage' ); ?></th>
						<td style="padding: 8px 0;"><?php echo esc_html( $job['completed_at'] ); ?></td>
					</tr>
					<?php endif; ?>
				</table>
			</div>

			<!-- Errors -->
			<?php if ( ! empty( $job['errors'] ) ) : ?>
			<div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
				<h3 style="margin-top: 0; color: #856404;">
					⚠️ <?php esc_html_e( 'Errors', 'fp-multilanguage' ); ?>
				</h3>
				<ul style="margin: 0; padding-left: 20px;">
					<?php foreach ( array_slice( $job['errors'], 0, 10 ) as $error ) : ?>
						<li>
							<strong><?php esc_html_e( 'Post', 'fp-multilanguage' ); ?> #<?php echo esc_html( $error['post_id'] ); ?>:</strong>
							<?php echo esc_html( $error['error'] ); ?>
							<span style="color: #666; font-size: 12px;">(<?php echo esc_html( $error['time'] ); ?>)</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $job['errors'] ) > 10 ) : ?>
					<p style="margin-bottom: 0; margin-top: 10px; color: #666;">
						<?php printf( __( '... and %d more errors', 'fp-multilanguage' ), count( $job['errors'] ) - 10 ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Actions -->
			<div style="margin-top: 30px; text-align: center;">
				<?php if ( 'completed' === $job['status'] || 'failed' === $job['status'] ) : ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="button button-primary button-large">
						<?php esc_html_e( 'View Posts', 'fp-multilanguage' ); ?>
					</a>
				<?php else : ?>
					<button type="button" class="button button-large" disabled>
						⏳ <?php esc_html_e( 'Processing...', 'fp-multilanguage' ); ?>
					</button>
				<?php endif; ?>
			</div>

		</div>
	</div>
</div>

<style>
.fpml-status-pending { background: #f0f6fc; color: #0969da; }
.fpml-status-processing { background: #fff8e1; color: #f57c00; }
.fpml-status-completed { background: #d4edda; color: #155724; }
.fpml-status-failed { background: #f8d7da; color: #721c24; }
</style>

<script>
jQuery(document).ready(function($) {
	var jobId = <?php echo intval( $job_id ); ?>;
	var currentStatus = '<?php echo esc_js( $job['status'] ); ?>';

	// Auto-refresh if job is still processing
	if (currentStatus === 'pending' || currentStatus === 'processing') {
		var refreshInterval = setInterval(function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fpml_bulk_progress',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_bulk_translate' ) ); ?>',
					job_id: jobId
				},
				success: function(response) {
					if (response.success && response.data) {
						var job = response.data;

						// Update progress
						$('#progress-text').text(job.progress + '%');
						$('#progress-bar').css('width', job.progress + '%');

						// Update stats
						$('#processed-posts').text(job.processed_posts);
						$('#failed-posts').text(job.failed_posts);

						// Check if completed
						if (job.status === 'completed' || job.status === 'failed') {
							clearInterval(refreshInterval);
							location.reload();
						}
					}
				}
			});
		}, 3000); // Refresh every 3 seconds

		// Cleanup on page unload
		$(window).on('beforeunload', function() {
			clearInterval(refreshInterval);
		});
	}
});
</script>
<?php
