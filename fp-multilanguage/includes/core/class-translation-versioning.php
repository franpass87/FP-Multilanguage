<?php
/**
 * Translation Versioning - Backup and rollback translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version control for translations to allow rollback.
 *
 * @since 0.4.1
 */
class FPML_Translation_Versioning {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Translation_Versioning|null
	 */
	protected static $instance = null;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return FPML_Translation_Versioning
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
	protected function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'fpml_translation_versions';

		// Install table if needed
		$this->maybe_install_table();

		// Hook into translation save
		add_action( 'fpml_post_translated', array( $this, 'save_post_version' ), 10, 4 );
		add_action( 'fpml_term_translated', array( $this, 'save_term_version' ), 10, 3 );
	}

	/**
	 * Install versions table.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function install_table() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(20) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			field varchar(100) NOT NULL,
			old_value longtext NULL,
			new_value longtext NULL,
			translation_provider varchar(50) NULL,
			user_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY object_lookup (object_type, object_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'fpml_versioning_table_version', '1', false );
	}

	/**
	 * Maybe install table if not exists.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	protected function maybe_install_table() {
		$version = get_option( 'fpml_versioning_table_version', '' );

		if ( '' === $version ) {
			$this->install_table();
		}
	}

	/**
	 * Save post translation version.
	 *
	 * @since 0.4.1
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param int    $target_post_id Target post ID.
	 * @param string $field          Field name.
	 * @param array  $data           Translation data.
	 *
	 * @return void
	 */
	public function save_post_version( $source_post_id, $target_post_id, $field, $data ) {
		if ( empty( $target_post_id ) ) {
			return;
		}

		$old_value = '';
		$new_value = isset( $data['translated'] ) ? $data['translated'] : '';

		// Get old value based on field
		switch ( $field ) {
			case 'post_title':
				$old_value = get_the_title( $target_post_id );
				break;
			case 'post_content':
				$post      = get_post( $target_post_id );
				$old_value = $post ? $post->post_content : '';
				break;
			case 'post_excerpt':
				$post      = get_post( $target_post_id );
				$old_value = $post ? $post->post_excerpt : '';
				break;
			default:
				$old_value = get_post_meta( $target_post_id, $field, true );
				break;
		}

		$this->save_version(
			'post',
			$target_post_id,
			$field,
			$old_value,
			$new_value,
			isset( $data['provider'] ) ? $data['provider'] : ''
		);
	}

	/**
	 * Save term translation version.
	 *
	 * @since 0.4.1
	 *
	 * @param int    $source_term_id Source term ID.
	 * @param int    $target_term_id Target term ID.
	 * @param array  $data           Translation data.
	 *
	 * @return void
	 */
	public function save_term_version( $source_term_id, $target_term_id, $data ) {
		if ( empty( $target_term_id ) ) {
			return;
		}

		$term = get_term( $target_term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$field     = isset( $data['field'] ) ? $data['field'] : 'name';
		$old_value = isset( $term->{$field} ) ? $term->{$field} : '';
		$new_value = isset( $data['translated'] ) ? $data['translated'] : '';

		$this->save_version(
			'term',
			$target_term_id,
			$field,
			$old_value,
			$new_value,
			isset( $data['provider'] ) ? $data['provider'] : ''
		);
	}

	/**
	 * Save a version entry.
	 *
	 * @since 0.4.1
	 *
	 * @param string $object_type Object type (post, term, menu, etc).
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field name.
	 * @param string $old_value   Previous value.
	 * @param string $new_value   New value.
	 * @param string $provider    Translation provider.
	 *
	 * @return int|false Insert ID or false on failure.
	 */
	public function save_version( $object_type, $object_id, $field, $old_value, $new_value, $provider = '' ) {
		global $wpdb;

		// Don't save if values are identical
		if ( $old_value === $new_value ) {
			return false;
		}

		$result = $wpdb->insert(
			$this->table,
			array(
				'object_type'          => sanitize_key( $object_type ),
				'object_id'            => absint( $object_id ),
				'field'                => sanitize_key( $field ),
				'old_value'            => $old_value,
				'new_value'            => $new_value,
				'translation_provider' => sanitize_text_field( $provider ),
				'user_id'              => get_current_user_id(),
				'created_at'           => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get version history for an object.
	 *
	 * @since 0.4.1
	 *
	 * @param string   $object_type Object type.
	 * @param int      $object_id   Object ID.
	 * @param string   $field       Optional. Specific field to filter.
	 * @param int      $limit       Maximum versions to return.
	 *
	 * @return array
	 */
	public function get_versions( $object_type, $object_id, $field = '', $limit = 20 ) {
		global $wpdb;

		$where = array(
			$wpdb->prepare( 'object_type = %s', sanitize_key( $object_type ) ),
			$wpdb->prepare( 'object_id = %d', absint( $object_id ) ),
		);

		if ( ! empty( $field ) ) {
			$where[] = $wpdb->prepare( 'field = %s', sanitize_key( $field ) );
		}

		$sql = "SELECT * FROM {$this->table} 
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY created_at DESC 
				LIMIT %d";

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, absint( $limit ) ),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Rollback to a specific version.
	 *
	 * @since 0.4.1
	 *
	 * @param int $version_id Version ID to rollback to.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function rollback( $version_id ) {
		global $wpdb;

		$version = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				absint( $version_id )
			),
			ARRAY_A
		);

		if ( ! $version ) {
			return new WP_Error( 'invalid_version', __( 'Versione non trovata.', 'fp-multilanguage' ) );
		}

		$object_type = $version['object_type'];
		$object_id   = (int) $version['object_id'];
		$field       = $version['field'];
		$old_value   = $version['old_value'];

		// Restore the old value
		switch ( $object_type ) {
			case 'post':
				return $this->rollback_post( $object_id, $field, $old_value );

			case 'term':
				return $this->rollback_term( $object_id, $field, $old_value );

			default:
				return new WP_Error(
					'unsupported_type',
					sprintf( __( 'Tipo di oggetto non supportato: %s', 'fp-multilanguage' ), $object_type )
				);
		}
	}

	/**
	 * Rollback post field.
	 *
	 * @since 0.4.1
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $field     Field name.
	 * @param string $old_value Value to restore.
	 *
	 * @return bool|WP_Error
	 */
	protected function rollback_post( $post_id, $field, $old_value ) {
		// Standard post fields
		if ( in_array( $field, array( 'post_title', 'post_content', 'post_excerpt' ), true ) ) {
			$result = wp_update_post(
				array(
					'ID'    => $post_id,
					$field  => $old_value,
				),
				true
			);

			return is_wp_error( $result ) ? $result : true;
		}

		// Post meta
		$result = update_post_meta( $post_id, $field, $old_value );

		return false !== $result;
	}

	/**
	 * Rollback term field.
	 *
	 * @since 0.4.1
	 *
	 * @param int    $term_id   Term ID.
	 * @param string $field     Field name.
	 * @param string $old_value Value to restore.
	 *
	 * @return bool|WP_Error
	 */
	protected function rollback_term( $term_id, $field, $old_value ) {
		$term = get_term( $term_id );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'invalid_term', __( 'Termine non trovato.', 'fp-multilanguage' ) );
		}

		// Standard term fields
		if ( in_array( $field, array( 'name', 'description' ), true ) ) {
			$result = wp_update_term(
				$term_id,
				$term->taxonomy,
				array(
					$field => $old_value,
				)
			);

			return is_wp_error( $result ) ? $result : true;
		}

		// Term meta
		$result = update_term_meta( $term_id, $field, $old_value );

		return false !== $result;
	}

	/**
	 * Cleanup old versions.
	 *
	 * @since 0.4.1
	 *
	 * @param int $days           Days to retain (default 90).
	 * @param int $keep_per_field Minimum versions to keep per field (default 5).
	 *
	 * @return int Number of deleted rows.
	 */
	public function cleanup_old_versions( $days = 90, $keep_per_field = 5 ) {
		global $wpdb;

		$threshold = date( 'Y-m-d H:i:s', strtotime( '-' . absint( $days ) . ' days' ) );

		// Delete old versions but keep minimum per object+field
		$sql = "DELETE v1 FROM {$this->table} v1
				WHERE v1.created_at < %s
				AND (
					SELECT COUNT(*)
					FROM {$this->table} v2
					WHERE v2.object_type = v1.object_type
					AND v2.object_id = v1.object_id
					AND v2.field = v1.field
					AND v2.created_at >= v1.created_at
				) > %d
				LIMIT 1000";

		$deleted = $wpdb->query(
			$wpdb->prepare( $sql, $threshold, absint( $keep_per_field ) )
		);

		return $deleted ? (int) $deleted : 0;
	}

	/**
	 * Get statistics about version history.
	 *
	 * @since 0.4.1
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;

		$total_versions = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );

		$by_type = $wpdb->get_results(
			"SELECT object_type, COUNT(*) as count 
			 FROM {$this->table} 
			 GROUP BY object_type",
			ARRAY_A
		);

		$oldest = $wpdb->get_var(
			"SELECT created_at FROM {$this->table} ORDER BY created_at ASC LIMIT 1"
		);

		return array(
			'total_versions' => (int) $total_versions,
			'by_type'        => $by_type ? $by_type : array(),
			'oldest_version' => $oldest ? $oldest : null,
		);
	}
}
