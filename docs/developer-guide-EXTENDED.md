# Developer Guide - FP Multilanguage

**Version:** 0.10.0+  
**Last Updated:** 2025-01-XX

Guida completa per sviluppatori che vogliono estendere e personalizzare FP Multilanguage.

---

## 📋 Indice

- [Getting Started](#getting-started)
- [Architettura](#architettura)
- [Estendere Funzionalità](#estendere-funzionalità)
- [Creare Provider Personalizzati](#creare-provider-personalizzati)
- [Best Practices](#best-practices)
- [Testing](#testing)

---

## Getting Started

### Prerequisiti

- WordPress 5.8+
- PHP 8.0+
- Conoscenza di base di WordPress Plugin Development
- Familiarità con PSR-4 autoloading

### Installazione

Il plugin utilizza Composer per le dipendenze:

```bash
cd wp-content/plugins/FP-Multilanguage
composer install
```

### Struttura Directory

```
FP-Multilanguage/
├── src/                          # Codice sorgente
│   ├── Admin/                    # Interfacce admin
│   ├── Content/                  # Gestione contenuti
│   ├── Core/                     # Core functionality
│   ├── Integrations/             # Integrazioni
│   ├── Providers/                # Provider traduzione
│   └── Rest/                     # REST API
├── admin/                        # View templates
├── assets/                       # CSS/JS
├── docs/                         # Documentazione
└── tests/                        # Test suite
```

---

## Architettura

### Pattern Singleton

La maggior parte delle classi principali usa il pattern Singleton:

```php
class MyClass {
    protected static $instance = null;
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    protected function __construct() {
        // Inizializzazione
    }
}
```

### Dependency Injection

Il plugin usa un `Container` per dependency injection:

```php
use FP\Multilanguage\Core\Container;

$container = Container::instance();
$manager = $container->get( 'TranslationManager' );
```

### PSR-4 Autoloading

Tutti i namespace seguono PSR-4:

```php
// File: src/Content/TranslationManager.php
namespace FP\Multilanguage\Content;

class TranslationManager {
    // ...
}
```

---

## Estendere Funzionalità

### Aggiungere Post Type Traducibili

```php
/**
 * Aggiungi post type personalizzato ai traducibili
 */
add_filter( '\FPML_translatable_post_types', function( $post_types ) {
    $post_types[] = 'custom_post_type';
    return $post_types;
});
```

### Aggiungere Taxonomies Traducibili

```php
/**
 * Aggiungi taxonomy personalizzata ai traducibili
 */
add_filter( '\FPML_translatable_taxonomies', function( $taxonomies ) {
    $taxonomies[] = 'custom_taxonomy';
    return $taxonomies;
});
```

### Customizzare Comportamento Traduzione

```php
/**
 * Intercetta dopo salvataggio traduzione
 */
add_action( 'fpml_after_translation_saved', function( $target_post_id, $source_post_id ) {
    // Log personalizzato
    error_log( "Custom: Translation saved {$source_post_id} -> {$target_post_id}" );
    
    // Aggiungi meta personalizzato
    update_post_meta( $target_post_id, '_custom_translated_by', get_current_user_id() );
}, 10, 2 );
```

### Personalizzare Cache TTL

```php
/**
 * Estendi cache per ambienti specifici
 */
add_filter( '\FPML_cache_ttl', function( $ttl ) {
    if ( is_admin() ) {
        return 0; // Nessuna cache in admin
    }
    return 2 * HOUR_IN_SECONDS; // 2 ore per frontend
});
```

---

## Creare Provider Personalizzati

### Interfaccia Provider

Tutti i provider devono implementare `FPML_Translator_Interface`:

```php
interface FPML_Translator_Interface {
    public function translate( $text, $source_lang, $target_lang );
    public function get_name();
    public function is_available();
}
```

### Esempio Provider Personalizzato

```php
<?php
/**
 * Provider traduzione personalizzato
 */
namespace FP\Multilanguage\Providers;

use FPML_Translator_Interface;

class CustomProvider implements FPML_Translator_Interface {
    
    protected $api_key;
    
    public function __construct( $api_key = '' ) {
        $this->api_key = $api_key;
    }
    
    public function translate( $text, $source_lang, $target_lang ) {
        // Implementa logica traduzione
        $result = wp_remote_post( 'https://api.example.com/translate', array(
            'body' => array(
                'text' => $text,
                'source' => $source_lang,
                'target' => $target_lang,
                'api_key' => $this->api_key,
            ),
        ) );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $body = json_decode( wp_remote_retrieve_body( $result ), true );
        return isset( $body['translated_text'] ) ? $body['translated_text'] : new \WP_Error( 'translation_failed', 'Translation failed' );
    }
    
    public function get_name() {
        return 'Custom Provider';
    }
    
    public function is_available() {
        return ! empty( $this->api_key );
    }
}
```

### Registrare Provider

```php
/**
 * Registra provider personalizzato
 */
add_filter( 'fpml_translator_providers', function( $providers ) {
    $providers['custom'] = array(
        'class' => 'FP\Multilanguage\Providers\CustomProvider',
        'name' => 'Custom Provider',
        'config' => array(
            'api_key' => get_option( 'custom_provider_api_key' ),
        ),
    );
    return $providers;
});
```

---

## Best Practices

### 1. Usa Cache Quando Possibile

```php
// ✅ Buono: Usa cache
$cache = \FP\Multilanguage\Core\TranslationCache::instance();
$cached = $cache->get( $text, $source, $target );
if ( $cached !== false ) {
    return $cached;
}
$translated = translate_text( $text );
$cache->set( $text, $translated, $source, $target );

// ❌ Male: Non usa cache
$translated = translate_text( $text ); // Ogni volta ri-traduce
```

### 2. Gestisci Errori Correttamente

```php
// ✅ Buono: Gestisci errori
$result = $manager->create_post_translation( $post, 'en' );
if ( is_wp_error( $result ) ) {
    error_log( 'Error: ' . $result->get_error_message() );
    return;
}

// ❌ Male: Ignora errori
$manager->create_post_translation( $post, 'en' ); // Può fallire silenziosamente
```

### 3. Usa Type Hints

```php
// ✅ Buono: Type hints completi
public function translate( string $text, string $source_lang, string $target_lang ): string|WP_Error {
    // ...
}

// ❌ Male: Nessun type hint
public function translate( $text, $source_lang, $target_lang ) {
    // ...
}
```

### 4. Invalida Cache Quando Necessario

```php
// ✅ Buono: Invalida cache dopo modifiche
add_action( 'save_post', function( $post_id ) {
    $cache = \FP\Multilanguage\Core\TranslationCache::instance();
    $cache->invalidate_post_translations( $post_id );
});

// ❌ Male: Cache non invalidata
// Le traduzioni cachate potrebbero essere obsolete
```

### 5. Usa Transients per Query Costose

```php
// ✅ Buono: Usa transients
$stats = get_transient( 'fpml_stats' );
if ( false === $stats ) {
    $stats = expensive_query();
    set_transient( 'fpml_stats', $stats, 5 * MINUTE_IN_SECONDS );
}

// ❌ Male: Query ogni volta
$stats = expensive_query(); // Lento!
```

---

## Testing

### Unit Tests

Il plugin usa PHPUnit per i test:

```bash
# Esegui tutti i test
composer test

# Test specifici
vendor/bin/phpunit tests/phpunit/TranslationManagerTest.php
```

### Struttura Test

```php
<?php
/**
 * Test per TranslationManager
 */
class TranslationManagerTest extends \WP_UnitTestCase {
    
    public function test_get_translation_id_returns_false_for_invalid_post() {
        $manager = \FP\Multilanguage\Content\TranslationManager::instance();
        $result = $manager->get_translation_id( 0, 'en' );
        $this->assertFalse( $result );
    }
    
    public function test_get_translation_id_uses_cache() {
        $manager = \FP\Multilanguage\Content\TranslationManager::instance();
        
        // Prima chiamata
        $result1 = $manager->get_translation_id( 123, 'en' );
        
        // Seconda chiamata (dovrebbe usare cache)
        $result2 = $manager->get_translation_id( 123, 'en' );
        
        $this->assertEquals( $result1, $result2 );
    }
}
```

### Integration Tests

```php
<?php
/**
 * Test integrazione
 */
class WooCommerceIntegrationTest extends \WP_UnitTestCase {
    
    public function test_product_translation_created() {
        // Crea prodotto
        $product_id = wp_insert_post( array(
            'post_type' => 'product',
            'post_title' => 'Test Product',
        ) );
        
        // Traduci
        $manager = \FP\Multilanguage\Content\TranslationManager::instance();
        $translation = $manager->create_post_translation( get_post( $product_id ), 'en' );
        
        $this->assertNotFalse( $translation );
        $this->assertEquals( 'product', $translation->post_type );
    }
}
```

---

## Esempi Completi

### Esempio 1: Sincronizzazione Custom Meta

```php
/**
 * Sincronizza meta personalizzati tra traduzioni
 */
add_action( 'fpml_after_translation_saved', function( $target_post_id, $source_post_id ) {
    // Copia meta custom
    $custom_meta = get_post_meta( $source_post_id, '_custom_field', true );
    if ( $custom_meta ) {
        update_post_meta( $target_post_id, '_custom_field', $custom_meta );
    }
}, 10, 2 );
```

### Esempio 2: Notifica Email dopo Traduzione

```php
/**
 * Invia email quando traduzione è completata
 */
add_action( 'fpml_after_translation_saved', function( $target_post_id, $source_post_id ) {
    $admin_email = get_option( 'admin_email' );
    $subject = 'Traduzione completata';
    $message = sprintf(
        'La traduzione del post #%d è stata completata (post #%d)',
        $source_post_id,
        $target_post_id
    );
    wp_mail( $admin_email, $subject, $message );
}, 10, 2 );
```

### Esempio 3: Logging Personalizzato

```php
/**
 * Log tutte le traduzioni in file personalizzato
 */
add_action( 'fpml_after_translation_saved', function( $target_post_id, $source_post_id ) {
    $log_file = WP_CONTENT_DIR . '/fpml-translations.log';
    $log_entry = sprintf(
        "[%s] Translation: %d -> %d\n",
        current_time( 'mysql' ),
        $source_post_id,
        $target_post_id
    );
    file_put_contents( $log_file, $log_entry, FILE_APPEND );
}, 10, 2 );
```

---

## 🔗 Link Utili

- [API Reference](./api-reference.md)
- [Hooks and Filters](./hooks-and-filters.md)
- [Architecture](./architecture.md)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)

---

## 📝 Note

- Tutte le funzionalità possono essere estese tramite hook e filter
- Il plugin è progettato per essere estensibile senza modificare il core
- Segui sempre le WordPress Coding Standards
- Testa sempre le tue estensioni prima di usarle in produzione

---

**Ultimo aggiornamento:** 2025-01-XX  
**Versione Plugin:** 0.10.0+







