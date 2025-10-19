<?php
/**
 * Test script to verify /en/ subfolder creation for translated pages.
 *
 * Usage: php -S localhost:8000 -t /path/to/wordpress
 * Then visit: http://localhost:8000/wp-content/plugins/fp-multilanguage/test-en-subfolder.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cannot load WordPress.' );
}

// Check if plugin is active
if ( ! class_exists( 'FPML_Plugin' ) ) {
	die( 'FP Multilanguage plugin is not active.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Test /en/ Subfolder Creation</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; }
		h1 { color: #333; }
		.success { color: green; font-weight: bold; }
		.error { color: red; font-weight: bold; }
		.info { color: blue; }
		table { border-collapse: collapse; width: 100%; margin: 20px 0; }
		th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
		th { background-color: #f2f2f2; }
		.button { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px; margin: 10px 0; }
		.button:hover { background: #005177; }
		code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
	</style>
</head>
<body>
	<h1>🔍 Test /en/ Subfolder Creation</h1>
	
	<?php
	// Test 1: Check rewrite rules
	echo '<h2>1. Verifica Rewrite Rules</h2>';
	global $wp_rewrite;
	$rules = get_option( 'rewrite_rules' );
	
	$en_rules_found = false;
	if ( is_array( $rules ) ) {
		foreach ( $rules as $pattern => $rewrite ) {
			if ( strpos( $pattern, 'en/' ) === 0 || strpos( $pattern, '^en' ) === 0 ) {
				$en_rules_found = true;
				echo '<p class="success">✓ Trovata regola rewrite per /en/: <code>' . esc_html( $pattern ) . '</code> → <code>' . esc_html( $rewrite ) . '</code></p>';
			}
		}
	}
	
	if ( ! $en_rules_found ) {
		echo '<p class="error">✗ Nessuna regola rewrite trovata per /en/</p>';
		echo '<p><a href="?flush=1" class="button">Flush Rewrite Rules</a></p>';
	}
	
	// Handle flush request
	if ( isset( $_GET['flush'] ) ) {
		flush_rewrite_rules();
		echo '<p class="success">✓ Rewrite rules flushed! <a href="' . remove_query_arg( 'flush' ) . '">Ricarica la pagina</a></p>';
	}
	
	// Test 2: Check translated pages
	echo '<h2>2. Verifica Pagine Tradotte</h2>';
	$translated_pages = get_posts( array(
		'post_type' => 'any',
		'posts_per_page' => -1,
		'meta_query' => array(
			array(
				'key' => '_fpml_is_translation',
				'value' => '1',
			),
		),
	) );
	
	if ( empty( $translated_pages ) ) {
		echo '<p class="info">Nessuna pagina tradotta trovata.</p>';
	} else {
		echo '<p class="success">✓ Trovate ' . count( $translated_pages ) . ' pagine tradotte</p>';
		echo '<table>';
		echo '<tr><th>ID</th><th>Titolo</th><th>Slug</th><th>Permalink</th><th>Test URL</th></tr>';
		
		foreach ( $translated_pages as $page ) {
			$permalink = get_permalink( $page->ID );
			$has_en_prefix = strpos( $permalink, '/en/' ) !== false;
			
			echo '<tr>';
			echo '<td>' . $page->ID . '</td>';
			echo '<td>' . esc_html( $page->post_title ) . '</td>';
			echo '<td><code>' . esc_html( $page->post_name ) . '</code></td>';
			echo '<td>';
			if ( $has_en_prefix ) {
				echo '<span class="success">✓</span> ';
			} else {
				echo '<span class="error">✗</span> ';
			}
			echo '<a href="' . esc_url( $permalink ) . '" target="_blank">' . esc_html( $permalink ) . '</a>';
			echo '</td>';
			echo '<td><a href="' . esc_url( $permalink ) . '" target="_blank" class="button">Testa</a></td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	// Test 3: Check routing mode
	echo '<h2>3. Verifica Impostazioni</h2>';
	$settings = FPML_Settings::instance();
	$routing_mode = $settings->get( 'routing_mode', 'segment' );
	
	echo '<p><strong>Routing Mode:</strong> <code>' . esc_html( $routing_mode ) . '</code>';
	if ( 'segment' === $routing_mode ) {
		echo ' <span class="success">✓ Corretto</span>';
	} else {
		echo ' <span class="error">✗ Dovrebbe essere "segment" per usare /en/</span>';
	}
	echo '</p>';
	
	// Test 4: Check language class hooks
	echo '<h2>4. Verifica Hook Filtri</h2>';
	$language = FPML_Language::instance();
	
	if ( has_filter( 'post_link', array( $language, 'filter_translation_permalink' ) ) ) {
		echo '<p class="success">✓ Filtro post_link registrato</p>';
	} else {
		echo '<p class="error">✗ Filtro post_link NON registrato</p>';
	}
	
	if ( has_filter( 'page_link', array( $language, 'filter_translation_permalink' ) ) ) {
		echo '<p class="success">✓ Filtro page_link registrato</p>';
	} else {
		echo '<p class="error">✗ Filtro page_link NON registrato</p>';
	}
	
	// Test 5: Manual permalink test
	echo '<h2>5. Test Manuale Permalink</h2>';
	echo '<form method="post">';
	echo '<p>Testa un post ID specifico:</p>';
	echo '<input type="number" name="test_post_id" placeholder="Post ID" style="padding: 8px; width: 200px;">';
	echo '<button type="submit" class="button">Testa Permalink</button>';
	echo '</form>';
	
	if ( isset( $_POST['test_post_id'] ) ) {
		$test_id = intval( $_POST['test_post_id'] );
		$test_post = get_post( $test_id );
		
		if ( $test_post ) {
			echo '<h3>Risultato Test per Post #' . $test_id . '</h3>';
			echo '<p><strong>Titolo:</strong> ' . esc_html( $test_post->post_title ) . '</p>';
			echo '<p><strong>Slug:</strong> <code>' . esc_html( $test_post->post_name ) . '</code></p>';
			echo '<p><strong>È traduzione:</strong> ' . ( get_post_meta( $test_id, '_fpml_is_translation', true ) ? '<span class="success">Sì</span>' : '<span class="error">No</span>' ) . '</p>';
			
			$original_permalink = get_permalink( $test_id );
			echo '<p><strong>Permalink originale:</strong> <a href="' . esc_url( $original_permalink ) . '" target="_blank">' . esc_html( $original_permalink ) . '</a></p>';
			
			// Apply filter manually
			$filtered_permalink = apply_filters( 'post_link', $original_permalink, $test_post );
			echo '<p><strong>Permalink filtrato:</strong> <a href="' . esc_url( $filtered_permalink ) . '" target="_blank">' . esc_html( $filtered_permalink ) . '</a></p>';
			
			if ( $original_permalink !== $filtered_permalink ) {
				echo '<p class="success">✓ Il filtro ha modificato il permalink</p>';
			} else {
				echo '<p class="info">ℹ Il filtro non ha modificato il permalink (potrebbe essere corretto se non è una traduzione)</p>';
			}
		} else {
			echo '<p class="error">✗ Post non trovato</p>';
		}
	}
	
	// Summary
	echo '<h2>📊 Riepilogo</h2>';
	echo '<ul>';
	if ( $en_rules_found ) {
		echo '<li class="success">✓ Le rewrite rules per /en/ sono registrate</li>';
	} else {
		echo '<li class="error">✗ Le rewrite rules per /en/ NON sono registrate</li>';
	}
	
	if ( 'segment' === $routing_mode ) {
		echo '<li class="success">✓ Il routing mode è impostato correttamente su "segment"</li>';
	} else {
		echo '<li class="error">✗ Il routing mode NON è impostato su "segment"</li>';
	}
	
	if ( ! empty( $translated_pages ) ) {
		$correct_permalinks = 0;
		foreach ( $translated_pages as $page ) {
			if ( strpos( get_permalink( $page->ID ), '/en/' ) !== false ) {
				$correct_permalinks++;
			}
		}
		
		if ( $correct_permalinks === count( $translated_pages ) ) {
			echo '<li class="success">✓ Tutte le ' . count( $translated_pages ) . ' pagine tradotte hanno il permalink corretto con /en/</li>';
		} else {
			echo '<li class="error">✗ Solo ' . $correct_permalinks . ' su ' . count( $translated_pages ) . ' pagine hanno il permalink corretto con /en/</li>';
		}
	}
	echo '</ul>';
	
	echo '<hr>';
	echo '<p><a href="' . admin_url( 'admin.php?page=fp-multilanguage-settings' ) . '" class="button">Vai alle Impostazioni</a></p>';
	?>
</body>
</html>
