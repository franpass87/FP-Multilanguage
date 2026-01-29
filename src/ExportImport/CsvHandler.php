<?php
/**
 * Export Import CSV Handler - Handles CSV conversion and parsing.
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
 * Handles CSV conversion and parsing.
 *
 * @since 0.10.0
 */
class CsvHandler {
	/**
	 * Convert entries to CSV string.
	 *
	 * @since 0.10.0
	 *
	 * @param array $entries Rows to export.
	 * @param array $header  Optional header row. If not provided, uses keys from first entry.
	 *
	 * @return string
	 */
	public function convert_entries_to_csv( array $entries, ?array $header = null ): string {
		$rows = array();

		if ( null === $header && ! empty( $entries ) ) {
			$header = array_keys( $entries[0] );
		}

		if ( $header ) {
			$rows[] = $header;
		}

		foreach ( $entries as $entry ) {
			$row = array();
			$keys = $header ? $header : array_keys( $entry );
			foreach ( $keys as $key ) {
				$row[] = isset( $entry[ $key ] ) ? $entry[ $key ] : '';
			}
			$rows[] = $row;
		}

		return $this->convert_rows_to_csv( $rows );
	}

	/**
	 * Convert rows to CSV output.
	 *
	 * @since 0.10.0
	 *
	 * @param array $rows Rows of data.
	 *
	 * @return string
	 */
	public function convert_rows_to_csv( array $rows ): string {
		$buffer = fopen( 'php://temp', 'w+' );

		foreach ( $rows as $row ) {
			fputcsv( $buffer, $row );
		}

		rewind( $buffer );
		$csv = stream_get_contents( $buffer );
		fclose( $buffer );

		return $csv;
	}

	/**
	 * Parse CSV rows into associative arrays.
	 *
	 * @since 0.10.0
	 *
	 * @param string $csv CSV payload.
	 *
	 * @return array
	 */
	public function parse_csv_rows( string $csv ): array {
		$buffer = fopen( 'php://temp', 'w+' );

		if ( false === $buffer ) {
			return array();
		}

		fwrite( $buffer, $csv );
		rewind( $buffer );

		$header = array();
		$rows   = array();

		while ( ( $columns = fgetcsv( $buffer ) ) !== false ) {
			if ( empty( $columns ) || ( 1 === count( $columns ) && '' === trim( (string) $columns[0] ) ) ) {
				continue;
			}

			if ( empty( $header ) ) {
				$header = array_map( 'sanitize_key', $columns );
				continue;
			}

			$row = array();

			foreach ( $header as $position => $key ) {
				$row[ $key ] = isset( $columns[ $position ] ) ? $columns[ $position ] : '';
			}

			$rows[] = $row;
		}

		fclose( $buffer );

		return $rows;
	}
}
















