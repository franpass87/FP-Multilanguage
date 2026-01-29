<?php
/**
 * Export Import Log Exporter - Exports logger entries.
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
 * Exports logger entries.
 *
 * @since 0.10.0
 */
class LogExporter {
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
	 * Export logger entries.
	 *
	 * @since 0.10.0
	 *
	 * @param string $format json|csv.
	 *
	 * @return string
	 */
	public function export_logs( string $format = 'json' ): string {
		$format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';
		$logs   = $this->logger->get_logs( 200 );

		if ( 'csv' === $format ) {
			$rows   = array();
			$rows[] = array( 'timestamp', 'level', 'message', 'context' );

			foreach ( $logs as $log ) {
				$rows[] = array(
					isset( $log['timestamp'] ) ? $log['timestamp'] : '',
					isset( $log['level'] ) ? $log['level'] : 'info',
					isset( $log['message'] ) ? $log['message'] : '',
					isset( $log['context'] ) ? wp_json_encode( $log['context'] ) : '',
				);
			}

			return $this->csv_handler->convert_rows_to_csv( $rows );
		}

		return wp_json_encode( $logs );
	}
}
















