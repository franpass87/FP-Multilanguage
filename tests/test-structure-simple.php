<?php
/**
 * Test Struttura Semplificato - Verifica struttura senza WordPress
 * 
 * Questo script verifica la struttura base del plugin senza richiedere WordPress.
 * Utile per verificare che tutti i file siano presenti.
 *
 * @package FP_Multilanguage
 */

$plugin_dir = __DIR__ . '/..';
$plugin_dir = realpath( $plugin_dir ) ?: $plugin_dir;

echo "=== TEST STRUTTURA PLUGIN FP-MULTILANGUAGE (Semplificato) ===\n\n";

$errors = array();
$warnings = array();
$success = array();

// 1. Verifica file principale
echo "1. Verifica File Principale...\n";
$main_file = $plugin_dir . '/fp-multilanguage.php';
if ( file_exists( $main_file ) ) {
    echo "   ✓ fp-multilanguage.php trovato\n";
    $success[] = 'File principale presente';
    
    // Verifica costanti nel file
    $content = file_get_contents( $main_file );
    if ( strpos( $content, 'FPML_PLUGIN_VERSION' ) !== false ) {
        echo "   ✓ Costante FPML_PLUGIN_VERSION presente\n";
        $success[] = 'Costante versione presente';
    }
    if ( strpos( $content, 'FPML_PLUGIN_DIR' ) !== false ) {
        echo "   ✓ Costante FPML_PLUGIN_DIR presente\n";
        $success[] = 'Costante directory presente';
    }
} else {
    echo "   ✗ fp-multilanguage.php NON trovato\n";
    $errors[] = 'File principale mancante';
}

// 2. Verifica autoloader
echo "\n2. Verifica Autoloader Composer...\n";
$autoload = $plugin_dir . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    echo "   ✓ vendor/autoload.php trovato\n";
    $success[] = 'Autoloader presente';
} else {
    echo "   ⚠ vendor/autoload.php NON trovato (eseguire: composer install)\n";
    $warnings[] = 'Autoloader non presente (normale se composer install non eseguito)';
}

// 3. Verifica directory src
echo "\n3. Verifica Directory src/...\n";
$src_dir = $plugin_dir . '/src';
if ( is_dir( $src_dir ) ) {
    echo "   ✓ Directory src/ trovata\n";
    $success[] = 'Directory src presente';
    
    // Verifica alcune classi chiave
    $key_classes = array(
        'Core/Plugin.php',
        'Admin/Admin.php',
        'Settings.php',
        'Queue.php',
        'Frontend/Routing/Rewrites.php',
    );
    
    foreach ( $key_classes as $class_file ) {
        $file_path = $src_dir . '/' . $class_file;
        if ( file_exists( $file_path ) ) {
            echo "   ✓ {$class_file}\n";
            $success[] = "File {$class_file} presente";
        } else {
            echo "   ✗ {$class_file} NON trovato\n";
            $warnings[] = "File {$class_file} non trovato";
        }
    }
} else {
    echo "   ✗ Directory src/ NON trovata\n";
    $errors[] = 'Directory src mancante';
}

// 4. Verifica directory admin/views
echo "\n4. Verifica Directory admin/views/...\n";
$views_dir = $plugin_dir . '/admin/views';
if ( is_dir( $views_dir ) ) {
    echo "   ✓ Directory admin/views/ trovata\n";
    $success[] = 'Directory admin/views presente';
    
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
        $file_path = $views_dir . '/' . $view_file;
        if ( file_exists( $file_path ) ) {
            echo "   ✓ {$view_file}\n";
            $success[] = "View {$view_file} presente";
        } else {
            echo "   ✗ {$view_file} NON trovato\n";
            $warnings[] = "View {$view_file} non trovata";
        }
    }
} else {
    echo "   ⚠ Directory admin/views/ NON trovata\n";
    $warnings[] = 'Directory admin/views non trovata';
}

// 5. Verifica composer.json
echo "\n5. Verifica composer.json...\n";
$composer_json = $plugin_dir . '/composer.json';
if ( file_exists( $composer_json ) ) {
    echo "   ✓ composer.json trovato\n";
    $success[] = 'composer.json presente';
    
    $composer_data = json_decode( file_get_contents( $composer_json ), true );
    if ( $composer_data && isset( $composer_data['autoload'] ) ) {
        echo "   ✓ Autoload configurato\n";
        $success[] = 'Autoload configurato in composer.json';
        
        if ( isset( $composer_data['autoload']['psr-4'] ) ) {
            $psr4 = $composer_data['autoload']['psr-4'];
            echo "   ✓ PSR-4 namespaces configurati: " . count( $psr4 ) . "\n";
            foreach ( $psr4 as $namespace => $path ) {
                echo "      - {$namespace} → {$path}\n";
            }
            $success[] = 'PSR-4 configurato';
        }
    }
} else {
    echo "   ✗ composer.json NON trovato\n";
    $errors[] = 'composer.json mancante';
}

// 6. Verifica file di test
echo "\n6. Verifica File di Test...\n";
$test_files = array(
    'test-plugin-structure.php',
    'test-rest-api-endpoints.php',
    'test-ajax-handlers.php',
    'test-frontend-routing.php',
);

foreach ( $test_files as $test_file ) {
    $file_path = __DIR__ . '/' . $test_file;
    if ( file_exists( $file_path ) ) {
        echo "   ✓ {$test_file}\n";
        $success[] = "Test file {$test_file} presente";
    } else {
        echo "   ✗ {$test_file} NON trovato\n";
        $warnings[] = "Test file {$test_file} non trovato";
    }
}

// 7. Verifica directory integrazioni
echo "\n7. Verifica Directory Integrazioni...\n";
$integrations_dir = $plugin_dir . '/src/Integrations';
if ( is_dir( $integrations_dir ) ) {
    echo "   ✓ Directory Integrations/ trovata\n";
    $success[] = 'Directory Integrations presente';
    
    $integration_files = array(
        'WooCommerceSupport.php',
        'SalientThemeSupport.php',
        'FpSeoSupport.php',
        'MenuSync.php',
        'WPBakerySupport.php',
    );
    
    foreach ( $integration_files as $integration_file ) {
        $file_path = $integrations_dir . '/' . $integration_file;
        if ( file_exists( $file_path ) ) {
            echo "   ✓ {$integration_file}\n";
            $success[] = "Integrazione {$integration_file} presente";
        } else {
            echo "   ⚠ {$integration_file} (opzionale)\n";
        }
    }
} else {
    echo "   ⚠ Directory Integrations/ NON trovata\n";
    $warnings[] = 'Directory Integrations non trovata';
}

// 8. Verifica documentazione test
echo "\n8. Verifica Documentazione Test...\n";
$docs = array(
    'TEST-PLAN-EXECUTION.md',
    'TEST-REPORT-EXECUTION.md',
    'TEST-EXECUTION-GUIDE.md',
    'TEST-COMPLETE-REPORT.md',
    'TEST-SUMMARY.md',
    'TEST-EXECUTION-STATUS.md',
);

foreach ( $docs as $doc ) {
    $file_path = $plugin_dir . '/' . $doc;
    if ( file_exists( $file_path ) ) {
        echo "   ✓ {$doc}\n";
        $success[] = "Documentazione {$doc} presente";
    } else {
        echo "   ⚠ {$doc} NON trovato\n";
        $warnings[] = "Documentazione {$doc} non trovata";
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
    echo "\n✓ Struttura plugin verificata correttamente!\n";
    echo "\nNOTA: Questo test verifica solo la struttura dei file.\n";
    echo "Per test completi con WordPress, eseguire i test completi.\n";
    exit( 0 );
}

