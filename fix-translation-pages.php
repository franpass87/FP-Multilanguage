<?php
/**
 * Script per correggere le pagine "Translation in progress" problematiche
 * 
 * Questo script:
 * 1. Trova tutte le pagine con "(EN - Translation in progress)" nel titolo
 * 2. Le elimina se sono vuote e hanno slug con -2
 * 3. Ripulisce i meta dati orfani
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

echo "=== CORREZIONE PAGINE TRANSLATION IN PROGRESS ===\n\n";

// Trova tutte le pagine con "(EN - Translation in progress)" nel titolo
$problematic_pages = get_posts( array(
    'post_type'      => array( 'page', 'post' ),
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'meta_query'     => array(
        array(
            'key'     => '_fpml_is_translation',
            'value'   => '1',
            'compare' => '='
        )
    ),
    's' => '(EN - Translation in progress)'
) );

echo "Trovate " . count( $problematic_pages ) . " pagine problematiche.\n\n";

$deleted_count = 0;
$fixed_count = 0;

foreach ( $problematic_pages as $page ) {
    echo "Analizzando pagina: {$page->post_title} (ID: {$page->ID})\n";
    echo "  Slug: {$page->post_name}\n";
    echo "  Contenuto: " . ( empty( $page->post_content ) ? 'VUOTO' : 'PRESENTE' ) . "\n";
    
    // Controlla se ha slug con -2 (indicativo di duplicato)
    $has_duplicate_slug = preg_match( '/-\d+$/', $page->post_name );
    
    // Controlla se è vuota
    $is_empty = empty( trim( $page->post_content ) );
    
    // Controlla se ha il titolo temporaneo
    $has_temp_title = strpos( $page->post_title, '(EN - Translation in progress)' ) !== false;
    
    echo "  Slug duplicato: " . ( $has_duplicate_slug ? 'SÌ' : 'NO' ) . "\n";
    echo "  Pagina vuota: " . ( $is_empty ? 'SÌ' : 'NO' ) . "\n";
    echo "  Titolo temporaneo: " . ( $has_temp_title ? 'SÌ' : 'NO' ) . "\n";
    
    // Se è una pagina vuota con slug duplicato e titolo temporaneo, la eliminiamo
    if ( $is_empty && $has_duplicate_slug && $has_temp_title ) {
        echo "  → ELIMINAZIONE: Pagina vuota con slug duplicato\n";
        
        // Rimuovi i meta dati di associazione
        delete_post_meta( $page->ID, '_fpml_is_translation' );
        delete_post_meta( $page->ID, '_fpml_pair_source_id' );
        
        // Trova e aggiorna la pagina sorgente per rimuovere il riferimento
        $source_id = get_post_meta( $page->ID, '_fpml_pair_source_id', true );
        if ( $source_id ) {
            delete_post_meta( $source_id, '_fpml_pair_id' );
            echo "  → Rimosso riferimento dalla pagina sorgente (ID: {$source_id})\n";
        }
        
        // Elimina la pagina
        $result = wp_delete_post( $page->ID, true );
        if ( $result ) {
            echo "  → PAGINA ELIMINATA\n";
            $deleted_count++;
        } else {
            echo "  → ERRORE nell'eliminazione\n";
        }
    } else {
        echo "  → MANTENUTA: Non soddisfa i criteri per l'eliminazione\n";
        $fixed_count++;
    }
    
    echo "\n";
}

// Pulisci i meta dati orfani
echo "=== PULIZIA META DATI ORFANI ===\n";

// Trova tutti i meta _fpml_pair_id che puntano a post inesistenti
global $wpdb;

$orphaned_meta = $wpdb->get_results( "
    SELECT pm.post_id, pm.meta_value as target_id
    FROM {$wpdb->postmeta} pm
    LEFT JOIN {$wpdb->posts} p ON pm.meta_value = p.ID
    WHERE pm.meta_key = '_fpml_pair_id'
    AND p.ID IS NULL
" );

echo "Trovati " . count( $orphaned_meta ) . " meta dati orfani.\n";

foreach ( $orphaned_meta as $meta ) {
    echo "Rimuovendo meta orfano: post_id {$meta->post_id} → target_id {$meta->target_id}\n";
    delete_post_meta( $meta->post_id, '_fpml_pair_id' );
}

// Trova tutti i meta _fpml_pair_source_id che puntano a post inesistenti
$orphaned_source_meta = $wpdb->get_results( "
    SELECT pm.post_id, pm.meta_value as source_id
    FROM {$wpdb->postmeta} pm
    LEFT JOIN {$wpdb->posts} p ON pm.meta_value = p.ID
    WHERE pm.meta_key = '_fpml_pair_source_id'
    AND p.ID IS NULL
" );

echo "Trovati " . count( $orphaned_source_meta ) . " meta dati sorgente orfani.\n";

foreach ( $orphaned_source_meta as $meta ) {
    echo "Rimuovendo meta sorgente orfano: post_id {$meta->post_id} → source_id {$meta->source_id}\n";
    delete_post_meta( $meta->post_id, '_fpml_pair_source_id' );
    delete_post_meta( $meta->post_id, '_fpml_is_translation' );
}

// Pulisci la cache
wp_cache_flush();

echo "\n=== RIEPILOGO ===\n";
echo "Pagine eliminate: {$deleted_count}\n";
echo "Pagine mantenute: {$fixed_count}\n";
echo "Meta dati orfani rimossi: " . ( count( $orphaned_meta ) + count( $orphaned_source_meta ) ) . "\n";
echo "\nCorrezione completata!\n";

// Suggerisci di eseguire il reindex
echo "\n=== PROSSIMI PASSI ===\n";
echo "1. Vai in WP Admin → FP Multilanguage → Reindex\n";
echo "2. Esegui un nuovo reindex per ricreare le traduzioni correttamente\n";
echo "3. Verifica che le nuove pagine abbiano URL con /en/ e slug corretti\n";
