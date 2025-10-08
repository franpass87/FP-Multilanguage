# Piano di Miglioramento della Modularizzazione - FP Multilanguage

## Analisi Corrente

### Metriche Codice
- **Totale classi**: 33
- **Classi più grandi**:
  - `class-language.php`: 1784 righe, 34 metodi
  - `class-processor.php`: 1723 righe, 32 metodi
  - `class-plugin.php`: 1508 righe, 29 metodi
  - `class-seo.php`: 1153 righe

### Punti di Forza
✅ Separazione chiara tra componenti (Admin, REST, CLI)  
✅ Pattern Singleton consistente  
✅ Provider di traduzione ben isolati  
✅ Autoloading automatico  

### Criticità
❌ Classi principali troppo grandi (>1500 righe)  
❌ `FPML_Plugin` ha troppe responsabilità  
❌ Accoppiamento stretto (16 dipendenze dirette da Plugin)  
❌ Mancanza di namespace PHP  
❌ Logica procedurale nel file bootstrap  

---

## Proposte di Miglioramento

### 1. Ristrutturazione `FPML_Plugin` ⭐⭐⭐ PRIORITÀ ALTA

**Problema**: Classe "God Object" con 1508 righe e responsabilità multiple.

**Soluzione**: Estrarre responsabilità in classi dedicate:

```
fp-multilanguage/includes/
├── core/
│   ├── class-plugin.php (ridotta a ~300 righe - solo bootstrap)
│   ├── class-translation-manager.php (gestione traduzioni post/term)
│   ├── class-job-enqueuer.php (accodamento job)
│   └── class-assisted-mode-detector.php
├── diagnostics/
│   ├── class-diagnostics.php (snapshot diagnostici)
│   └── class-cost-estimator.php (stime costi)
└── indexing/
    ├── class-content-indexer.php (reindex generale)
    ├── class-post-indexer.php
    └── class-term-indexer.php
```

**Benefici**:
- Ogni classe < 500 righe
- Responsabilità singole e chiare
- Più facile testare e mantenere

---

### 2. Suddivisione `FPML_Language` ⭐⭐⭐ PRIORITÀ ALTA

**Problema**: 1784 righe - gestisce detection, routing, URL rewriting, cookie, redirect.

**Soluzione**: Dividere in sotto-componenti:

```
fp-multilanguage/includes/language/
├── class-language-detector.php (detection lingua corrente)
├── class-url-translator.php (conversione URL tra lingue)
├── class-language-switcher.php (generazione switcher)
├── class-cookie-manager.php (gestione preferenze)
└── class-redirect-handler.php (redirect automatici)
```

---

### 3. Refactoring `FPML_Processor` ⭐⭐ PRIORITÀ MEDIA

**Problema**: 1723 righe - orchestrazione, lock, traduzione, retry, fallback.

**Soluzione**: Estrarre logiche specifiche:

```
fp-multilanguage/includes/processor/
├── class-processor.php (orchestratore principale ~400 righe)
├── class-lock-manager.php (gestione lock)
├── class-job-executor.php (esecuzione singolo job)
├── class-retry-handler.php (logica retry)
└── class-content-sanitizer.php (sanitizzazione pre/post traduzione)
```

---

### 4. Introduzione Namespace ⭐⭐ PRIORITÀ MEDIA

**Migrazione graduale** da prefissi a namespace:

```php
// Vecchio
class FPML_Plugin { }

// Nuovo
namespace FP\Multilanguage\Core;
class Plugin { }
```

**Struttura namespace proposta**:
```
FP\Multilanguage\
├── Core\              (Plugin, Settings, Logger)
├── Translation\       (Processor, Queue, Providers)
├── Language\          (Language, Rewrites)
├── Content\           (Post/Term management)
├── Integrations\      (ACF, WooCommerce, SEO)
├── Admin\             (Admin UI)
├── REST\              (REST API)
└── CLI\               (WP-CLI commands)
```

**Compatibilità**: Mantenere alias per backward compatibility:
```php
class_alias('FP\Multilanguage\Core\Plugin', 'FPML_Plugin');
```

---

### 5. Dependency Injection invece di Singleton ⭐⭐ PRIORITÀ MEDIA

**Problema attuale**:
```php
// In qualsiasi classe
$plugin = FPML_Plugin::instance();
$settings = FPML_Settings::instance();
```
→ Accoppiamento stretto, difficile da testare

**Soluzione**: Service Container:

```php
// Nuovo approccio
class FPML_Service_Container {
    protected static $services = [];
    
    public static function register(string $name, callable $factory) {
        self::$services[$name] = $factory;
    }
    
    public static function get(string $name) {
        if (!isset(self::$services[$name])) {
            throw new Exception("Service {$name} not found");
        }
        return call_user_func(self::$services[$name]);
    }
}

// Registrazione (in bootstrap)
FPML_Service_Container::register('settings', function() {
    return FPML_Settings::instance();
});

FPML_Service_Container::register('queue', function() {
    return FPML_Queue::instance();
});

// Uso nelle classi
class FPML_Processor {
    protected $settings;
    protected $queue;
    
    public function __construct() {
        $this->settings = FPML_Service_Container::get('settings');
        $this->queue = FPML_Service_Container::get('queue');
    }
}
```

**Benefici**:
- Dipendenze esplicite
- Più facile mockare nei test
- Configurazione centralizzata

---

### 6. Autoloader PSR-4 ⭐ PRIORITÀ BASSA

**Problema**: Funzione `autoload_fpml_files()` procedurale nel file principale.

**Soluzione**: Classe Autoloader conforme PSR-4:

```php
class FPML_Autoloader {
    protected $prefix = 'FPML_';
    protected $base_dir;
    
    public function __construct($base_dir) {
        $this->base_dir = $base_dir;
    }
    
    public function register() {
        spl_autoload_register([$this, 'load_class']);
    }
    
    protected function load_class($class) {
        if (strpos($class, $this->prefix) !== 0) {
            return;
        }
        
        $relative_class = substr($class, strlen($this->prefix));
        $file = $this->base_dir . 'includes/class-' . 
                str_replace('_', '-', strtolower($relative_class)) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

// In fp-multilanguage.php
$autoloader = new FPML_Autoloader(FPML_PLUGIN_DIR);
$autoloader->register();
```

---

### 7. Raggruppamento Feature in Moduli ⭐ PRIORITÀ BASSA

Creare sotto-cartelle tematiche:

```
fp-multilanguage/includes/
├── core/               (Plugin, Settings, Logger, Queue)
├── translation/        (Processor, Providers)
├── language/           (Language, Rewrites)
├── content/            (gestione post/term/menu)
├── integrations/       (ACF, SEO, WooCommerce)
├── automation/         (Auto-Detection, Auto-Translate, Rush Mode)
└── export/             (Export/Import)
```

---

## Roadmap di Implementazione

### Fase 1: Fondamenta (2-3 settimane)
1. ✅ Creare struttura cartelle `includes/core/`, `includes/translation/`, etc.
2. ✅ Implementare Service Container
3. ✅ Estrarre `Translation_Manager` da `FPML_Plugin`
4. ✅ Estrarre `Job_Enqueuer` da `FPML_Plugin`
5. ✅ Scrivere test per le nuove classi

### Fase 2: Classi Grandi (3-4 settimane)
1. ✅ Suddividere `FPML_Language` in sotto-componenti
2. ✅ Suddividere `FPML_Processor` in sotto-componenti
3. ✅ Refactoring `FPML_SEO`
4. ✅ Aggiornare tutti i riferimenti

### Fase 3: Modernizzazione (2-3 settimane)
1. ✅ Introdurre namespace (con alias per BC)
2. ✅ Aggiornare Autoloader a PSR-4
3. ✅ Convertire chiamate Singleton → Service Container
4. ✅ Aggiornare documentazione

### Fase 4: Ottimizzazione (1-2 settimane)
1. ✅ Review finale codice
2. ✅ Performance testing
3. ✅ Documentazione architettura
4. ✅ Update deployment scripts

**TOTALE STIMATO**: 8-12 settimane (part-time)

---

## Metriche di Successo

- [ ] Nessuna classe > 800 righe
- [ ] Nessuna classe con > 25 metodi
- [ ] Copertura test >= 70%
- [ ] Zero breaking changes per utenti finali
- [ ] Documentazione architettura completa
- [ ] Performance invariata o migliorata

---

## Note Importanti

⚠️ **Backward Compatibility**: Essenziale mantenere compatibilità con:
- Plugin/temi che usano `FPML_Plugin::instance()`
- Eventuali estensioni esterne
- Hook/filtri esistenti

✅ **Strategia**: 
- Mantenere vecchie classi come "facade" che delegano alle nuove
- Deprecare gradualmente con avvisi nei log
- Rimuovere solo in major version (es. 1.0.0)

---

## Conclusione

Il progetto ha già una **buona base modulare**, ma le classi principali sono cresciute troppo. 

**Raccomandazione**: Procedere con Fase 1 e 2 della roadmap per ottenere il massimo beneficio con impatto controllato.

I miglioramenti proposti porteranno a:
- ✅ **Manutenibilità**: più facile trovare e modificare codice
- ✅ **Testabilità**: classi piccole = test più semplici  
- ✅ **Scalabilità**: aggiungere nuove feature senza "gonfiare" classi esistenti
- ✅ **Collaborazione**: sviluppatori multipli possono lavorare senza conflitti
