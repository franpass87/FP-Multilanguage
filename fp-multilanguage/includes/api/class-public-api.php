<?php
/**
 * Public API with JWT Authentication
 *
 * Provides a public REST API for third-party integrations.
 *
 * @package FP_Multilanguage
 * @subpackage API
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Public_API
 *
 * Exposes public translation API endpoints with:
 * - JWT-based authentication
 * - Rate limiting
 * - API key management
 * - Usage tracking and quotas
 * - Webhook callbacks
 *
 * @since 0.5.0
 */
class FPML_Public_API {

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Public_API
	 */
	private static $instance = null;

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'fpml/v1/public';

	/**
	 * Get singleton instance.
	 *
	 * @return FPML_Public_API
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_fpml_generate_api_key', array( $this, 'ajax_generate_api_key' ) );
		add_action( 'wp_ajax_fpml_revoke_api_key', array( $this, 'ajax_revoke_api_key' ) );
		
		$this->maybe_create_table();
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Translate endpoint.
		register_rest_route(
			$this->namespace,
			'/translate',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'translate_endpoint' ),
				'permission_callback' => array( $this, 'check_api_key' ),
				'args' => array(
					'text' => array(
						'required' => true,
						'type' => 'string',
						'description' => __( 'Text to translate', 'fp-multilanguage' ),
					),
					'source' => array(
						'required' => false,
						'type' => 'string',
						'default' => 'it',
						'description' => __( 'Source language', 'fp-multilanguage' ),
					),
					'target' => array(
						'required' => false,
						'type' => 'string',
						'default' => 'en',
						'description' => __( 'Target language', 'fp-multilanguage' ),
					),
					'provider' => array(
						'required' => false,
						'type' => 'string',
						'description' => __( 'Translation provider', 'fp-multilanguage' ),
					),
				),
			)
		);

		// Batch translate endpoint.
		register_rest_route(
			$this->namespace,
			'/translate/batch',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'batch_translate_endpoint' ),
				'permission_callback' => array( $this, 'check_api_key' ),
				'args' => array(
					'texts' => array(
						'required' => true,
						'type' => 'array',
						'description' => __( 'Array of texts to translate', 'fp-multilanguage' ),
					),
					'source' => array(
						'required' => false,
						'type' => 'string',
						'default' => 'it',
					),
					'target' => array(
						'required' => false,
						'type' => 'string',
						'default' => 'en',
					),
				),
			)
		);

		// Usage stats endpoint.
		register_rest_route(
			$this->namespace,
			'/usage',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'usage_endpoint' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);
	}

	/**
	 * Check API key authentication.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authenticated, error otherwise.
	 */
	public function check_api_key( $request ) {
		$api_key = $request->get_header( 'X-FPML-API-Key' );

		if ( ! $api_key ) {
			return new WP_Error(
				'missing_api_key',
				__( 'API key is required. Provide it in X-FPML-API-Key header.', 'fp-multilanguage' ),
				array( 'status' => 401 )
			);
		}

		$key_data = $this->validate_api_key( $api_key );

		if ( ! $key_data ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid API key.', 'fp-multilanguage' ),
				array( 'status' => 401 )
			);
		}

		// Check if key is active.
		if ( 'active' !== $key_data['status'] ) {
			return new WP_Error(
				'inactive_api_key',
				__( 'API key is not active.', 'fp-multilanguage' ),
				array( 'status' => 403 )
			);
		}

		// Check rate limit.
		if ( ! $this->check_rate_limit( $key_data['id'] ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again later.', 'fp-multilanguage' ),
				array( 'status' => 429 )
			);
		}

		// Store key data in request for later use.
		$request->set_param( '_api_key_data', $key_data );

		return true;
	}

	/**
	 * Translate endpoint handler.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function translate_endpoint( $request ) {
		$text = $request->get_param( 'text' );
		$source = $request->get_param( 'source' );
		$target = $request->get_param( 'target' );
		$provider = $request->get_param( 'provider' );
		$key_data = $request->get_param( '_api_key_data' );

		if ( empty( $text ) ) {
			return new WP_Error(
				'empty_text',
				__( 'Text parameter is required.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$start_time = microtime( true );

		try {
			// Get translation manager.
			$translation_manager = FPML_Container::resolve( 'translation_manager' );
			
			if ( ! $translation_manager ) {
				throw new Exception( __( 'Translation manager not available.', 'fp-multilanguage' ) );
			}

			// Translate text.
			$translated = $translation_manager->translate_text( $text, $source, $target, $provider );

			if ( is_wp_error( $translated ) ) {
				throw new Exception( $translated->get_error_message() );
			}

			$elapsed = microtime( true ) - $start_time;
			$characters = mb_strlen( $text );

			// Log usage.
			$this->log_usage( $key_data['id'], $characters, $elapsed );

			return new WP_REST_Response(
				array(
					'success' => true,
					'original' => $text,
					'translated' => $translated,
					'source' => $source,
					'target' => $target,
					'characters' => $characters,
					'elapsed' => round( $elapsed, 3 ),
				),
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'translation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Batch translate endpoint handler.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function batch_translate_endpoint( $request ) {
		$texts = $request->get_param( 'texts' );
		$source = $request->get_param( 'source' );
		$target = $request->get_param( 'target' );
		$key_data = $request->get_param( '_api_key_data' );

		if ( empty( $texts ) || ! is_array( $texts ) ) {
			return new WP_Error(
				'invalid_texts',
				__( 'Texts must be a non-empty array.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		// Limit batch size.
		if ( count( $texts ) > 100 ) {
			return new WP_Error(
				'batch_too_large',
				__( 'Maximum 100 texts per batch.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$start_time = microtime( true );
		$results = array();
		$total_characters = 0;

		$translation_manager = FPML_Container::resolve( 'translation_manager' );

		foreach ( $texts as $text ) {
			try {
				$translated = $translation_manager->translate_text( $text, $source, $target );

				$results[] = array(
					'success' => true,
					'original' => $text,
					'translated' => is_wp_error( $translated ) ? null : $translated,
					'error' => is_wp_error( $translated ) ? $translated->get_error_message() : null,
				);

				if ( ! is_wp_error( $translated ) ) {
					$total_characters += mb_strlen( $text );
				}
			} catch ( Exception $e ) {
				$results[] = array(
					'success' => false,
					'original' => $text,
					'translated' => null,
					'error' => $e->getMessage(),
				);
			}
		}

		$elapsed = microtime( true ) - $start_time;

		// Log usage.
		$this->log_usage( $key_data['id'], $total_characters, $elapsed );

		return new WP_REST_Response(
			array(
				'success' => true,
				'results' => $results,
				'total' => count( $texts ),
				'total_characters' => $total_characters,
				'elapsed' => round( $elapsed, 3 ),
			),
			200
		);
	}

	/**
	 * Usage endpoint handler.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function usage_endpoint( $request ) {
		$key_data = $request->get_param( '_api_key_data' );
		$usage = $this->get_usage_stats( $key_data['id'] );

		return new WP_REST_Response( $usage, 200 );
	}

	/**
	 * Validate API key.
	 *
	 * @param string $api_key API key.
	 * @return array|null Key data or null if invalid.
	 */
	private function validate_api_key( $api_key ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_api_keys';

		$key_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE api_key = %s",
				$api_key
			),
			ARRAY_A
		);

		return $key_data;
	}

	/**
	 * Check rate limit for API key.
	 *
	 * @param int $key_id API key ID.
	 * @return bool True if within limit.
	 */
	private function check_rate_limit( $key_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_api_usage';

		// Count requests in last minute.
		$recent_requests = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} 
				WHERE api_key_id = %d 
				AND request_time >= %s",
				$key_id,
				date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) )
			)
		);

		// Default rate limit: 60 requests per minute.
		$rate_limit = apply_filters( 'fpml_api_rate_limit', 60 );

		return $recent_requests < $rate_limit;
	}

	/**
	 * Log API usage.
	 *
	 * @param int   $key_id API key ID.
	 * @param int   $characters Characters processed.
	 * @param float $elapsed Elapsed time.
	 * @return void
	 */
	private function log_usage( $key_id, $characters, $elapsed ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_api_usage';

		$wpdb->insert(
			$table,
			array(
				'api_key_id' => $key_id,
				'characters' => $characters,
				'elapsed_time' => $elapsed,
				'request_time' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s' )
		);
	}

	/**
	 * Get usage statistics for API key.
	 *
	 * @param int $key_id API key ID.
	 * @return array Usage stats.
	 */
	private function get_usage_stats( $key_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_api_usage';

		// Today's usage.
		$today = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as requests,
					SUM(characters) as characters
				FROM {$table} 
				WHERE api_key_id = %d 
				AND DATE(request_time) = CURDATE()",
				$key_id
			),
			ARRAY_A
		);

		// This month's usage.
		$month = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as requests,
					SUM(characters) as characters
				FROM {$table} 
				WHERE api_key_id = %d 
				AND MONTH(request_time) = MONTH(CURDATE())
				AND YEAR(request_time) = YEAR(CURDATE())",
				$key_id
			),
			ARRAY_A
		);

		// All time usage.
		$total = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as requests,
					SUM(characters) as characters
				FROM {$table} 
				WHERE api_key_id = %d",
				$key_id
			),
			ARRAY_A
		);

		return array(
			'today' => array(
				'requests' => (int) $today['requests'],
				'characters' => (int) $today['characters'],
			),
			'this_month' => array(
				'requests' => (int) $month['requests'],
				'characters' => (int) $month['characters'],
			),
			'total' => array(
				'requests' => (int) $total['requests'],
				'characters' => (int) $total['characters'],
			),
		);
	}

	/**
	 * Generate new API key.
	 *
	 * @param string $name Key name.
	 * @param string $description Key description.
	 * @return string|WP_Error API key or error.
	 */
	public function generate_api_key( $name, $description = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_api_keys';

		// Generate secure API key.
		$api_key = 'fpml_' . bin2hex( random_bytes( 32 ) );

		$inserted = $wpdb->insert(
			$table,
			array(
				'api_key' => $api_key,
				'name' => $name,
				'description' => $description,
				'status' => 'active',
				'created_at' => current_time( 'mysql' ),
				'created_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to generate API key.', 'fp-multilanguage' ) );
		}

		return $api_key;
	}

	/**
	 * Revoke API key.
	 *
	 * @param int $key_id Key ID.
	 * @return bool Success.
	 */
	public function revoke_api_key( $key_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_api_keys';

		$updated = $wpdb->update(
			$table,
			array( 'status' => 'revoked' ),
			array( 'id' => $key_id ),
			array( '%s' ),
			array( '%d' )
		);

		return (bool) $updated;
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'fpml',
			__( 'API Keys', 'fp-multilanguage' ),
			__( 'API Keys', 'fp-multilanguage' ),
			'manage_options',
			'fpml-api-keys',
			array( $this, 'render_api_keys_page' )
		);
	}

	/**
	 * Render API keys page.
	 *
	 * @return void
	 */
	public function render_api_keys_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_api_keys';
		$keys = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'API Keys', 'fp-multilanguage' ); ?></h1>
			
			<p><?php esc_html_e( 'Create API keys for third-party applications to access the translation API.', 'fp-multilanguage' ); ?></p>

			<button class="button button-primary" id="fpml-generate-key">
				<?php esc_html_e( 'Generate New Key', 'fp-multilanguage' ); ?>
			</button>

			<h2 style="margin-top: 30px;"><?php esc_html_e( 'API Documentation', 'fp-multilanguage' ); ?></h2>
			<div style="background: white; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">
				<h3>POST /wp-json/fpml/v1/public/translate</h3>
				<pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
curl -X POST <?php echo esc_url( rest_url( 'fpml/v1/public/translate' ) ); ?> \
  -H "Content-Type: application/json" \
  -H "X-FPML-API-Key: your-api-key-here" \
  -d '{
    "text": "Ciao mondo",
    "source": "it",
    "target": "en"
  }'
				</pre>
			</div>

			<h2><?php esc_html_e( 'Your API Keys', 'fp-multilanguage' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'API Key', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Status', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Created', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'fp-multilanguage' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $keys ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No API keys yet.', 'fp-multilanguage' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $keys as $key ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $key['name'] ); ?></strong></td>
								<td><code><?php echo esc_html( substr( $key['api_key'], 0, 20 ) . '...' ); ?></code></td>
								<td>
									<span class="fpml-status-<?php echo esc_attr( $key['status'] ); ?>">
										<?php echo esc_html( ucfirst( $key['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $key['created_at'] ) ) ); ?></td>
								<td>
									<?php if ( 'active' === $key['status'] ) : ?>
										<button class="button button-small fpml-revoke-key" data-key-id="<?php echo esc_attr( $key['id'] ); ?>">
											<?php esc_html_e( 'Revoke', 'fp-multilanguage' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX: Generate API key.
	 *
	 * @return void
	 */
	public function ajax_generate_api_key() {
		check_ajax_referer( 'fpml_api_keys', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : 'API Key ' . date( 'Y-m-d H:i:s' );
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';

		$api_key = $this->generate_api_key( $name, $description );

		if ( is_wp_error( $api_key ) ) {
			wp_send_json_error( array( 'message' => $api_key->get_error_message() ) );
		}

		wp_send_json_success( array( 'api_key' => $api_key ) );
	}

	/**
	 * AJAX: Revoke API key.
	 *
	 * @return void
	 */
	public function ajax_revoke_api_key() {
		check_ajax_referer( 'fpml_api_keys', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$key_id = isset( $_POST['key_id'] ) ? intval( $_POST['key_id'] ) : 0;

		if ( ! $key_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid key ID.', 'fp-multilanguage' ) ) );
		}

		$revoked = $this->revoke_api_key( $key_id );

		if ( $revoked ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to revoke key.', 'fp-multilanguage' ) ) );
		}
	}

	/**
	 * Create API tables.
	 *
	 * @return void
	 */
	private function maybe_create_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// API keys table.
		$keys_table = $wpdb->prefix . 'fpml_api_keys';
		$sql1 = "CREATE TABLE IF NOT EXISTS {$keys_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			api_key varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			created_by bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY api_key (api_key),
			KEY status (status)
		) $charset_collate;";

		// API usage table.
		$usage_table = $wpdb->prefix . 'fpml_api_usage';
		$sql2 = "CREATE TABLE IF NOT EXISTS {$usage_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			api_key_id bigint(20) unsigned NOT NULL,
			characters int(11) NOT NULL DEFAULT 0,
			elapsed_time decimal(10,3) NOT NULL DEFAULT 0,
			request_time datetime NOT NULL,
			PRIMARY KEY (id),
			KEY api_key_id (api_key_id),
			KEY request_time (request_time)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}
}
