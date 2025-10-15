#!/usr/bin/env php
<?php
/**
 * Performance profiler for FP Multilanguage.
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
 * Profile reindex operation.
 */
function fpml_profile_reindex( $limit = 100 ) {
	echo "🔬 Profiling reindex operation ($limit posts)...\n\n";

	// Enable timing
	$start_time = microtime( true );
	$start_memory = memory_get_usage( true );

	// Track query count
	global $wpdb;
	$query_count_before = count( (array) $wpdb->queries );

	// Run reindex
	$plugin = FPML_Plugin::instance();
	
	// Temporarily limit post types
	add_filter( 'fpml_translatable_post_types', function( $types ) {
		return array( 'post' ); // Only posts for profiling
	});

	// Track phases
	$phases = array();

	// Phase 1: Post scanning
	$phase_start = microtime( true );
	$summary = $plugin->reindex_content();
	$phases['total'] = microtime( true ) - $phase_start;

	// Collect metrics
	$end_time = microtime( true );
	$end_memory = memory_get_usage( true );
	$query_count_after = count( (array) $wpdb->queries );

	// Calculate
	$elapsed = $end_time - $start_time;
	$memory_used = $end_memory - $start_memory;
	$queries = $query_count_after - $query_count_before;

	// Display results
	echo "Results:\n";
	echo str_repeat( '=', 70 ) . "\n";
	printf( "Posts scanned:         %d\n", $summary['posts_scanned'] ?? 0 );
	printf( "Translations created:  %d\n", $summary['translations_created'] ?? 0 );
	printf( "Jobs enqueued:         %d\n", $summary['posts_enqueued'] ?? 0 );
	echo "\n";

	printf( "Time elapsed:          %.3f seconds\n", $elapsed );
	printf( "Time per post:         %.3f seconds\n", $elapsed / max( 1, $summary['posts_scanned'] ?? 1 ) );
	printf( "Posts per second:      %.2f\n", ( $summary['posts_scanned'] ?? 0 ) / max( 0.001, $elapsed ) );
	echo "\n";

	printf( "Memory used:           %.2f MB\n", $memory_used / 1024 / 1024 );
	printf( "Memory per post:       %.2f KB\n", $memory_used / 1024 / max( 1, $summary['posts_scanned'] ?? 1 ) );
	printf( "Peak memory:           %.2f MB\n", memory_get_peak_usage( true ) / 1024 / 1024 );
	echo "\n";

	printf( "Database queries:      %d\n", $queries );
	printf( "Queries per post:      %.2f\n", $queries / max( 1, $summary['posts_scanned'] ?? 1 ) );

	echo "\n" . str_repeat( '=', 70 ) . "\n";

	// Performance grade
	$posts_per_second = ( $summary['posts_scanned'] ?? 0 ) / max( 0.001, $elapsed );
	
	echo "\nPerformance Grade: ";
	if ( $posts_per_second > 20 ) {
		echo "🌟 EXCELLENT (>20 posts/s)\n";
	} elseif ( $posts_per_second > 10 ) {
		echo "✅ GOOD (10-20 posts/s)\n";
	} elseif ( $posts_per_second > 5 ) {
		echo "⚠️  FAIR (5-10 posts/s)\n";
	} else {
		echo "❌ POOR (<5 posts/s)\n";
	}

	// Recommendations
	echo "\nRecommendations:\n";
	
	if ( $queries / max( 1, $summary['posts_scanned'] ?? 1 ) > 20 ) {
		echo "  ⚠️  High query count - verify update_meta_cache() is used\n";
	}
	
	if ( $memory_used / 1024 / 1024 > 256 ) {
		echo "  ⚠️  High memory usage - consider reducing batch size\n";
	}
	
	if ( $posts_per_second < 10 ) {
		echo "  💡 Consider enabling object cache (Redis/Memcached)\n";
		echo "  💡 Add database indexes\n";
		echo "  💡 Check docs/performance-optimization.md\n";
	}
}

/**
 * Profile queue processing.
 */
function fpml_profile_queue( $batch_size = 20 ) {
	echo "🔬 Profiling queue processing ($batch_size jobs)...\n\n";

	$queue = FPML_Queue::instance();
	$processor = FPML_Processor::instance();

	// Get jobs
	$jobs = $queue->get_next_jobs( $batch_size );
	$actual_count = count( $jobs );

	if ( $actual_count === 0 ) {
		echo "No jobs in queue. Enqueue some first.\n";
		return;
	}

	echo "Processing $actual_count jobs...\n\n";

	// Track per-job metrics
	$job_times = array();
	$job_memory = array();

	foreach ( $jobs as $index => $job ) {
		$job_start = microtime( true );
		$job_mem_start = memory_get_usage( true );

		$result = $processor->process_job( $job );

		$job_time = microtime( true ) - $job_start;
		$job_mem = memory_get_usage( true ) - $job_mem_start;

		$job_times[] = $job_time;
		$job_memory[] = $job_mem;

		$status = is_wp_error( $result ) ? '❌' : '✅';
		printf( "%s Job %d: %.3fs, %.2fKB\n", $status, $index + 1, $job_time, $job_mem / 1024 );
	}

	// Summary
	echo "\n" . str_repeat( '=', 70 ) . "\n";
	printf( "Total jobs:            %d\n", $actual_count );
	printf( "Average time/job:      %.3f seconds\n", array_sum( $job_times ) / $actual_count );
	printf( "Min time:              %.3f seconds\n", min( $job_times ) );
	printf( "Max time:              %.3f seconds\n", max( $job_times ) );
	printf( "Total time:            %.3f seconds\n", array_sum( $job_times ) );
	echo "\n";
	printf( "Average memory/job:    %.2f KB\n", array_sum( $job_memory ) / $actual_count / 1024 );
	printf( "Peak memory:           %.2f MB\n", memory_get_peak_usage( true ) / 1024 / 1024 );
	
	echo "\n" . str_repeat( '=', 70 ) . "\n";
}

/**
 * Profile API providers.
 */
function fpml_profile_providers() {
	echo "🔬 Profiling translation providers...\n\n";

	$test_text = "Questo è un testo di prova per verificare le performance del provider di traduzione. Include HTML <strong>markup</strong> e caratteri speciali: è, à, ò.";

	$providers = array(
		'openai' => 'FPML_Provider_OpenAI',
		'google' => 'FPML_Provider_Google',
	);

	$results = array();

	foreach ( $providers as $slug => $class ) {
		if ( ! class_exists( $class ) ) {
			continue;
		}

		echo "Testing $slug...\n";

		$provider = new $class();

		if ( ! $provider->is_configured() ) {
			echo "  ⚠️  Not configured, skipping\n\n";
			continue;
		}

		// Profile translation
		$start = microtime( true );
		$start_mem = memory_get_usage( true );

		$translated = $provider->translate( $test_text, 'it', 'en', 'general' );

		$elapsed = microtime( true ) - $start;
		$memory = memory_get_usage( true ) - $start_mem;

		$cost = $provider->estimate_cost( $test_text );

		$results[ $slug ] = array(
			'time' => $elapsed,
			'memory' => $memory,
			'cost' => $cost,
			'success' => ! is_wp_error( $translated ),
			'chars' => strlen( $test_text ),
		);

		if ( is_wp_error( $translated ) ) {
			printf( "  ❌ Error: %s\n", $translated->get_error_message() );
		} else {
			printf( "  ✅ Success\n" );
			printf( "  Time: %.3fs\n", $elapsed );
			printf( "  Cost: €%.6f\n", $cost );
			printf( "  Chars/s: %.0f\n", strlen( $test_text ) / max( 0.001, $elapsed ) );
		}

		echo "\n";
	}

	// Comparison table
	if ( count( $results ) > 1 ) {
		echo str_repeat( '=', 70 ) . "\n";
		echo "Comparison:\n\n";

		printf( "%-15s %10s %10s %12s %12s\n", 'Provider', 'Time', 'Cost', 'Chars/s', 'Status' );
		echo str_repeat( '-', 70 ) . "\n";

		foreach ( $results as $slug => $data ) {
			$status = $data['success'] ? '✅ OK' : '❌ FAIL';
			$chars_per_sec = $data['success'] ? $data['chars'] / max( 0.001, $data['time'] ) : 0;

			printf( "%-15s %8.3fs €%8.6f %10.0f  %s\n",
				ucfirst( $slug ),
				$data['time'],
				$data['cost'],
				$chars_per_sec,
				$status
			);
		}
	}
}

// Main
$command = isset( $argv[1] ) ? $argv[1] : 'help';

switch ( $command ) {
	case 'reindex':
		$limit = isset( $argv[2] ) ? absint( $argv[2] ) : 100;
		fpml_profile_reindex( $limit );
		break;

	case 'queue':
		$batch = isset( $argv[2] ) ? absint( $argv[2] ) : 20;
		fpml_profile_queue( $batch );
		break;

	case 'providers':
		fpml_profile_providers();
		break;

	default:
		echo "FP Multilanguage Performance Profiler\n\n";
		echo "Usage: php profiler.php <command> [args]\n\n";
		echo "Commands:\n";
		echo "  reindex [limit]        Profile reindex operation (default: 100)\n";
		echo "  queue [batch]          Profile queue processing (default: 20)\n";
		echo "  providers              Profile all providers\n\n";
		echo "Examples:\n";
		echo "  php profiler.php reindex 50\n";
		echo "  php profiler.php queue 30\n";
		echo "  php profiler.php providers\n";
		break;
}

exit( 0 );
