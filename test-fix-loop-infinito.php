<?php
/**
 * Test per verificare la correzione del loop infinito nel reindex
 */

echo "=== TEST CORREZIONE LOOP INFINITO ===\n\n";

// Test 1: Verifica handler AJAX corretto
echo "1. Verifica handler AJAX...\n";

$admin_user = get_users(['role' => 'administrator', 'number' => 1])[0];
if ($admin_user) {
    wp_set_current_user($admin_user->ID);
    echo "   ✓ Utente amministratore: {$admin_user->user_login}\n";
    
    $admin = new FPML_Admin();
    
    // Testa diversi step per verificare la logica di completamento
    $test_steps = [0, 1, 2, 10, 20];
    
    foreach ($test_steps as $test_step) {
        echo "\n   Test step $test_step:\n";
        
        // Simula chiamata AJAX
        $_POST['action'] = 'fpml_reindex_batch_ajax';
        $_POST['step'] = (string)$test_step;
        
        ob_start();
        try {
            $admin->handle_reindex_batch_ajax();
        } catch (Exception $e) {
            echo "     ❌ Errore: " . $e->getMessage() . "\n";
            continue;
        }
        $output = ob_get_clean();
        
        if (!empty($output)) {
            $data = json_decode($output, true);
            if ($data && isset($data['success']) && $data['success']) {
                $response_data = $data['data'];
                echo "     ✓ Step: " . ($response_data['step'] ?? 'N/A') . "\n";
                echo "     ✓ Complete: " . ($response_data['complete'] ? 'SI' : 'NO') . "\n";
                echo "     ✓ Progress: " . ($response_data['progress_percent'] ?? 0) . "%\n";
                echo "     ✓ Task: " . ($response_data['current_task'] ?? 'N/A') . "\n";
                
                // Verifica che i campi richiesti dal JavaScript siano presenti
                $required_fields = ['complete', 'step', 'progress_percent', 'current_task', 'summary'];
                $missing_fields = [];
                foreach ($required_fields as $field) {
                    if (!isset($response_data[$field])) {
                        $missing_fields[] = $field;
                    }
                }
                
                if (empty($missing_fields)) {
                    echo "     ✅ Tutti i campi richiesti sono presenti\n";
                } else {
                    echo "     ❌ Campi mancanti: " . implode(', ', $missing_fields) . "\n";
                }
            } else {
                echo "     ❌ Risposta non valida\n";
            }
        } else {
            echo "     ℹ️ Nessun output (normale se redirect)\n";
        }
    }
} else {
    echo "   ❌ Nessun utente amministratore trovato\n";
}

// Test 2: Verifica Content Indexer
echo "\n2. Verifica Content Indexer...\n";

if (class_exists('FPML_Content_Indexer')) {
    $indexer = FPML_Content_Indexer::instance();
    
    // Testa la logica di completamento
    $post_types = $indexer->get_translatable_post_types();
    $taxonomies = get_taxonomies(['public' => true], 'names');
    
    $total_steps = count($post_types) + count($taxonomies) + 1; // +1 per menu
    echo "   ✓ Post types: " . count($post_types) . "\n";
    echo "   ✓ Taxonomies: " . count($taxonomies) . "\n";
    echo "   ✓ Total steps: " . $total_steps . "\n";
    
    // Testa alcuni step per verificare la logica
    $test_completion_steps = [
        count($post_types) + count($taxonomies), // Ultimo step (dovrebbe essere complete=true)
        count($post_types) + count($taxonomies) + 1, // Step successivo (dovrebbe essere complete=true)
        0, // Primo step (dovrebbe essere complete=false)
        count($post_types) - 1, // Ultimo post type (dovrebbe essere complete=false)
    ];
    
    foreach ($test_completion_steps as $test_step) {
        echo "\n   Test completamento step $test_step:\n";
        
        try {
            $result = $indexer->reindex_batch($test_step);
            echo "     ✓ Complete: " . ($result['complete'] ? 'SI' : 'NO') . "\n";
            echo "     ✓ Step: " . ($result['step'] ?? 'N/A') . "\n";
            echo "     ✓ Progress: " . ($result['progress_percent'] ?? 0) . "%\n";
            
            // Verifica che la logica di completamento sia corretta
            $should_be_complete = ($test_step >= count($post_types) + count($taxonomies));
            $is_complete = $result['complete'] ?? false;
            
            if ($should_be_complete === $is_complete) {
                echo "     ✅ Logica di completamento corretta\n";
            } else {
                echo "     ❌ Logica di completamento ERRATA (dovrebbe essere: " . ($should_be_complete ? 'complete' : 'incomplete') . ")\n";
            }
            
        } catch (Exception $e) {
            echo "     ❌ Errore durante test: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "   ❌ Classe FPML_Content_Indexer non trovata\n";
}

// Test 3: Verifica JavaScript
echo "\n3. Verifica JavaScript...\n";

$js_file = 'fp-multilanguage/assets/admin.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    
    $js_checks = [
        'maxSteps = 50',
        'stepCount < maxSteps',
        'mappedPayload.complete',
        'Raggiunto limite massimo di step'
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
        echo "   ✅ Tutte le correzioni JavaScript sono presenti\n";
    } else {
        echo "   ❌ Alcune correzioni JavaScript mancano\n";
    }
} else {
    echo "   ❌ File JavaScript non trovato\n";
}

echo "\n=== TEST COMPLETATO ===\n";

echo "\n🎯 RISULTATO ATTESO:\n";
echo "1. ✅ Il reindex si ferma quando 'complete: true'\n";
echo "2. ✅ Limite di sicurezza di 50 step per evitare loop infiniti\n";
echo "3. ✅ Mapping corretto dei campi tra AJAX e JavaScript\n";
echo "4. ✅ Logica di completamento corretta nel Content Indexer\n";

echo "\n📋 PROSSIMI PASSI:\n";
echo "1. Ricarica la pagina delle impostazioni\n";
echo "2. Prova il reindex\n";
echo "3. Il processo dovrebbe fermarsi automaticamente\n";
echo "4. Controlla la console per vedere '🎉 REINDEX COMPLETATO!'\n";

echo "\n🔍 COSA CERCARE NEI LOG:\n";
echo "- '🎉 REINDEX COMPLETATO!' quando finisce\n";
echo "- Nessun loop infinito oltre 50 step\n";
echo "- Progress bar che raggiunge 100%\n";
echo "- Messaggio 'Completato!' finale\n";
