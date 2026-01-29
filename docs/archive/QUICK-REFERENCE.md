# Quick Reference - FP Multilanguage v1.0.0

**Versione**: 1.0.0  
**Data**: 2025-01-XX

Guida rapida per sviluppatori dopo il refactoring strutturale.

---

## 🚀 Quick Start

### Ottenere Plugin Instance
```php
// ✅ NUOVO (Raccomandato)
$plugin = \FP\Multilanguage\Kernel\Plugin::getInstance();

// ⚠️ DEPRECATO (funziona ancora)
$plugin = \FP\Multilanguage\Core\Plugin::instance();
```

### Ottenere Container
```php
// ✅ NUOVO (Raccomandato)
$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
$container = $kernel->getContainer();

// ⚠️ DEPRECATO (funziona ancora)
$service = \FP\Multilanguage\Core\Container::get( 'service_name' );
```

### Ottenere Servizi
```php
// ✅ NUOVO (Raccomandato)
$container = $kernel->getContainer();
$settings = $container->get( 'settings' );
$logger = $container->get( 'logger' );
$queue = $container->get( 'queue' );

// ⚠️ DEPRECATO (funziona ancora, mostra deprecation notice)
$settings = \FPML_Settings::instance();
$logger = \FPML_Logger::instance();
$queue = \FPML_Queue::instance();
```

---

## 📋 Service IDs Disponibili

### Foundation Services
- `settings` - Settings instance
- `logger` - Logger instance
- `options` - Options adapter

### Core Services
- `queue` - Queue handler
- `translation.manager` - Translation manager
- `translation.job_enqueuer` - Job enqueuer
- `content.indexer` - Content indexer
- `content.post_handler` - Post handlers
- `content.term_handler` - Term handlers
- `cost_estimator` - Cost estimator
- `glossary` - Glossary manager

### Hook Handlers
- `hooks.post` - Post hooks handler
- `hooks.term` - Term hooks handler
- `hooks.comment` - Comment hooks handler
- `hooks.widget` - Widget hooks handler
- `hooks.attachment` - Attachment hooks handler

---

## 🔧 Hook Handlers

### PostHooks
```php
$container = $kernel->getContainer();
$post_hooks = $container->get( 'hooks.post' );
$post_hooks->register(); // Registra tutti gli hook sui post
```

### TermHooks
```php
$term_hooks = $container->get( 'hooks.term' );
$term_hooks->register_hooks(); // Registra tutti gli hook sui termini
```

### CommentHooks
```php
$comment_hooks = $container->get( 'hooks.comment' );
$comment_hooks->register(); // Registra tutti gli hook sui commenti
```

### WidgetHooks
```php
$widget_hooks = $container->get( 'hooks.widget' );
$widget_hooks->register(); // Registra hook sui widget
```

### AttachmentHooks
```php
$attachment_hooks = $container->get( 'hooks.attachment' );
$attachment_hooks->register_hooks(); // Registra hook sugli attachment
```

---

## 📝 Classi Deprecate

### ⚠️ Da Evitare (Deprecate)
- `Core\Plugin` → Usa `Kernel\Plugin`
- `Core\Container` → Usa `Kernel\Container`
- `ContentHandlers` → Usa hook handlers dedicati
- `PostHandlers::instance()` → Usa DI via container
- `TermHandlers::instance()` → Usa DI via container

### ✅ Da Usare (Nuove)
- `Kernel\Plugin` - Sistema principale
- `Kernel\Container` - Container principale
- `Core\Hooks\PostHooks` - Hook handler per post
- `Core\Hooks\TermHooks` - Hook handler per termini
- `Core\Hooks\CommentHooks` - Hook handler per commenti
- `Core\Hooks\WidgetHooks` - Hook handler per widget
- `Core\Hooks\AttachmentHooks` - Hook handler per attachment

---

## 🎯 Best Practices

### ✅ DO
- Usa `Kernel\Plugin::getInstance()` per ottenere il plugin
- Usa `Container::get()` per ottenere servizi
- Inietta dipendenze invece di usare singleton
- Usa hook handlers dedicati per nuovi hook
- Consulta `MIGRATION-GUIDE.md` per dettagli

### ❌ DON'T
- Non usare `Core\Plugin::instance()` (deprecato)
- Non usare `Core\Container::get()` (deprecato)
- Non usare `Class::instance()` su classi core (deprecato)
- Non aggiungere hook direttamente in Plugin.php

---

## 📚 Documentazione Completa

Per dettagli completi, consulta:
- `MIGRATION-GUIDE.md` - Guida completa alla migrazione
- `REFACTORING-COMPLETE-SUMMARY.md` - Riepilogo completo
- `README-REFACTORING.md` - Indice principale

---

**Versione**: 1.0.0  
**Ultimo aggiornamento**: 2025-01-XX

