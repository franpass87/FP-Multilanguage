<?php
/**
 * Translation Versioning Version Retriever - Retrieves version history.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Core\Versioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieves version history.
 *
 * @since 0.10.0
 */
class VersionRetriever {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Constructor.
	 *
	 * @param string $table Table name.
	 */
	public function __construct( string $table ) {
		$this->table = $table;
	}

	/**
	 * Get version history for an object.
	 *
	 * @since 0.10.0
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Optional. Specific field to filter.
	 * @param int    $limit       Maximum versions to return.
	 * @return array
	 */
	public function get_versions( string $object_type, int $object_id, string $field = '', int $limit = 20 ): array {
		global $wpdb;

		$object_id = absint( $object_id );
		if ( 0 === $object_id ) {
			return array();
		}

		$where = array(
			$wpdb->prepare( 'object_type = %s', sanitize_key( $object_type ) ),
			$wpdb->prepare( 'object_id = %d', $object_id ),
		);

		if ( ! empty( $field ) ) {
			$where[] = $wpdb->prepare( 'field = %s', sanitize_key( $field ) );
		}

		$sql = "SELECT * FROM {$this->table} 
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY created_at DESC 
				LIMIT " . absint( $limit );

		$results = $wpdb->get_results(
			$sql,
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get a specific version by ID.
	 *
	 * @since 0.10.0
	 *
	 * @param int $version_id Version ID.
	 * @return array|null
	 */
	public function get_version( int $version_id ): ?array {
		global $wpdb;

		$version = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				absint( $version_id )
			),
			ARRAY_A
		);

		return $version ? $version : null;
	}
}















