# FP Multilanguage - Architecture Documentation

## Panoramica Architetturale

FP Multilanguage utilizza un'architettura modulare basata su:
- **Service Provider Pattern** per la registrazione dei servizi
- **Dependency Injection** tramite Container PSR-11
- **Separation of Concerns** con moduli ben definiti
- **PSR Standards** (PSR-3, PSR-4, PSR-11)

## 🏗️ Struttura Architetturale

### Livello 1: Kernel

Il kernel è il cuore del plugin, responsabile di:
- Inizializzazione del container
- Registrazione dei service providers
- Bootstrap del plugin

**File principali:**
- `src/Kernel/Plugin.php` - Plugin kernel
- `src/Kernel/Container.php` - Container PSR-11
- `src/Kernel/ServiceProvider.php` - Interfaccia service provider
- `src/Kernel/Bootstrap.php` - Bootstrap orchestrator

### Livello 2: Foundation

Servizi cross-cutting utilizzati da tutti i moduli:

- **Logger** - Logging PSR-3 compatible
- **Cache** - Caching abstraction (Transients, Object Cache)
- **Options** - Gestione opzioni con defaults e validazione
- **Validation** - Validazione dati
- **Sanitization** - Sanitizzazione input
- **Http** - HTTP client abstraction
- **Environment** - Controlli ambiente e compatibilità

### Livello 3: Core

Logica di business principale:

- **Queue** - Gestione coda traduzioni
- **Translation** - Gestione traduzioni
- **Content** - Handlers per post, term, media, comment
- **Hook** - Gestione hook WordPress

### Livello 4: Modules

Moduli specializzati per contesti specifici:

- **Admin** - Interfaccia amministrativa
- **REST** - REST API endpoints
- **Frontend** - Rendering frontend
- **CLI** - WP-CLI commands
- **Integrations** - Integrazioni terze parti

## 🔄 Flusso di Bootstrap

```
1. fp-multilanguage.php
   └─> Bootstrap::boot()
       └─> Plugin::__construct()
           └─> Container creato
           └─> registerProviders()
               └─> FoundationServiceProvider::register()
               └─> CoreServiceProvider::register()
               └─> AdminServiceProvider::register()
               └─> RESTServiceProvider::register()
               └─> FrontendServiceProvider::register()
               └─> CLIServiceProvider::register()
               └─> IntegrationServiceProvider::register()
           └─> boot()
               └─> Ogni provider esegue boot()
```

## 📦 Service Providers

### FoundationServiceProvider

**Responsabilità:**
- Registra tutti i servizi Foundation
- Disponibile in tutti i contesti

**Servizi registrati:**
- logger, cache, options, validator, sanitizer, http.client, environment.checker, compatibility.checker

### CoreServiceProvider

**Responsabilità:**
- Registra servizi core business logic
- Registra hook WordPress per content lifecycle
- Registra cron events

**Servizi registrati:**
- queue, translation.manager, translation.job_enqueuer, content.indexer, content.post_handler, content.term_handler, hook.manager, translation.orchestrator, processor

### AdminServiceProvider

**Responsabilità:**
- Registra servizi admin
- Disponibile solo in contesto admin

**Servizi registrati:**
- admin, admin.page_renderer, admin.ajax_handlers, admin.post_handlers, admin.nonce_manager

### RESTServiceProvider

**Responsabilità:**
- Registra REST API endpoints
- Disponibile in admin e frontend

**Servizi registrati:**
- rest.admin, rest.route_registrar, rest.handlers.*

### FrontendServiceProvider

**Responsabilità:**
- Registra servizi frontend
- Disponibile solo in frontend

**Servizi registrati:**
- frontend.rewrites, frontend.language, frontend.language_resolver, frontend.url_filter

### CLIServiceProvider

**Responsabilità:**
- Registra WP-CLI commands
- Disponibile solo con WP-CLI

**Servizi registrati:**
- cli.command.queue, cli.command.utility

### IntegrationServiceProvider

**Responsabilità:**
- Auto-detect integrazioni attive
- Inizializza integrazioni se dipendenze presenti

**Servizi registrati:**
- integration.acf, integration.woocommerce, integration.fp_seo, integration.fp_experiences

## 🔌 Dependency Injection

### Container PSR-11

Il container implementa `Psr\Container\ContainerInterface` e fornisce:

- **bind()** - Registra un servizio
- **get()** - Ottiene un servizio
- **has()** - Verifica se un servizio esiste
- **singleton()** - Registra un singleton
- **alias()** - Crea un alias per un servizio

### Risoluzione Dipendenze

Il container risolve automaticamente le dipendenze:

1. Controlla se il servizio è già istanziato (singleton)
2. Controlla se esiste una factory
3. Esegue la factory passando il container
4. Cachea l'istanza se shared
5. Restituisce l'istanza

### Esempio

```php
$container->bind('my.service', function(Container $c) {
    $logger = $c->get('logger');
    $queue = $c->get('queue');
    return new MyService($logger, $queue);
}, true); // true = singleton
```

## 🧩 Classi Base

### BaseHandler

Classe base per tutti i REST handlers:

- `checkPermission()` - Verifica permessi
- `validateRequest()` - Valida richiesta
- `logError()`, `logDebug()` - Logging
- `success()`, `error()` - Response helpers
- `sanitize()` - Sanitizzazione

### BaseCommand

Classe base per tutti i CLI commands:

- `logError()`, `logWarning()`, `logInfo()`, `logDebug()` - Logging
- `isAssistedMode()` - Verifica modalità assistita
- `ensureQueueAvailable()` - Verifica disponibilità coda

### BaseIntegration

Classe base per tutte le integrazioni:

- `checkDependencies()` - Verifica dipendenze
- `init()` - Inizializza integrazione
- `isActive()` - Verifica se attiva
- `getName()` - Nome integrazione

## 🔄 Lifecycle Hooks

### Plugin Lifecycle

- `fpml_activate` - Attivazione plugin
- `fpml_deactivate` - Disattivazione plugin
- `fpml_after_initialization` - Dopo inizializzazione

### Service Provider Lifecycle

1. **register()** - Registra servizi nel container
2. **boot()** - Inizializza servizi e registra hook

### Module Lifecycle

Ogni modulo può registrare i propri hook nel metodo `boot()` del Service Provider.

## 🧪 Testing

### Unit Tests

Test per singole classi in isolamento:
- `tests/Unit/Foundation/` - Test Foundation services

### Integration Tests

Test per interazioni tra componenti:
- `tests/Integration/` - Test Container, Service Providers, Backward Compatibility

## 🔒 Security

### Nonce Management

Gestito da `Admin\NonceManager`:
- Refresh automatico nonce scaduti
- Redirect su nonce invalidi
- Custom wp_die handler

### Permission Checks

Tutti i REST endpoints verificano:
- `current_user_can('manage_options')`
- Nonce validation
- Request sanitization

## 📈 Performance

### Lazy Loading

I servizi sono caricati solo quando richiesti:
- Service Providers registrano factory, non istanze
- Istanze create al primo `get()`

### Caching

- Container cachea istanze singleton
- Cache service per dati frequenti
- Options service con caching interno

## 🔧 Estendibilità

### Filtri WordPress

- `fpml_service_providers` - Aggiungi Service Providers
- `fpml_translatable_post_types` - Filtra post types traducibili
- `fpml_translatable_taxonomies` - Filtra taxonomies traducibili

### Hook Personalizzati

- `fpml_activate` - Dopo attivazione
- `fpml_deactivate` - Dopo disattivazione
- `fpml_after_initialization` - Dopo inizializzazione

---

*Documentazione aggiornata: Fase 3 completata*
