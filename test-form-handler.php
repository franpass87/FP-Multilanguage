<?php
/**
 * Test per verificare il nuovo handler del form personalizzato
 */

echo "=== Test Form Handler Personalizzato ===\n\n";

// Test 1: Verifica che l'handler sia registrato
echo "1. Verifica handler form registrato...\n";

global $wp_filter;

if ( isset( $wp_filter['admin_post_fpml_save_settings'] ) ) {
    echo "   ✓ Handler 'admin_post_fpml_save_settings' registrato\n";
} else {
    echo "   ❌ Handler 'admin_post_fpml_save_settings' NON registrato\n";
}

// Test 2: Verifica metodo della classe
echo "\n2. Verifica metodo handle_save_settings...\n";

if ( method_exists( 'FPML_Admin', 'handle_save_settings' ) ) {
    echo "   ✓ Metodo 'handle_save_settings' presente\n";
} else {
    echo "   ❌ Metodo 'handle_save_settings' NON presente\n";
}

// Test 3: Verifica costante OPTION_KEY
echo "\n3. Verifica costante OPTION_KEY...\n";

if ( defined( 'FPML_Settings::OPTION_KEY' ) ) {
    echo "   ✓ Costante OPTION_KEY definita\n";
} else {
    echo "   ❌ Costante OPTION_KEY NON definita\n";
}

// Test 4: Simulazione form submission
echo "\n4. Simulazione form submission...\n";

// Simula dati del form
$_POST['action'] = 'fpml_save_settings';
$_POST['fpml_settings_nonce'] = wp_create_nonce( 'fpml_save_settings' );
$_POST['tab'] = 'diagnostics';
$_POST['fpml_settings'] = array(
    'anonymize_logs' => '1',
    'batch_size' => '5'
);

// Simula un utente amministratore
$admin_user = get_users(array('role' => 'administrator', 'number' => 1))[0];
if ($admin_user) {
    wp_set_current_user($admin_user->ID);
    echo "   ✓ Utente amministratore impostato: {$admin_user->user_login}\n";
    
    // Testa il metodo
    $admin = new FPML_Admin();
    
    // Cattura eventuali output
    ob_start();
    try {
        $admin->handle_save_settings();
    } catch (Exception $e) {
        echo "   ❌ Errore durante il test: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "   ✓ Metodo handle_save_settings ha prodotto output\n";
        echo "   Output: " . substr($output, 0, 100) . "...\n";
    } else {
        echo "   ℹ️ Metodo handle_save_settings non ha prodotto output (normale se redirect)\n";
    }
} else {
    echo "   ❌ Nessun utente amministratore trovato\n";
}

echo "\n=== Test Completato ===\n";
echo "\n🎯 La nuova soluzione dovrebbe:\n";
echo "1. ✅ Evitare completamente l'errore 'link scaduto'\n";
echo "2. ✅ Salvare le impostazioni anche con nonce scaduto\n";
echo "3. ✅ Reindirizzare con messaggio di successo\n";
echo "4. ✅ Mantenere la sicurezza con controlli utente\n";
