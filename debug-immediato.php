<?php
/**
 * Script di debug immediato per verificare se la soluzione funziona
 */

echo "=== DEBUG IMMEDIATO SOLUZIONE NONCE ===\n\n";

// Test 1: Verifica ambiente WordPress
echo "1. Verifica ambiente WordPress...\n";
if (defined('ABSPATH')) {
    echo "   ✓ WordPress caricato correttamente\n";
    echo "   ✓ ABSPATH: " . ABSPATH . "\n";
} else {
    echo "   ❌ WordPress non caricato\n";
    exit(1);
}

// Test 2: Verifica plugin attivo
echo "\n2. Verifica plugin FPML...\n";
if (class_exists('FPML_Admin')) {
    echo "   ✓ Classe FPML_Admin disponibile\n";
} else {
    echo "   ❌ Classe FPML_Admin non trovata\n";
}

if (class_exists('FPML_Settings')) {
    echo "   ✓ Classe FPML_Settings disponibile\n";
} else {
    echo "   ❌ Classe FPML_Settings non trovata\n";
}

// Test 3: Verifica hook registrati
echo "\n3. Verifica hook registrati...\n";
global $wp_filter;

$hooks_to_check = [
    'admin_post_fpml_save_settings',
    'wp_ajax_fpml_refresh_nonce',
    'plugins_loaded',
    'init',
    'admin_init'
];

foreach ($hooks_to_check as $hook) {
    if (isset($wp_filter[$hook])) {
        echo "   ✓ Hook '$hook': REGISTRATO\n";
    } else {
        echo "   ❌ Hook '$hook': NON REGISTRATO\n";
    }
}

// Test 4: Verifica metodi classe
echo "\n4. Verifica metodi classe admin...\n";
$admin_methods = [
    'handle_save_settings',
    'handle_refresh_nonce',
    'handle_expired_nonce_early',
    'handle_expired_nonce_very_early'
];

foreach ($admin_methods as $method) {
    if (method_exists('FPML_Admin', $method)) {
        echo "   ✓ Metodo '$method': PRESENTE\n";
    } else {
        echo "   ❌ Metodo '$method': MANCANTE\n";
    }
}

// Test 5: Verifica file modificati
echo "\n5. Verifica file modificati...\n";
$files_to_check = [
    'fp-multilanguage/admin/class-admin.php',
    'fp-multilanguage/admin/views/settings-diagnostics.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'handle_save_settings') !== false) {
            echo "   ✓ File '$file': MODIFICATO CORRETTAMENTE\n";
        } else {
            echo "   ❌ File '$file': NON MODIFICATO\n";
        }
    } else {
        echo "   ❌ File '$file': NON TROVATO\n";
    }
}

// Test 6: Verifica form diagnostics
echo "\n6. Verifica form diagnostics...\n";
$diagnostics_file = 'fp-multilanguage/admin/views/settings-diagnostics.php';
if (file_exists($diagnostics_file)) {
    $content = file_get_contents($diagnostics_file);
    
    if (strpos($content, 'admin-post.php') !== false) {
        echo "   ✓ Form usa admin-post.php\n";
    } else {
        echo "   ❌ Form NON usa admin-post.php\n";
    }
    
    if (strpos($content, 'fpml_save_settings') !== false) {
        echo "   ✓ Form usa action fpml_save_settings\n";
    } else {
        echo "   ❌ Form NON usa action fpml_save_settings\n";
    }
    
    if (strpos($content, 'fpml_settings_nonce') !== false) {
        echo "   ✓ Form usa nonce personalizzato\n";
    } else {
        echo "   ❌ Form NON usa nonce personalizzato\n";
    }
} else {
    echo "   ❌ File diagnostics non trovato\n";
}

// Test 7: Simulazione nonce
echo "\n7. Test creazione nonce...\n";
$admin_user = get_users(['role' => 'administrator', 'number' => 1])[0];
if ($admin_user) {
    wp_set_current_user($admin_user->ID);
    echo "   ✓ Utente amministratore: {$admin_user->user_login}\n";
    
    $nonce = wp_create_nonce('fpml_save_settings');
    if ($nonce) {
        echo "   ✓ Nonce creato: " . substr($nonce, 0, 10) . "...\n";
        
        $valid = wp_verify_nonce($nonce, 'fpml_save_settings');
        echo "   " . ($valid ? "✓" : "❌") . " Nonce valido: " . ($valid ? "SI" : "NO") . "\n";
    } else {
        echo "   ❌ Impossibile creare nonce\n";
    }
} else {
    echo "   ❌ Nessun utente amministratore trovato\n";
}

// Test 8: Verifica URL
echo "\n8. Verifica URL...\n";
echo "   Admin URL: " . admin_url() . "\n";
echo "   Options.php: " . admin_url('options.php') . "\n";
echo "   Admin-post.php: " . admin_url('admin-post.php') . "\n";

echo "\n=== DEBUG COMPLETATO ===\n";

// Raccomandazioni
echo "\n📋 RACCOMANDAZIONI:\n";
echo "1. Se tutti i test sono ✓, la soluzione dovrebbe funzionare\n";
echo "2. Se ci sono ❌, segui il PIANO_BACKUP_SE_NON_FUNZIONA.md\n";
echo "3. Testa il form salvando le impostazioni\n";
echo "4. Se l'errore persiste, controlla i log di WordPress\n";
echo "5. Considera di testare con plugin disabilitati\n";

echo "\n🎯 PROSSIMI PASSI:\n";
echo "- Vai alla pagina delle impostazioni del plugin\n";
echo "- Prova a salvare le impostazioni\n";
echo "- Se vedi ancora l'errore 'link scaduto', usa il piano di backup\n";
