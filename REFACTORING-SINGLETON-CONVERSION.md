# Conversione Singleton Pattern - FP Multilanguage

**Data**: 2025-01-XX  
**Versione**: 1.0.0

## 📊 Progresso Conversione Singleton → Dependency Injection

### ✅ Classi Core Convertite (9/9 - 100%)

| Classe | Status | Costruttore | Dependencies | Service Provider |
|--------|--------|-------------|--------------|------------------|
| Settings | ✅ | Pubblico | Nessuna | FoundationServiceProvider |
| Logger | ✅ | Pubblico | Settings (opzionale) | FoundationServiceProvider |
| Queue | ✅ | Pubblico | Nessuna | CoreServiceProvider |
| TranslationManager | ✅ | Pubblico | Logger (opzionale) | CoreServiceProvider |
| JobEnqueuer | ✅ | Pubblico | Queue, Settings (opzionali) | CoreServiceProvider |
| ContentIndexer | ✅ | Pubblico | TranslationManager, JobEnqueuer (opzionali) | CoreServiceProvider |
| MenuSync | ✅ | Pubblico | Logger, Settings (opzionali) | LanguageServiceProvider |
| Glossary | ✅ | Pubblico | Nessuna | CoreServiceProvider |
| CostEstimator | ✅ | Pubblico | Queue (opzionale) | CoreServiceProvider |

**Progresso**: 9/9 classi core convertite (100%) ✅

---

## 🔄 Modifiche Implementate

### 1. Settings
- ✅ Costruttore reso pubblico
- ✅ `instance()` deprecato con `_doing_it_wrong()`
- ✅ Registrato in `FoundationServiceProvider` come `settings`

### 2. Logger
- ✅ Costruttore reso pubblico con parametro opzionale `Settings`
- ✅ `instance()` deprecato
- ✅ Registrato in `FoundationServiceProvider` come `logger.core` e `logger`

### 3. Queue
- ✅ Costruttore reso pubblico
- ✅ `instance()` deprecato
- ✅ Registrato in `CoreServiceProvider` come `queue`

### 4. TranslationManager
- ✅ Costruttore reso pubblico con parametro opzionale `Logger`
- ✅ `instance()` deprecato
- ✅ Registrato in `CoreServiceProvider` come `translation.manager`

### 5. JobEnqueuer
- ✅ Costruttore reso pubblico con parametri opzionali `Queue`, `Settings`
- ✅ `instance()` deprecato
- ✅ Registrato in `CoreServiceProvider` come `translation.job_enqueuer`

### 6. ContentIndexer
- ✅ Costruttore reso pubblico con parametri opzionali `TranslationManager`, `JobEnqueuer`
- ✅ `instance()` deprecato
- ✅ Registrato in `CoreServiceProvider` come `content.indexer`

### 7. MenuSync
- ✅ Costruttore reso pubblico con parametri opzionali `Logger`, `Settings`
- ✅ `instance()` deprecato
- ✅ Registrato in `LanguageServiceProvider` come `menu.sync`
- ✅ Aggiornato per usare nuove istanze invece di singleton

### 8. Glossary
- ✅ Costruttore reso pubblico
- ✅ `instance()` deprecato
- ✅ Registrato in `CoreServiceProvider` come `glossary`

### 9. CostEstimator
- ✅ Costruttore reso pubblico con parametro opzionale `Queue`
- ✅ `instance()` deprecato
- ✅ Registrato in `CoreServiceProvider` come `cost_estimator`
- ✅ Aggiornato `PluginFacade` per usare namespace corretto

---

## 📝 Pattern di Conversione

### Prima (Singleton)
```php
class MyClass {
    protected static $instance = null;
    
    protected function __construct() {
        // initialization
    }
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### Dopo (Dependency Injection)
```php
class MyClass {
    protected static $instance = null; // Mantenuto per backward compatibility
    
    /**
     * @deprecated 1.0.0 Use dependency injection via container instead
     */
    public static function instance() {
        _doing_it_wrong( 
            'MyClass::instance()', 
            'MyClass::instance() is deprecated. Use dependency injection via container instead.', 
            '1.0.0' 
        );
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * @since 1.0.0 Now public to support dependency injection
     */
    public function __construct( $dependency = null ) {
        // Use injected dependency or get from container/singleton
        if ( null === $dependency ) {
            $dependency = Container::get( 'dependency' ) ?: Dependency::instance();
        } else {
            $this->dependency = $dependency;
        }
    }
}
```

---

## 🎯 Benefici Ottenuti

1. **Testabilità**: Classi più facili da testare con mock
2. **Flessibilità**: Dipendenze possono essere sostituite facilmente
3. **Manutenibilità**: Codice più chiaro sulle dipendenze
4. **Backward Compatibility**: Singleton ancora funzionanti (deprecati)
5. **Service Providers**: Centralizzazione della creazione istanze

---

## ⚠️ Note Importanti

### Backward Compatibility
- Tutti i metodi `instance()` sono ancora funzionanti
- Mostrano deprecation notice con `_doing_it_wrong()`
- Verranno rimossi in versione 1.1.0

### Service Providers
- Tutte le classi convertite sono registrate nei service providers
- Le istanze vengono create con dipendenze iniettate
- Fallback a singleton se container non disponibile

### Migrazione Graduale
- Il codice esistente continua a funzionare
- Nuovo codice dovrebbe usare DI via container
- Esempio: `$container->get( 'logger' )` invece di `Logger::instance()`

---

## 📈 Statistiche

- **Classi convertite**: 9/9 (100%)
- **Service Providers aggiornati**: 3
- **Deprecation notices aggiunti**: 9
- **Breaking changes**: 0 (backward compatibility mantenuta)

---

## ✅ Checklist Completamento

- [x] Settings convertito
- [x] Logger convertito
- [x] Queue convertito
- [x] TranslationManager convertito
- [x] JobEnqueuer convertito
- [x] ContentIndexer convertito
- [x] MenuSync convertito
- [x] Glossary convertito
- [x] CostEstimator convertito
- [x] Service Providers aggiornati
- [x] PluginFacade aggiornato
- [x] Backward compatibility verificata

---

**Conversione completata**: 2025-01-XX  
**Prossimo step**: Continuare con classi meno critiche (se necessario)








