<?php
/**
 * Export and import helpers for FP Multilanguage.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.2.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\ExportImport\CsvHandler;
use FP\Multilanguage\ExportImport\TextCleaner;
use FP\Multilanguage\ExportImport\StateCollector;
use FP\Multilanguage\ExportImport\StateExporter;
use FP\Multilanguage\ExportImport\StateImporter;
use FP\Multilanguage\ExportImport\LogExporter;
use FP\Multilanguage\ExportImport\LogImporter;
use FP\Multilanguage\ExportImport\SandboxManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle structured export/import routines and sandbox previews.
 *
 * @since 0.2.0
 * @since 0.10.0 Refactored to use modular components.
 */
class ExportImport {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Logger reference.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * State exporter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var StateExporter
	 */
	protected StateExporter $state_exporter;

	/**
	 * State importer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var StateImporter
	 */
	protected StateImporter $state_importer;

	/**
	 * Log exporter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var LogExporter
	 */
	protected LogExporter $log_exporter;

	/**
	 * Log importer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var LogImporter
	 */
	protected LogImporter $log_importer;

	/**
	 * Sandbox manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var SandboxManager
	 */
	protected SandboxManager $sandbox_manager;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.2.0
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		$container = $this->getContainer();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : \FPML_fpml_get_logger();

		// Initialize modules
		$csv_handler = new CsvHandler();
		$text_cleaner = new TextCleaner();
		$state_collector = new StateCollector( $text_cleaner );
		$this->state_exporter = new StateExporter( $state_collector, $csv_handler );
		$this->state_importer = new StateImporter( $csv_handler );
		$this->log_exporter = new LogExporter( $this->logger, $csv_handler );
		$this->log_importer = new LogImporter( $this->logger, $csv_handler );
		$this->sandbox_manager = new SandboxManager( $text_cleaner );
	}

	/**
	 * Export translation status state.
	 *
	 * @since 0.2.0
	 *
	 * @param string $format Format: json|csv.
	 *
	 * @return string
	 */
	public function export_translation_state( string $format = 'json' ): string {
		return $this->state_exporter->export_translation_state( $format );
	}

	/**
	 * Import translation state entries.
	 *
	 * @since 0.2.0
	 *
	 * @param string $payload Raw payload.
	 * @param string $format  json|csv.
	 *
	 * @return int Number of imported rows.
	 */
	public function import_translation_state( string $payload, string $format = 'json' ): int {
		return $this->state_importer->import_translation_state( $payload, $format );
	}

	/**
	 * Export logger entries.
	 *
	 * @since 0.2.0
	 *
	 * @param string $format json|csv.
	 *
	 * @return string
	 */
	public function export_logs( string $format = 'json' ): string {
		return $this->log_exporter->export_logs( $format );
	}

	/**
	 * Import log entries.
	 *
	 * @since 0.2.0
	 *
	 * @param string $payload Payload body.
	 * @param string $format  json|csv.
	 *
	 * @return int Imported count.
	 */
	public function import_logs( string $payload, string $format = 'json' ): int {
		return $this->log_importer->import_logs( $payload, $format );
	}

	/**
	 * Retrieve stored sandbox previews.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function get_sandbox_previews(): array {
		return $this->sandbox_manager->get_sandbox_previews();
	}

	/**
	 * Record a sandbox preview entry.
	 *
	 * @since 0.2.0
	 *
	 * @param array $data Preview data.
	 *
	 * @return void
	 */
	public function record_sandbox_preview( array $data ): void {
		$this->sandbox_manager->record_sandbox_preview( $data );
	}

	/**
	 * Clear sandbox previews.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function clear_sandbox_previews(): void {
		$this->sandbox_manager->clear_sandbox_previews();
	}
}
