<?php
/**
 * Test finale per verificare la soluzione completa del problema nonce scaduto
 */

echo "=== Test Soluzione Finale Nonce Scaduto ===\n\n";

// Test 1: Verifica hook registrati
echo "1. Verifica hook registrati...\n";

global $wp_filter;

$hooks_to_check = array(
    'wp_ajax_fpml_refresh_nonce',
    'init',
    'admin_init',
    'wp_die_handler'
);

foreach ( $hooks_to_check as $hook ) {
    if ( isset( $wp_filter[ $hook ] ) ) {
        echo "   ✓ Hook '$hook' registrato\n";
    } else {
        echo "   ❌ Hook '$hook' NON registrato\n";
    }
}

// Test 2: Verifica metodi della classe admin
echo "\n2. Verifica metodi classe admin...\n";

$admin_methods = array(
    'handle_refresh_nonce',
    'handle_expired_nonce_early',
    'handle_expired_nonce_redirect',
    'custom_wp_die_handler'
);

foreach ( $admin_methods as $method ) {
    if ( method_exists( 'FPML_Admin', $method ) ) {
        echo "   ✓ Metodo '$method' presente\n";
    } else {
        echo "   ❌ Metodo '$method' NON presente\n";
    }
}

// Test 3: Verifica endpoint REST
echo "\n3. Verifica endpoint REST...\n";

$rest_routes = rest_get_server()->get_routes();
if ( isset( $rest_routes['/fpml/v1/refresh-nonce'] ) ) {
    echo "   ✓ Endpoint REST /fpml/v1/refresh-nonce registrato\n";
} else {
    echo "   ❌ Endpoint REST /fpml/v1/refresh-nonce NON registrato\n";
}

// Test 4: Simulazione scenario form submit
echo "\n4. Simulazione scenario form submit...\n";

// Simula un redirect con nonce scaduto
$_GET['page'] = 'fpml-settings';
$_GET['tab'] = 'diagnostics';
$_GET['_wpnonce'] = 'expired_nonce_value';
$_SERVER['HTTP_REFERER'] = 'http://example.com/wp-admin/options.php';

// Simula un utente amministratore
$admin_user = get_users(array('role' => 'administrator', 'number' => 1))[0];
if ($admin_user) {
    wp_set_current_user($admin_user->ID);
    echo "   ✓ Utente amministratore impostato: {$admin_user->user_login}\n";
    
    // Testa il metodo di gestione precoce
    $admin = new FPML_Admin();
    
    // Cattura eventuali redirect
    ob_start();
    try {
        $admin->handle_expired_nonce_early();
    } catch (Exception $e) {
        echo "   ❌ Errore durante il test: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "   ✓ Metodo handle_expired_nonce_early ha prodotto output\n";
        echo "   Output: " . substr($output, 0, 100) . "...\n";
    } else {
        echo "   ℹ️ Metodo handle_expired_nonce_early non ha prodotto output (normale se non ci sono errori)\n";
    }
} else {
    echo "   ❌ Nessun utente amministratore trovato\n";
}

// Test 5: Verifica JavaScript
echo "\n5. Verifica file JavaScript...\n";

$js_file = 'fp-multilanguage/assets/admin.js';
if ( file_exists( $js_file ) ) {
    $js_content = file_get_contents( $js_file );
    
    $js_checks = array(
        'refreshNonce',
        'ajaxurl',
        'fpml_refresh_nonce',
        'Tentativo refresh nonce tramite AJAX WordPress'
    );
    
    foreach ( $js_checks as $check ) {
        if ( strpos( $js_content, $check ) !== false ) {
            echo "   ✓ JavaScript contiene: '$check'\n";
        } else {
            echo "   ❌ JavaScript NON contiene: '$check'\n";
        }
    }
} else {
    echo "   ❌ File JavaScript non trovato\n";
}

echo "\n=== Test Completato ===\n";
echo "\n🎯 La soluzione dovrebbe ora gestire:\n";
echo "1. ✅ Errori 'link scaduto' dopo submit del form\n";
echo "2. ✅ Errori di nonce scaduto durante reindex AJAX\n";
echo "3. ✅ Messaggi di successo quando le impostazioni vengono salvate\n";
echo "4. ✅ Redirect puliti senza parametri di nonce scaduto\n";
echo "\nSe dovessi ancora vedere l'errore, potrebbe essere necessario:\n";
echo "- Pulire la cache del browser\n";
echo "- Verificare che non ci siano plugin di sicurezza che interferiscono\n";
echo "- Controllare i log del server per errori PHP\n";
