<?php
/**
 * Script di test per identificare l'errore 500
 * Carica questo file nella root di WordPress e accedi via browser
 */

// Mostra tutti gli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST DIAGNOSTICO ERRORE 500 ===\n\n";

// 1. Verifica WordPress
echo "1. VERIFICA WORDPRESS\n";
$wp_load = dirname(__FILE__) . '/wp-load.php';
echo "   wp-load.php esiste: " . (file_exists($wp_load) ? "SI" : "NO") . "\n";

if (file_exists($wp_load)) {
    echo "   Caricamento WordPress... ";
    try {
        require_once $wp_load;
        echo "OK\n";
    } catch (Exception $e) {
        echo "ERRORE: " . $e->getMessage() . "\n";
        die();
    }
} else {
    die("ERRORE: Carica questo file nella root di WordPress!\n");
}

echo "\n";

// 2. Verifica plugin directory
echo "2. VERIFICA PLUGIN\n";
$plugin_dir = WP_PLUGIN_DIR . '/FP-Multilanguage';
$plugin_file = $plugin_dir . '/fp-multilanguage.php';

echo "   Directory plugin: " . (is_dir($plugin_dir) ? "SI" : "NO") . "\n";
echo "   File principale: " . (file_exists($plugin_file) ? "SI" : "NO") . "\n\n";

if (!file_exists($plugin_file)) {
    die("ERRORE: Plugin non trovato!\n");
}

// 3. Test caricamento file principale
echo "3. TEST CARICAMENTO FILE PRINCIPALE\n";
echo "   Tentativo di caricamento... ";
try {
    // Definisci le costanti necessarie
    if (!defined('FPML_PLUGIN_VERSION')) {
        define('FPML_PLUGIN_VERSION', '0.4.1');
    }
    if (!defined('FPML_PLUGIN_FILE')) {
        define('FPML_PLUGIN_FILE', $plugin_file);
    }
    if (!defined('FPML_PLUGIN_DIR')) {
        define('FPML_PLUGIN_DIR', $plugin_dir . '/');
    }
    if (!defined('FPML_PLUGIN_URL')) {
        define('FPML_PLUGIN_URL', plugins_url('', $plugin_file) . '/');
    }
    
    // Prova a caricare
    ob_start();
    include_once $plugin_file;
    $output = ob_get_clean();
    
    echo "OK\n";
    if ($output) {
        echo "   Output: $output\n";
    }
} catch (Exception $e) {
    echo "ERRORE!\n";
    echo "   Messaggio: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Linea: " . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
    die();
} catch (Error $e) {
    echo "ERRORE FATALE!\n";
    echo "   Messaggio: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Linea: " . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
    die();
}

echo "\n";

// 4. Verifica classi caricate
echo "4. VERIFICA CLASSI\n";
$classes = array(
    'FPML_Plugin',
    'FPML_Plugin_Core',
    'FPML_Container',
    'FPML_Settings',
    'FPML_Queue',
);

foreach ($classes as $class) {
    echo "   $class: " . (class_exists($class) ? "SI" : "NO") . "\n";
}

echo "\n";

// 5. Verifica funzioni
echo "5. VERIFICA FUNZIONI\n";
$functions = array(
    'fpml_bootstrap',
    'fpml_do_activation',
    'fpml_run_plugin',
    'fpml_activate',
    'fpml_load_files',
);

foreach ($functions as $func) {
    echo "   $func(): " . (function_exists($func) ? "SI" : "NO") . "\n";
}

echo "\n";

// 6. Verifica hooks registrati
echo "6. VERIFICA HOOKS\n";
$hooks = array(
    'plugins_loaded' => array('fpml_bootstrap', 'fpml_do_activation', 'fpml_run_plugin'),
);

foreach ($hooks as $hook => $callbacks) {
    echo "   Hook '$hook':\n";
    foreach ($callbacks as $callback) {
        $priority = has_action($hook, $callback);
        echo "      $callback: " . ($priority !== false ? "SI (priorità $priority)" : "NO") . "\n";
    }
}

echo "\n";

// 7. Test attivazione simulata
echo "7. TEST ATTIVAZIONE SIMULATA\n";
echo "   Simulazione fpml_activate()... ";
try {
    if (function_exists('fpml_activate')) {
        ob_start();
        fpml_activate();
        $output = ob_get_clean();
        echo "OK\n";
        if ($output) {
            echo "   Output: $output\n";
        }
        echo "   Flag attivazione: " . get_option('fpml_needs_activation', 'NON IMPOSTATO') . "\n";
    } else {
        echo "FUNZIONE NON TROVATA\n";
    }
} catch (Exception $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}

echo "\n";

// 8. Test bootstrap
echo "8. TEST BOOTSTRAP\n";
echo "   Esecuzione fpml_bootstrap()... ";
try {
    if (function_exists('fpml_bootstrap')) {
        ob_start();
        fpml_bootstrap();
        $output = ob_get_clean();
        echo "OK\n";
        if ($output) {
            echo "   Output: $output\n";
        }
    } else {
        echo "FUNZIONE NON TROVATA\n";
    }
} catch (Exception $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Linea: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "ERRORE FATALE: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Linea: " . $e->getLine() . "\n";
}

echo "\n";

// 9. Test inizializzazione
echo "9. TEST INIZIALIZZAZIONE\n";
echo "   Esecuzione fpml_run_plugin()... ";
try {
    if (function_exists('fpml_run_plugin')) {
        ob_start();
        fpml_run_plugin();
        $output = ob_get_clean();
        echo "OK\n";
        if ($output) {
            echo "   Output: $output\n";
        }
        
        // Verifica istanza plugin
        if (class_exists('FPML_Plugin')) {
            echo "   Plugin instance: " . (FPML_Plugin::instance() ? "CREATO" : "ERRORE") . "\n";
        }
    } else {
        echo "FUNZIONE NON TROVATA\n";
    }
} catch (Exception $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Linea: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "ERRORE FATALE: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Linea: " . $e->getLine() . "\n";
}

echo "\n";

// 10. Riepilogo
echo "=== RISULTATO ===\n";
echo "Se vedi questo messaggio, il plugin si carica correttamente!\n";
echo "L'errore 500 probabilmente avviene in un altro momento.\n\n";

echo "INFORMAZIONI SISTEMA:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";

// Cleanup
delete_option('fpml_needs_activation');

echo "\n=== TEST COMPLETATO ===\n";
