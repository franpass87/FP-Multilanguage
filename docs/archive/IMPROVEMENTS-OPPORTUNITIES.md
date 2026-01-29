# Opportunità di Miglioramento - FP Multilanguage

**Data Analisi**: 2025-01-XX  
**Versione Plugin**: 1.0.0

Documento che identifica le opportunità di miglioramento future per il plugin.

---

## 🎯 Miglioramenti Prioritari

### 1. Base Class per Hook Handlers ⭐⭐⭐

**Problema**: I hook handlers (`PostHooks`, `TermHooks`, `CommentHooks`, `WidgetHooks`) hanno codice duplicato per:
- `getAssistedMode()` - stesso metodo in tutti
- Pattern di delegazione a `PostHandlers`/`TermHandlers`
- Fallback a `Core\Plugin`

**Soluzione**:
```php
abstract class BaseHookHandler {
    use ContainerAwareTrait;
    
    protected function getAssistedMode(): bool {
        $container = $this->getContainer();
        if ( $container && $container->has( 'plugin.assisted_mode' ) ) {
            return (bool) $container->get( 'plugin.assisted_mode' );
        }
        return false;
    }
    
    protected function delegateToHandler( string $handler_id, string $method, ...$args ) {
        $container = $this->getContainer();
        $handler = $container && $container->has( $handler_id ) 
            ? $container->get( $handler_id ) 
            : null;
        
        if ( $handler && method_exists( $handler, $method ) ) {
            return call_user_func_array( [ $handler, $method ], $args );
        }
        
        // Fallback to Core\Plugin
        if ( class_exists( '\FP\Multilanguage\Core\Plugin' ) ) {
            $plugin = \FP\Multilanguage\Core\Plugin::instance();
            if ( method_exists( $plugin, $method ) ) {
                return call_user_func_array( [ $plugin, $method ], $args );
            }
        }
        return null;
    }
}
```

**Benefici**:
- Elimina duplicazione di codice
- Facilita manutenzione
- Pattern consistente

**Priorità**: Alta

---

### 2. Costruttori con DI per Hook Handlers ⭐⭐

**Problema**: `PostHooks`, `TermHooks`, `CommentHooks`, `WidgetHooks` usano `ContainerAwareTrait` invece di dependency injection esplicita.

**Soluzione**:
```php
class PostHooks {
    protected $translation_manager;
    protected $job_enqueuer;
    
    public function __construct( 
        TranslationManager $translation_manager, 
        JobEnqueuer $job_enqueuer 
    ) {
        $this->translation_manager = $translation_manager;
        $this->job_enqueuer = $job_enqueuer;
    }
}
```

**Benefici**:
- Dipendenze esplicite
- Più facile da testare
- Pattern DI consistente con altre classi

**Priorità**: Media

---

### 3. Ridurre Plugin.php a < 500 righe ⭐⭐⭐

**Problema**: `Plugin.php` ha ancora ~1200 righe e molte responsabilità.

**Soluzioni**:
- Estrarre logica di diagnostica in `DiagnosticsService`
- Estrarre logica di reindex in `ReindexService`
- Estrarre logica di cost estimation in `CostEstimationService`
- Estrarre logica di assisted mode in `AssistedModeService`

**Benefici**:
- Plugin.php più snello
- Responsabilità chiare
- Facile da testare

**Priorità**: Alta

---

### 4. Convertire Altri Singleton ⭐⭐

**Problema**: Ci sono ancora classi con singleton pattern che potrebbero beneficiare di DI:
- `Processor`
- `Language`
- `Rewrites`
- `MenuSync` (già convertito, ma verificare)
- `Glossary` (già convertito, ma verificare)

**Soluzione**: Convertire gradualmente usando lo stesso pattern:
1. Aggiungere costruttore pubblico
2. Deprecare `instance()`
3. Registrare nel service provider

**Priorità**: Media

---

### 5. Interfacce per Servizi ⭐⭐

**Problema**: I servizi non hanno interfacce, rendendo difficile il testing e il mocking.

**Soluzione**:
```php
interface TranslationManagerInterface {
    public function create_translation( ... );
    public function sync_translation( ... );
}

class TranslationManager implements TranslationManagerInterface {
    // ...
}
```

**Benefici**:
- Facile da testare
- Facile da mockare
- Contratti chiari

**Priorità**: Media

---

### 6. Event System ⭐

**Problema**: Il plugin usa molti hook WordPress diretti. Un sistema di eventi interno potrebbe migliorare la decoupling.

**Soluzione**:
```php
class EventDispatcher {
    public function dispatch( string $event_name, array $data = [] ) {
        do_action( "fpml_{$event_name}", $data );
    }
}
```

**Benefici**:
- Decoupling migliorato
- Facile da estendere
- Testing più semplice

**Priorità**: Bassa

---

### 7. Repository Pattern ⭐⭐

**Problema**: L'accesso al database è sparso in molte classi.

**Soluzione**: Creare repository per:
- `TranslationRepository`
- `QueueRepository`
- `GlossaryRepository`

**Benefici**:
- Logica database centralizzata
- Facile da testare
- Facile da cambiare implementazione

**Priorità**: Media

---

### 8. Value Objects ⭐

**Problema**: Dati passati come array o parametri multipli.

**Soluzione**:
```php
class TranslationRequest {
    public function __construct(
        public readonly int $source_id,
        public readonly string $target_language,
        public readonly array $options = []
    ) {}
}
```

**Benefici**:
- Type safety
- Validazione centralizzata
- Codice più leggibile

**Priorità**: Bassa

---

### 9. Command Pattern ⭐

**Problema**: Operazioni complesse (reindex, sync, etc.) sono metodi diretti.

**Soluzione**:
```php
interface Command {
    public function execute(): CommandResult;
}

class ReindexContentCommand implements Command {
    // ...
}
```

**Benefici**:
- Operazioni incapsulate
- Facile da testare
- Facile da estendere

**Priorità**: Bassa

---

### 10. Test Coverage ⭐⭐⭐

**Problema**: Non ci sono test unitari o di integrazione.

**Soluzione**:
- Aggiungere PHPUnit
- Test per hook handlers
- Test per service providers
- Test per PluginFacade

**Benefici**:
- Maggiore sicurezza
- Refactoring più sicuro
- Documentazione vivente

**Priorità**: Alta

---

## 📊 Priorità Riepilogo

### Alta Priorità ⭐⭐⭐
1. Base Class per Hook Handlers
2. Ridurre Plugin.php a < 500 righe
3. Test Coverage

### Media Priorità ⭐⭐
4. Costruttori con DI per Hook Handlers
5. Convertire Altri Singleton
6. Interfacce per Servizi
7. Repository Pattern

### Bassa Priorità ⭐
8. Event System
9. Value Objects
10. Command Pattern

---

## 🎯 Raccomandazioni Immediate

### Fase 1 (Prossimo Sprint)
1. ✅ Creare `BaseHookHandler` abstract class
2. ✅ Refactor hook handlers per estendere base class
3. ✅ Aggiungere costruttori con DI

### Fase 2 (Sprint Successivo)
1. ✅ Estrarre servizi da Plugin.php
2. ✅ Ridurre Plugin.php a < 500 righe
3. ✅ Aggiungere interfacce per servizi principali

### Fase 3 (Futuro)
1. ✅ Setup PHPUnit
2. ✅ Test per hook handlers
3. ✅ Test per service providers

---

## 📝 Note

- Tutti i miglioramenti proposti mantengono backward compatibility
- I miglioramenti possono essere implementati gradualmente
- Ogni miglioramento può essere fatto indipendentemente

---

**Versione**: 1.0.0  
**Data**: 2025-01-XX

