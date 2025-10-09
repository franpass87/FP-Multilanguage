<?php
/**
 * Advanced Debug Mode
 *
 * Provides comprehensive debugging and profiling capabilities.
 *
 * @package FP_Multilanguage
 * @subpackage Debug
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Debug_Mode
 *
 * Advanced debugging features:
 * - Detailed logging of all operations
 * - API call tracking with timing
 * - Input/output validation
 * - Performance profiling
 * - Memory usage tracking
 * - Debug toolbar integration
 *
 * Enable with: define('FPML_DEBUG', true);
 *
 * @since 0.5.0
 */
class FPML_Debug_Mode {

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Debug_Mode
	 */
	private static $instance = null;

	/**
	 * Debug log entries.
	 *
	 * @var array
	 */
	private $debug_log = array();

	/**
	 * Profiling data.
	 *
	 * @var array
	 */
	private $profiles = array();

	/**
	 * API call log.
	 *
	 * @var array
	 */
	private $api_calls = array();

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool True if debug mode is enabled.
	 */
	public static function is_enabled() {
		return defined( 'FPML_DEBUG' ) && FPML_DEBUG;
	}

	/**
	 * Get singleton instance.
	 *
	 * @return FPML_Debug_Mode
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
		if ( ! self::is_enabled() ) {
			return;
		}

		// Hook into various events for logging.
		add_action( 'fpml_before_translate', array( $this, 'log_before_translate' ), 10, 3 );
		add_action( 'fpml_after_translate', array( $this, 'log_after_translate' ), 10, 4 );
		add_filter( 'fpml_api_request', array( $this, 'log_api_request' ), 10, 3 );
		add_filter( 'fpml_api_response', array( $this, 'log_api_response' ), 10, 3 );
		
		// Admin toolbar.
		if ( is_admin() || ( ! is_admin() && is_user_logged_in() ) ) {
			add_action( 'admin_bar_menu', array( $this, 'add_toolbar_item' ), 999 );
			add_action( 'admin_footer', array( $this, 'render_debug_panel' ) );
			add_action( 'wp_footer', array( $this, 'render_debug_panel' ) );
		}

		// Admin page.
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_fpml_debug_clear', array( $this, 'ajax_clear_debug_log' ) );
		add_action( 'wp_ajax_fpml_debug_export', array( $this, 'ajax_export_debug_log' ) );

		// Register shutdown function to save debug data.
		register_shutdown_function( array( $this, 'save_debug_data' ) );
	}

	/**
	 * Log message to debug log.
	 *
	 * @param string $message Log message.
	 * @param string $level Log level (info, warning, error, debug).
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function log( $message, $level = 'info', $context = array() ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$this->debug_log[] = array(
			'time' => microtime( true ),
			'timestamp' => current_time( 'mysql' ),
			'level' => $level,
			'message' => $message,
			'context' => $context,
			'memory' => memory_get_usage( true ),
			'backtrace' => $this->get_simplified_backtrace(),
		);
	}

	/**
	 * Start profiling a section.
	 *
	 * @param string $label Profile label.
	 * @return void
	 */
	public function profile_start( $label ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$this->profiles[ $label ] = array(
			'start_time' => microtime( true ),
			'start_memory' => memory_get_usage( true ),
		);
	}

	/**
	 * End profiling a section.
	 *
	 * @param string $label Profile label.
	 * @return array|null Profile data or null if not started.
	 */
	public function profile_end( $label ) {
		if ( ! self::is_enabled() || ! isset( $this->profiles[ $label ] ) ) {
			return null;
		}

		$start = $this->profiles[ $label ];
		$elapsed = microtime( true ) - $start['start_time'];
		$memory_used = memory_get_usage( true ) - $start['start_memory'];

		$this->profiles[ $label ] = array_merge( $start, array(
			'end_time' => microtime( true ),
			'end_memory' => memory_get_usage( true ),
			'elapsed' => $elapsed,
			'memory_used' => $memory_used,
		) );

		$this->log(
			sprintf( 'Profile: %s completed in %.4fs using %s', $label, $elapsed, size_format( $memory_used ) ),
			'debug',
			$this->profiles[ $label ]
		);

		return $this->profiles[ $label ];
	}

	/**
	 * Log before translation.
	 *
	 * @param string $text Text to translate.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return void
	 */
	public function log_before_translate( $text, $source_lang, $target_lang ) {
		$this->log(
			sprintf( 'Starting translation: %s → %s', $source_lang, $target_lang ),
			'info',
			array(
				'text_length' => mb_strlen( $text ),
				'text_preview' => mb_substr( $text, 0, 100 ),
				'source_lang' => $source_lang,
				'target_lang' => $target_lang,
			)
		);

		$this->profile_start( 'translate_' . md5( $text ) );
	}

	/**
	 * Log after translation.
	 *
	 * @param string $text Original text.
	 * @param string $translated Translated text.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return void
	 */
	public function log_after_translate( $text, $translated, $source_lang, $target_lang ) {
		$profile_key = 'translate_' . md5( $text );
		$profile = $this->profile_end( $profile_key );

		$this->log(
			sprintf( 'Translation completed: %s → %s', $source_lang, $target_lang ),
			'info',
			array(
				'original_length' => mb_strlen( $text ),
				'translated_length' => mb_strlen( $translated ),
				'elapsed' => $profile ? $profile['elapsed'] : null,
				'memory_used' => $profile ? $profile['memory_used'] : null,
			)
		);
	}

	/**
	 * Log API request.
	 *
	 * @param array  $request Request data.
	 * @param string $provider Provider name.
	 * @param string $endpoint API endpoint.
	 * @return array Modified request data.
	 */
	public function log_api_request( $request, $provider, $endpoint ) {
		$this->api_calls[] = array(
			'time' => microtime( true ),
			'direction' => 'request',
			'provider' => $provider,
			'endpoint' => $endpoint,
			'data' => $this->sanitize_api_data( $request ),
		);

		$this->log(
			sprintf( 'API Request: %s → %s', $provider, $endpoint ),
			'debug',
			array(
				'provider' => $provider,
				'endpoint' => $endpoint,
				'request_size' => strlen( wp_json_encode( $request ) ),
			)
		);

		return $request;
	}

	/**
	 * Log API response.
	 *
	 * @param mixed  $response Response data.
	 * @param string $provider Provider name.
	 * @param string $endpoint API endpoint.
	 * @return mixed Response data.
	 */
	public function log_api_response( $response, $provider, $endpoint ) {
		$this->api_calls[] = array(
			'time' => microtime( true ),
			'direction' => 'response',
			'provider' => $provider,
			'endpoint' => $endpoint,
			'data' => $this->sanitize_api_data( $response ),
			'is_error' => is_wp_error( $response ),
		);

		$level = is_wp_error( $response ) ? 'error' : 'debug';
		$message = is_wp_error( $response )
			? sprintf( 'API Error: %s → %s: %s', $provider, $endpoint, $response->get_error_message() )
			: sprintf( 'API Response: %s → %s', $provider, $endpoint );

		$this->log( $message, $level );

		return $response;
	}

	/**
	 * Sanitize API data for logging (remove sensitive information).
	 *
	 * @param mixed $data Data to sanitize.
	 * @return mixed Sanitized data.
	 */
	private function sanitize_api_data( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				if ( in_array( strtolower( $key ), array( 'api_key', 'authorization', 'password', 'secret' ), true ) ) {
					$sanitized[ $key ] = '***REDACTED***';
				} else {
					$sanitized[ $key ] = $this->sanitize_api_data( $value );
				}
			}
			return $sanitized;
		}

		return $data;
	}

	/**
	 * Get simplified backtrace.
	 *
	 * @return array Simplified backtrace.
	 */
	private function get_simplified_backtrace() {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
		$simplified = array();

		foreach ( $trace as $item ) {
			if ( isset( $item['file'] ) && isset( $item['line'] ) ) {
				$file = str_replace( ABSPATH, '', $item['file'] );
				$simplified[] = sprintf( '%s:%d', $file, $item['line'] );
			}
		}

		return $simplified;
	}

	/**
	 * Save debug data to transient.
	 *
	 * @return void
	 */
	public function save_debug_data() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$data = array(
			'debug_log' => $this->debug_log,
			'profiles' => $this->profiles,
			'api_calls' => $this->api_calls,
			'timestamp' => current_time( 'mysql' ),
		);

		set_transient( 'fpml_debug_data_' . get_current_user_id(), $data, HOUR_IN_SECONDS );
	}

	/**
	 * Get debug data.
	 *
	 * @return array Debug data.
	 */
	public function get_debug_data() {
		$data = get_transient( 'fpml_debug_data_' . get_current_user_id() );
		
		if ( ! $data ) {
			return array(
				'debug_log' => array(),
				'profiles' => array(),
				'api_calls' => array(),
			);
		}

		return $data;
	}

	/**
	 * Add toolbar item.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_toolbar_item( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data = $this->get_debug_data();
		$log_count = count( $data['debug_log'] );
		$api_count = count( $data['api_calls'] );

		$wp_admin_bar->add_node(
			array(
				'id' => 'fpml-debug',
				'title' => sprintf(
					'🐛 FPML Debug <span class="fpml-debug-badge" style="background: #d63638; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">%d logs | %d API</span>',
					$log_count,
					$api_count
				),
				'href' => admin_url( 'admin.php?page=fpml-debug' ),
			)
		);
	}

	/**
	 * Render debug panel.
	 *
	 * @return void
	 */
	public function render_debug_panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data = $this->get_debug_data();

		?>
		<style>
		.fpml-debug-panel {
			position: fixed;
			bottom: 0;
			right: 0;
			width: 400px;
			max-height: 400px;
			background: #1d2327;
			color: #c3c4c7;
			border-top: 3px solid #d63638;
			box-shadow: -2px 0 10px rgba(0,0,0,0.3);
			z-index: 99999;
			font-size: 12px;
			font-family: monospace;
			overflow-y: auto;
			display: none;
		}
		.fpml-debug-panel.active {
			display: block;
		}
		.fpml-debug-header {
			background: #d63638;
			color: white;
			padding: 10px;
			font-weight: bold;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.fpml-debug-content {
			padding: 10px;
		}
		.fpml-debug-entry {
			padding: 5px;
			border-bottom: 1px solid #2c3338;
			margin-bottom: 5px;
		}
		.fpml-debug-level-error { color: #ff6b6b; }
		.fpml-debug-level-warning { color: #ffa500; }
		.fpml-debug-level-info { color: #4dabf7; }
		.fpml-debug-level-debug { color: #c3c4c7; }
		</style>

		<div id="fpml-debug-panel" class="fpml-debug-panel">
			<div class="fpml-debug-header">
				<span>FPML Debug Panel</span>
				<button onclick="document.getElementById('fpml-debug-panel').classList.remove('active')" style="background: transparent; border: none; color: white; cursor: pointer; font-size: 20px;">×</button>
			</div>
			<div class="fpml-debug-content">
				<div class="fpml-debug-tabs">
					<strong>Recent Logs (<?php echo count( $data['debug_log'] ); ?>):</strong>
				</div>
				<?php
				$recent_logs = array_slice( array_reverse( $data['debug_log'] ), 0, 20 );
				foreach ( $recent_logs as $entry ) :
					?>
					<div class="fpml-debug-entry">
						<div class="fpml-debug-level-<?php echo esc_attr( $entry['level'] ); ?>">
							[<?php echo esc_html( date( 'H:i:s', strtotime( $entry['timestamp'] ) ) ); ?>]
							<strong><?php echo esc_html( strtoupper( $entry['level'] ) ); ?></strong>
						</div>
						<div><?php echo esc_html( $entry['message'] ); ?></div>
						<?php if ( ! empty( $entry['context'] ) ) : ?>
							<details style="margin-top: 5px; color: #8c8f94;">
								<summary style="cursor: pointer;">Context</summary>
								<pre style="margin: 5px 0; font-size: 10px;"><?php echo esc_html( print_r( $entry['context'], true ) ); ?></pre>
							</details>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<script>
		// Show debug panel on Ctrl+Shift+D
		document.addEventListener('keydown', function(e) {
			if (e.ctrlKey && e.shiftKey && e.key === 'D') {
				e.preventDefault();
				document.getElementById('fpml-debug-panel').classList.toggle('active');
			}
		});
		</script>
		<?php
	}

	/**
	 * Add debug menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'fpml',
			__( 'Debug', 'fp-multilanguage' ),
			'🐛 ' . __( 'Debug', 'fp-multilanguage' ),
			'manage_options',
			'fpml-debug',
			array( $this, 'render_debug_page' )
		);
	}

	/**
	 * Render debug page.
	 *
	 * @return void
	 */
	public function render_debug_page() {
		$data = $this->get_debug_data();

		?>
		<div class="wrap">
			<h1>🐛 <?php esc_html_e( 'Debug Mode', 'fp-multilanguage' ); ?></h1>

			<?php if ( ! self::is_enabled() ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Debug mode is currently disabled.', 'fp-multilanguage' ); ?></strong><br>
						<?php esc_html_e( 'To enable debug mode, add the following to your wp-config.php:', 'fp-multilanguage' ); ?>
					</p>
					<pre style="background: #f5f5f5; padding: 15px; margin: 10px 0;">define('FPML_DEBUG', true);</pre>
				</div>
			<?php else : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Debug mode is ACTIVE', 'fp-multilanguage' ); ?></strong><br>
						<?php esc_html_e( 'All translation operations are being logged. Press Ctrl+Shift+D to toggle the debug panel.', 'fp-multilanguage' ); ?>
					</p>
				</div>

				<div style="margin: 20px 0;">
					<button class="button" id="fpml-debug-clear">
						<?php esc_html_e( 'Clear Debug Log', 'fp-multilanguage' ); ?>
					</button>
					<button class="button" id="fpml-debug-export">
						<?php esc_html_e( 'Export Debug Log', 'fp-multilanguage' ); ?>
					</button>
				</div>

				<div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
					<h2><?php esc_html_e( 'Debug Statistics', 'fp-multilanguage' ); ?></h2>
					<table class="widefat">
						<tr>
							<th><?php esc_html_e( 'Total Log Entries', 'fp-multilanguage' ); ?></th>
							<td><?php echo esc_html( number_format( count( $data['debug_log'] ) ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'API Calls', 'fp-multilanguage' ); ?></th>
							<td><?php echo esc_html( number_format( count( $data['api_calls'] ) ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Profiles', 'fp-multilanguage' ); ?></th>
							<td><?php echo esc_html( number_format( count( $data['profiles'] ) ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Last Updated', 'fp-multilanguage' ); ?></th>
							<td><?php echo esc_html( $data['timestamp'] ?? '-' ); ?></td>
						</tr>
					</table>
				</div>

				<div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
					<h2><?php esc_html_e( 'Recent Debug Logs', 'fp-multilanguage' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 140px;"><?php esc_html_e( 'Time', 'fp-multilanguage' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Level', 'fp-multilanguage' ); ?></th>
								<th><?php esc_html_e( 'Message', 'fp-multilanguage' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Memory', 'fp-multilanguage' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$recent_logs = array_slice( array_reverse( $data['debug_log'] ), 0, 100 );
							foreach ( $recent_logs as $entry ) :
								$level_class = 'fpml-debug-level-' . $entry['level'];
								?>
								<tr>
									<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
									<td><span class="<?php echo esc_attr( $level_class ); ?>"><?php echo esc_html( strtoupper( $entry['level'] ) ); ?></span></td>
									<td>
										<?php echo esc_html( $entry['message'] ); ?>
										<?php if ( ! empty( $entry['context'] ) ) : ?>
											<details style="margin-top: 5px;">
												<summary style="cursor: pointer;"><?php esc_html_e( 'Context', 'fp-multilanguage' ); ?></summary>
												<pre style="margin: 5px 0; font-size: 11px; background: #f5f5f5; padding: 10px;"><?php echo esc_html( print_r( $entry['context'], true ) ); ?></pre>
											</details>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( size_format( $entry['memory'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<style>
		.fpml-debug-level-error { color: #d63638; font-weight: bold; }
		.fpml-debug-level-warning { color: #f0b849; font-weight: bold; }
		.fpml-debug-level-info { color: #0073aa; }
		.fpml-debug-level-debug { color: #646970; }
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('#fpml-debug-clear').on('click', function() {
				if (!confirm('<?php esc_html_e( 'Are you sure you want to clear the debug log?', 'fp-multilanguage' ); ?>')) {
					return;
				}

				$.post(ajaxurl, {
					action: 'fpml_debug_clear',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_debug' ) ); ?>'
				}, function() {
					location.reload();
				});
			});

			$('#fpml-debug-export').on('click', function() {
				window.location.href = ajaxurl + '?action=fpml_debug_export&nonce=<?php echo esc_js( wp_create_nonce( 'fpml_debug' ) ); ?>';
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Clear debug log.
	 *
	 * @return void
	 */
	public function ajax_clear_debug_log() {
		check_ajax_referer( 'fpml_debug', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		delete_transient( 'fpml_debug_data_' . get_current_user_id() );
		wp_send_json_success();
	}

	/**
	 * AJAX: Export debug log.
	 *
	 * @return void
	 */
	public function ajax_export_debug_log() {
		check_ajax_referer( 'fpml_debug', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$data = $this->get_debug_data();

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="fpml-debug-' . date( 'Y-m-d-H-i-s' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}
}
