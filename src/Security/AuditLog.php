<?php
/**
 * Audit Log System.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuditLog {
	protected static $instance = null;
	protected $table;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'FPML_audit_log';
		$this->maybe_install();
		$this->setup_hooks();
	}

	protected function maybe_install() {
		if ( get_option( 'fpml_audit_installed' ) ) {
			return;
		}

		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			action varchar(50) NOT NULL,
			object_type varchar(50) NOT NULL,
			object_id bigint(20) unsigned DEFAULT NULL,
			details text DEFAULT NULL,
			ip_address varchar(45) NOT NULL,
			timestamp datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_lookup (user_id),
			KEY action_lookup (action, timestamp),
			KEY object_lookup (object_type, object_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'fpml_audit_installed', '1', false );
	}

	protected function setup_hooks() {
		add_action( 'fpml_settings_saved', array( $this, 'log_settings_change' ) );
		add_action( 'fpml_translation_created', array( $this, 'log_translation_created' ), 10, 2 );
		add_action( 'fpml_queue_processed', array( $this, 'log_queue_processed' ) );
	}

	public function log( $action, $object_type = '', $object_id = null, $details = '' ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$ip      = $this->get_client_ip();

		$wpdb->insert(
			$this->table,
			array(
				'user_id'     => $user_id,
				'action'      => sanitize_key( $action ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => $object_id ? absint( $object_id ) : null,
				'details'     => is_array( $details ) ? wp_json_encode( $details ) : sanitize_text_field( $details ),
				'ip_address'  => $ip,
				'timestamp'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	protected function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '';
	}

	public function log_settings_change() {
		$this->log( 'settings_updated', 'settings' );
	}

	public function log_translation_created( $translation_id, $source_id ) {
		$this->log( 'translation_created', 'post', $translation_id, "Source: {$source_id}" );
	}

	public function log_queue_processed() {
		$this->log( 'queue_processed', 'queue' );
	}
}

