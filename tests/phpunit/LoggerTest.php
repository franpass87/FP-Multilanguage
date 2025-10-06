<?php
/**
 * Logger tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test logger functionality.
 */
final class LoggerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		// Clear logs before each test
		FPML_Logger::instance()->clear();
	}

	public function test_logger_instance_returns_singleton(): void {
		$logger1 = FPML_Logger::instance();
		$logger2 = FPML_Logger::instance();

		$this->assertSame( $logger1, $logger2 );
	}

	public function test_log_stores_entry(): void {
		$logger = FPML_Logger::instance();

		$logger->log( 'info', 'Test message', array( 'test' => true ) );

		$logs = $logger->get_logs( 1 );

		$this->assertCount( 1, $logs );
		$this->assertEquals( 'Test message', $logs[0]['message'] );
		$this->assertEquals( 'info', $logs[0]['level'] );
	}

	public function test_log_normalizes_level(): void {
		$logger = FPML_Logger::instance();

		// Try invalid level
		$logger->log( 'INVALID', 'Test', array() );

		$logs = $logger->get_logs( 1 );

		// Should default to 'info'
		$this->assertEquals( 'info', $logs[0]['level'] );
	}

	public function test_log_accepts_valid_levels(): void {
		$logger = FPML_Logger::instance();

		$valid_levels = array( 'info', 'warn', 'error' );

		foreach ( $valid_levels as $level ) {
			$logger->log( $level, "Test $level", array() );
		}

		$logs = $logger->get_logs( 3 );

		$this->assertCount( 3, $logs );
	}

	public function test_get_logs_respects_limit(): void {
		$logger = FPML_Logger::instance();

		// Add 10 logs
		for ( $i = 0; $i < 10; $i++ ) {
			$logger->log( 'info', "Message $i", array() );
		}

		$logs = $logger->get_logs( 5 );

		$this->assertCount( 5, $logs );
	}

	public function test_clear_removes_all_logs(): void {
		$logger = FPML_Logger::instance();

		$logger->log( 'info', 'Test', array() );
		$logger->clear();

		$logs = $logger->get_logs( 100 );

		$this->assertCount( 0, $logs );
	}

	public function test_get_stats_counts_by_level(): void {
		$logger = FPML_Logger::instance();

		$logger->log( 'info', 'Info 1', array() );
		$logger->log( 'info', 'Info 2', array() );
		$logger->log( 'error', 'Error 1', array() );

		$stats = $logger->get_stats();

		$this->assertEquals( 2, $stats['info'] );
		$this->assertEquals( 1, $stats['error'] );
	}

	public function test_log_translation_start_creates_structured_log(): void {
		$logger = FPML_Logger::instance();

		$logger->log_translation_start( 123, 'openai', 500 );

		$logs = $logger->get_logs( 1 );

		$this->assertArrayHasKey( 'context', $logs[0] );
		$this->assertEquals( 'translation.start', $logs[0]['context']['event'] );
		$this->assertEquals( 123, $logs[0]['context']['job_id'] );
		$this->assertEquals( 'openai', $logs[0]['context']['provider'] );
		$this->assertEquals( 500, $logs[0]['context']['characters'] );
	}

	public function test_log_translation_complete_creates_structured_log(): void {
		$logger = FPML_Logger::instance();

		$logger->log_translation_complete( 456, 1200, 0.0012 );

		$logs = $logger->get_logs( 1 );

		$this->assertEquals( 'translation.complete', $logs[0]['context']['event'] );
		$this->assertEquals( 456, $logs[0]['context']['job_id'] );
		$this->assertEquals( 1200, $logs[0]['context']['duration'] );
		$this->assertEquals( 0.0012, $logs[0]['context']['cost'] );
	}

	public function test_log_api_error_creates_structured_log(): void {
		$logger = FPML_Logger::instance();

		$logger->log_api_error( 'deepl', 'auth_error', 'Invalid API key', 401 );

		$logs = $logger->get_logs( 1 );

		$this->assertEquals( 'error', $logs[0]['level'] );
		$this->assertEquals( 'api.error', $logs[0]['context']['event'] );
		$this->assertEquals( 'deepl', $logs[0]['context']['provider'] );
		$this->assertEquals( 401, $logs[0]['context']['http_status'] );
	}

	public function test_get_logs_by_event_filters_correctly(): void {
		$logger = FPML_Logger::instance();

		$logger->log_translation_start( 1, 'openai', 100 );
		$logger->log_translation_complete( 2, 1000, 0.001 );
		$logger->log_api_error( 'deepl', 'error', 'test', 500 );

		$start_logs = $logger->get_logs_by_event( 'translation.start', 10 );
		$error_logs = $logger->get_logs_by_event( 'api.error', 10 );

		$this->assertCount( 1, $start_logs );
		$this->assertCount( 1, $error_logs );
	}

	public function test_import_logs_adds_entries(): void {
		$logger = FPML_Logger::instance();

		$entries = array(
			array(
				'message' => 'Imported log',
				'level'   => 'info',
				'context' => array( 'test' => true ),
			),
		);

		$count = $logger->import_logs( $entries );

		$this->assertEquals( 1, $count );

		$logs = $logger->get_logs( 1 );
		$this->assertEquals( 'Imported log', $logs[0]['message'] );
	}

	public function test_logger_respects_max_entries(): void {
		$logger = FPML_Logger::instance();

		// Add more than max entries (200 default)
		for ( $i = 0; $i < 250; $i++ ) {
			$logger->log( 'info', "Message $i", array() );
		}

		$logs = $logger->get_logs( 300 );

		// Should only keep max_entries
		$this->assertLessThanOrEqual( 200, count( $logs ) );
	}
}
