<?php
/**
 * Test REST API Endpoints - Verifica tutti gli endpoint REST API
 * 
 * Questo script verifica che tutti gli endpoint REST API siano registrati correttamente.
 * Eseguire da WordPress admin o tramite WP-CLI: wp eval-file tests/test-rest-api-endpoints.php
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';
}

if ( ! defined( 'ABSPATH' ) ) {
    die( 'WordPress non trovato.' );
}

echo "=== TEST REST API ENDPOINTS FP-MULTILANGUAGE ===\n\n";

$errors = array();
$warnings = array();
$success = array();

// Ottieni REST server
if ( ! class_exists( 'WP_REST_Server' ) ) {
    echo "✗ REST Server non disponibile\n";
    exit( 1 );
}

$rest_server = rest_get_server();
$routes = $rest_server->get_routes();

// Filtra solo route FPML
$fpml_routes = array_filter( $routes, function( $route ) {
    return strpos( $route, '/fpml/v1/' ) === 0;
}, ARRAY_FILTER_USE_KEY );

echo "Route FPML trovate: " . count( $fpml_routes ) . "\n\n";

// Lista endpoint attesi
$expected_endpoints = array(
    // Queue routes
    array(
        'path' => '/fpml/v1/queue/run',
        'methods' => array( 'POST' ),
        'description' => 'Run translation queue',
    ),
    array(
        'path' => '/fpml/v1/queue/cleanup',
        'methods' => array( 'POST' ),
        'description' => 'Cleanup queue',
    ),
    // Provider routes
    array(
        'path' => '/fpml/v1/test-provider',
        'methods' => array( 'POST' ),
        'description' => 'Test translation provider',
    ),
    array(
        'path' => '/fpml/v1/preview-translation',
        'methods' => array( 'POST' ),
        'description' => 'Preview translation',
    ),
    array(
        'path' => '/fpml/v1/check-billing',
        'methods' => array( 'POST' ),
        'description' => 'Check billing status',
    ),
    array(
        'path' => '/fpml/v1/refresh-nonce',
        'methods' => array( 'GET' ),
        'description' => 'Refresh nonce',
    ),
    // Reindex routes
    array(
        'path' => '/fpml/v1/reindex',
        'methods' => array( 'POST' ),
        'description' => 'Reindex content',
    ),
    array(
        'path' => '/fpml/v1/reindex-batch',
        'methods' => array( 'POST' ),
        'description' => 'Batch reindex',
    ),
    // System routes
    array(
        'path' => '/fpml/v1/health',
        'methods' => array( 'GET' ),
        'description' => 'Health check',
    ),
    array(
        'path' => '/fpml/v1/stats',
        'methods' => array( 'GET' ),
        'description' => 'Get statistics',
    ),
    array(
        'path' => '/fpml/v1/logs',
        'methods' => array( 'GET' ),
        'description' => 'Get logs',
    ),
    // Translation routes
    array(
        'path' => '/fpml/v1/translations',
        'methods' => array( 'GET' ),
        'description' => 'Get translations list',
    ),
    array(
        'path' => '/fpml/v1/translations/bulk',
        'methods' => array( 'POST' ),
        'description' => 'Bulk translate',
    ),
);

// Verifica endpoint base (senza parametri dinamici)
echo "1. Verifica Endpoint Base...\n";
foreach ( $expected_endpoints as $endpoint ) {
    $found = false;
    $found_methods = array();
    
    foreach ( $fpml_routes as $registered_route => $route_config ) {
        // Verifica se route corrisponde (supporta regex)
        if ( preg_match( '#^' . str_replace( array( '?', '/', '(', ')' ), array( '\?', '\/', '\(', '\)' ), $endpoint['path'] ) . '#', $registered_route ) ) {
            $found = true;
            
            // Verifica metodi supportati
            if ( isset( $route_config[0] ) && is_array( $route_config[0] ) ) {
                $methods = isset( $route_config[0]['methods'] ) ? array_keys( $route_config[0]['methods'] ) : array();
                $found_methods = array_merge( $found_methods, $methods );
            }
            break;
        }
    }
    
    if ( $found ) {
        echo "   ✓ {$endpoint['path']} registrato";
        if ( ! empty( $found_methods ) ) {
            echo " (metodi: " . implode( ', ', array_unique( $found_methods ) ) . ")";
        }
        echo "\n";
        $success[] = "Endpoint {$endpoint['path']} registrato";
        
        // Verifica metodi richiesti
        $missing_methods = array_diff( $endpoint['methods'], $found_methods );
        if ( ! empty( $missing_methods ) ) {
            echo "      ⚠ Metodi mancanti: " . implode( ', ', $missing_methods ) . "\n";
            $warnings[] = "Endpoint {$endpoint['path']} manca metodi: " . implode( ', ', $missing_methods );
        }
    } else {
        echo "   ✗ {$endpoint['path']} NON registrato\n";
        $errors[] = "Endpoint {$endpoint['path']} non registrato";
    }
}

// Verifica endpoint con parametri dinamici
echo "\n2. Verifica Endpoint con Parametri Dinamici...\n";
$dynamic_endpoints = array(
    '/fpml/v1/translations/(?P<id>\d+)/regenerate',
    '/fpml/v1/translations/(?P<id>\d+)/versions',
    '/fpml/v1/translations/(?P<id>\d+)/rollback',
);

foreach ( $dynamic_endpoints as $endpoint_pattern ) {
    $found = false;
    foreach ( array_keys( $fpml_routes ) as $registered_route ) {
        // Normalizza pattern per confronto
        $normalized_pattern = str_replace( array( '?P<id>', '\d+' ), array( 'id', '\d+' ), $endpoint_pattern );
        if ( preg_match( '#^' . $normalized_pattern . '$#', $registered_route ) ) {
            $found = true;
            break;
        }
    }
    
    if ( $found ) {
        echo "   ✓ Pattern {$endpoint_pattern} registrato\n";
        $success[] = "Endpoint pattern {$endpoint_pattern} registrato";
    } else {
        echo "   ✗ Pattern {$endpoint_pattern} NON registrato\n";
        $warnings[] = "Endpoint pattern {$endpoint_pattern} non registrato";
    }
}

// Verifica permission callbacks
echo "\n3. Verifica Permission Callbacks...\n";
foreach ( $fpml_routes as $route => $handlers ) {
    if ( ! is_array( $handlers ) || empty( $handlers ) ) {
        continue;
    }
    
    foreach ( $handlers as $handler ) {
        if ( isset( $handler['permission_callback'] ) ) {
            echo "   ✓ {$route} ha permission callback\n";
            $success[] = "Route {$route} ha permission callback";
        } else {
            echo "   ⚠ {$route} NON ha permission callback\n";
            $warnings[] = "Route {$route} non ha permission callback";
        }
    }
}

// Lista tutte le route registrate
echo "\n4. Route FPML Registrate:\n";
foreach ( array_keys( $fpml_routes ) as $route ) {
    echo "   - {$route}\n";
}

// Summary
echo "\n=== RIEPILOGO ===\n";
echo "✓ Successi: " . count( $success ) . "\n";
echo "⚠ Warning: " . count( $warnings ) . "\n";
echo "✗ Errori: " . count( $errors ) . "\n";

if ( ! empty( $warnings ) ) {
    echo "\nWarning:\n";
    foreach ( $warnings as $warning ) {
        echo "  - {$warning}\n";
    }
}

if ( ! empty( $errors ) ) {
    echo "\nErrori:\n";
    foreach ( $errors as $error ) {
        echo "  - {$error}\n";
    }
    echo "\n⚠ ATTENZIONE: Ci sono errori che devono essere risolti!\n";
    exit( 1 );
} else {
    echo "\n✓ Tutti i test REST API passati!\n";
    exit( 0 );
}





