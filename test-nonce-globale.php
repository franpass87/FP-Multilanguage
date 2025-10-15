<?php
/**
 * Test per verificare che il sistema di nonce globale funzioni
 */

echo "=== Test Sistema Nonce Globale ===\n\n";

// Test 1: Verifica che il JavaScript sia aggiornato
echo "1. Verifica aggiornamenti JavaScript...\n";

$js_file = 'fp-multilanguage/assets/admin.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    
    $js_checks = [
        'window.fpmlCurrentNonce',
        'currentNonce = window.fpmlCurrentNonce || nonce',
        'window.fpmlCurrentNonce = newNonce',
        'X-WP-Nonce.*currentNonce'
    ];
    
    $all_passed = true;
    foreach ($js_checks as $check) {
        if (preg_match('/' . str_replace(['(', ')', '[', ']', '.'], ['\\(', '\\)', '\\[', '\\]', '\\.'], $check) . '/', $js_content)) {
            echo "   ✓ JavaScript contiene: '$check'\n";
        } else {
            echo "   ❌ JavaScript NON contiene: '$check'\n";
            $all_passed = false;
        }
    }
    
    if ($all_passed) {
        echo "   ✅ Tutti gli aggiornamenti JavaScript sono presenti\n";
    } else {
        echo "   ❌ Alcuni aggiornamenti JavaScript mancano\n";
    }
} else {
    echo "   ❌ File JavaScript non trovato\n";
}

// Test 2: Verifica handler AJAX
echo "\n2. Verifica handler AJAX per refresh nonce...\n";

if (class_exists('FPML_Admin')) {
    if (method_exists('FPML_Admin', 'handle_refresh_nonce')) {
        echo "   ✓ Handler AJAX presente\n";
        
        // Testa il metodo
        $admin = new FPML_Admin();
        $admin_user = get_users(['role' => 'administrator', 'number' => 1])[0];
        if ($admin_user) {
            wp_set_current_user($admin_user->ID);
            
            // Simula chiamata AJAX
            ob_start();
            try {
                $admin->handle_refresh_nonce();
            } catch (Exception $e) {
                echo "   ❌ Errore durante test handler: " . $e->getMessage() . "\n";
            }
            $output = ob_get_clean();
            
            if (!empty($output)) {
                $data = json_decode($output, true);
                if ($data && isset($data['success']) && $data['success'] && isset($data['data']['nonce'])) {
                    echo "   ✓ Handler AJAX funziona correttamente\n";
                    echo "   ✓ Nonce generato: " . substr($data['data']['nonce'], 0, 10) . "...\n";
                } else {
                    echo "   ❌ Handler AJAX non ha restituito dati validi\n";
                }
            } else {
                echo "   ℹ️ Handler AJAX non ha prodotto output (normale se redirect)\n";
            }
        } else {
            echo "   ❌ Nessun utente amministratore trovato\n";
        }
    } else {
        echo "   ❌ Metodo handle_refresh_nonce mancante\n";
    }
} else {
    echo "   ❌ Classe FPML_Admin non trovata\n";
}

// Test 3: Verifica endpoint REST
echo "\n3. Verifica endpoint REST...\n";

$rest_routes = rest_get_server()->get_routes();
if (isset($rest_routes['/fpml/v1/refresh-nonce'])) {
    echo "   ✓ Endpoint REST /fpml/v1/refresh-nonce registrato\n";
} else {
    echo "   ❌ Endpoint REST /fpml/v1/refresh-nonce NON registrato\n";
}

if (isset($rest_routes['/fpml/v1/reindex-batch'])) {
    echo "   ✓ Endpoint REST /fpml/v1/reindex-batch registrato\n";
} else {
    echo "   ❌ Endpoint REST /fpml/v1/reindex-batch NON registrato\n";
}

// Test 4: Verifica nonce creation
echo "\n4. Test creazione e validazione nonce...\n";

$admin_user = get_users(['role' => 'administrator', 'number' => 1])[0];
if ($admin_user) {
    wp_set_current_user($admin_user->ID);
    
    $nonce = wp_create_nonce('wp_rest');
    if ($nonce) {
        echo "   ✓ Nonce creato: " . substr($nonce, 0, 10) . "...\n";
        
        $valid = wp_verify_nonce($nonce, 'wp_rest');
        echo "   " . ($valid ? "✓" : "❌") . " Nonce valido: " . ($valid ? "SI" : "NO") . "\n";
        
        // Test con nonce scaduto (simulato)
        $old_nonce = 'old_expired_nonce';
        $valid_old = wp_verify_nonce($old_nonce, 'wp_rest');
        echo "   " . ($valid_old ? "❌" : "✓") . " Nonce scaduto rilevato correttamente: " . ($valid_old ? "NO" : "SI") . "\n";
    } else {
        echo "   ❌ Impossibile creare nonce\n";
    }
} else {
    echo "   ❌ Nessun utente amministratore trovato\n";
}

echo "\n=== Test Completato ===\n";

echo "\n🎯 RISULTATO ATTESO:\n";
echo "Dopo questi aggiornamenti, il sistema dovrebbe:\n";
echo "1. ✅ Mantenere un nonce globale sempre aggiornato\n";
echo "2. ✅ Usare il nonce più recente per tutte le richieste\n";
echo "3. ✅ Aggiornare automaticamente il nonce quando scade\n";
echo "4. ✅ Evitare errori 403 Forbidden durante il reindex\n";

echo "\n📋 PROSSIMI PASSI:\n";
echo "1. Ricarica la pagina delle impostazioni\n";
echo "2. Prova a eseguire il reindex\n";
echo "3. Controlla la console per vedere i log di aggiornamento nonce\n";
echo "4. Se vedi ancora errori 403, il problema potrebbe essere server-side\n";

echo "\n🔍 DEBUG AGGIUNTIVO:\n";
echo "Se il problema persiste, controlla:\n";
echo "- Headers della richiesta nella Network tab del browser\n";
echo "- Se il nonce viene effettivamente aggiornato nelle richieste\n";
echo "- Se ci sono plugin di sicurezza che bloccano le richieste\n";
echo "- Se il server ha limitazioni sui nonce\n";
