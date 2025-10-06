#!/usr/bin/env php
<?php
/**
 * Maintenance utilities for FP Multilanguage.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
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
 * Display usage information.
 */
function fpml_maintenance_usage() {
	echo "FP Multilanguage Maintenance Utilities\n\n";
	echo "Usage: php maintenance.php <command> [options]\n\n";
	echo "Commands:\n";
	echo "  health-check           Check plugin health status\n";
	echo "  optimize-queue         Optimize queue table\n";
	echo "  clear-stuck-jobs       Clear jobs stuck in 'translating' state\n";
	echo "  reset-rate-limits      Reset all provider rate limits\n";
	echo "  export-diagnostics     Export full diagnostics report\n";
	echo "  analyze-costs          Analyze translation costs\n";
	echo "  vacuum-database        Optimize database tables\n\n";
	echo "Examples:\n";
	echo "  php maintenance.php health-check\n";
	echo "  php maintenance.php optimize-queue\n";
	echo "  php maintenance.php analyze-costs --days=30\n\n";
}

/**
 * Health check command.
 */
function fpml_maintenance_health_check() {
	echo "🔍 Running health check...\n\n";

	$plugin = FPML_Plugin::instance();
	$queue = FPML_Queue::instance();
	$processor = FPML_Processor::instance();

	// Check database
	global $wpdb;
	$table = $wpdb->prefix . 'fpml_queue';
	$db_check = $wpdb->query( "SELECT 1 FROM {$table} LIMIT 1" );

	echo "Database:\n";
	echo $db_check !== false ? "  ✅ Accessible\n" : "  ❌ Not accessible\n";

	// Check queue
	$counts = $queue->get_state_counts();
	echo "\nQueue:\n";
	echo "  Pending: " . ( $counts['pending'] ?? 0 ) . "\n";
	echo "  Translating: " . ( $counts['translating'] ?? 0 ) . "\n";
	echo "  Done: " . ( $counts['done'] ?? 0 ) . "\n";
	echo "  Error: " . ( $counts['error'] ?? 0 ) . "\n";

	// Check lock
	echo "\nProcessor:\n";
	echo $processor->is_locked() ? "  ⚠️  Locked\n" : "  ✅ Available\n";

	// Check provider
	$translator = $processor->get_translator_instance();
	echo "\nProvider:\n";
	if ( is_wp_error( $translator ) ) {
		echo "  ❌ Not configured: " . $translator->get_error_message() . "\n";
	} else {
		echo "  ✅ Configured\n";
	}

	// Check assisted mode
	echo "\nMode:\n";
	echo $plugin->is_assisted_mode() 
		? "  ⚠️  Assisted mode (" . $plugin->get_assisted_reason_label() . ")\n"
		: "  ✅ Normal mode\n";

	echo "\n✅ Health check complete\n";
}

/**
 * Optimize queue table.
 */
function fpml_maintenance_optimize_queue() {
	echo "⚙️  Optimizing queue table...\n";

	global $wpdb;
	$table = $wpdb->prefix . 'fpml_queue';

	// Optimize table
	$result = $wpdb->query( "OPTIMIZE TABLE {$table}" );

	if ( false !== $result ) {
		echo "✅ Queue table optimized\n";
	} else {
		echo "❌ Optimization failed\n";
	}

	// Show table stats
	$row = $wpdb->get_row(
		"SELECT 
			COUNT(*) as total_rows,
			SUM(CASE WHEN state = 'done' THEN 1 ELSE 0 END) as done_rows
		FROM {$table}"
	);

	if ( $row ) {
		echo "\nTable Stats:\n";
		echo "  Total rows: " . number_format( $row->total_rows ) . "\n";
		echo "  Done rows: " . number_format( $row->done_rows ) . "\n";
		
		$percent = $row->total_rows > 0 ? ( $row->done_rows / $row->total_rows * 100 ) : 0;
		echo "  Done %: " . number_format( $percent, 1 ) . "%\n";
	}
}

/**
 * Clear stuck jobs.
 */
function fpml_maintenance_clear_stuck_jobs() {
	echo "🔧 Clearing stuck jobs...\n";

	$queue = FPML_Queue::instance();
	global $wpdb;
	$table = $wpdb->prefix . 'fpml_queue';

	// Jobs stuck in 'translating' for more than 1 hour
	$result = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} 
			SET state = %s 
			WHERE state = %s 
			AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)",
			'pending',
			'translating'
		)
	);

	if ( false !== $result ) {
		echo "✅ Reset $result stuck jobs to pending\n";
	} else {
		echo "❌ Failed to reset stuck jobs\n";
	}

	// Release lock if stuck
	$lock = get_option( 'fpml_queue_lock' );
	if ( $lock && $lock < time() - 1800 ) {
		delete_option( 'fpml_queue_lock' );
		echo "✅ Released stuck processor lock\n";
	}
}

/**
 * Reset rate limits.
 */
function fpml_maintenance_reset_rate_limits() {
	echo "🔄 Resetting rate limits...\n";

	$providers = array( 'openai', 'deepl', 'google', 'libretranslate' );

	foreach ( $providers as $provider ) {
		FPML_Rate_Limiter::reset( $provider );
		echo "  ✅ Reset $provider\n";
	}

	echo "\n✅ All rate limits reset\n";
}

/**
 * Export diagnostics.
 */
function fpml_maintenance_export_diagnostics() {
	echo "📊 Exporting diagnostics...\n";

	$plugin = FPML_Plugin::instance();
	$snapshot = $plugin->get_diagnostics_snapshot();

	$filename = 'fpml-diagnostics-' . date( 'Y-m-d-His' ) . '.json';
	$filepath = sys_get_temp_dir() . '/' . $filename;

	file_put_contents( $filepath, wp_json_encode( $snapshot, JSON_PRETTY_PRINT ) );

	echo "✅ Diagnostics exported to: $filepath\n";
}

/**
 * Analyze costs.
 */
function fpml_maintenance_analyze_costs( $days = 30 ) {
	echo "💰 Analyzing costs (last $days days)...\n\n";

	$logger = FPML_Logger::instance();
	$logs = $logger->get_logs_by_event( 'translation.complete', 1000 );

	$cutoff = strtotime( "-$days days" );
	$total_cost = 0;
	$total_chars = 0;
	$count = 0;

	foreach ( $logs as $log ) {
		$timestamp = isset( $log['timestamp'] ) ? strtotime( $log['timestamp'] ) : 0;
		
		if ( $timestamp < $cutoff ) {
			continue;
		}

		if ( isset( $log['context']['cost'] ) ) {
			$total_cost += (float) $log['context']['cost'];
		}

		if ( isset( $log['context']['characters'] ) ) {
			$total_chars += (int) $log['context']['characters'];
		}

		$count++;
	}

	echo "Period: Last $days days\n";
	echo "Total translations: " . number_format( $count ) . "\n";
	echo "Total characters: " . number_format( $total_chars ) . "\n";
	echo "Total cost: €" . number_format( $total_cost, 4 ) . "\n";

	if ( $count > 0 ) {
		echo "Average per translation: €" . number_format( $total_cost / $count, 6 ) . "\n";
		echo "Average characters: " . number_format( $total_chars / $count, 0 ) . "\n";
	}

	echo "\n✅ Analysis complete\n";
}

/**
 * Vacuum database.
 */
function fpml_maintenance_vacuum_database() {
	echo "🧹 Vacuuming database...\n";

	global $wpdb;
	$table = $wpdb->prefix . 'fpml_queue';

	// Analyze table
	$wpdb->query( "ANALYZE TABLE {$table}" );
	echo "  ✅ Analyzed queue table\n";

	// Optimize table
	$wpdb->query( "OPTIMIZE TABLE {$table}" );
	echo "  ✅ Optimized queue table\n";

	// Clean up orphaned meta
	$deleted = $wpdb->query(
		"DELETE pm FROM {$wpdb->postmeta} pm
		LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
		WHERE p.ID IS NULL
		AND pm.meta_key LIKE '_fpml_%'"
	);

	echo "  ✅ Cleaned up $deleted orphaned post meta\n";

	// Clean up orphaned term meta
	$deleted_terms = $wpdb->query(
		"DELETE tm FROM {$wpdb->termmeta} tm
		LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
		WHERE t.term_id IS NULL
		AND tm.meta_key LIKE '_fpml_%'"
	);

	echo "  ✅ Cleaned up $deleted_terms orphaned term meta\n";

	echo "\n✅ Database vacuum complete\n";
}

// Parse arguments
$command = isset( $argv[1] ) ? $argv[1] : '';

// Parse options
$options = array();
foreach ( $argv as $index => $arg ) {
	if ( $index === 0 || $index === 1 ) {
		continue;
	}

	if ( strpos( $arg, '--' ) === 0 ) {
		$parts = explode( '=', substr( $arg, 2 ), 2 );
		$options[ $parts[0] ] = isset( $parts[1] ) ? $parts[1] : true;
	}
}

// Execute command
switch ( $command ) {
	case 'health-check':
		fpml_maintenance_health_check();
		break;

	case 'optimize-queue':
		fpml_maintenance_optimize_queue();
		break;

	case 'clear-stuck-jobs':
		fpml_maintenance_clear_stuck_jobs();
		break;

	case 'reset-rate-limits':
		fpml_maintenance_reset_rate_limits();
		break;

	case 'export-diagnostics':
		fpml_maintenance_export_diagnostics();
		break;

	case 'analyze-costs':
		$days = isset( $options['days'] ) ? absint( $options['days'] ) : 30;
		fpml_maintenance_analyze_costs( $days );
		break;

	case 'vacuum-database':
		fpml_maintenance_vacuum_database();
		break;

	case '':
	case 'help':
	case '--help':
	case '-h':
		fpml_maintenance_usage();
		break;

	default:
		echo "Unknown command: $command\n\n";
		fpml_maintenance_usage();
		exit( 1 );
}

exit( 0 );
