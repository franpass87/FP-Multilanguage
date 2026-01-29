# FP Multilanguage - API Reference

## Container API

### Ottenere il Container

```php
use FP\Multilanguage\Kernel\Plugin;
use FP\Multilanguage\Kernel\Container;

$kernel = Plugin::getInstance();
$container = $kernel->getContainer();
```

### Metodi Container

#### `bind(string $id, callable|object $factory, bool $shared = true): void`

Registra un servizio nel container.

```php
$container->bind('my.service', function(Container $c) {
    return new MyService();
}, true); // true = singleton
```

#### `get(string $id): mixed`

Ottiene un servizio dal container.

```php
$service = $container->get('my.service');
```

#### `has(string $id): bool`

Verifica se un servizio è registrato.

```php
if ($container->has('my.service')) {
    $service = $container->get('my.service');
}
```

#### `singleton(string $id, object $instance): void`

Registra un'istanza singleton.

```php
$container->singleton('my.service', new MyService());
```

#### `alias(string $alias, string $id): void`

Crea un alias per un servizio.

```php
$container->alias('my.alias', 'my.service');
$service = $container->get('my.alias'); // Restituisce my.service
```

## Logger API

### Ottenere Logger

```php
// Dal container
$logger = $container->get('logger');

// Backward compatible
use FP\Multilanguage\Logger;
Logger::debug('Message', array('context' => 'data'));
```

### Metodi Logger (PSR-3)

```php
$logger->emergency(string $message, array $context = []);
$logger->alert(string $message, array $context = []);
$logger->critical(string $message, array $context = []);
$logger->error(string $message, array $context = []);
$logger->warning(string $message, array $context = []);
$logger->notice(string $message, array $context = []);
$logger->info(string $message, array $context = []);
$logger->debug(string $message, array $context = []);
```

## Options API

### Ottenere Options

```php
// Dal container
$options = $container->get('options');

// Backward compatible
use FP\Multilanguage\Settings;
$settings = Settings::instance();
```

### Metodi Options

```php
// Get option
$value = $options->get('key', 'default');

// Set option
$options->set('key', 'value');

// Delete option
$options->delete('key');

// Get all options
$all = $options->all();

// Check if exists
$exists = $options->has('key');

// Nested keys (dot notation)
$value = $options->get('nested.key', 'default');
$options->set('nested.key', 'value');
```

## Cache API

### Ottenere Cache

```php
$cache = $container->get('cache');
```

### Metodi Cache

```php
// Get cached value
$value = $cache->get('key', 'default');

// Set cached value
$cache->set('key', 'value', 3600); // TTL in secondi

// Delete cached value
$cache->delete('key');

// Clear all cache
$cache->clear();

// Check if exists
$exists = $cache->has('key');
```

## Queue API

### Ottenere Queue

```php
$queue = $container->get('queue');
```

### Metodi Queue

```php
// Enqueue job
$job_id = $queue->enqueue('post', $post_id, 'content', $hash);

// Get jobs by state
$jobs = $queue->getByState('pending', 100);

// Get state counts
$counts = $queue->getStateCounts();

// Process batch
$results = $queue->processBatch(5);

// Get job state
$state = $queue->getJobState('post', $post_id, 'content');
```

## Validation API

### Ottenere Validator

```php
$validator = $container->get('validator');
```

### Metodi Validator

```php
// Validate single value
$valid = $validator->validate('value', 'required');

// Validate all
$errors = $validator->validateAll($data, $rules);

// Check if passes
$passes = $validator->passes();

// Get errors
$errors = $validator->errors();
```

## Sanitization API

### Ottenere Sanitizer

```php
$sanitizer = $container->get('sanitizer');
```

### Metodi Sanitizer

```php
// Sanitize single value
$clean = $sanitizer->sanitize('dirty', 'text');

// Sanitize all
$clean = $sanitizer->sanitizeAll($data, $rules);
```

## HTTP Client API

### Ottenere HttpClient

```php
$http = $container->get('http.client');
```

### Metodi HttpClient

```php
// GET request
$response = $http->get('https://api.example.com/endpoint', array(
    'timeout' => 30,
    'headers' => array('Authorization' => 'Bearer token')
));

// POST request
$response = $http->post('https://api.example.com/endpoint', array(
    'body' => array('key' => 'value'),
    'timeout' => 30
));

// Custom request
$response = $http->request('https://api.example.com/endpoint', array(
    'method' => 'PUT',
    'body' => json_encode($data),
    'headers' => array('Content-Type' => 'application/json')
));
```

## Service Provider API

### Creare Service Provider

```php
namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\ServiceProvider;

class MyServiceProvider implements ServiceProvider {
    public function register(Container $container): void {
        // Registra servizi
    }

    public function boot(Container $container): void {
        // Inizializza servizi
    }

    public function provides(): array {
        return ['my.service'];
    }
}
```

## Filtri WordPress

### `fpml_service_providers`

Filtra la lista di Service Providers da registrare.

```php
add_filter('fpml_service_providers', function($providers) {
    $providers[] = MyCustomServiceProvider::class;
    return $providers;
});
```

### `fpml_translatable_post_types`

Filtra i post types traducibili.

```php
add_filter('fpml_translatable_post_types', function($post_types) {
    $post_types[] = 'my_custom_post_type';
    return $post_types;
});
```

### `fpml_translatable_taxonomies`

Filtra le taxonomies traducibili.

```php
add_filter('fpml_translatable_taxonomies', function($taxonomies) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});
```

## Hook WordPress

### `fpml_activate`

Eseguito dopo l'attivazione del plugin.

```php
add_action('fpml_activate', function() {
    // Your activation logic
});
```

### `fpml_deactivate`

Eseguito dopo la disattivazione del plugin.

```php
add_action('fpml_deactivate', function() {
    // Your deactivation logic
});
```

### `fpml_after_initialization`

Eseguito dopo l'inizializzazione completa del plugin.

```php
add_action('fpml_after_initialization', function() {
    // Your initialization logic
});
```

---

*API Reference aggiornata: Fase 4 - Documentazione*









