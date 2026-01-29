<?php
/**
 * Test Struttura Codice - Verifica struttura classi e metodi senza WordPress
 * 
 * Questo script verifica che tutte le classi e metodi necessari esistano nel codice.
 * Può essere eseguito senza WordPress.
 *
 * @package FP_Multilanguage
 */

$plugin_dir = __DIR__ . '/..';
$plugin_dir = realpath( $plugin_dir ) ?: $plugin_dir;

echo "=== TEST STRUTTURA CODICE FP-MULTILANGUAGE ===\n\n";

$errors = array();
$warnings = array();
$success = array();

// Non caricare autoloader - verifichiamo solo i file

// 1. Verifica classi REST API
echo "1. Verifica Classi REST API...\n";
$rest_classes = array(
    'FP\\Multilanguage\\Rest\\RouteRegistrar',
    'FP\\Multilanguage\\Rest\\PermissionChecker',
    'FP\\Multilanguage\\Rest\\Handlers\\QueueHandler',
    'FP\\Multilanguage\\Rest\\Handlers\\ProviderHandler',
    'FP\\Multilanguage\\Rest\\Handlers\\ReindexHandler',
    'FP\\Multilanguage\\Rest\\Handlers\\SystemHandler',
    'FP\\Multilanguage\\Rest\\Handlers\\TranslationHandler',
);

foreach ( $rest_classes as $class ) {
    // Verifica solo se il file esiste (non carichiamo le classi senza WordPress)
    $class_path = str_replace( 'FP\\Multilanguage\\', $plugin_dir . '/src/', $class );
    $class_path = str_replace( '\\', '/', $class_path ) . '.php';
    
    if ( file_exists( $class_path ) ) {
        echo "   ✓ {$class} (file presente)\n";
        $success[] = "File per {$class} presente";
        
        // Verifica metodo register_routes se RouteRegistrar
        if ( $class === 'FP\\Multilanguage\\Rest\\RouteRegistrar' ) {
            $content = file_get_contents( $class_path );
            if ( strpos( $content, 'register_routes' ) !== false || strpos( $content, 'function register_routes' ) !== false || strpos( $content, 'public function register_routes' ) !== false ) {
                echo "      ✓ register_routes() presente nel codice\n";
                $success[] = "Metodo register_routes() presente";
            }
        }
    } else {
        echo "   ✗ {$class} (file NON trovato: {$class_path})\n";
        $errors[] = "File per {$class} mancante";
    }
}

// 2. Verifica AJAX Handlers
echo "\n2. Verifica AJAX Handlers...\n";
$ajax_file = $plugin_dir . '/src/Admin/Ajax/AjaxHandlers.php';
if ( file_exists( $ajax_file ) ) {
    echo "   ✓ AjaxHandlers.php trovato\n";
    $success[] = 'File AjaxHandlers.php presente';
    
    $content = file_get_contents( $ajax_file );
    
    // Verifica metodi AJAX
    $ajax_methods = array(
        'handle_refresh_nonce',
        'handle_reindex_batch_ajax',
        'handle_cleanup_orphaned_pairs',
        'handle_trigger_detection',
        'handle_bulk_translate',
        'handle_bulk_regenerate',
        'handle_bulk_sync',
        'handle_translate_single',
        'handle_translate_site_part',
    );
    
    foreach ( $ajax_methods as $method ) {
        if ( strpos( $content, "function {$method}" ) !== false || strpos( $content, "public function {$method}" ) !== false ) {
            echo "   ✓ {$method}() presente\n";
            $success[] = "Metodo AJAX {$method}() presente";
        } else {
            echo "   ✗ {$method}() NON trovato\n";
            $warnings[] = "Metodo AJAX {$method}() non trovato";
        }
    }
    
    // Verifica nonce checks
    if ( strpos( $content, 'check_ajax_referer' ) !== false || strpos( $content, 'wp_verify_nonce' ) !== false ) {
        echo "   ✓ Nonce verification presente nel codice\n";
        $success[] = 'Nonce verification presente';
    } else {
        echo "   ⚠ Nonce verification non verificata\n";
        $warnings[] = 'Nonce verification non verificata';
    }
    
    // Verifica permission checks
    if ( strpos( $content, 'current_user_can' ) !== false ) {
        echo "   ✓ Permission checks presenti nel codice\n";
        $success[] = 'Permission checks presenti';
    } else {
        echo "   ⚠ Permission checks non verificati\n";
        $warnings[] = 'Permission checks non verificati';
    }
} else {
    echo "   ✗ AjaxHandlers.php NON trovato\n";
    $errors[] = 'File AjaxHandlers.php mancante';
}

// 3. Verifica Routing Frontend
echo "\n3. Verifica Routing Frontend...\n";
$routing_classes_required = array(
    'FP\\Multilanguage\\Frontend\\Routing\\Rewrites',
    'FP\\Multilanguage\\Frontend\\Routing\\RewriteRules',
);

$routing_classes_optional = array(
    'FP\\Multilanguage\\Frontend\\Routing\\QueryFilter',
    'FP\\Multilanguage\\Frontend\\Routing\\PostResolver',
    'FP\\Multilanguage\\Frontend\\Routing\\RequestHandler',
    'FP\\Multilanguage\\Frontend\\Routing\\AdjacentPostFilter',
);

// Verifica classi richieste
foreach ( $routing_classes_required as $class ) {
    $class_path = str_replace( 'FP\\Multilanguage\\', $plugin_dir . '/src/', $class );
    $class_path = str_replace( '\\', '/', $class_path ) . '.php';
    
    if ( file_exists( $class_path ) ) {
        echo "   ✓ {$class} (file presente)\n";
        $success[] = "File per {$class} presente";
        
        // Verifica metodo principale
        $content = file_get_contents( $class_path );
        if ( $class === 'FP\\Multilanguage\\Frontend\\Routing\\Rewrites' ) {
            if ( strpos( $content, 'register_rewrites' ) !== false || strpos( $content, 'function register_rewrites' ) !== false ) {
                echo "      ✓ register_rewrites() presente\n";
                $success[] = "Metodo register_rewrites() presente";
            }
            // Verifica che le classi referenziate siano almeno menzionate
            foreach ( $routing_classes_optional as $opt_class ) {
                $opt_class_name = substr( strrchr( $opt_class, '\\' ), 1 );
                if ( strpos( $content, $opt_class_name ) !== false || strpos( $content, str_replace( '\\', '\\\\', $opt_class ) ) !== false ) {
                    echo "      ✓ {$opt_class_name} referenziato in Rewrites.php\n";
                    $success[] = "Classe {$opt_class_name} referenziata";
                }
            }
        }
    } else {
        echo "   ✗ {$class} (file NON trovato)\n";
        $errors[] = "File per {$class} mancante";
    }
}

// Verifica classi opzionali (potrebbero essere in altri file o namespace)
foreach ( $routing_classes_optional as $class ) {
    $class_path = str_replace( 'FP\\Multilanguage\\', $plugin_dir . '/src/', $class );
    $class_path = str_replace( '\\', '/', $class_path ) . '.php';
    
    if ( file_exists( $class_path ) ) {
        echo "   ✓ {$class} (file presente)\n";
        $success[] = "File per {$class} presente";
    } else {
        // Verifica se è referenziato in Rewrites.php (potrebbe essere inline o in altro namespace)
        $rewrites_file = $plugin_dir . '/src/Frontend/Routing/Rewrites.php';
        if ( file_exists( $rewrites_file ) ) {
            $content = file_get_contents( $rewrites_file );
            $class_name = substr( strrchr( $class, '\\' ), 1 );
            if ( strpos( $content, $class_name ) !== false ) {
                echo "   ⚠ {$class} (referenziato ma file separato non trovato - potrebbe essere inline o in altro namespace)\n";
                $warnings[] = "Classe {$class} referenziata ma file separato non trovato";
            }
        }
    }
}

// 4. Verifica Translation Manager
echo "\n4. Verifica Translation Manager...\n";
$translation_file = $plugin_dir . '/src/Content/TranslationManager.php';
if ( file_exists( $translation_file ) ) {
    echo "   ✓ TranslationManager.php trovato\n";
    $success[] = 'File TranslationManager.php presente';
} else {
    echo "   ✗ TranslationManager.php NON trovato\n";
    $errors[] = 'File TranslationManager.php mancante';
}

$job_enqueuer_file = $plugin_dir . '/src/Translation/JobEnqueuer.php';
if ( file_exists( $job_enqueuer_file ) ) {
    echo "   ✓ JobEnqueuer.php trovato\n";
    $success[] = 'File JobEnqueuer.php presente';
} else {
    echo "   ⚠ JobEnqueuer.php NON trovato\n";
    $warnings[] = 'File JobEnqueuer.php non trovato';
}

// 5. Verifica Queue
echo "\n5. Verifica Queue System...\n";
$queue_file = $plugin_dir . '/src/Queue.php';
if ( file_exists( $queue_file ) ) {
    echo "   ✓ Queue.php trovato\n";
    $success[] = 'File Queue.php presente';
} else {
    $queue_file = $plugin_dir . '/src/Queue/Queue.php';
    if ( file_exists( $queue_file ) ) {
        echo "   ✓ Queue/Queue.php trovato\n";
        $success[] = 'File Queue/Queue.php presente';
    } else {
        echo "   ⚠ Queue.php NON trovato\n";
        $warnings[] = 'File Queue.php non trovato';
    }
}

// 6. Verifica Integrazioni
echo "\n6. Verifica File Integrazioni...\n";
$integrations = array(
    'WooCommerceSupport.php',
    'SalientThemeSupport.php',
    'FpSeoSupport.php',
    'WPBakerySupport.php',
);

$integrations_dir = $plugin_dir . '/src/Integrations';
foreach ( $integrations as $integration ) {
    $file_path = $integrations_dir . '/' . $integration;
    if ( file_exists( $file_path ) ) {
        echo "   ✓ {$integration}\n";
        $success[] = "Integrazione {$integration} presente";
    } else {
        echo "   ⚠ {$integration} NON trovato\n";
        $warnings[] = "Integrazione {$integration} non trovata";
    }
}

// 7. Verifica CLI
echo "\n7. Verifica CLI Commands...\n";
$cli_file = $plugin_dir . '/src/CLI/CLI.php';
if ( file_exists( $cli_file ) ) {
    echo "   ✓ CLI.php trovato\n";
    $success[] = 'File CLI.php presente';
    
    $content = file_get_contents( $cli_file );
    $cli_commands = array( 'queue', 'run', 'status' );
    foreach ( $cli_commands as $cmd ) {
        if ( strpos( $content, $cmd ) !== false ) {
            echo "      ✓ Comando '{$cmd}' referenziato\n";
            $success[] = "Comando CLI '{$cmd}' presente";
        }
    }
} else {
    echo "   ⚠ CLI.php NON trovato\n";
    $warnings[] = 'File CLI.php non trovato';
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
    echo "\n✓ Struttura codice verificata correttamente!\n";
    echo "\nNOTA: Questo test verifica solo la struttura dei file e metodi.\n";
    echo "Per test completi con WordPress, eseguire i test completi.\n";
    exit( 0 );
}

