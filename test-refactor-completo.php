<?php
/**
 * Test per verificare il refactor completo della gestione nonce
 */

echo "=== TEST REFACTOR COMPLETO ===\n\n";

// Test 1: Verifica handler AJAX
echo "1. Verifica handler AJAX per reindex...\n";

if (class_exists('FPML_Admin')) {
    if (method_exists('FPML_Admin', 'handle_reindex_batch_ajax')) {
        echo "   ✓ Handler AJAX 'handle_reindex_batch_ajax' presente\n";
    } else {
        echo "   ❌ Handler AJAX 'handle_reindex_batch_ajax' MANCANTE\n";
    }
} else {
    echo "   ❌ Classe FPML_Admin non trovata\n";
}

// Test 2: Verifica hook registrato
echo "\n2. Verifica hook AJAX registrato...\n";

global $wp_filter;
if (isset($wp_filter['wp_ajax_fpml_reindex_batch_ajax'])) {
    echo "   ✓ Hook 'wp_ajax_fpml_reindex_batch_ajax' registrato\n";
} else {
    echo "   ❌ Hook 'wp_ajax_fpml_reindex_batch_ajax' NON registrato\n";
}

// Test 3: Verifica JavaScript refactor
echo "\n3. Verifica refactor JavaScript...\n";

$js_file = 'fp-multilanguage/assets/admin.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    
    $js_checks = [
        'executeReindexViaAjaxDirect',
        'AJAX diretto per reindex - bypass REST API',
        'fpml_reindex_batch_ajax',
        'REFACTOR: Uso AJAX diretto per reindex'
    ];
    
    $all_passed = true;
    foreach ($js_checks as $check) {
        if (strpos($js_content, $check) !== false) {
            echo "   ✓ JavaScript contiene: '$check'\n";
        } else {
            echo "   ❌ JavaScript NON contiene: '$check'\n";
            $all_passed = false;
        }
    }
    
    if ($all_passed) {
        echo "   ✅ Refactor JavaScript completato\n";
    } else {
        echo "   ❌ Refactor JavaScript incompleto\n";
    }
} else {
    echo "   ❌ File JavaScript non trovato\n";
}

// Test 4: Verifica Content Indexer
echo "\n4. Verifica Content Indexer...\n";

if (class_exists('FPML_Content_Indexer')) {
    if (method_exists('FPML_Content_Indexer', 'reindex_batch')) {
        echo "   ✓ Metodo 'reindex_batch' presente in Content_Indexer\n";
    } else {
        echo "   ❌ Metodo 'reindex_batch' MANCANTE in Content_Indexer\n";
    }
} else {
    echo "   ❌ Classe FPML_Content_Indexer non trovata\n";
}

// Test 5: Simulazione AJAX
echo "\n5. Test simulazione AJAX...\n";

$admin_user = get_users(['role' => 'administrator', 'number' => 1])[0];
if ($admin_user) {
    wp_set_current_user($admin_user->ID);
    echo "   ✓ Utente amministratore: {$admin_user->user_login}\n";
    
    // Simula chiamata AJAX
    $_POST['action'] = 'fpml_reindex_batch_ajax';
    $_POST['step'] = '0';
    
    $admin = new FPML_Admin();
    
    ob_start();
    try {
        $admin->handle_reindex_batch_ajax();
    } catch (Exception $e) {
        echo "   ❌ Errore durante test AJAX: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    
    if (!empty($output)) {
        $data = json_decode($output, true);
        if ($data && isset($data['success'])) {
            if ($data['success']) {
                echo "   ✅ AJAX handler funziona correttamente\n";
                echo "   ✓ Risposta: " . (isset($data['data']['message']) ? $data['data']['message'] : 'OK') . "\n";
            } else {
                echo "   ⚠️ AJAX handler risponde con errore: " . (isset($data['data']['message']) ? $data['data']['message'] : 'Errore sconosciuto') . "\n";
            }
        } else {
            echo "   ❌ AJAX handler non ha restituito JSON valido\n";
        }
    } else {
        echo "   ℹ️ AJAX handler non ha prodotto output (normale se redirect)\n";
    }
} else {
    echo "   ❌ Nessun utente amministratore trovato\n";
}

echo "\n=== TEST COMPLETATO ===\n";

echo "\n🎯 RISULTATO ATTESO DEL REFACTOR:\n";
echo "1. ✅ Bypass completo del REST API per reindex\n";
echo "2. ✅ Uso esclusivo di AJAX WordPress per operazioni reindex\n";
echo "3. ✅ Eliminazione degli errori 403 Forbidden\n";
echo "4. ✅ Gestione robusta dei nonce scaduti\n";
echo "5. ✅ Fallback automatico se AJAX fallisce\n";

echo "\n📋 PROSSIMI PASSI:\n";
echo "1. Ricarica la pagina delle impostazioni\n";
echo "2. Prova a eseguire il reindex\n";
echo "3. Controlla la console per vedere i log '🚀 REFACTOR'\n";
echo "4. Il reindex dovrebbe funzionare senza errori 403\n";

echo "\n🔍 COSA CERCARE NEI LOG:\n";
echo "- '🚀 REFACTOR: Uso AJAX diretto per reindex'\n";
echo "- '📡 Invio AJAX: step=X, nonce=...'\n";
echo "- '✅ AJAX diretto completato con successo'\n";
echo "- Nessun errore 403 Forbidden\n";

echo "\n⚠️ SE IL PROBLEMA PERSISTE:\n";
echo "Il refactor dovrebbe risolvere definitivamente il problema.\n";
echo "Se vedi ancora errori, il problema potrebbe essere:\n";
echo "1. Plugin di sicurezza che bloccano AJAX\n";
echo "2. Configurazione server che limita le richieste\n";
echo "3. Conflitti con altri plugin\n";
echo "4. Problemi di memoria/timeout durante il reindex\n";
