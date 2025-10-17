<?php
/**
 * Script per testare la correzione delle pagine "Translation in progress"
 * 
 * Questo script:
 * 1. Testa la creazione di una nuova pagina tradotta
 * 2. Verifica che lo slug sia corretto (en-*)
 * 3. Verifica che l'URL sia corretto (/en/*)
 * 4. Verifica che il contenuto non sia vuoto
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 */

// Assicurati che WordPress sia caricato
if ( ! defined( 'ABSPATH' ) ) {
    // Carica WordPress
    require_once( dirname( __FILE__ ) . '/wp-config.php' );
}

// Verifica che siamo in admin o CLI
if ( ! is_admin() && ! defined( 'WP_CLI' ) ) {
    die( 'Questo script può essere eseguito solo da admin o WP-CLI' );
}

echo "=== TEST CORREZIONE PAGINE TRANSLATION ===\n\n";

// Test 1: Verifica che il plugin sia attivo
if ( ! class_exists( 'FPML_Translation_Manager' ) ) {
    echo "❌ ERRORE: Plugin FP Multilanguage non trovato o non attivo\n";
    exit( 1 );
}

echo "✅ Plugin FP Multilanguage trovato\n";

// Test 2: Trova una pagina italiana per testare
$test_pages = get_posts( array(
    'post_type'      => 'page',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'meta_query'     => array(
        array(
            'key'     => '_fpml_is_translation',
            'compare' => 'NOT EXISTS'
        )
    )
) );

if ( empty( $test_pages ) ) {
    echo "❌ ERRORE: Nessuna pagina italiana trovata per il test\n";
    exit( 1 );
}

$test_page = $test_pages[0];
echo "✅ Pagina di test trovata: {$test_page->post_title} (ID: {$test_page->ID})\n";
echo "   Slug originale: {$test_page->post_name}\n";

// Test 3: Crea una traduzione usando il Translation Manager
$translation_manager = FPML_Translation_Manager::instance();
echo "\n--- Test creazione traduzione ---\n";

$translation = $translation_manager->ensure_post_translation( $test_page );

if ( ! $translation ) {
    echo "❌ ERRORE: Impossibile creare la traduzione\n";
    exit( 1 );
}

echo "✅ Traduzione creata: {$translation->post_title} (ID: {$translation->ID})\n";
echo "   Slug traduzione: {$translation->post_name}\n";
echo "   Contenuto: " . ( empty( trim( $translation->post_content ) ) ? 'VUOTO' : 'PRESENTE' ) . "\n";

// Test 4: Verifica che lo slug sia corretto
$expected_slug = 'en-' . $test_page->post_name;
if ( $translation->post_name === $expected_slug ) {
    echo "✅ Slug corretto: {$translation->post_name}\n";
} else {
    echo "❌ Slug errato: {$translation->post_name} (atteso: {$expected_slug})\n";
}

// Test 5: Verifica che l'URL sia corretto
$permalink = get_permalink( $translation->ID );
$expected_url = home_url( '/en/' . $test_page->post_name . '/' );

echo "   URL generato: {$permalink}\n";
echo "   URL atteso: {$expected_url}\n";

if ( $permalink === $expected_url ) {
    echo "✅ URL corretto\n";
} else {
    echo "❌ URL errato\n";
}

// Test 6: Verifica che il contenuto non sia vuoto
if ( ! empty( trim( $translation->post_content ) ) ) {
    echo "✅ Contenuto presente\n";
} else {
    echo "❌ Contenuto vuoto\n";
}

// Test 7: Verifica che il titolo non contenga "(EN - Translation in progress)"
if ( strpos( $translation->post_title, '(EN - Translation in progress)' ) === false ) {
    echo "✅ Titolo corretto: {$translation->post_title}\n";
} else {
    echo "❌ Titolo contiene ancora il testo temporaneo\n";
}

// Test 8: Verifica i meta dati
$is_translation = get_post_meta( $translation->ID, '_fpml_is_translation', true );
$pair_source_id = get_post_meta( $translation->ID, '_fpml_pair_source_id', true );
$translation_status = get_post_meta( $translation->ID, '_fpml_translation_status', true );

echo "\n--- Verifica meta dati ---\n";
echo "   _fpml_is_translation: {$is_translation}\n";
echo "   _fpml_pair_source_id: {$pair_source_id}\n";
echo "   _fpml_translation_status: {$translation_status}\n";

if ( $is_translation && $pair_source_id == $test_page->ID ) {
    echo "✅ Meta dati corretti\n";
} else {
    echo "❌ Meta dati errati\n";
}

// Test 9: Verifica che la pagina sorgente abbia il riferimento
$source_pair_id = get_post_meta( $test_page->ID, '_fpml_pair_id', true );
echo "   Riferimento nella pagina sorgente: {$source_pair_id}\n";

if ( $source_pair_id == $translation->ID ) {
    echo "✅ Riferimento bidirezionale corretto\n";
} else {
    echo "❌ Riferimento bidirezionale errato\n";
}

// Test 10: Test del routing
echo "\n--- Test routing ---\n";
$routing_mode = get_option( 'fpml_routing_mode', 'segment' );
echo "   Routing mode: {$routing_mode}\n";

if ( $routing_mode === 'segment' ) {
    echo "✅ Routing mode corretto per /en/ URLs\n";
} else {
    echo "⚠️  Routing mode non è 'segment' - gli URL /en/ potrebbero non funzionare\n";
}

// Pulizia: elimina la pagina di test se richiesto
echo "\n--- Pulizia ---\n";
echo "Vuoi eliminare la pagina di test creata? (y/N): ";
$handle = fopen( "php://stdin", "r" );
$line = fgets( $handle );
fclose( $handle );

if ( trim( strtolower( $line ) ) === 'y' ) {
    $deleted = wp_delete_post( $translation->ID, true );
    if ( $deleted ) {
        delete_post_meta( $test_page->ID, '_fpml_pair_id' );
        echo "✅ Pagina di test eliminata\n";
    } else {
        echo "❌ Errore nell'eliminazione della pagina di test\n";
    }
} else {
    echo "ℹ️  Pagina di test mantenuta: {$translation->post_title} (ID: {$translation->ID})\n";
}

echo "\n=== TEST COMPLETATO ===\n";
echo "Se tutti i test sono passati, la correzione funziona correttamente!\n";
echo "Ora puoi eseguire il reindex per applicare le correzioni a tutte le pagine.\n";
