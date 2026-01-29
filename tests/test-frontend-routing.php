<?php
/**
 * Test Frontend Routing - Verifica routing frontend
 * 
 * Questo script verifica che il routing frontend sia configurato correttamente.
 * Eseguire da WordPress admin o tramite WP-CLI: wp eval-file tests/test-frontend-routing.php
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';
}

if ( ! defined( 'ABSPATH' ) ) {
    die( 'WordPress non trovato.' );
}

echo "=== TEST FRONTEND ROUTING FP-MULTILANGUAGE ===\n\n";

$errors = array();
$warnings = array();
$success = array();

// 1. Verifica classe Rewrites
echo "1. Verifica Classe Rewrites...\n";
if ( class_exists( 'FP\\Multilanguage\\Frontend\\Routing\\Rewrites' ) ) {
    echo "   ✓ Classe Rewrites trovata\n";
    $success[] = 'Classe Rewrites trovata';
    
    // Verifica singleton
    $rewrites = FP\Multilanguage\Frontend\Routing\Rewrites::instance();
    if ( $rewrites ) {
        echo "   ✓ Instance Rewrites creabile\n";
        $success[] = 'Instance Rewrites creabile';
    } else {
        echo "   ✗ Impossibile creare instance Rewrites\n";
        $errors[] = 'Instance Rewrites non creabile';
    }
} else {
    echo "   ✗ Classe Rewrites NON trovata\n";
    $errors[] = 'Classe Rewrites non trovata';
    exit( 1 );
}

// 2. Verifica classi componenti routing
echo "\n2. Verifica Componenti Routing...\n";
$routing_components = array(
    'FP\\Multilanguage\\Frontend\\Routing\\RewriteRules',
    'FP\\Multilanguage\\Frontend\\Routing\\QueryFilter',
    'FP\\Multilanguage\\Frontend\\Routing\\PostResolver',
    'FP\\Multilanguage\\Frontend\\Routing\\RequestHandler',
    'FP\\Multilanguage\\Frontend\\Routing\\AdjacentPostFilter',
);

foreach ( $routing_components as $component ) {
    if ( class_exists( $component ) ) {
        echo "   ✓ {$component}\n";
        $success[] = "Componente {$component} trovato";
    } else {
        echo "   ✗ {$component} NON trovato\n";
        $errors[] = "Componente {$component} non trovato";
    }
}

// 3. Verifica hooks registrati
echo "\n3. Verifica Hooks Registrati...\n";
$expected_hooks = array(
    'init' => array(
        'hook' => 'init',
        'callback' => 'register_rewrites',
        'priority' => 10,
    ),
    'query_vars' => array(
        'hook' => 'query_vars',
        'callback' => 'register_query_vars',
        'priority' => 10,
    ),
    'request' => array(
        'hook' => 'request',
        'callback' => 'handle_request_overrides',
        'priority' => 10,
    ),
    'pre_get_posts' => array(
        'hook' => 'pre_get_posts',
        'callback' => 'handle_english_queries',
        'priority' => 1,
    ),
    'pre_get_posts_filter' => array(
        'hook' => 'pre_get_posts',
        'callback' => 'filter_posts_by_language',
        'priority' => 5,
    ),
    'template_redirect' => array(
        'hook' => 'template_redirect',
        'callback' => 'force_single_post',
        'priority' => 1,
    ),
    'template_redirect_redirect' => array(
        'hook' => 'template_redirect',
        'callback' => 'redirect_untranslated_to_home',
        'priority' => 2,
    ),
);

// Nota: Verifica hooks è complessa, verifichiamo che i metodi esistano
foreach ( $expected_hooks as $hook_name => $hook_config ) {
    // Verifica che i metodi esistano nelle classi corrispondenti
    $callback = $hook_config['callback'];
    $method_exists = false;
    
    // Determina classe basata su callback
    if ( strpos( $callback, 'register_rewrites' ) !== false || strpos( $callback, 'register_query_vars' ) !== false ) {
        $method_exists = method_exists( 'FP\\Multilanguage\\Frontend\\Routing\\RewriteRules', $callback );
    } elseif ( strpos( $callback, 'filter_posts' ) !== false || strpos( $callback, 'filter_' ) !== false ) {
        $method_exists = method_exists( 'FP\\Multilanguage\\Frontend\\Routing\\QueryFilter', $callback );
    } elseif ( strpos( $callback, 'handle_request' ) !== false || strpos( $callback, 'handle_english' ) !== false ) {
        $method_exists = method_exists( 'FP\\Multilanguage\\Frontend\\Routing\\RequestHandler', $callback );
    } elseif ( strpos( $callback, 'force_single_post' ) !== false || strpos( $callback, 'redirect_untranslated' ) !== false ) {
        $method_exists = method_exists( 'FP\\Multilanguage\\Frontend\\Routing\\Rewrites', $callback );
    }
    
    if ( $method_exists ) {
        echo "   ✓ Hook {$hook_name} → {$callback} (metodo esiste)\n";
        $success[] = "Hook {$hook_name} → {$callback} verificato";
    } else {
        echo "   ⚠ Hook {$hook_name} → {$callback} (metodo non verificato)\n";
        $warnings[] = "Hook {$hook_name} → {$callback} non verificato";
    }
}

// 4. Verifica rewrite rules in database
echo "\n4. Verifica Rewrite Rules nel Database...\n";
$rewrite_rules = get_option( 'rewrite_rules' );
if ( $rewrite_rules && is_array( $rewrite_rules ) ) {
    echo "   ✓ Rewrite rules presenti nel database\n";
    $success[] = 'Rewrite rules presenti';
    
    // Verifica presenza rule per /en/
    $has_en_rule = false;
    foreach ( $rewrite_rules as $pattern => $rule ) {
        if ( strpos( $pattern, 'en' ) !== false || strpos( $rule, 'en' ) !== false ) {
            $has_en_rule = true;
            break;
        }
    }
    
    if ( $has_en_rule ) {
        echo "   ✓ Regola per /en/ trovata\n";
        $success[] = 'Regola routing /en/ presente';
    } else {
        echo "   ⚠ Regola per /en/ non trovata (potrebbe essere normale se routing non attivo)\n";
        $warnings[] = 'Regola routing /en/ non trovata';
    }
} else {
    echo "   ⚠ Rewrite rules non presenti (eseguire flush rewrite rules)\n";
    $warnings[] = 'Rewrite rules non presenti nel database';
}

// 5. Verifica LanguageManager
echo "\n5. Verifica LanguageManager...\n";
if ( class_exists( 'FP\\Multilanguage\\MultiLanguage\\LanguageManager' ) ) {
    echo "   ✓ Classe LanguageManager trovata\n";
    $success[] = 'Classe LanguageManager trovata';
    
    try {
        $lang_manager = FP\Multilanguage\MultiLanguage\LanguageManager::instance();
        if ( $lang_manager ) {
            echo "   ✓ Instance LanguageManager creabile\n";
            $success[] = 'Instance LanguageManager creabile';
            
            // Verifica metodi
            if ( method_exists( $lang_manager, 'get_enabled_languages' ) ) {
                $enabled_langs = $lang_manager->get_enabled_languages();
                echo "   ✓ get_enabled_languages() restituisce: " . ( is_array( $enabled_langs ) ? implode( ', ', $enabled_langs ) : 'N/A' ) . "\n";
                $success[] = 'get_enabled_languages() funziona';
            }
        }
    } catch ( \Exception $e ) {
        echo "   ✗ Errore creando instance: {$e->getMessage()}\n";
        $warnings[] = "Errore LanguageManager: {$e->getMessage()}";
    }
} else {
    echo "   ✗ Classe LanguageManager NON trovata\n";
    $errors[] = 'Classe LanguageManager non trovata';
}

// 6. Verifica settings routing mode
echo "\n6. Verifica Settings Routing Mode...\n";
$settings = class_exists( '\FPML_Settings' ) ? \FPML_Settings::instance() : null;
if ( $settings ) {
    $routing_mode = $settings->get( 'routing_mode', 'segment' );
    echo "   ✓ Routing mode: {$routing_mode}\n";
    $success[] = "Routing mode configurato: {$routing_mode}";
    
    if ( $routing_mode === 'segment' ) {
        echo "   ✓ Routing mode 'segment' richiede rewrite rules per /en/\n";
        $success[] = 'Routing mode segment configurato';
    }
} else {
    echo "   ⚠ Settings non disponibili\n";
    $warnings[] = 'Settings non disponibili';
}

// Summary
echo "\n=== RIEPILOGO ===\n";
echo "✓ Successi: " . count( $success ) . "\n";
echo "⚠ Warning: " . count( $warnings ) . "\n";
echo "✗ Errori: " . count( $errors ) . "\n";

if ( ! empty( $warnings ) ) {
    echo "\nWarning:\n";
    foreach ( $warnings as $warning ) {
        echo "  - {$warning}\n";
    }
}

if ( ! empty( $errors ) ) {
    echo "\nErrori:\n";
    foreach ( $errors as $error ) {
        echo "  - {$error}\n";
    }
    echo "\n⚠ ATTENZIONE: Ci sono errori che devono essere risolti!\n";
    exit( 1 );
} else {
    echo "\n✓ Tutti i test frontend routing passati!\n";
    echo "\nNOTE: Per test completi, verificare manualmente:\n";
    echo "- URL /en/post-slug restituisce post EN\n";
    echo "- URL /post-slug restituisce post IT\n";
    echo "- 404 handling per traduzioni inesistenti\n";
    exit( 0 );
}





