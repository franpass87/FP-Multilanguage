<?php
/**
 * Export Import State Exporter - Exports translation state.
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
 * Exports translation state.
 *
 * @since 0.10.0
 */
class StateExporter {
	/**
	 * State collector instance.
	 *
	 * @var StateCollector
	 */
	protected StateCollector $collector;

	/**
	 * CSV handler instance.
	 *
	 * @var CsvHandler
	 */
	protected CsvHandler $csv_handler;

	/**
	 * Constructor.
	 *
	 * @param StateCollector $collector  State collector instance.
	 * @param CsvHandler     $csv_handler CSV handler instance.
	 */
	public function __construct( StateCollector $collector, CsvHandler $csv_handler ) {
		$this->collector = $collector;
		$this->csv_handler = $csv_handler;
	}

	/**
	 * Export translation status state.
	 *
	 * @since 0.10.0
	 *
	 * @param string $format Format: json|csv.
	 *
	 * @return string
	 */
	public function export_translation_state( string $format = 'json' ): string {
		$entries = $this->collector->get_translation_state_entries();
		$format  = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

		if ( 'csv' === $format ) {
			$header = array( 'object_type', 'object_subtype', 'source_id', 'translation_id', 'field', 'status', 'source_url', 'translation_url', 'status_date', 'title' );
			return $this->csv_handler->convert_entries_to_csv( $entries, $header );
		}

		return wp_json_encode( $entries );
	}
}
















