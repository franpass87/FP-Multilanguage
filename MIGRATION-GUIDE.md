# Guida alla Migrazione - FP Multilanguage v1.0.0

**Versione**: 1.0.0  
**Data**: 2025-01-XX

Questa guida aiuta gli sviluppatori a migrare il loro codice per usare la nuova architettura del plugin FP Multilanguage.

---

## 🔄 Cambiamenti Principali

### 1. Sistema Kernel Unificato

**Prima (Deprecato)**:
```php
$plugin = \FP\Multilanguage\Core\Plugin::instance();
```

**Dopo (Raccomandato)**:
```php
$plugin = \FP\Multilanguage\Kernel\Plugin::getInstance();
```

**Nota**: `Core\Plugin` è deprecato e sarà rimosso in v1.1.0.

---

### 2. Dependency Injection invece di Singleton

**Prima (Deprecato)**:
```php
$settings = \FPML_Settings::instance();
$logger = \FPML_Logger::instance();
$queue = \FPML_Queue::instance();
```

**Dopo (Raccomandato)**:
```php
// Via Container
$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
$container = $kernel->getContainer();

$settings = $container->get( 'settings' );
$logger = $container->get( 'logger' );
$queue = $container->get( 'queue' );
```

**Nota**: I metodi `instance()` sono ancora funzionanti ma mostrano deprecation notice.

---

### 3. Hook Handlers Dedicati

**Prima (Deprecato)**:
```php
// Hook gestiti direttamente in Plugin.php
add_action( 'save_post', array( $plugin, 'handle_save_post' ), 999, 3 );
```

**Dopo (Raccomandato)**:
```php
// Hook gestiti da hook handlers dedicati
// I hook vengono registrati automaticamente da HookManager
// Non è necessario registrarli manualmente
```

**Hook Handlers Disponibili**:
- `PostHooks` - Gestisce hook sui post
- `TermHooks` - Gestisce hook sui termini
- `CommentHooks` - Gestisce hook sui commenti
- `WidgetHooks` - Gestisce hook sui widget
- `AttachmentHooks` - Gestisce hook sugli attachment

---

### 4. Container Unificato

**Prima (Deprecato)**:
```php
$service = \FP\Multilanguage\Core\Container::get( 'service_name' );
```

**Dopo (Raccomandato)**:
```php
$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
$container = $kernel->getContainer();
$service = $container->get( 'service_name' );
```

**Nota**: `Core\Container` è un adapter che delega a `Kernel\Container`. Funziona ancora ma è deprecato.

---

## 📋 Checklist Migrazione

### Per Sviluppatori di Estensioni

- [ ] Sostituire `Core\Plugin::instance()` con `Kernel\Plugin::getInstance()`
- [ ] Sostituire `Core\Container::get()` con `Kernel\Container::get()`
- [ ] Sostituire `Class::instance()` con dependency injection via container
- [ ] Verificare che gli hook siano ancora funzionanti
- [ ] Testare tutte le funzionalità

### Per Sviluppatori del Plugin

- [ ] Usare Service Providers per registrare servizi
- [ ] Usare hook handlers dedicati invece di metodi in Plugin.php
- [ ] Iniettare dipendenze invece di usare singleton
- [ ] Testare backward compatibility

---

## 🔧 Esempi Pratici

### Esempio 1: Ottenere Settings

**Prima**:
```php
$settings = \FPML_Settings::instance();
$value = $settings->get( 'option_key' );
```

**Dopo**:
```php
$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
$container = $kernel->getContainer();
$settings = $container->get( 'settings' );
$value = $settings->get( 'option_key' );
```

**Oppure (se hai già accesso al container)**:
```php
$settings = $container->get( 'settings' );
$value = $settings->get( 'option_key' );
```

---

### Esempio 2: Ottenere Logger

**Prima**:
```php
\FPML_Logger::info( 'Message', array( 'context' => 'data' ) );
```

**Dopo**:
```php
// I metodi statici funzionano ancora, ma è meglio usare l'istanza
$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
$container = $kernel->getContainer();
$logger = $container->get( 'logger' );
$logger->info( 'Message', array( 'context' => 'data' ) );
```

**Nota**: I metodi statici di Logger funzionano ancora per backward compatibility.

---

### Esempio 3: Ottenere Translation Manager

**Prima**:
```php
$manager = \FP\Multilanguage\Content\TranslationManager::instance();
$manager->ensure_translation( $post );
```

**Dopo**:
```php
$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
$container = $kernel->getContainer();
$manager = $container->get( 'translation.manager' );
$manager->ensure_translation( $post );
```

---

### Esempio 4: Aggiungere un Hook Handler Personalizzato

**Prima**:
```php
// Aggiungere metodo in Plugin.php
public function handle_custom_hook( $data ) {
    // logic
}
add_action( 'custom_hook', array( $plugin, 'handle_custom_hook' ), 10, 1 );
```

**Dopo**:
```php
// Creare CustomHooks class
namespace FP\Multilanguage\Core\Hooks;

class CustomHooks {
    public function register_hooks(): void {
        add_action( 'custom_hook', array( $this, 'handle_custom_hook' ), 10, 1 );
    }
    
    public function handle_custom_hook( $data ) {
        // logic
    }
}

// Registrare in Service Provider
$container->bind( 'hooks.custom', function( Container $c ) {
    return new \FP\Multilanguage\Core\Hooks\CustomHooks();
}, true );

// Registrare in HookManager
$custom_hooks = $container->get( 'hooks.custom' );
if ( $custom_hooks ) {
    $custom_hooks->register_hooks();
}
```

---

## ⚠️ Breaking Changes

**Nessun breaking change in v1.0.0!**

Tutte le modifiche mantengono backward compatibility:
- Singleton ancora funzionanti (deprecati)
- `Core\Plugin` ancora funzionante (deprecato)
- `Core\Container` ancora funzionante (deprecato)
- Metodi pubblici invariati

**Breaking changes previsti in v1.1.0**:
- Rimozione di `Core\Plugin`
- Rimozione di `Core\Container`
- Rimozione di metodi `instance()` su classi core

---

## 🐛 Troubleshooting

### Problema: "Class not found"

**Soluzione**: Verifica che stai usando il namespace corretto:
- `\FP\Multilanguage\Kernel\Plugin` invece di `\FP\Multilanguage\Core\Plugin`
- `\FP\Multilanguage\Diagnostics\CostEstimator` invece di `\FP\Multilanguage\CostEstimator`

### Problema: "Container service not found"

**Soluzione**: Verifica che il service sia registrato nel Service Provider corretto:
- Settings/Logger → `FoundationServiceProvider`
- Queue/TranslationManager → `CoreServiceProvider`
- MenuSync → `LanguageServiceProvider`

### Problema: "Hook not working"

**Soluzione**: Verifica che l'hook sia registrato in un hook handler dedicato o in `HookManager`.

---

## 📚 Risorse Aggiuntive

- `REFACTORING-COMPLETE-SUMMARY.md` - Riepilogo completo del refactoring
- `REFACTORING-SINGLETON-CONVERSION.md` - Dettagli sulla conversione singleton
- `REFACTORING-FINAL-REPORT.md` - Report finale del refactoring

---

## ✅ Supporto

Per domande o problemi durante la migrazione:
1. Controlla i file di documentazione
2. Verifica i deprecation notices in admin
3. Controlla i log per errori

---

**Ultimo aggiornamento**: 2025-01-XX  
**Versione Plugin**: 1.0.0








