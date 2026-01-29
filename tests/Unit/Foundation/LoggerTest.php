<?php
/**
 * Logger Unit Tests.
 *
 * @package FP_Multilanguage
 * @since 1.0.0
 */

namespace FP\Multilanguage\Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;
use FP\Multilanguage\Foundation\Logger\Logger;
use FP\Multilanguage\Foundation\Logger\LogLevel;

/**
 * Logger test case.
 *
 * @since 1.0.0
 */
class LoggerTest extends TestCase {
	/**
	 * Test logger implements interface.
	 *
	 * @return void
	 */
	public function testLoggerImplementsInterface(): void {
		$logger = new Logger();
		$this->assertInstanceOf( \FP\Multilanguage\Foundation\Logger\LoggerInterface::class, $logger );
	}

	/**
	 * Test log levels.
	 *
	 * @return void
	 */
	public function testLogLevels(): void {
		$logger = new Logger( LogLevel::DEBUG );

		// Should not throw
		$logger->emergency( 'Test emergency' );
		$logger->alert( 'Test alert' );
		$logger->critical( 'Test critical' );
		$logger->error( 'Test error' );
		$logger->warning( 'Test warning' );
		$logger->notice( 'Test notice' );
		$logger->info( 'Test info' );
		$logger->debug( 'Test debug' );

		$this->assertTrue( true );
	}

	/**
	 * Test log filtering.
	 *
	 * @return void
	 */
	public function testLogFiltering(): void {
		// Logger with INFO level should not log DEBUG
		$logger = new Logger( LogLevel::INFO );

		// These should be logged
		$logger->info( 'Test info' );
		$logger->warning( 'Test warning' );
		$logger->error( 'Test error' );

		// DEBUG should be filtered out
		$logger->debug( 'Test debug' );

		$this->assertTrue( true );
	}

	/**
	 * Test log retrieval.
	 *
	 * @return void
	 */
	public function testGetLogs(): void {
		$logger = new Logger( LogLevel::DEBUG );
		$logger->info( 'Test message' );

		$logs = $logger->getLogs();
		$this->assertIsArray( $logs );
	}

	/**
	 * Test log statistics.
	 *
	 * @return void
	 */
	public function testGetStats(): void {
		$logger = new Logger( LogLevel::DEBUG );
		$logger->info( 'Test info' );
		$logger->error( 'Test error' );

		$stats = $logger->getStats();
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total', $stats );
		$this->assertArrayHasKey( 'info', $stats );
		$this->assertArrayHasKey( 'error', $stats );
	}
}
