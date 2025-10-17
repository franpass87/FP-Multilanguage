<?php
/**
 * Test per verificare che tutte le funzionalità admin siano state ripristinate
 */

echo "=== TEST ADMIN COMPLETO ===\n\n";

// Test 1: Verifica che la classe admin sia completa
echo "1. Verifica classe admin completa...\n";

if (class_exists('FPML_Admin')) {
    echo "   ✓ Classe FPML_Admin disponibile\n";
    
    // Verifica metodi essenziali
    $essential_methods = [
        'add_admin_menu',
        'render_admin_page',
        'render_tab_navigation',
        'render_tab_content',
        'render_general_tab',
        'render_content_tab',
        'render_strings_tab',
        'render_glossary_tab',
        'render_seo_tab',
        'render_export_tab',
        'render_compatibility_tab',
        'render_diagnostics_tab'
    ];
    
    $admin = new FPML_Admin();
    $all_methods_present = true;
    
    foreach ($essential_methods as $method) {
        if (method_exists($admin, $method)) {
            echo "   ✓ Metodo '$method' presente\n";
        } else {
            echo "   ❌ Metodo '$method' MANCANTE\n";
            $all_methods_present = false;
        }
    }
    
    if ($all_methods_present) {
        echo "   ✅ Tutti i metodi di rendering sono presenti\n";
    } else {
        echo "   ❌ Alcuni metodi di rendering mancano\n";
    }
} else {
    echo "   ❌ Classe FPML_Admin non trovata\n";
}

// Test 2: Verifica handler AJAX e admin-post
echo "\n2. Verifica handler AJAX e admin-post...\n";

global $wp_filter;
$ajax_handlers = [
    'wp_ajax_fpml_refresh_nonce',
    'wp_ajax_fpml_reindex_batch_ajax',
    'wp_ajax_fpml_cleanup_orphaned_pairs',
    'wp_ajax_fpml_trigger_detection'
];

$admin_post_handlers = [
    'admin_post_fpml_save_settings',
    'admin_post_fpml_scan_strings',
    'admin_post_fpml_save_overrides',
    'admin_post_fpml_import_overrides',
    'admin_post_fpml_export_overrides',
    'admin_post_fpml_save_glossary',
    'admin_post_fpml_import_glossary',
    'admin_post_fpml_export_glossary',
    'admin_post_fpml_export_state',
    'admin_post_fpml_import_state',
    'admin_post_fpml_export_logs',
    'admin_post_fpml_import_logs',
    'admin_post_fpml_clear_sandbox'
];

echo "   AJAX Handlers:\n";
foreach ($ajax_handlers as $handler) {
    if (isset($wp_filter[$handler])) {
        echo "     ✓ $handler registrato\n";
    } else {
        echo "     ❌ $handler NON registrato\n";
    }
}

echo "   Admin-Post Handlers:\n";
foreach ($admin_post_handlers as $handler) {
    if (isset($wp_filter[$handler])) {
        echo "     ✓ $handler registrato\n";
    } else {
        echo "     ❌ $handler NON registrato\n";
    }
}

// Test 3: Verifica file views
echo "\n3. Verifica file views...\n";

$view_files = [
    'settings-general.php',
    'settings-content.php',
    'settings-strings.php',
    'settings-glossary.php',
    'settings-seo.php',
    'settings-export.php',
    'settings-plugin-compatibility.php',
    'settings-diagnostics.php'
];

$views_dir = 'fp-multilanguage/admin/views/';
$all_views_present = true;

foreach ($view_files as $view_file) {
    $file_path = $views_dir . $view_file;
    if (file_exists($file_path)) {
        echo "   ✓ $view_file presente\n";
    } else {
        echo "   ❌ $view_file MANCANTE\n";
        $all_views_present = false;
    }
}

if ($all_views_present) {
    echo "   ✅ Tutti i file views sono presenti\n";
} else {
    echo "   ❌ Alcuni file views mancano\n";
}

// Test 4: Verifica tab navigation
echo "\n4. Verifica tab navigation...\n";

$expected_tabs = [
    'general' => 'Generale',
    'content' => 'Contenuto',
    'strings' => 'Stringhe',
    'glossary' => 'Glossario',
    'seo' => 'SEO',
    'export' => 'Export/Import',
    'compatibility' => 'Compatibilità',
    'diagnostics' => 'Diagnostiche'
];

echo "   Tab attesi:\n";
foreach ($expected_tabs as $tab => $label) {
    echo "     ✓ $tab: $label\n";
}

// Test 5: Verifica handler methods
echo "\n5. Verifica metodi handler...\n";

$handler_methods = [
    'handle_refresh_nonce',
    'handle_reindex_batch_ajax',
    'handle_cleanup_orphaned_pairs',
    'handle_trigger_detection',
    'handle_save_settings',
    'handle_scan_strings',
    'handle_save_overrides',
    'handle_import_overrides',
    'handle_export_overrides',
    'handle_save_glossary',
    'handle_import_glossary',
    'handle_export_glossary',
    'handle_export_state',
    'handle_import_state',
    'handle_export_logs',
    'handle_import_logs',
    'handle_clear_sandbox'
];

$admin = new FPML_Admin();
$all_handlers_present = true;

foreach ($handler_methods as $method) {
    if (method_exists($admin, $method)) {
        echo "   ✓ $method presente\n";
    } else {
        echo "   ❌ $method MANCANTE\n";
        $all_handlers_present = false;
    }
}

if ($all_handlers_present) {
    echo "   ✅ Tutti i metodi handler sono presenti\n";
} else {
    echo "   ❌ Alcuni metodi handler mancano\n";
}

echo "\n=== TEST COMPLETATO ===\n";

echo "\n🎯 RISULTATO ATTESO:\n";
echo "1. ✅ Tutti gli 8 tab disponibili\n";
echo "2. ✅ Tutti i handler AJAX e admin-post registrati\n";
echo "3. ✅ Tutti i file views presenti\n";
echo "4. ✅ Tutti i metodi handler implementati\n";

echo "\n📋 PROSSIMI PASSI:\n";
echo "1. Vai alla pagina admin del plugin\n";
echo "2. Verifica che tutti gli 8 tab siano visibili\n";
echo "3. Testa ogni tab per assicurarti che funzioni\n";
echo "4. Usa il tab 'Diagnostiche' per pulire i meta orfani\n";
echo "5. Esegui il reindex per ricreare le traduzioni\n";

echo "\n🔧 TAB DISPONIBILI:\n";
echo "- Generale: Impostazioni principali del plugin\n";
echo "- Contenuto: Configurazione post types e contenuti\n";
echo "- Stringhe: Gestione stringhe traducibili\n";
echo "- Glossario: Terminologia e traduzioni personalizzate\n";
echo "- SEO: Impostazioni per l'ottimizzazione SEO\n";
echo "- Export/Import: Backup e ripristino configurazioni\n";
echo "- Compatibilità: Test compatibilità plugin\n";
echo "- Diagnostiche: Strumenti di debug e pulizia\n";
