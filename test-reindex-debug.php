<?php
/**
 * Test per debuggare perché il reindex non crea nuove traduzioni
 */

echo "=== DEBUG REINDEX - PERCHÉ NON CREA TRADUZIONI ===\n\n";

// Test 1: Verifica che ci siano post da processare
echo "1. Verifica post esistenti...\n";

$post_types = ['page', 'post'];
foreach ($post_types as $post_type) {
    $query = new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => 5,
        'fields' => 'ids'
    ]);
    
    echo "   $post_type: " . $query->found_posts . " post trovati\n";
    
    if ($query->have_posts()) {
        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            $is_translation = get_post_meta($post_id, '_fpml_is_translation', true);
            $pair_id = get_post_meta($post_id, '_fpml_pair_id', true);
            
            echo "     Post #$post_id: '{$post->post_title}'";
            if ($is_translation) {
                echo " [TRADUZIONE]";
            }
            if ($pair_id) {
                echo " [PAIR: $pair_id]";
            }
            echo "\n";
        }
    }
}

// Test 2: Verifica Translation Manager
echo "\n2. Verifica Translation Manager...\n";

if (class_exists('FPML_Translation_Manager')) {
    $translation_manager = FPML_Translation_Manager::instance();
    echo "   ✓ Translation Manager disponibile\n";
    
    // Testa con un post esistente
    $test_post = get_posts(['post_type' => 'page', 'numberposts' => 1])[0] ?? null;
    
    if ($test_post) {
        echo "   Test con post: #{$test_post->ID} '{$test_post->post_title}'\n";
        
        // Verifica se ha già una traduzione
        $existing_pair = get_post_meta($test_post->ID, '_fpml_pair_id', true);
        echo "   Pair esistente: " . ($existing_pair ? $existing_pair : 'Nessuna') . "\n";
        
        // Se non ha traduzione, prova a crearne una
        if (!$existing_pair) {
            echo "   Tentativo di creare traduzione...\n";
            
            try {
                $translation = $translation_manager->ensure_post_translation($test_post);
                
                if ($translation) {
                    echo "   ✅ Traduzione creata: #{$translation->ID} '{$translation->post_title}'\n";
                    
                    // Verifica i meta
                    $new_pair = get_post_meta($test_post->ID, '_fpml_pair_id', true);
                    $source_id = get_post_meta($translation->ID, '_fpml_pair_source_id', true);
                    $is_translation = get_post_meta($translation->ID, '_fpml_is_translation', true);
                    
                    echo "   Meta verifica:\n";
                    echo "     Source pair_id: $new_pair\n";
                    echo "     Translation source_id: $source_id\n";
                    echo "     Translation flag: $is_translation\n";
                } else {
                    echo "   ❌ Creazione traduzione fallita\n";
                }
            } catch (Exception $e) {
                echo "   ❌ Errore durante creazione: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ℹ️ Post ha già una traduzione\n";
        }
    } else {
        echo "   ❌ Nessun post trovato per il test\n";
    }
} else {
    echo "   ❌ Translation Manager non disponibile\n";
}

// Test 3: Verifica Content Indexer
echo "\n3. Verifica Content Indexer...\n";

if (class_exists('FPML_Content_Indexer')) {
    $indexer = FPML_Content_Indexer::instance();
    echo "   ✓ Content Indexer disponibile\n";
    
    // Testa reindex_post_type
    echo "   Test reindex_post_type('page')...\n";
    
    try {
        $result = $indexer->reindex_post_type('page');
        
        echo "   Risultato reindex:\n";
        echo "     Posts scanned: " . ($result['posts_scanned'] ?? 0) . "\n";
        echo "     Posts enqueued: " . ($result['posts_enqueued'] ?? 0) . "\n";
        echo "     Translations created: " . ($result['translations_created'] ?? 0) . "\n";
        
        if (($result['translations_created'] ?? 0) > 0) {
            echo "   ✅ Reindex ha creato traduzioni\n";
        } else {
            echo "   ❌ Reindex NON ha creato traduzioni\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Errore durante reindex: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ Content Indexer non disponibile\n";
}

// Test 4: Verifica configurazione plugin
echo "\n4. Verifica configurazione plugin...\n";

$settings = FPML_Settings::instance();
$config = $settings->get();

echo "   Lingua principale: " . ($config['primary_language'] ?? 'N/A') . "\n";
echo "   Lingua secondaria: " . ($config['secondary_language'] ?? 'N/A') . "\n";
echo "   Modalità assistita: " . (($config['assisted_mode'] ?? false) ? 'SÌ' : 'NO') . "\n";

// Test 5: Verifica post types traducibili
echo "\n5. Verifica post types traducibili...\n";

$translatable_types = $indexer->get_translatable_post_types();
echo "   Post types traducibili: " . implode(', ', $translatable_types) . "\n";

echo "\n=== DEBUG COMPLETATO ===\n";

echo "\n🎯 POSSIBILI CAUSE:\n";
echo "1. Modalità assistita attiva (disabilita creazione automatica)\n";
echo "2. Post già hanno traduzioni (ma sono state cancellate)\n";
echo "3. Translation Manager non funziona correttamente\n";
echo "4. Configurazione plugin errata\n";
echo "5. Post types non configurati come traducibili\n";

echo "\n📋 PROSSIMI PASSI:\n";
echo "1. Verifica se la modalità assistita è attiva\n";
echo "2. Controlla i log di WordPress per errori\n";
echo "3. Verifica che i post types siano configurati correttamente\n";
echo "4. Testa manualmente la creazione di una traduzione\n";
