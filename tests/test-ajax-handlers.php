<?php
/**
 * Test AJAX Handlers - Verifica tutti gli AJAX handlers registrati
 * 
 * Questo script verifica che tutti gli AJAX handlers siano registrati correttamente.
 * Eseguire da WordPress admin o tramite WP-CLI: wp eval-file tests/test-ajax-handlers.php
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';
}

if ( ! defined( 'ABSPATH' ) ) {
    die( 'WordPress non trovato.' );
}

echo "=== TEST AJAX HANDLERS FP-MULTILANGUAGE ===\n\n";

$errors = array();
$warnings = array();
$success = array();

// Lista AJAX actions attesi
$expected_ajax_actions = array(
    // Admin.php
    'fpml_refresh_nonce' => array(
        'description' => 'Refresh nonce',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_refresh_nonce',
    ),
    'fpml_reindex_batch_ajax' => array(
        'description' => 'Reindex batch AJAX',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_reindex_batch_ajax',
    ),
    'fpml_cleanup_orphaned_pairs' => array(
        'description' => 'Cleanup orphaned pairs',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_cleanup_orphaned_pairs',
    ),
    'fpml_trigger_detection' => array(
        'description' => 'Trigger detection',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_trigger_detection',
    ),
    'fpml_bulk_translate' => array(
        'description' => 'Bulk translate',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_bulk_translate',
    ),
    'fpml_bulk_regenerate' => array(
        'description' => 'Bulk regenerate',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_bulk_regenerate',
    ),
    'fpml_bulk_sync' => array(
        'description' => 'Bulk sync',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_bulk_sync',
    ),
    'fpml_translate_single' => array(
        'description' => 'Translate single',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_translate_single',
    ),
    'fpml_translate_site_part' => array(
        'description' => 'Translate site part',
        'handler_class' => 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers',
        'handler_method' => 'handle_translate_site_part',
    ),
    // TranslationMetabox.php
    'fpml_force_translate_now' => array(
        'description' => 'Force translate now',
        'optional' => true, // Potrebbe essere gestito diversamente
    ),
    'fpml_get_translate_nonce' => array(
        'description' => 'Get translate nonce',
        'optional' => true,
    ),
    // BulkTranslator.php
    // 'fpml_bulk_translate' già presente sopra
    // PreviewInline.php
    'fpml_preview_translation' => array(
        'description' => 'Preview translation',
        'optional' => true,
    ),
    // TranslationHistoryUI.php
    'fpml_restore_version' => array(
        'description' => 'Restore version',
        'optional' => true,
    ),
);

echo "1. Verifica AJAX Actions Registrate...\n";
foreach ( $expected_ajax_actions as $action => $config ) {
    $action_hook = "wp_ajax_{$action}";
    $has_action = has_action( $action_hook );
    
    if ( $has_action ) {
        echo "   ✓ {$action_hook} registrato ({$config['description']})\n";
        $success[] = "AJAX action {$action} registrata";
        
        // Verifica handler class esiste
        if ( isset( $config['handler_class'] ) ) {
            if ( class_exists( $config['handler_class'] ) ) {
                echo "      ✓ Handler class {$config['handler_class']} esiste\n";
                $success[] = "Handler class per {$action} esiste";
                
                // Verifica metodo esiste
                if ( isset( $config['handler_method'] ) ) {
                    $handler_method = $config['handler_method'];
                    if ( method_exists( $config['handler_class'], $handler_method ) ) {
                        echo "      ✓ Handler method {$handler_method} esiste\n";
                        $success[] = "Handler method per {$action} esiste";
                    } else {
                        echo "      ✗ Handler method {$handler_method} NON esiste\n";
                        $warnings[] = "Handler method {$handler_method} non trovato per {$action}";
                    }
                }
            } else {
                echo "      ✗ Handler class {$config['handler_class']} NON esiste\n";
                $warnings[] = "Handler class non trovata per {$action}";
            }
        }
    } else {
        if ( isset( $config['optional'] ) && $config['optional'] ) {
            echo "   ⚠ {$action_hook} non registrato (opzionale: {$config['description']})\n";
            // Non è un errore se opzionale
        } else {
            echo "   ✗ {$action_hook} NON registrato ({$config['description']})\n";
            $errors[] = "AJAX action {$action} non registrata";
        }
    }
}

// Verifica classe AjaxHandlers
echo "\n2. Verifica Classe AjaxHandlers...\n";
if ( class_exists( 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers' ) ) {
    echo "   ✓ Classe AjaxHandlers trovata\n";
    $success[] = 'Classe AjaxHandlers trovata';
    
    // Verifica metodi principali
    $expected_methods = array(
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
    
    $handler_class = 'FP\\Multilanguage\\Admin\\Ajax\\AjaxHandlers';
    foreach ( $expected_methods as $method ) {
        if ( method_exists( $handler_class, $method ) ) {
            echo "   ✓ Metodo {$method} esiste\n";
            $success[] = "Metodo {$method} trovato";
        } else {
            echo "   ✗ Metodo {$method} NON esiste\n";
            $errors[] = "Metodo {$method} non trovato in AjaxHandlers";
        }
    }
} else {
    echo "   ✗ Classe AjaxHandlers NON trovata\n";
    $errors[] = 'Classe AjaxHandlers non trovata';
}

// Verifica nonce verification (static analysis dei file)
echo "\n3. Verifica Nonce Verification nei Handlers...\n";
$ajax_handlers_file = FPML_PLUGIN_DIR . 'src/Admin/Ajax/AjaxHandlers.php';
if ( file_exists( $ajax_handlers_file ) ) {
    $file_content = file_get_contents( $ajax_handlers_file );
    
    // Verifica presenza check_ajax_referer o wp_verify_nonce
    $has_nonce_check = (
        strpos( $file_content, 'check_ajax_referer' ) !== false ||
        strpos( $file_content, 'wp_verify_nonce' ) !== false ||
        strpos( $file_content, 'verify_nonce' ) !== false
    );
    
    if ( $has_nonce_check ) {
        echo "   ✓ File contiene nonce verification\n";
        $success[] = 'File AjaxHandlers contiene nonce verification';
    } else {
        echo "   ⚠ File potrebbe non avere nonce verification esplicita\n";
        $warnings[] = 'Nonce verification non verificata nel codice';
    }
    
    // Verifica permission checks
    $has_permission_check = (
        strpos( $file_content, 'current_user_can' ) !== false ||
        strpos( $file_content, 'check_permissions' ) !== false
    );
    
    if ( $has_permission_check ) {
        echo "   ✓ File contiene permission checks\n";
        $success[] = 'File AjaxHandlers contiene permission checks';
    } else {
        echo "   ⚠ File potrebbe non avere permission checks espliciti\n";
        $warnings[] = 'Permission checks non verificati nel codice';
    }
} else {
    echo "   ✗ File AjaxHandlers non trovato\n";
    $errors[] = 'File AjaxHandlers non trovato';
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
    echo "\n✓ Tutti i test AJAX handlers passati!\n";
    exit( 0 );
}





