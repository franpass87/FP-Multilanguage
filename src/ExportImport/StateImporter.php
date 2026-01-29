<?php
/**
 * Export Import State Importer - Imports translation state.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\ExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports translation state.
 *
 * @since 0.10.0
 */
class StateImporter {
	/**
	 * CSV handler instance.
	 *
	 * @var CsvHandler
	 */
	protected CsvHandler $csv_handler;

	/**
	 * Constructor.
	 *
	 * @param CsvHandler $csv_handler CSV handler instance.
	 */
	public function __construct( CsvHandler $csv_handler ) {
		$this->csv_handler = $csv_handler;
	}

	/**
	 * Import translation state entries.
	 *
	 * @since 0.10.0
	 *
	 * @param string $payload Raw payload.
	 * @param string $format  json|csv.
	 *
	 * @return int Number of imported rows.
	 */
	public function import_translation_state( string $payload, string $format = 'json' ): int {
		$format  = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';
		$payload = is_string( $payload ) ? trim( $payload ) : '';

		if ( '' === $payload ) {
			return 0;
		}

		if ( 'csv' === $format ) {
			$rows = $this->csv_handler->parse_csv_rows( $payload );
		} else {
			$rows = json_decode( $payload, true );
		}

		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$imported = 0;

		foreach ( $rows as $row ) {
			if ( empty( $row['object_type'] ) || empty( $row['field'] ) || empty( $row['status'] ) ) {
				continue;
			}

			$object_type = sanitize_key( $row['object_type'] );
			$field       = sanitize_key( $row['field'] );
			$status      = sanitize_key( $row['status'] );
			$target_id   = isset( $row['translation_id'] ) ? absint( $row['translation_id'] ) : 0;

			if ( ! $target_id ) {
				continue;
			}

			$meta_key = '_fpml_status_' . $field;

			switch ( $object_type ) {
				case 'post':
				case 'menu':
					update_post_meta( $target_id, $meta_key, $status );
					$imported++;
					break;
				case 'term':
					update_term_meta( $target_id, $meta_key, $status );
					$imported++;
					break;
			}
		}

		return $imported;
	}
}
















