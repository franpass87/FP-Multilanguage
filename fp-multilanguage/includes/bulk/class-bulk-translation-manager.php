<?php
/**
 * Bulk Translation Manager
 *
 * Manages bulk translation operations with cost estimation and progress tracking.
 *
 * @package FP_Multilanguage
 * @subpackage Bulk
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Bulk_Translation_Manager
 *
 * Handles bulk translation operations with advanced features:
 * - Cost estimation before execution
 * - Real-time progress tracking
 * - Batch processing with queue
 * - Error handling and retry logic
 * - WordPress admin integration
 *
 * @since 0.5.0
 */
class FPML_Bulk_Translation_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Bulk_Translation_Manager
	 */
	private static $instance = null;

	/**
	 * Batch size for processing.
	 *
	 * @var int
	 */
	private $batch_size = 10;

	/**
	 * Get singleton instance.
	 *
	 * @return FPML_Bulk_Translation_Manager
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
		add_action( 'admin_init', array( $this, 'register_bulk_actions' ) );
		add_action( 'wp_ajax_fpml_bulk_estimate', array( $this, 'ajax_estimate_cost' ) );
		add_action( 'wp_ajax_fpml_bulk_translate', array( $this, 'ajax_start_bulk_translation' ) );
		add_action( 'wp_ajax_fpml_bulk_progress', array( $this, 'ajax_get_progress' ) );
		add_action( 'fpml_process_bulk_batch', array( $this, 'process_batch' ), 10, 2 );
	}

	/**
	 * Register bulk actions in WordPress admin.
	 *
	 * @return void
	 */
	public function register_bulk_actions() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		foreach ( $post_types as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'add_bulk_action' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Add bulk translation action to dropdown.
	 *
	 * @param array $actions Existing actions.
	 * @return array Modified actions.
	 */
	public function add_bulk_action( $actions ) {
		$actions['fpml_bulk_translate'] = __( 'Translate to English', 'fp-multilanguage' );
		return $actions;
	}

	/**
	 * Handle bulk translation action.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action Action name.
	 * @param array  $post_ids Selected post IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'fpml_bulk_translate' !== $action ) {
			return $redirect_to;
		}

		if ( empty( $post_ids ) ) {
			return $redirect_to;
		}

		// Create bulk job.
		$job_id = $this->create_bulk_job( $post_ids );

		if ( is_wp_error( $job_id ) ) {
			return add_query_arg( 'fpml_bulk_error', urlencode( $job_id->get_error_message() ), $redirect_to );
		}

		// Redirect to progress page.
		return admin_url( 'admin.php?page=fpml-bulk-progress&job_id=' . $job_id );
	}

	/**
	 * Estimate translation cost for posts.
	 *
	 * @param array $post_ids Post IDs to estimate.
	 * @return array|WP_Error Estimation data or error.
	 */
	public function estimate_cost( $post_ids ) {
		if ( empty( $post_ids ) ) {
			return new WP_Error( 'empty_posts', __( 'No posts provided for estimation.', 'fp-multilanguage' ) );
		}

		$total_characters = 0;
		$posts_data = array();
		$cost_estimator = FPML_Container::resolve( 'cost_estimator' );
		$settings = FPML_Container::resolve( 'settings' );
		$provider = $settings->get( 'translation_provider', 'openai' );

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			
			if ( ! $post ) {
				continue;
			}

			// Calculate characters for title and content.
			$title_chars = mb_strlen( $post->post_title );
			$content_chars = mb_strlen( wp_strip_all_tags( $post->post_content ) );
			$excerpt_chars = mb_strlen( $post->post_excerpt );
			
			$post_total = $title_chars + $content_chars + $excerpt_chars;
			$total_characters += $post_total;

			$posts_data[] = array(
				'id' => $post_id,
				'title' => $post->post_title,
				'characters' => $post_total,
			);
		}

		// Estimate cost.
		$estimated_cost = $cost_estimator->estimate_cost( $total_characters, $provider );

		// Estimate time (roughly 1 second per 100 characters + API overhead).
		$estimated_seconds = ceil( $total_characters / 100 ) + ( count( $post_ids ) * 2 );
		$estimated_time = $this->format_duration( $estimated_seconds );

		return array(
			'total_posts' => count( $posts_data ),
			'total_characters' => $total_characters,
			'estimated_cost' => $estimated_cost,
			'estimated_time' => $estimated_time,
			'estimated_seconds' => $estimated_seconds,
			'provider' => $provider,
			'posts' => $posts_data,
		);
	}

	/**
	 * Create a bulk translation job.
	 *
	 * @param array $post_ids Post IDs to translate.
	 * @param array $options Job options.
	 * @return int|WP_Error Job ID or error.
	 */
	public function create_bulk_job( $post_ids, $options = array() ) {
		if ( empty( $post_ids ) ) {
			return new WP_Error( 'empty_posts', __( 'No posts provided.', 'fp-multilanguage' ) );
		}

		$defaults = array(
			'source_lang' => 'it',
			'target_lang' => 'en',
			'translate_title' => true,
			'translate_content' => true,
			'translate_excerpt' => true,
			'translate_meta' => true,
		);

		$options = wp_parse_args( $options, $defaults );

		// Create job record in database.
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_bulk_jobs';

		// Create table if not exists.
		$this->maybe_create_table();

		$inserted = $wpdb->insert(
			$table,
			array(
				'post_ids' => wp_json_encode( $post_ids ),
				'total_posts' => count( $post_ids ),
				'processed_posts' => 0,
				'failed_posts' => 0,
				'status' => 'pending',
				'options' => wp_json_encode( $options ),
				'created_at' => current_time( 'mysql' ),
				'user_id' => get_current_user_id(),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to create bulk job.', 'fp-multilanguage' ) );
		}

		$job_id = $wpdb->insert_id;

		// Schedule first batch.
		$this->schedule_next_batch( $job_id );

		// Fire action hook.
		do_action( 'fpml_bulk_job_created', $job_id, $post_ids, $options );

		return $job_id;
	}

	/**
	 * Get bulk job status.
	 *
	 * @param int $job_id Job ID.
	 * @return array|null Job data or null if not found.
	 */
	public function get_job_status( $job_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_bulk_jobs';

		$job = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ),
			ARRAY_A
		);

		if ( ! $job ) {
			return null;
		}

		// Decode JSON fields.
		$job['post_ids'] = json_decode( $job['post_ids'], true );
		$job['options'] = json_decode( $job['options'], true );
		$job['errors'] = ! empty( $job['errors'] ) ? json_decode( $job['errors'], true ) : array();

		// Calculate progress.
		$job['progress'] = $job['total_posts'] > 0 
			? round( ( $job['processed_posts'] / $job['total_posts'] ) * 100, 2 ) 
			: 0;

		return $job;
	}

	/**
	 * Schedule next batch for processing.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function schedule_next_batch( $job_id ) {
		$job = $this->get_job_status( $job_id );

		if ( ! $job || 'processing' !== $job['status'] && 'pending' !== $job['status'] ) {
			return;
		}

		// Get next batch of posts.
		$processed = $job['processed_posts'] + $job['failed_posts'];
		$remaining = array_slice( $job['post_ids'], $processed, $this->batch_size );

		if ( empty( $remaining ) ) {
			$this->complete_job( $job_id );
			return;
		}

		// Update status to processing.
		if ( 'pending' === $job['status'] ) {
			global $wpdb;
			$table = $wpdb->prefix . 'fpml_bulk_jobs';
			$wpdb->update(
				$table,
				array( 'status' => 'processing', 'started_at' => current_time( 'mysql' ) ),
				array( 'id' => $job_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// Schedule batch processing.
		if ( ! wp_next_scheduled( 'fpml_process_bulk_batch', array( $job_id, $remaining ) ) ) {
			wp_schedule_single_event( time(), 'fpml_process_bulk_batch', array( $job_id, $remaining ) );
		}
	}

	/**
	 * Process a batch of posts.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $post_ids Post IDs in this batch.
	 * @return void
	 */
	public function process_batch( $job_id, $post_ids ) {
		$job = $this->get_job_status( $job_id );

		if ( ! $job ) {
			return;
		}

		$translation_manager = FPML_Container::resolve( 'translation_manager' );
		$job_enqueuer = FPML_Container::resolve( 'job_enqueuer' );
		$errors = ! empty( $job['errors'] ) ? $job['errors'] : array();

		foreach ( $post_ids as $post_id ) {
			try {
				// Enqueue translation job.
				$result = $job_enqueuer->enqueue_post( $post_id, $job['options'] );

				if ( is_wp_error( $result ) ) {
					$errors[] = array(
						'post_id' => $post_id,
						'error' => $result->get_error_message(),
						'time' => current_time( 'mysql' ),
					);
					$this->increment_failed( $job_id );
				} else {
					$this->increment_processed( $job_id );
				}
			} catch ( Exception $e ) {
				$errors[] = array(
					'post_id' => $post_id,
					'error' => $e->getMessage(),
					'time' => current_time( 'mysql' ),
				);
				$this->increment_failed( $job_id );
			}
		}

		// Update errors if any.
		if ( ! empty( $errors ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'fpml_bulk_jobs';
			$wpdb->update(
				$table,
				array( 'errors' => wp_json_encode( $errors ) ),
				array( 'id' => $job_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Schedule next batch.
		$this->schedule_next_batch( $job_id );
	}

	/**
	 * Increment processed count.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function increment_processed( $job_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_bulk_jobs';
		$wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET processed_posts = processed_posts + 1 WHERE id = %d", $job_id )
		);
	}

	/**
	 * Increment failed count.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function increment_failed( $job_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_bulk_jobs';
		$wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET failed_posts = failed_posts + 1 WHERE id = %d", $job_id )
		);
	}

	/**
	 * Complete bulk job.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function complete_job( $job_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_bulk_jobs';

		$wpdb->update(
			$table,
			array(
				'status' => 'completed',
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Fire completion hook.
		do_action( 'fpml_bulk_job_completed', $job_id );

		// Send notification if configured.
		$this->send_completion_notification( $job_id );
	}

	/**
	 * Send completion notification.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function send_completion_notification( $job_id ) {
		$job = $this->get_job_status( $job_id );

		if ( ! $job ) {
			return;
		}

		$user = get_user_by( 'id', $job['user_id'] );
		
		if ( ! $user ) {
			return;
		}

		$subject = sprintf(
			__( '[FP Multilanguage] Bulk translation completed - %d posts', 'fp-multilanguage' ),
			$job['total_posts']
		);

		$message = sprintf(
			__( "Your bulk translation job has been completed.\n\nTotal posts: %d\nSuccessfully translated: %d\nFailed: %d\n\nView details: %s", 'fp-multilanguage' ),
			$job['total_posts'],
			$job['processed_posts'],
			$job['failed_posts'],
			admin_url( 'admin.php?page=fpml-bulk-progress&job_id=' . $job_id )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * AJAX: Estimate cost.
	 *
	 * @return void
	 */
	public function ajax_estimate_cost() {
		check_ajax_referer( 'fpml_bulk_translate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : array();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No posts selected.', 'fp-multilanguage' ) ) );
		}

		$estimation = $this->estimate_cost( $post_ids );

		if ( is_wp_error( $estimation ) ) {
			wp_send_json_error( array( 'message' => $estimation->get_error_message() ) );
		}

		wp_send_json_success( $estimation );
	}

	/**
	 * AJAX: Start bulk translation.
	 *
	 * @return void
	 */
	public function ajax_start_bulk_translation() {
		check_ajax_referer( 'fpml_bulk_translate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : array();
		$options = isset( $_POST['options'] ) ? $_POST['options'] : array();

		$job_id = $this->create_bulk_job( $post_ids, $options );

		if ( is_wp_error( $job_id ) ) {
			wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'job_id' => $job_id,
			'redirect_url' => admin_url( 'admin.php?page=fpml-bulk-progress&job_id=' . $job_id ),
		) );
	}

	/**
	 * AJAX: Get progress.
	 *
	 * @return void
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'fpml_bulk_translate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$job_id = isset( $_POST['job_id'] ) ? intval( $_POST['job_id'] ) : 0;

		if ( ! $job_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'fp-multilanguage' ) ) );
		}

		$job = $this->get_job_status( $job_id );

		if ( ! $job ) {
			wp_send_json_error( array( 'message' => __( 'Job not found.', 'fp-multilanguage' ) ) );
		}

		wp_send_json_success( $job );
	}

	/**
	 * Format duration in human-readable format.
	 *
	 * @param int $seconds Seconds.
	 * @return string Formatted duration.
	 */
	private function format_duration( $seconds ) {
		if ( $seconds < 60 ) {
			return sprintf( __( '%d seconds', 'fp-multilanguage' ), $seconds );
		} elseif ( $seconds < 3600 ) {
			$minutes = ceil( $seconds / 60 );
			return sprintf( __( '%d minutes', 'fp-multilanguage' ), $minutes );
		} else {
			$hours = floor( $seconds / 3600 );
			$minutes = ceil( ( $seconds % 3600 ) / 60 );
			return sprintf( __( '%d hours %d minutes', 'fp-multilanguage' ), $hours, $minutes );
		}
	}

	/**
	 * Maybe create bulk jobs table.
	 *
	 * @return void
	 */
	private function maybe_create_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_bulk_jobs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_ids longtext NOT NULL,
			total_posts int(11) NOT NULL DEFAULT 0,
			processed_posts int(11) NOT NULL DEFAULT 0,
			failed_posts int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			options longtext,
			errors longtext,
			created_at datetime NOT NULL,
			started_at datetime,
			completed_at datetime,
			user_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
