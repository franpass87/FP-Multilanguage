#!/usr/bin/env php
<?php
/**
 * Real-time monitoring for FP Multilanguage.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

// Load WordPress
$wp_load_paths = array(
	__DIR__ . '/../../../../wp-load.php',
	__DIR__ . '/../../../wp-load.php',
	__DIR__ . '/../../wp-load.php',
	__DIR__ . '/../wp-load.php',
);

foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		break;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	echo "Error: Could not find WordPress installation.\n";
	exit( 1 );
}

/**
 * Real-time queue monitor.
 */
function fpml_monitor_queue( $interval = 5 ) {
	echo "🔍 FP Multilanguage Queue Monitor\n";
	echo "Refresh every {$interval}s (Ctrl+C to stop)\n";
	echo str_repeat( '=', 70 ) . "\n\n";

	$queue = FPML_Queue::instance();
	$processor = FPML_Processor::instance();

	while ( true ) {
		// Clear screen
		echo "\033[2J\033[;H";
		
		echo "🕐 " . date( 'Y-m-d H:i:s' ) . "\n";
		echo str_repeat( '=', 70 ) . "\n\n";

		// Queue stats
		$counts = $queue->get_state_counts();
		
		echo "Queue Status:\n";
		printf( "  ⏳ Pending:      %6d\n", $counts['pending'] ?? 0 );
		printf( "  🔄 Translating:  %6d\n", $counts['translating'] ?? 0 );
		printf( "  ✅ Done:         %6d\n", $counts['done'] ?? 0 );
		printf( "  ⚠️  Outdated:     %6d\n", $counts['outdated'] ?? 0 );
		printf( "  ❌ Error:        %6d\n", $counts['error'] ?? 0 );
		printf( "  ⏭️  Skipped:      %6d\n\n", $counts['skipped'] ?? 0 );

		// Processor status
		echo "Processor:\n";
		$locked = $processor->is_locked();
		printf( "  Status: %s\n", $locked ? "🔴 Locked" : "🟢 Available" );
		
		if ( $locked ) {
			$lock_time = get_option( 'fpml_queue_lock', 0 );
			$lock_age = time() - $lock_time;
			printf( "  Locked for: %d seconds\n", $lock_age );
		}
		echo "\n";

		// Rate limiter status
		echo "Rate Limiters:\n";
		$providers = array( 'openai', 'deepl', 'google', 'libretranslate' );
		foreach ( $providers as $provider ) {
			$status = FPML_Rate_Limiter::get_status( $provider );
			$indicator = $status['available'] ? '🟢' : '🔴';
			printf( "  %s %-15s: %d requests, reset in %ds\n", 
				$indicator,
				ucfirst( $provider ),
				$status['count'],
				$status['reset_in']
			);
		}
		echo "\n";

		// Recent activity
		echo "Recent Activity (last 5 logs):\n";
		$logs = FPML_Logger::instance()->get_logs( 5 );
		foreach ( $logs as $log ) {
			$level_icon = array(
				'info' => 'ℹ️',
				'warn' => '⚠️',
				'error' => '❌',
			);
			
			$icon = isset( $level_icon[ $log['level'] ] ) ? $level_icon[ $log['level'] ] : '•';
			$time = isset( $log['timestamp'] ) ? substr( $log['timestamp'], 11, 8 ) : '??:??:??';
			
			echo "  $icon [$time] " . substr( $log['message'], 0, 50 ) . "\n";
		}

		echo "\n" . str_repeat( '=', 70 ) . "\n";
		echo "Next refresh in {$interval}s...\n";

		sleep( $interval );
	}
}

/**
 * Cost analytics dashboard.
 */
function fpml_monitor_costs( $days = 7 ) {
	echo "💰 Translation Cost Analytics (Last $days days)\n";
	echo str_repeat( '=', 70 ) . "\n\n";

	$logger = FPML_Logger::instance();
	$logs = $logger->get_logs_by_event( 'translation.complete', 1000 );

	$cutoff = strtotime( "-$days days" );
	$by_day = array();
	$by_provider = array();
	$total_cost = 0;
	$total_chars = 0;

	foreach ( $logs as $log ) {
		$timestamp = isset( $log['timestamp'] ) ? strtotime( $log['timestamp'] ) : 0;
		
		if ( $timestamp < $cutoff ) {
			continue;
		}

		$date = date( 'Y-m-d', $timestamp );
		$provider = isset( $log['context']['provider'] ) ? $log['context']['provider'] : 'unknown';
		$cost = isset( $log['context']['cost'] ) ? (float) $log['context']['cost'] : 0;
		$chars = isset( $log['context']['characters'] ) ? (int) $log['context']['characters'] : 0;

		// By day
		if ( ! isset( $by_day[ $date ] ) ) {
			$by_day[ $date ] = array( 'cost' => 0, 'chars' => 0, 'count' => 0 );
		}
		$by_day[ $date ]['cost'] += $cost;
		$by_day[ $date ]['chars'] += $chars;
		$by_day[ $date ]['count']++;

		// By provider
		if ( ! isset( $by_provider[ $provider ] ) ) {
			$by_provider[ $provider ] = array( 'cost' => 0, 'chars' => 0, 'count' => 0 );
		}
		$by_provider[ $provider ]['cost'] += $cost;
		$by_provider[ $provider ]['chars'] += $chars;
		$by_provider[ $provider ]['count']++;

		$total_cost += $cost;
		$total_chars += $chars;
	}

	// Display by day
	echo "Daily Breakdown:\n";
	krsort( $by_day );
	foreach ( array_slice( $by_day, 0, 7 ) as $date => $data ) {
		printf( "  %s: €%-8s (%s chars, %d translations)\n",
			$date,
			number_format( $data['cost'], 4 ),
			number_format( $data['chars'] ),
			$data['count']
		);
	}

	echo "\n";

	// Display by provider
	echo "Provider Breakdown:\n";
	foreach ( $by_provider as $provider => $data ) {
		printf( "  %-15s: €%-8s (%s chars, %d translations)\n",
			ucfirst( $provider ),
			number_format( $data['cost'], 4 ),
			number_format( $data['chars'] ),
			$data['count']
		);
	}

	echo "\n";

	// Totals
	echo "Total Summary:\n";
	printf( "  Total cost:      €%s\n", number_format( $total_cost, 4 ) );
	printf( "  Total chars:     %s\n", number_format( $total_chars ) );
	printf( "  Avg per 1k:      €%s\n", number_format( $total_chars > 0 ? ( $total_cost / $total_chars * 1000 ) : 0, 4 ) );
	printf( "  Daily average:   €%s\n", number_format( $total_cost / $days, 4 ) );
	printf( "  Monthly proj:    €%s\n", number_format( $total_cost / $days * 30, 2 ) );

	echo "\n" . str_repeat( '=', 70 ) . "\n";
}

/**
 * Performance metrics.
 */
function fpml_monitor_performance() {
	echo "⚡ Performance Metrics\n";
	echo str_repeat( '=', 70 ) . "\n\n";

	$logs = FPML_Logger::instance()->get_logs_by_event( 'translation.complete', 100 );

	if ( empty( $logs ) ) {
		echo "No translation data available.\n";
		return;
	}

	$durations = array();
	$by_provider = array();

	foreach ( $logs as $log ) {
		$duration = isset( $log['context']['duration'] ) ? (int) $log['context']['duration'] : 0;
		$provider = isset( $log['context']['provider'] ) ? $log['context']['provider'] : 'unknown';
		$chars = isset( $log['context']['characters'] ) ? (int) $log['context']['characters'] : 0;

		$durations[] = $duration;

		if ( ! isset( $by_provider[ $provider ] ) ) {
			$by_provider[ $provider ] = array( 'durations' => array(), 'chars' => array() );
		}

		$by_provider[ $provider ]['durations'][] = $duration;
		$by_provider[ $provider ]['chars'][] = $chars;
	}

	// Overall stats
	echo "Overall (last 100 translations):\n";
	printf( "  Average duration:  %dms\n", array_sum( $durations ) / count( $durations ) );
	printf( "  Min duration:      %dms\n", min( $durations ) );
	printf( "  Max duration:      %dms\n", max( $durations ) );
	printf( "  P95 duration:      %dms\n", percentile( $durations, 95 ) );

	echo "\n";

	// By provider
	echo "By Provider:\n";
	foreach ( $by_provider as $provider => $data ) {
		$avg_duration = array_sum( $data['durations'] ) / count( $data['durations'] );
		$avg_chars = array_sum( $data['chars'] ) / count( $data['chars'] );
		$chars_per_sec = $avg_duration > 0 ? ( $avg_chars / ( $avg_duration / 1000 ) ) : 0;

		printf( "  %-15s: %dms avg, %d chars/s\n",
			ucfirst( $provider ),
			$avg_duration,
			$chars_per_sec
		);
	}

	echo "\n" . str_repeat( '=', 70 ) . "\n";
}

/**
 * Calculate percentile.
 */
function percentile( $array, $percentile ) {
	sort( $array );
	$index = ceil( count( $array ) * $percentile / 100 ) - 1;
	return $array[ $index ] ?? 0;
}

// Parse command
$command = isset( $argv[1] ) ? $argv[1] : 'queue';

switch ( $command ) {
	case 'queue':
		$interval = isset( $argv[2] ) ? absint( $argv[2] ) : 5;
		fpml_monitor_queue( $interval );
		break;

	case 'costs':
		$days = isset( $argv[2] ) ? absint( $argv[2] ) : 7;
		fpml_monitor_costs( $days );
		break;

	case 'performance':
		fpml_monitor_performance();
		break;

	default:
		echo "Usage: php monitor.php <command> [args]\n\n";
		echo "Commands:\n";
		echo "  queue [interval]       Monitor queue in real-time (default: 5s)\n";
		echo "  costs [days]           Cost analytics (default: 7 days)\n";
		echo "  performance            Performance metrics\n\n";
		echo "Examples:\n";
		echo "  php monitor.php queue 3\n";
		echo "  php monitor.php costs 30\n";
		echo "  php monitor.php performance\n";
		break;
}

exit( 0 );
