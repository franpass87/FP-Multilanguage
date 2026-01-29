<?php
/**
 * Test Plugin Structure - Verifica struttura base plugin
 * 
 * Questo script verifica che tutte le componenti base del plugin siano presenti e caricabili.
 * Eseguire da WordPress admin o tramite WP-CLI: wp eval-file tests/test-plugin-structure.php
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Load WordPress if running standalone
    // Try multiple possible paths
    $possible_paths = array(
        dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php',
        __DIR__ . '/../../../../wp-load.php',
        dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php',
    );
    
    $wp_loaded = false;
    foreach ( $possible_paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if ( ! $wp_loaded ) {
        // Try to find wp-load.php in parent directories
        $current_dir = __DIR__;
        for ( $i = 0; $i < 10; $i++ ) {
            $test_path = $current_dir . str_repeat( '/..', $i ) . '/wp-load.php';
            $real_path = realpath( $test_path );
            if ( $real_path && file_exists( $real_path ) ) {
                require_once $real_path;
                $wp_loaded = true;
                break;
            }
        }
    }
}

if ( ! defined( 'ABSPATH' ) ) {
    die( 'WordPress non trovato. Eseguire questo script da WordPress admin o WP-CLI.' );
}

echo "=== TEST STRUTTURA PLUGIN FP-MULTILANGUAGE ===\n\n";

$errors = array();
$warnings = array();
$success = array();

// 1. Verifica autoloader
echo "1. Verifica Autoloader Composer...\n";
if ( file_exists( FPML_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    echo "   ✓ Autoloader trovato\n";
    $success[] = 'Autoloader presente';
} else {
    echo "   ✗ Autoloader NON trovato\n";
    $errors[] = 'Autoloader Composer mancante';
}

// 2. Verifica classi core
echo "\n2. Verifica Classi Core...\n";
$core_classes = array(
    'FP\\Multilanguage\\Core\\Plugin',
    'FP\\Multilanguage\\Core\\Container',
    'FP\\Multilanguage\\Settings',
    'FP\\Multilanguage\\Logger',
    'FP\\Multilanguage\\Queue',
    'FP\\Multilanguage\\Admin\\Admin',
    'FP\\Multilanguage\\Kernel\\Plugin',
    'FP\\Multilanguage\\Kernel\\Bootstrap',
);

foreach ( $core_classes as $class ) {
    if ( class_exists( $class ) ) {
        echo "   ✓ {$class}\n";
        $success[] = "Classe {$class} caricabile";
    } else {
        echo "   ✗ {$class} NON trovata\n";
        $errors[] = "Classe {$class} non trovata";
    }
}

// 3. Verifica pagine admin
echo "\n3. Verifica Pagine Admin...\n";
if ( class_exists( 'FP\\Multilanguage\\Admin\\Admin' ) ) {
    $admin = FP\Multilanguage\Admin\Admin::instance();
    if ( $admin ) {
        echo "   ✓ Admin instance creata\n";
        $success[] = 'Admin instance creabile';
    } else {
        echo "   ✗ Impossibile creare Admin instance\n";
        $errors[] = 'Admin instance non creabile';
    }
} else {
    echo "   ✗ Classe Admin non trovata\n";
    $errors[] = 'Classe Admin non trovata';
}

// 4. Verifica view files
echo "\n4. Verifica View Files Admin...\n";
$view_files = array(
    'settings-dashboard.php',
    'settings-general.php',
    'settings-content.php',
    'settings-strings.php',
    'settings-glossary.php',
    'settings-seo.php',
    'settings-export.php',
    'settings-plugin-compatibility.php',
    'settings-site-parts.php',
    'settings-translations.php',
    'settings-diagnostics.php',
);

foreach ( $view_files as $view_file ) {
    $old_path = FPML_PLUGIN_DIR . 'admin/views/' . $view_file;
    $new_path = FPML_PLUGIN_DIR . 'src/Admin/Views/' . $view_file;
    
    if ( file_exists( $old_path ) || file_exists( $new_path ) ) {
        echo "   ✓ {$view_file}\n";
        $success[] = "View {$view_file} trovata";
    } else {
        echo "   ✗ {$view_file} NON trovata\n";
        $warnings[] = "View {$view_file} non trovata";
    }
}

// 5. Verifica AJAX handlers
echo "\n5. Verifica AJAX Handlers...\n";
if ( class_exists( 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers' ) ) {
    echo "   ✓ Classe AjaxHandlers trovata\n";
    $success[] = 'Classe AjaxHandlers trovata';
    
    $ajax_actions = array(
        'fpml_refresh_nonce',
        'fpml_reindex_batch_ajax',
        'fpml_cleanup_orphaned_pairs',
        'fpml_trigger_detection',
        'fpml_bulk_translate',
        'fpml_bulk_regenerate',
        'fpml_bulk_sync',
        'fpml_translate_single',
        'fpml_translate_site_part',
    );
    
    foreach ( $ajax_actions as $action ) {
        if ( has_action( "wp_ajax_{$action}" ) ) {
            echo "   ✓ wp_ajax_{$action} registrato\n";
            $success[] = "AJAX action {$action} registrata";
        } else {
            echo "   ✗ wp_ajax_{$action} NON registrato\n";
            $warnings[] = "AJAX action {$action} non registrata";
        }
    }
} else {
    echo "   ✗ Classe AjaxHandlers non trovata\n";
    $errors[] = 'Classe AjaxHandlers non trovata';
}

// 6. Verifica REST API routes
echo "\n6. Verifica REST API Routes...\n";
if ( class_exists( 'WP_REST_Server' ) ) {
    $rest_server = rest_get_server();
    $routes = $rest_server->get_routes();
    
    $fpml_routes = array_filter( array_keys( $routes ), function( $route ) {
        return strpos( $route, '/fpml/v1/' ) === 0;
    } );
    
    $expected_routes = array(
        '/fpml/v1/health',
        '/fpml/v1/stats',
        '/fpml/v1/logs',
        '/fpml/v1/queue/run',
        '/fpml/v1/queue/cleanup',
        '/fpml/v1/test-provider',
        '/fpml/v1/preview-translation',
        '/fpml/v1/check-billing',
        '/fpml/v1/refresh-nonce',
        '/fpml/v1/reindex',
        '/fpml/v1/reindex-batch',
        '/fpml/v1/translations',
        '/fpml/v1/translations/bulk',
    );
    
    foreach ( $expected_routes as $route ) {
        $found = false;
        foreach ( $fpml_routes as $registered_route ) {
            if ( strpos( $registered_route, $route ) === 0 ) {
                $found = true;
                break;
            }
        }
        
        if ( $found ) {
            echo "   ✓ {$route} registrato\n";
            $success[] = "REST route {$route} registrata";
        } else {
            echo "   ✗ {$route} NON registrato\n";
            $warnings[] = "REST route {$route} non registrata";
        }
    }
    
    echo "   Totale route FPML registrate: " . count( $fpml_routes ) . "\n";
} else {
    echo "   ✗ REST Server non disponibile\n";
    $warnings[] = 'REST Server non disponibile';
}

// 7. Verifica integrazioni
echo "\n7. Verifica Classi Integrazioni...\n";
$integration_classes = array(
    'FP\\Multilanguage\\Integrations\\WooCommerceSupport',
    'FP\\Multilanguage\\Integrations\\SalientThemeSupport',
    'FP\\Multilanguage\\Integrations\\FpSeoSupport',
    'FP\\Multilanguage\\Integrations\\MenuSync',
    'FP\\Multilanguage\\Integrations\\WPBakerySupport',
);

foreach ( $integration_classes as $class ) {
    if ( class_exists( $class ) ) {
        echo "   ✓ {$class}\n";
        $success[] = "Integrazione {$class} trovata";
    } else {
        echo "   ⚠ {$class} (opzionale)\n";
        // Non è un errore se non presente, sono integrazioni opzionali
    }
}

// 8. Verifica routing frontend
echo "\n8. Verifica Routing Frontend...\n";
if ( class_exists( 'FP\\Multilanguage\\Frontend\\Routing\\Rewrites' ) ) {
    echo "   ✓ Classe Rewrites trovata\n";
    $success[] = 'Classe Rewrites trovata';
} else {
    echo "   ✗ Classe Rewrites non trovata\n";
    $errors[] = 'Classe Rewrites non trovata';
}

// 9. Verifica database tables
echo "\n9. Verifica Database Tables...\n";
global $wpdb;
$queue_table = $wpdb->prefix . 'FPML_queue';
$queue_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$queue_table}'" ) === $queue_table;

if ( $queue_exists ) {
    echo "   ✓ Tabella {$queue_table} esiste\n";
    $success[] = "Tabella {$queue_table} presente";
} else {
    echo "   ⚠ Tabella {$queue_table} non trovata (verrà creata al primo utilizzo)\n";
    $warnings[] = "Tabella {$queue_table} non trovata";
}

// 10. Verifica costanti
echo "\n10. Verifica Costanti Plugin...\n";
$constants = array(
    'FPML_PLUGIN_VERSION',
    'FPML_PLUGIN_FILE',
    'FPML_PLUGIN_DIR',
    'FPML_PLUGIN_URL',
);

foreach ( $constants as $constant ) {
    if ( defined( $constant ) ) {
        $value = constant( $constant );
        echo "   ✓ {$constant} = " . ( is_string( $value ) && strlen( $value ) > 50 ? substr( $value, 0, 50 ) . '...' : $value ) . "\n";
        $success[] = "Costante {$constant} definita";
    } else {
        echo "   ✗ {$constant} NON definita\n";
        $errors[] = "Costante {$constant} non definita";
    }
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
    echo "\n✓ Tutti i test strutturali passati!\n";
    exit( 0 );
}

