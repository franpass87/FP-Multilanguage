<?php
/**
 * Export Import Log Importer - Imports log entries.
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
 * Imports log entries.
 *
 * @since 0.10.0
 */
class LogImporter {
	/**
	 * Logger instance.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * CSV handler instance.
	 *
	 * @var CsvHandler
	 */
	protected CsvHandler $csv_handler;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Logger $logger     Logger instance.
	 * @param CsvHandler   $csv_handler CSV handler instance.
	 */
	public function __construct( $logger, CsvHandler $csv_handler ) {
		$this->logger = $logger;
		$this->csv_handler = $csv_handler;
	}

	/**
	 * Import log entries.
	 *
	 * @since 0.10.0
	 *
	 * @param string $payload Payload body.
	 * @param string $format  json|csv.
	 *
	 * @return int Imported count.
	 */
	public function import_logs( string $payload, string $format = 'json' ): int {
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

		$normalized = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['message'] ) ) {
				continue;
			}

			$normalized[] = array(
				'timestamp' => isset( $row['timestamp'] ) ? sanitize_text_field( $row['timestamp'] ) : current_time( 'mysql', true ),
				'level'     => isset( $row['level'] ) ? sanitize_key( $row['level'] ) : 'info',
				'message'   => sanitize_textarea_field( $row['message'] ),
				'context'   => isset( $row['context'] ) ? $this->normalize_context_column( $row['context'] ) : array(),
			);
		}

		if ( empty( $normalized ) ) {
			return 0;
		}

		return $this->logger->import_logs( $normalized );
	}

	/**
	 * Normalize context column from CSV imports.
	 *
	 * @since 0.10.0
	 *
	 * @param mixed $context Raw context.
	 *
	 * @return array
	 */
	protected function normalize_context_column( $context ): array {
		if ( empty( $context ) ) {
			return array();
		}

		if ( is_array( $context ) ) {
			return $context;
		}

		$decoded = json_decode( (string) $context, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		return array();
	}
}
















