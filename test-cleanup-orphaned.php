<?php
/**
 * Test per verificare la pulizia dei meta orfani
 */

echo "=== TEST PULIZIA META ORFANI ===\n\n";

// Test 1: Verifica che ci siano post con meta orfani
echo "1. Verifica meta orfani esistenti...\n";

$post_types = ['page', 'post'];
$orphaned_count = 0;

foreach ($post_types as $post_type) {
    $query = new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => '_fpml_pair_id',
                'compare' => 'EXISTS'
            ]
        ]
    ]);
    
    echo "   $post_type: " . count($query->posts) . " post con _fpml_pair_id\n";
    
    foreach ($query->posts as $post_id) {
        $pair_id = get_post_meta($post_id, '_fpml_pair_id', true);
        
        if ($pair_id) {
            $paired_post = get_post($pair_id);
            
            if (!$paired_post || $paired_post->post_status === 'trash') {
                $orphaned_count++;
                echo "     Post #$post_id -> Pair #$pair_id (ORFANO)\n";
            } else {
                echo "     Post #$post_id -> Pair #$pair_id (OK)\n";
            }
        }
    }
}

echo "\n   Totale meta orfani trovati: $orphaned_count\n";

// Test 2: Simula la pulizia
echo "\n2. Simula pulizia meta orfani...\n";

if (class_exists('FPML_Admin_Cleanup')) {
    $cleanup = new FPML_Admin_Cleanup();
    
    // Simula la chiamata AJAX
    $_POST['action'] = 'fpml_cleanup_orphaned_pairs';
    
    $admin_user = get_users(['role' => 'administrator', 'number' => 1])[0];
    if ($admin_user) {
        wp_set_current_user($admin_user->ID);
        
        ob_start();
        try {
            $cleanup->handle_cleanup_orphaned_pairs();
        } catch (Exception $e) {
            echo "   ❌ Errore durante pulizia: " . $e->getMessage() . "\n";
        }
        $output = ob_get_clean();
        
        if (!empty($output)) {
            $data = json_decode($output, true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "   ✅ Pulizia completata: " . $data['data']['message'] . "\n";
                echo "   ✅ Meta orfani rimossi: " . $data['data']['cleaned_count'] . "\n";
            } else {
                echo "   ❌ Pulizia fallita: " . (isset($data['data']['message']) ? $data['data']['message'] : 'Errore sconosciuto') . "\n";
            }
        }
    } else {
        echo "   ❌ Nessun utente amministratore trovato\n";
    }
} else {
    echo "   ❌ Classe FPML_Admin_Cleanup non trovata\n";
}

// Test 3: Verifica che la pulizia abbia funzionato
echo "\n3. Verifica dopo pulizia...\n";

$orphaned_after = 0;
foreach ($post_types as $post_type) {
    $query = new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => '_fpml_pair_id',
                'compare' => 'EXISTS'
            ]
        ]
    ]);
    
    foreach ($query->posts as $post_id) {
        $pair_id = get_post_meta($post_id, '_fpml_pair_id', true);
        
        if ($pair_id) {
            $paired_post = get_post($pair_id);
            
            if (!$paired_post || $paired_post->post_status === 'trash') {
                $orphaned_after++;
            }
        }
    }
}

echo "   Meta orfani dopo pulizia: $orphaned_after\n";

if ($orphaned_after < $orphaned_count) {
    echo "   ✅ Pulizia funzionante: ridotti da $orphaned_count a $orphaned_after\n";
} else {
    echo "   ❌ Pulizia non ha funzionato: rimangono $orphaned_after meta orfani\n";
}

echo "\n=== TEST COMPLETATO ===\n";

echo "\n🎯 RISULTATO ATTESO:\n";
echo "1. ✅ Identificazione dei meta orfani\n";
echo "2. ✅ Pulizia automatica dei meta orfani\n";
echo "3. ✅ Riduzione del numero di meta orfani\n";

echo "\n📋 PROSSIMI PASSI:\n";
echo "1. Vai alla pagina delle impostazioni del plugin\n";
echo "2. Clicca su 'Pulisci Meta Orfani'\n";
echo "3. Conferma l'operazione\n";
echo "4. Esegui nuovamente il reindex\n";
echo "5. Le traduzioni dovrebbero essere ricreate\n";

echo "\n💡 SPIEGAZIONE DEL PROBLEMA:\n";
echo "Quando hai cancellato le pagine tradotte, i meta '_fpml_pair_id' sono rimasti\n";
echo "sui post originali. Il sistema pensa che le traduzioni esistano ancora e\n";
echo "non le ricrea. La pulizia rimuove questi meta orfani, permettendo al\n";
echo "reindex di ricreare le traduzioni.\n";
