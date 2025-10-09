<?php
/**
 * Analytics and Reporting Dashboard
 *
 * Provides comprehensive analytics for translation operations.
 *
 * @package FP_Multilanguage
 * @subpackage Analytics
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Analytics_Dashboard
 *
 * Manages analytics and reporting features:
 * - Cost tracking per provider
 * - Translation quality metrics
 * - Performance statistics
 * - Monthly reports via email
 * - Language conversion tracking
 *
 * @since 0.5.0
 */
class FPML_Analytics_Dashboard {

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Analytics_Dashboard
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return FPML_Analytics_Dashboard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_fpml_analytics_data', array( $this, 'ajax_get_analytics_data' ) );
		add_action( 'fpml_post_translated', array( $this, 'track_translation' ), 10, 4 );
		add_action( 'fpml_monthly_report', array( $this, 'send_monthly_report' ) );
		
		// Schedule monthly report if not scheduled.
		if ( ! wp_next_scheduled( 'fpml_monthly_report' ) ) {
			wp_schedule_event( strtotime( 'first day of next month 9:00am' ), 'monthly', 'fpml_monthly_report' );
		}
	}

	/**
	 * Add analytics menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'fpml',
			__( 'Analytics', 'fp-multilanguage' ),
			__( 'Analytics', 'fp-multilanguage' ),
			'manage_options',
			'fpml-analytics',
			array( $this, 'render_analytics_page' )
		);
	}

	/**
	 * Render analytics page.
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		$this->maybe_create_table();
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Translation Analytics', 'fp-multilanguage' ); ?></h1>
			
			<div class="fpml-analytics-filters" style="margin: 20px 0;">
				<label>
					<?php esc_html_e( 'Period:', 'fp-multilanguage' ); ?>
					<select id="fpml-period" style="margin-left: 10px;">
						<option value="7"><?php esc_html_e( 'Last 7 days', 'fp-multilanguage' ); ?></option>
						<option value="30" selected><?php esc_html_e( 'Last 30 days', 'fp-multilanguage' ); ?></option>
						<option value="90"><?php esc_html_e( 'Last 90 days', 'fp-multilanguage' ); ?></option>
						<option value="365"><?php esc_html_e( 'Last year', 'fp-multilanguage' ); ?></option>
					</select>
				</label>
			</div>

			<div class="fpml-analytics-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
				<!-- Stats cards will be populated via JavaScript -->
			</div>

			<div class="fpml-analytics-charts" style="margin-top: 30px;">
				<div class="fpml-chart-container" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd;">
					<h2><?php esc_html_e( 'Cost by Provider', 'fp-multilanguage' ); ?></h2>
					<canvas id="fpml-cost-chart" height="80"></canvas>
				</div>

				<div class="fpml-chart-container" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd;">
					<h2><?php esc_html_e( 'Translations Over Time', 'fp-multilanguage' ); ?></h2>
					<canvas id="fpml-timeline-chart" height="80"></canvas>
				</div>

				<div class="fpml-chart-container" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd;">
					<h2><?php esc_html_e( 'Language Pairs', 'fp-multilanguage' ); ?></h2>
					<canvas id="fpml-languages-chart" height="80"></canvas>
				</div>

				<div class="fpml-chart-container" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd;">
					<h2><?php esc_html_e( 'Content Types', 'fp-multilanguage' ); ?></h2>
					<canvas id="fpml-content-chart" height="80"></canvas>
				</div>
			</div>

			<div class="fpml-analytics-tables" style="margin-top: 30px;">
				<div style="background: white; padding: 20px; border: 1px solid #ddd;">
					<h2><?php esc_html_e( 'Recent Translations', 'fp-multilanguage' ); ?></h2>
					<table class="wp-list-table widefat fixed striped" id="fpml-recent-translations">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'fp-multilanguage' ); ?></th>
								<th><?php esc_html_e( 'Content', 'fp-multilanguage' ); ?></th>
								<th><?php esc_html_e( 'Type', 'fp-multilanguage' ); ?></th>
								<th><?php esc_html_e( 'Provider', 'fp-multilanguage' ); ?></th>
								<th><?php esc_html_e( 'Characters', 'fp-multilanguage' ); ?></th>
								<th><?php esc_html_e( 'Cost', 'fp-multilanguage' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'fp-multilanguage' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<!-- Populated via JavaScript -->
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
		<script>
		jQuery(document).ready(function($) {
			let charts = {};

			function loadAnalytics(period = 30) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fpml_analytics_data',
						nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_analytics' ) ); ?>',
						period: period
					},
					success: function(response) {
						if (response.success) {
							renderStats(response.data.stats);
							renderCharts(response.data.charts);
							renderRecentTranslations(response.data.recent);
						}
					}
				});
			}

			function renderStats(stats) {
				const container = $('.fpml-analytics-stats');
				container.html(`
					<div class="fpml-stat-card" style="background: white; padding: 20px; border: 1px solid #ddd; border-left: 4px solid #0073aa;">
						<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Total Translations', 'fp-multilanguage' ); ?></h3>
						<p style="margin: 0; font-size: 32px; font-weight: bold; color: #0073aa;">${stats.total_translations}</p>
					</div>
					<div class="fpml-stat-card" style="background: white; padding: 20px; border: 1px solid #ddd; border-left: 4px solid #00a32a;">
						<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Total Characters', 'fp-multilanguage' ); ?></h3>
						<p style="margin: 0; font-size: 32px; font-weight: bold; color: #00a32a;">${stats.total_characters.toLocaleString()}</p>
					</div>
					<div class="fpml-stat-card" style="background: white; padding: 20px; border: 1px solid #ddd; border-left: 4px solid #d63638;">
						<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Total Cost', 'fp-multilanguage' ); ?></h3>
						<p style="margin: 0; font-size: 32px; font-weight: bold; color: #d63638;">$${stats.total_cost.toFixed(2)}</p>
					</div>
					<div class="fpml-stat-card" style="background: white; padding: 20px; border: 1px solid #ddd; border-left: 4px solid #f0b849;">
						<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e( 'Avg. Duration', 'fp-multilanguage' ); ?></h3>
						<p style="margin: 0; font-size: 32px; font-weight: bold; color: #f0b849;">${stats.avg_duration.toFixed(1)}s</p>
					</div>
				`);
			}

			function renderCharts(data) {
				// Cost by Provider
				if (charts.cost) charts.cost.destroy();
				charts.cost = new Chart(document.getElementById('fpml-cost-chart'), {
					type: 'bar',
					data: {
						labels: data.cost_by_provider.labels,
						datasets: [{
							label: '<?php esc_html_e( 'Cost ($)', 'fp-multilanguage' ); ?>',
							data: data.cost_by_provider.data,
							backgroundColor: ['#0073aa', '#00a32a', '#d63638', '#f0b849']
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true
					}
				});

				// Timeline
				if (charts.timeline) charts.timeline.destroy();
				charts.timeline = new Chart(document.getElementById('fpml-timeline-chart'), {
					type: 'line',
					data: {
						labels: data.timeline.labels,
						datasets: [{
							label: '<?php esc_html_e( 'Translations', 'fp-multilanguage' ); ?>',
							data: data.timeline.data,
							borderColor: '#0073aa',
							backgroundColor: 'rgba(0, 115, 170, 0.1)',
							fill: true,
							tension: 0.4
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true
					}
				});

				// Languages
				if (charts.languages) charts.languages.destroy();
				charts.languages = new Chart(document.getElementById('fpml-languages-chart'), {
					type: 'doughnut',
					data: {
						labels: data.languages.labels,
						datasets: [{
							data: data.languages.data,
							backgroundColor: ['#0073aa', '#00a32a', '#d63638', '#f0b849', '#00a0d2']
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true
					}
				});

				// Content Types
				if (charts.content) charts.content.destroy();
				charts.content = new Chart(document.getElementById('fpml-content-chart'), {
					type: 'pie',
					data: {
						labels: data.content_types.labels,
						datasets: [{
							data: data.content_types.data,
							backgroundColor: ['#0073aa', '#00a32a', '#d63638', '#f0b849']
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true
					}
				});
			}

			function renderRecentTranslations(recent) {
				const tbody = $('#fpml-recent-translations tbody');
				tbody.empty();

				if (recent.length === 0) {
					tbody.html('<tr><td colspan="7" style="text-align: center;"><?php esc_html_e( 'No recent translations', 'fp-multilanguage' ); ?></td></tr>');
					return;
				}

				recent.forEach(function(item) {
					tbody.append(`
						<tr>
							<td>${item.date}</td>
							<td><a href="${item.url}" target="_blank">${item.title}</a></td>
							<td>${item.type}</td>
							<td><span style="padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px;">${item.provider}</span></td>
							<td>${item.characters}</td>
							<td>$${item.cost}</td>
							<td>${item.duration}s</td>
						</tr>
					`);
				});
			}

			// Load data on page load
			loadAnalytics(30);

			// Period change handler
			$('#fpml-period').on('change', function() {
				loadAnalytics($(this).val());
			});
		});
		</script>
		<?php
	}

	/**
	 * Track translation event.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $object_type Object type.
	 * @param string $provider Provider used.
	 * @param array  $data Translation data.
	 * @return void
	 */
	public function track_translation( $object_id, $object_type, $provider, $data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_analytics';

		$this->maybe_create_table();

		$defaults = array(
			'source_lang' => 'it',
			'target_lang' => 'en',
			'characters' => 0,
			'cost' => 0,
			'duration' => 0,
			'quality_score' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert(
			$table,
			array(
				'object_id' => $object_id,
				'object_type' => $object_type,
				'provider' => $provider,
				'source_lang' => $data['source_lang'],
				'target_lang' => $data['target_lang'],
				'characters' => $data['characters'],
				'cost' => $data['cost'],
				'duration' => $data['duration'],
				'quality_score' => $data['quality_score'],
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%s' )
		);
	}

	/**
	 * Get analytics data.
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Analytics data.
	 */
	public function get_analytics_data( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_analytics';
		$date_from = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Basic stats.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total_translations,
					SUM(characters) as total_characters,
					SUM(cost) as total_cost,
					AVG(duration) as avg_duration
				FROM {$table}
				WHERE created_at >= %s",
				$date_from
			),
			ARRAY_A
		);

		// Cost by provider.
		$cost_by_provider = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT provider, SUM(cost) as total_cost
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY provider
				ORDER BY total_cost DESC",
				$date_from
			),
			ARRAY_A
		);

		// Timeline data.
		$timeline = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as count
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$date_from
			),
			ARRAY_A
		);

		// Language pairs.
		$languages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					CONCAT(source_lang, ' → ', target_lang) as pair,
					COUNT(*) as count
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY source_lang, target_lang
				ORDER BY count DESC
				LIMIT 10",
				$date_from
			),
			ARRAY_A
		);

		// Content types.
		$content_types = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT object_type, COUNT(*) as count
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY object_type
				ORDER BY count DESC",
				$date_from
			),
			ARRAY_A
		);

		// Recent translations.
		$recent = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$table}
				WHERE created_at >= %s
				ORDER BY created_at DESC
				LIMIT 20",
				$date_from
			),
			ARRAY_A
		);

		return array(
			'stats' => array(
				'total_translations' => (int) $stats['total_translations'],
				'total_characters' => (int) $stats['total_characters'],
				'total_cost' => (float) $stats['total_cost'],
				'avg_duration' => (float) $stats['avg_duration'],
			),
			'charts' => array(
				'cost_by_provider' => array(
					'labels' => array_column( $cost_by_provider, 'provider' ),
					'data' => array_map( 'floatval', array_column( $cost_by_provider, 'total_cost' ) ),
				),
				'timeline' => array(
					'labels' => array_column( $timeline, 'date' ),
					'data' => array_map( 'intval', array_column( $timeline, 'count' ) ),
				),
				'languages' => array(
					'labels' => array_column( $languages, 'pair' ),
					'data' => array_map( 'intval', array_column( $languages, 'count' ) ),
				),
				'content_types' => array(
					'labels' => array_column( $content_types, 'object_type' ),
					'data' => array_map( 'intval', array_column( $content_types, 'count' ) ),
				),
			),
			'recent' => $this->format_recent_translations( $recent ),
		);
	}

	/**
	 * Format recent translations for display.
	 *
	 * @param array $recent Recent translations.
	 * @return array Formatted data.
	 */
	private function format_recent_translations( $recent ) {
		$formatted = array();

		foreach ( $recent as $item ) {
			$post = get_post( $item['object_id'] );
			$title = $post ? $post->post_title : __( 'Unknown', 'fp-multilanguage' );
			$url = $post ? get_edit_post_link( $item['object_id'] ) : '#';

			$formatted[] = array(
				'date' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ),
				'title' => $title,
				'url' => $url,
				'type' => ucfirst( $item['object_type'] ),
				'provider' => ucfirst( $item['provider'] ),
				'characters' => number_format( $item['characters'] ),
				'cost' => number_format( $item['cost'], 4 ),
				'duration' => number_format( $item['duration'], 2 ),
			);
		}

		return $formatted;
	}

	/**
	 * AJAX: Get analytics data.
	 *
	 * @return void
	 */
	public function ajax_get_analytics_data() {
		check_ajax_referer( 'fpml_analytics', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$period = isset( $_POST['period'] ) ? intval( $_POST['period'] ) : 30;
		$data = $this->get_analytics_data( $period );

		wp_send_json_success( $data );
	}

	/**
	 * Send monthly report via email.
	 *
	 * @return void
	 */
	public function send_monthly_report() {
		$data = $this->get_analytics_data( 30 );
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			__( '[FP Multilanguage] Monthly Report - %s', 'fp-multilanguage' ),
			date_i18n( 'F Y', strtotime( 'last month' ) )
		);

		$message = $this->format_email_report( $data );

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Format email report.
	 *
	 * @param array $data Analytics data.
	 * @return string Formatted message.
	 */
	private function format_email_report( $data ) {
		$stats = $data['stats'];

		$message = sprintf(
			__( "FP Multilanguage Monthly Report\n\n" .
				"=== Summary ===\n" .
				"Total Translations: %d\n" .
				"Total Characters: %s\n" .
				"Total Cost: $%s\n" .
				"Average Duration: %.1fs\n\n" .
				"=== Top Providers by Cost ===\n", 'fp-multilanguage' ),
			$stats['total_translations'],
			number_format( $stats['total_characters'] ),
			number_format( $stats['total_cost'], 2 ),
			$stats['avg_duration']
		);

		// Add provider costs.
		foreach ( $data['charts']['cost_by_provider']['labels'] as $i => $provider ) {
			$cost = $data['charts']['cost_by_provider']['data'][ $i ];
			$message .= sprintf( "%s: $%s\n", $provider, number_format( $cost, 2 ) );
		}

		$message .= sprintf(
			"\n\n%s\n%s",
			__( 'View detailed analytics:', 'fp-multilanguage' ),
			admin_url( 'admin.php?page=fpml-analytics' )
		);

		return $message;
	}

	/**
	 * Maybe create analytics table.
	 *
	 * @return void
	 */
	private function maybe_create_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_analytics';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_id bigint(20) unsigned NOT NULL,
			object_type varchar(20) NOT NULL,
			provider varchar(50) NOT NULL,
			source_lang varchar(10) NOT NULL,
			target_lang varchar(10) NOT NULL,
			characters int(11) NOT NULL DEFAULT 0,
			cost decimal(10,6) NOT NULL DEFAULT 0,
			duration decimal(10,2) NOT NULL DEFAULT 0,
			quality_score decimal(3,2),
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY object_lookup (object_type, object_id),
			KEY provider (provider),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
