# FP Multilanguage - Quick Start (Refactored Architecture)

## 🚀 Inizio Rapido

### Per Sviluppatori Esistenti

**Nessuna modifica necessaria!** Tutto il codice esistente continua a funzionare.

```php
// Funziona ancora!
$settings = Settings::instance();
$value = $settings->get('key');

Logger::debug('Message');
$queue = Queue::instance();
```

### Per Nuovi Sviluppi

Usa la nuova architettura con Dependency Injection:

```php
use FP\Multilanguage\Kernel\Plugin;

// Ottenere container
$kernel = Plugin::getInstance();
$container = $kernel->getContainer();

// Ottenere servizi
$logger = $container->get('logger');
$queue = $container->get('queue');
$options = $container->get('options');
```

## 📦 Servizi Disponibili

### Foundation Services (Sempre disponibili)

```php
$logger = $container->get('logger');        // LoggerInterface
$cache = $container->get('cache');          // CacheInterface
$options = $container->get('options');       // OptionsInterface
$validator = $container->get('validator');    // ValidatorInterface
$sanitizer = $container->get('sanitizer');   // SanitizerInterface
$http = $container->get('http.client');      // HttpClientInterface
```

### Core Services (Sempre disponibili)

```php
$queue = $container->get('queue');                    // QueueInterface
$translation_manager = $container->get('translation.manager');
$job_enqueuer = $container->get('translation.job_enqueuer');
```

### Admin Services (Solo in admin)

```php
$admin = $container->get('admin');
$page_renderer = $container->get('admin.page_renderer');
$ajax_handlers = $container->get('admin.ajax_handlers');
```

### REST Services (Sempre disponibili)

```php
$rest_admin = $container->get('rest.admin');
$route_registrar = $container->get('rest.route_registrar');
```

## 🔧 Esempi Pratici

### Creare un Nuovo Servizio

```php
namespace FP\Multilanguage\YourModule;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;

class YourService {
    protected $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function doSomething() {
        $this->logger->info('Doing something');
    }
}
```

Registra nel Service Provider:

```php
$container->bind('your.service', function(Container $c) {
    $logger = $c->get('logger');
    return new YourService($logger);
}, true);
```

### Creare un REST Handler

```php
namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Rest\Handlers\BaseHandler;
use WP_REST_Request;
use WP_REST_Response;

class YourHandler extends BaseHandler {
    public function handleRequest(WP_REST_Request $request): WP_REST_Response {
        // Check permission (ereditato)
        $permission = $this->checkPermission($request);
        if (is_wp_error($permission)) {
            return $this->error('Permission denied', 'forbidden', 403);
        }

        // Your logic
        return $this->success(array('data' => 'value'));
    }
}
```

### Creare un CLI Command

```php
namespace FP\Multilanguage\CLI;

use FP\Multilanguage\CLI\BaseCommand;

class YourCommand extends BaseCommand {
    /**
     * Your command description.
     */
    public function __invoke($args, $assoc_args) {
        $this->logInfo('Running your command');
        // Your logic
    }
}
```

## 📚 Documentazione Completa

- **[Migration Guide](docs/MIGRATION_GUIDE.md)** - Guida dettagliata
- **[Architecture](docs/ARCHITECTURE.md)** - Documentazione architetturale
- **[API Reference](docs/API_REFERENCE.md)** - Riferimento API completo

## ✅ Checklist Sviluppo

- [ ] Usa Dependency Injection nel costruttore
- [ ] Estendi classi base quando possibile
- [ ] Registra servizi nei Service Providers
- [ ] Usa interfacce invece di classi concrete
- [ ] Testa le dipendenze prima di usarle
- [ ] Consulta documentazione per esempi

---

*Quick Start - Refactored Architecture*









