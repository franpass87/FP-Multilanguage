# FP Multilanguage - Migration Guide

## Guida per Sviluppatori

Questa guida spiega come utilizzare la nuova architettura refactorizzata del plugin FP Multilanguage.

## 📚 Indice

1. [Dependency Injection](#dependency-injection)
2. [Service Providers](#service-providers)
3. [Usare il Container](#usare-il-container)
4. [Migrare Codice Esistente](#migrare-codice-esistente)
5. [Best Practices](#best-practices)

---

## Dependency Injection

### Ottenere Servizi dal Container

```php
use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\Plugin;

// Metodo 1: Dal Plugin kernel
$kernel = Plugin::getInstance();
$container = $kernel->getContainer();
$logger = $container->get('logger');

// Metodo 2: Usando ContainerBridge (backward compatible)
use FP\Multilanguage\Core\ContainerBridge;
$logger = ContainerBridge::get('logger');
```

### Iniettare Dipendenze nel Costruttore

```php
namespace FP\Multilanguage\YourModule;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use FP\Multilanguage\Core\Queue\QueueInterface;

class YourClass {
    protected $logger;
    protected $queue;

    public function __construct(
        LoggerInterface $logger,
        QueueInterface $queue
    ) {
        $this->logger = $logger;
        $this->queue = $queue;
    }
}
```

---

## Service Providers

### Registrare un Nuovo Servizio

```php
namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\ServiceProvider;

class YourServiceProvider implements ServiceProvider {
    public function register(Container $container): void {
        $container->bind('your.service', function(Container $c) {
            $logger = $c->get('logger');
            return new YourService($logger);
        }, true);
    }

    public function boot(Container $container): void {
        // Hook registration, initialization, etc.
    }

    public function provides(): array {
        return ['your.service'];
    }
}
```

### Aggiungere Provider al Kernel

Modifica `src/Kernel/Plugin.php`:

```php
protected function getProviders(): array {
    $providers = array(
        // ... existing providers
        \FP\Multilanguage\Providers\YourServiceProvider::class,
    );
    return apply_filters('fpml_service_providers', $providers);
}
```

---

## Usare il Container

### Servizi Disponibili

#### Foundation Services
- `logger` - LoggerInterface (PSR-3)
- `cache` - CacheInterface
- `options` - OptionsInterface
- `validator` - ValidatorInterface
- `sanitizer` - SanitizerInterface
- `http.client` - HttpClientInterface
- `environment.checker` - EnvironmentChecker
- `compatibility.checker` - CompatibilityChecker

#### Core Services
- `queue` - QueueInterface
- `translation.manager` - TranslationManager
- `translation.job_enqueuer` - JobEnqueuer
- `content.indexer` - ContentIndexer
- `content.post_handler` - PostHandlers
- `content.term_handler` - TermHandlers
- `hook.manager` - HookManager
- `translation.orchestrator` - TranslationOrchestrator

#### Admin Services (solo in admin)
- `admin` - Admin
- `admin.page_renderer` - PageRenderer
- `admin.ajax_handlers` - AjaxHandlers
- `admin.post_handlers` - PostHandlers
- `admin.nonce_manager` - NonceManager

#### REST Services
- `rest.admin` - RestAdmin
- `rest.route_registrar` - RouteRegistrar
- `rest.handlers.provider` - ProviderHandler
- `rest.handlers.queue` - QueueHandler
- `rest.handlers.translation` - TranslationHandler

#### Frontend Services (solo in frontend)
- `frontend.rewrites` - Rewrites
- `frontend.language` - Language
- `frontend.language_resolver` - LanguageResolver
- `frontend.url_filter` - UrlFilter

#### CLI Services (solo con WP-CLI)
- `cli.command.queue` - QueueCommand
- `cli.command.utility` - UtilityCommand

#### Integration Services
- `integration.acf` - ACFSupport
- `integration.woocommerce` - WooCommerceSupport
- `integration.fp_seo` - FpSeoSupport
- `integration.fp_experiences` - FpExperiencesSupport

---

## Migrare Codice Esistente

### Da Settings::instance() a Options

**Prima:**
```php
$settings = Settings::instance();
$value = $settings->get('key', 'default');
```

**Dopo (con container):**
```php
$options = $container->get('options');
$value = $options->get('key', 'default');
```

**Dopo (backward compatible):**
```php
// Funziona ancora!
$settings = Settings::instance();
$value = $settings->get('key', 'default');
```

### Da Logger::debug() a LoggerInterface

**Prima:**
```php
Logger::debug('Message', array('context' => 'data'));
```

**Dopo (con container):**
```php
$logger = $container->get('logger');
$logger->debug('Message', array('context' => 'data'));
```

**Dopo (backward compatible):**
```php
// Funziona ancora!
Logger::debug('Message', array('context' => 'data'));
```

### Da Container::get() a nuovo Container

**Prima:**
```php
$service = Container::get('service_name');
```

**Dopo:**
```php
// Usa ContainerBridge per compatibilità
use FP\Multilanguage\Core\ContainerBridge;
$service = ContainerBridge::get('service_name');

// Oppure direttamente dal kernel
$kernel = Plugin::getInstance();
$container = $kernel->getContainer();
$service = $container->get('service_name');
```

---

## Best Practices

### 1. Usa Dependency Injection

✅ **Buono:**
```php
class MyClass {
    protected $logger;
    
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }
}
```

❌ **Evita:**
```php
class MyClass {
    public function doSomething() {
        $logger = Logger::instance(); // Tight coupling
    }
}
```

### 2. Estendi Classi Base

✅ **Buono:**
```php
class MyRestHandler extends BaseHandler {
    public function handleRequest(WP_REST_Request $request) {
        // Usa metodi ereditati: checkPermission(), logError(), success(), etc.
    }
}
```

### 3. Registra Hook nei Service Providers

✅ **Buono:**
```php
class MyServiceProvider implements ServiceProvider {
    public function boot(Container $container): void {
        add_action('init', array($this, 'registerHooks'));
    }
}
```

### 4. Usa Interfacce, Non Classi Concrete

✅ **Buono:**
```php
public function __construct(LoggerInterface $logger) {
    // Accetta interfaccia, non implementazione concreta
}
```

### 5. Testa le Dipendenze

✅ **Buono:**
```php
if (!$container->has('logger')) {
    return; // Graceful degradation
}
$logger = $container->get('logger');
```

---

## Esempi Pratici

### Creare un Nuovo REST Handler

```php
namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Rest\Handlers\BaseHandler;
use WP_REST_Request;
use WP_REST_Response;

class MyHandler extends BaseHandler {
    public function handleRequest(WP_REST_Request $request): WP_REST_Response {
        // Check permission (ereditato da BaseHandler)
        $permission = $this->checkPermission($request);
        if (is_wp_error($permission)) {
            return $this->error('Permission denied', 'forbidden', 403);
        }

        // Your logic here
        $data = array('success' => true);

        // Return success (ereditato da BaseHandler)
        return $this->success($data);
    }
}
```

### Creare un Nuovo CLI Command

```php
namespace FP\Multilanguage\CLI;

use FP\Multilanguage\CLI\BaseCommand;

class MyCommand extends BaseCommand {
    /**
     * My custom command.
     *
     * ## EXAMPLES
     *
     *     wp fpml my-command
     */
    public function __invoke($args, $assoc_args) {
        $this->logInfo('Running my command');
        // Your logic here
    }
}
```

### Creare una Nuova Integrazione

```php
namespace FP\Multilanguage\Integrations;

use FP\Multilanguage\Integrations\BaseIntegration;

class MyIntegration extends BaseIntegration {
    protected function checkDependencies(): bool {
        return class_exists('MyPlugin');
    }

    public function init(): void {
        if (!$this->checkDependencies()) {
            return;
        }
        $this->active = true;
        // Register hooks, etc.
    }

    public function getName(): string {
        return 'My Integration';
    }
}
```

---

## Troubleshooting

### Service Not Found

Se ottieni un errore "Service not found":

1. Verifica che il Service Provider sia registrato in `Plugin::getProviders()`
2. Verifica che il servizio sia registrato nel metodo `register()` del provider
3. Verifica che il provider sia nella lista `provides()`

### Circular Dependency

Se ottieni un errore "Circular dependency detected":

1. Rivedi le dipendenze tra servizi
2. Usa lazy loading quando possibile
3. Considera di usare setter injection invece di constructor injection

### Backward Compatibility

Se il codice esistente non funziona:

1. Verifica che i LegacyAliases siano registrati
2. Verifica che gli adapter (SettingsAdapter, LoggerAdapter) siano attivi
3. Usa ContainerBridge per Container::get() legacy

---

*Per domande o supporto, consulta la documentazione completa o apri un issue su GitHub.*









