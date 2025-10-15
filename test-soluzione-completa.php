<?php
/**
 * Test completo per verificare la soluzione del problema nonce scaduto
 */

echo "=== Test Soluzione Nonce Scaduto ===\n\n";

// Test 1: Verifica che l'handler AJAX sia registrato
echo "1. Verifica handler AJAX...\n";

// Simula WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/fp-multilanguage/');
}

require_once ABSPATH . 'wp-config.php';

// Verifica che l'azione AJAX sia registrata
$ajax_actions = array();
global $wp_filter;
if (isset($wp_filter['wp_ajax_fpml_refresh_nonce'])) {
    echo "   ✓ Handler AJAX fpml_refresh_nonce registrato\n";
} else {
    echo "   ❌ Handler AJAX fpml_refresh_nonce NON registrato\n";
}

// Test 2: Verifica che l'endpoint REST sia registrato
echo "\n2. Verifica endpoint REST...\n";

$rest_routes = rest_get_server()->get_routes();
if (isset($rest_routes['/fpml/v1/refresh-nonce'])) {
    echo "   ✓ Endpoint REST /fpml/v1/refresh-nonce registrato\n";
    
    $route = $rest_routes['/fpml/v1/refresh-nonce'];
    $methods = array_keys($route);
    echo "   ✓ Metodi supportati: " . implode(', ', $methods) . "\n";
} else {
    echo "   ❌ Endpoint REST /fpml/v1/refresh-nonce NON registrato\n";
}

// Test 3: Test simulazione AJAX
echo "\n3. Test simulazione AJAX...\n";

// Simula un utente amministratore
$admin_user = get_users(array('role' => 'administrator', 'number' => 1))[0];
if (!$admin_user) {
    echo "   ❌ Nessun utente amministratore trovato\n";
} else {
    wp_set_current_user($admin_user->ID);
    echo "   ✓ Utente amministratore impostato: {$admin_user->user_login}\n";
    
    // Simula la chiamata AJAX
    $_POST['action'] = 'fpml_refresh_nonce';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Cattura l'output
    ob_start();
    
    try {
        // Simula la chiamata all'handler
        $admin = new FPML_Admin();
        $admin->handle_refresh_nonce();
    } catch (Exception $e) {
        echo "   ❌ Errore durante la chiamata AJAX: " . $e->getMessage() . "\n";
    }
    
    $output = ob_get_clean();
    
    if (!empty($output)) {
        $data = json_decode($output, true);
        if ($data && isset($data['success']) && $data['success'] && isset($data['data']['nonce'])) {
            echo "   ✓ AJAX handler funziona correttamente\n";
            echo "   ✓ Nonce generato: " . substr($data['data']['nonce'], 0, 10) . "...\n";
            
            // Verifica validità del nonce
            $is_valid = wp_verify_nonce($data['data']['nonce'], 'wp_rest');
            if ($is_valid) {
                echo "   ✓ Nonce generato è valido per wp_rest\n";
            } else {
                echo "   ❌ Nonce generato NON è valido per wp_rest\n";
            }
        } else {
            echo "   ❌ AJAX handler non ha restituito dati validi\n";
            echo "   Output: " . $output . "\n";
        }
    } else {
        echo "   ❌ AJAX handler non ha prodotto output\n";
    }
}

// Test 4: Verifica disponibilità ajaxurl
echo "\n4. Verifica disponibilità ajaxurl...\n";

$ajaxurl = admin_url('admin-ajax.php');
if (!empty($ajaxurl)) {
    echo "   ✓ ajaxurl disponibile: " . $ajaxurl . "\n";
} else {
    echo "   ❌ ajaxurl NON disponibile\n";
}

echo "\n=== Test Completato ===\n";
echo "\nLa soluzione dovrebbe ora funzionare correttamente!\n";
echo "Se dovessi ancora riscontrare problemi, controlla:\n";
echo "1. I log del server web\n";
echo "2. La console del browser per errori JavaScript\n";
echo "3. I permessi dell'utente amministratore\n";
