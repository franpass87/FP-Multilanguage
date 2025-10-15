#!/usr/bin/env php
<?php
/**
 * Performance Analyzer per FP Multilanguage
 *
 * Analizza metriche di performance e identifica colli di bottiglia
 *
 * Uso: php analyze-performance.php [--verbose]
 *
 * @package FP_Multilanguage
 * @since 0.4.1
 */

// Configurazione
define( 'FPML_PERF_ANALYZER', true );

$verbose = in_array( '--verbose', $argv, true ) || in_array( '-v', $argv, true );

echo "\n";
echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║  FP Multilanguage - Performance Analyzer              ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n";
echo "\n";

// 1. Analizza dimensioni file
echo "📊 [1/6] Analisi dimensioni file...\n";
$files = glob( __DIR__ . '/../fp-multilanguage/**/*.php', GLOB_BRACE );
$file_sizes = array();

foreach ( $files as $file ) {
	$size = filesize( $file );
	$file_sizes[ basename( $file ) ] = $size;
}

arsort( $file_sizes );
$top_files = array_slice( $file_sizes, 0, 5, true );

echo "   File più grandi:\n";
foreach ( $top_files as $filename => $size ) {
	$kb = round( $size / 1024, 2 );
	echo sprintf( "   - %s: %.2f KB\n", $filename, $kb );
}

// 2. Analizza complessità
echo "\n📊 [2/6] Analisi complessità ciclomatica...\n";
$complex_functions = array();

foreach ( glob( __DIR__ . '/../fp-multilanguage/includes/**/*.php' ) as $file ) {
	$content = file_get_contents( $file );
	preg_match_all( '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE );
	
	foreach ( $matches[1] as $match ) {
		$function_name = $match[0];
		$start_pos = $match[1];
		
		// Conta if, while, for, foreach, case (indicatori di complessità)
		$next_function_pos = strlen( $content );
		foreach ( $matches[1] as $next_match ) {
			if ( $next_match[1] > $start_pos ) {
				$next_function_pos = $next_match[1];
				break;
			}
		}
		
		$function_body = substr( $content, $start_pos, $next_function_pos - $start_pos );
		$complexity = preg_match_all( '/\b(if|while|for|foreach|case)\b/', $function_body, $dummy );
		
		if ( $complexity > 10 ) {
			$complex_functions[ basename( $file ) . '::' . $function_name ] = $complexity;
		}
	}
}

if ( ! empty( $complex_functions ) ) {
	arsort( $complex_functions );
	$top_complex = array_slice( $complex_functions, 0, 5, true );
	
	echo "   Funzioni con alta complessità:\n";
	foreach ( $top_complex as $func => $complexity ) {
		echo sprintf( "   - %s: %d\n", $func, $complexity );
	}
} else {
	echo "   ✓ Nessuna funzione con complessità eccessiva\n";
}

// 3. Analizza query potenzialmente lente
echo "\n📊 [3/6] Analisi query database...\n";
$slow_queries = array();

foreach ( glob( __DIR__ . '/../fp-multilanguage/includes/**/*.php' ) as $file ) {
	$content = file_get_contents( $file );
	
	// Cerca posts_per_page => -1
	if ( preg_match_all( "/'posts_per_page'\s*=>\s*-1/", $content, $matches ) ) {
		$slow_queries[ basename( $file ) ] = count( $matches[0] );
	}
}

if ( ! empty( $slow_queries ) ) {
	echo "   File con query illimitate:\n";
	foreach ( $slow_queries as $file => $count ) {
		echo sprintf( "   - %s: %d occorrenze\n", $file, $count );
	}
} else {
	echo "   ✓ Nessuna query illimitata trovata\n";
}

// 4. Analizza uso memoria
echo "\n📊 [4/6] Stima utilizzo memoria...\n";

$memory_intensive = array();

foreach ( glob( __DIR__ . '/../fp-multilanguage/includes/**/*.php' ) as $file ) {
	$content = file_get_contents( $file );
	$score = 0;
	
	// Fattori che aumentano uso memoria
	$score += preg_match_all( '/array_merge/', $content, $dummy ) * 2;
	$score += preg_match_all( '/get_posts.*-1/', $content, $dummy ) * 5;
	$score += preg_match_all( '/get_terms.*-1/', $content, $dummy ) * 5;
	$score += preg_match_all( '/foreach.*foreach/', $content, $dummy ) * 3;
	
	if ( $score > 10 ) {
		$memory_intensive[ basename( $file ) ] = $score;
	}
}

if ( ! empty( $memory_intensive ) ) {
	arsort( $memory_intensive );
	echo "   File con potenziale alto uso memoria:\n";
	foreach ( array_slice( $memory_intensive, 0, 5, true ) as $file => $score ) {
		echo sprintf( "   - %s: score %d\n", $file, $score );
	}
} else {
	echo "   ✓ Uso memoria ottimizzato\n";
}

// 5. Analizza caching
echo "\n📊 [5/6] Analisi strategie caching...\n";

$cache_usage = array(
	'transient' => 0,
	'wp_cache' => 0,
	'object_cache' => 0,
);

foreach ( glob( __DIR__ . '/../fp-multilanguage/includes/**/*.php' ) as $file ) {
	$content = file_get_contents( $file );
	$cache_usage['transient'] += preg_match_all( '/set_transient|get_transient/', $content, $dummy );
	$cache_usage['wp_cache'] += preg_match_all( '/wp_cache_set|wp_cache_get/', $content, $dummy );
}

$total_cache = array_sum( $cache_usage );

echo "   Utilizzo caching:\n";
echo sprintf( "   - Transient API: %d chiamate\n", $cache_usage['transient'] );
echo sprintf( "   - WP Cache: %d chiamate\n", $cache_usage['wp_cache'] );
echo sprintf( "   - Totale: %d operazioni cache\n", $total_cache );

// 6. Raccomandazioni
echo "\n📊 [6/6] Generazione raccomandazioni...\n";
echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo " RACCOMANDAZIONI\n";
echo "═══════════════════════════════════════════════════════\n";

$recommendations = array();

if ( ! empty( $slow_queries ) ) {
	$recommendations[] = "⚠️  Implementare paginazione per query illimitate";
}

if ( ! empty( $complex_functions ) ) {
	$recommendations[] = "⚠️  Refactoring funzioni complesse (>10 complessità)";
}

if ( $total_cache < 20 ) {
	$recommendations[] = "💡 Considerare più caching per ridurre query DB";
}

if ( ! empty( $memory_intensive ) ) {
	$recommendations[] = "⚠️  Ottimizzare file memory-intensive con batch processing";
}

if ( empty( $recommendations ) ) {
	echo "\n✅ Performance ECCELLENTE - Nessuna raccomandazione\n";
} else {
	echo "\n";
	foreach ( $recommendations as $rec ) {
		echo "   $rec\n";
	}
}

// Summary
echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo " RIEPILOGO\n";
echo "═══════════════════════════════════════════════════════\n";
echo sprintf( "File analizzati: %d\n", count( $files ) );
echo sprintf( "Dimensione totale: %.2f MB\n", array_sum( $file_sizes ) / 1024 / 1024 );
echo sprintf( "Operazioni cache: %d\n", $total_cache );
echo sprintf( "Funzioni complesse: %d\n", count( $complex_functions ) );
echo "\n";

if ( $verbose ) {
	echo "\nModalità verbose attiva - dettagli completi mostrati sopra\n";
}

echo "✓ Analisi completata\n\n";

exit( 0 );
