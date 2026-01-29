# Test Matrix - FP Multilanguage

Complete test matrix covering all modules with responsibilities, inputs, outputs, dependencies, failure modes, and severity levels.

## Core Modules

### Kernel Module (`src/Kernel/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Plugin bootstrap, service container, dependency injection |
| **Inputs** | Plugin file path, service providers |
| **Outputs** | Initialized plugin instance, registered services |
| **Dependencies** | Composer autoloader, PHP 8.0+ |
| **Failure Modes** | Missing autoloader, service provider errors, container resolution failures |
| **Severity** | HIGH |
| **Expected Behavior** | Graceful fallback to legacy bootstrap on error |
| **Test Files** | `tests/Unit/Kernel/BootstrapTest.php`, `tests/Unit/Kernel/ContainerTest.php` |

### Foundation Module (`src/Foundation/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Logger, Cache, Options, HTTP Client, Validation, Sanitization |
| **Inputs** | Log messages, cache keys, option names, HTTP requests, user input |
| **Outputs** | Logged entries, cached data, option values, HTTP responses, validated/sanitized data |
| **Dependencies** | WordPress transients API, options API |
| **Failure Modes** | Cache corruption, option autoload issues, HTTP timeout |
| **Severity** | MEDIUM |
| **Expected Behavior** | Fallback to WordPress native APIs on failure |
| **Test Files** | `tests/Unit/Foundation/*Test.php` |

### Domain Module (`src/Domain/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Translation models, repositories, business logic |
| **Inputs** | Post IDs, term IDs, translation data |
| **Outputs** | Translation entities, repository queries |
| **Dependencies** | WordPress database, Core services |
| **Failure Modes** | Invalid post/term IDs, database errors, missing relationships |
| **Severity** | HIGH |
| **Expected Behavior** | Exception handling, validation errors |
| **Test Files** | `tests/Unit/Domain/*Test.php`, `tests/Integration/Domain/*Test.php` |

## Admin Modules

### Admin UI (`src/Admin/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Dashboard, settings pages, bulk operations, translation history |
| **Inputs** | User settings, bulk action selections, filter parameters |
| **Outputs** | Rendered pages, AJAX responses, bulk operation results |
| **Dependencies** | WordPress admin API, REST API |
| **Failure Modes** | Permission denied, invalid settings, AJAX errors |
| **Severity** | MEDIUM |
| **Expected Behavior** | Error messages, permission checks, validation feedback |
| **Test Files** | `tests/Integration/Admin/*Test.php` |

### Translation Metabox (`src/Admin/TranslationMetabox.php`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Post edit screen translation UI |
| **Inputs** | Post ID, translation status, user actions |
| **Outputs** | Metabox HTML, AJAX responses |
| **Dependencies** | WordPress post API, AJAX API |
| **Failure Modes** | Missing post, invalid pair ID, translation errors |
| **Severity** | HIGH |
| **Expected Behavior** | Status indicators, error messages, retry options |
| **Test Files** | `tests/Integration/Admin/TranslationMetaboxTest.php` |

### Bulk Translator (`src/Admin/BulkTranslator.php`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Bulk translation operations |
| **Inputs** | Post IDs array, translation options |
| **Outputs** | Queue jobs, progress updates |
| **Dependencies** | Queue system, TranslationManager |
| **Failure Modes** | Queue full, provider errors, timeout |
| **Severity** | MEDIUM |
| **Expected Behavior** | Progress tracking, error reporting, partial success handling |
| **Test Files** | `tests/Integration/Admin/BulkTranslatorTest.php` |

## Frontend Modules

### Routing (`src/Frontend/Routing/Rewrites.php`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | `/en/` segment URL rewriting, language detection |
| **Inputs** | Request URL, query parameters |
| **Outputs** | Rewritten URLs, language context |
| **Dependencies** | WordPress rewrite API |
| **Failure Modes** | Rewrite rules not flushed, conflicting rules, invalid URLs |
| **Severity** | HIGH |
| **Expected Behavior** | Automatic rewrite flush on activation, fallback to query parameter |
| **Test Files** | `tests/Integration/Frontend/RewritesTest.php` |

### Site Translations (`src/Frontend/Content/SiteTranslations.php`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Menu, widget, options translation on frontend |
| **Inputs** | Menu locations, widget IDs, option names |
| **Outputs** | Translated content |
| **Dependencies** | Menu API, Widget API, Options API |
| **Failure Modes** | Missing translations, cache issues, filter conflicts |
| **Severity** | MEDIUM |
| **Expected Behavior** | Fallback to source language, cache invalidation |
| **Test Files** | `tests/Integration/Frontend/SiteTranslationsTest.php` |

### Language Switcher Widget (`src/Frontend/Widgets/LanguageSwitcherWidget.php`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Frontend language switching UI |
| **Inputs** | Current URL, language selection |
| **Outputs** | Widget HTML, language switch links |
| **Dependencies** | Widget API, Routing |
| **Failure Modes** | Invalid URLs, missing translations |
| **Severity** | LOW |
| **Expected Behavior** | Valid links, current language highlighting |
| **Test Files** | `tests/Integration/Frontend/LanguageSwitcherWidgetTest.php` |

## REST API Module

### REST Controllers (`src/Rest/Controllers/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | REST endpoint handlers |
| **Inputs** | HTTP requests (GET, POST, PUT, DELETE) |
| **Outputs** | JSON responses, HTTP status codes |
| **Dependencies** | WordPress REST API |
| **Failure Modes** | Authentication failure, invalid parameters, server errors |
| **Severity** | HIGH |
| **Expected Behavior** | Proper status codes (200, 400, 401, 403, 500), error structures |
| **Test Files** | `tests/Integration/Rest/*Test.php` |

## CLI Module

### WP-CLI Commands (`src/CLI/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Command-line operations |
| **Inputs** | Command arguments, options |
| **Outputs** | Console output, exit codes |
| **Dependencies** | WP-CLI |
| **Failure Modes** | Invalid arguments, permission errors, command errors |
| **Severity** | MEDIUM |
| **Expected Behavior** | Help text, validation errors, proper exit codes |
| **Test Files** | `tests/Integration/CLI/*Test.php` |

## Queue Module

### Queue System (`src/Queue/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Translation job queue management |
| **Inputs** | Translation jobs, processing requests |
| **Outputs** | Processed jobs, queue status |
| **Dependencies** | Database table (`wp_fpml_queue`), Cron API |
| **Failure Modes** | Queue lock, processing timeout, job errors |
| **Severity** | HIGH |
| **Expected Behavior** | Retry logic, error logging, status tracking |
| **Test Files** | `tests/Integration/Queue/*Test.php` |

## Translation Module

### Translation Manager (`src/Content/TranslationManager/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Content translation orchestration |
| **Inputs** | Source post/term IDs, translation options |
| **Outputs** | Translated posts/terms, relationship metadata |
| **Dependencies** | Queue, Providers, Content handlers |
| **Failure Modes** | Provider errors, content parsing failures, relationship errors |
| **Severity** | HIGH |
| **Expected Behavior** | Error recovery, partial translation, relationship preservation |
| **Test Files** | `tests/Integration/Translation/TranslationManagerTest.php` |

### Translation Providers (`src/Providers/`)

| Aspect | Details |
|--------|---------|
| **Responsibilities** | AI translation API integration |
| **Inputs** | Source text, language pair, options |
| **Outputs** | Translated text, quality scores |
| **Dependencies** | External APIs (OpenAI, etc.), HTTP client |
| **Failure Modes** | API errors, rate limiting, timeout, invalid responses |
| **Severity** | HIGH |
| **Expected Behavior** | Fallback providers, retry logic, error messages |
| **Test Files** | `tests/Integration/Translation/ProvidersTest.php` |

## Integration Modules

### WooCommerce Support

| Aspect | Details |
|--------|---------|
| **Responsibilities** | Product translation, variations, attributes |
| **Inputs** | Product IDs, variation data, attribute values |
| **Outputs** | Translated products, synchronized variations |
| **Dependencies** | WooCommerce plugin |
| **Failure Modes** | Missing WooCommerce, product errors, variation sync failures |
| **Severity** | HIGH |
| **Expected Behavior** | Conditional loading, error handling, relationship mapping |
| **Test Files** | `tests/Integration/WooCommerce/*Test.php` |

### Salient Theme Support

| Aspect | Details |
|--------|---------|
| **Responsibilities** | 70+ meta fields translation |
| **Inputs** | Post meta keys/values |
| **Outputs** | Translated meta |
| **Dependencies** | Salient theme |
| **Failure Modes** | Missing theme, meta key changes |
| **Severity** | MEDIUM |
| **Expected Behavior** | Conditional loading, meta whitelist |
| **Test Files** | `tests/Integration/Salient/*Test.php` |

### FP-SEO Support

| Aspect | Details |
|--------|---------|
| **Responsibilities** | 25+ SEO meta fields translation |
| **Inputs** | SEO meta keys/values |
| **Outputs** | Translated SEO meta |
| **Dependencies** | FP-SEO-Manager plugin |
| **Failure Modes** | Missing plugin, meta structure changes |
| **Severity** | MEDIUM |
| **Expected Behavior** | Conditional loading, meta detection |
| **Test Files** | `tests/Integration/FPSEO/*Test.php` |

## Database Modules

### Queue Table (`wp_fpml_queue`)

| Aspect | Details |
|--------|---------|
| **Schema** | id, job_type, source_id, target_id, status, data, created_at, processed_at |
| **Operations** | INSERT (enqueue), SELECT (process), UPDATE (status), DELETE (cleanup) |
| **Failure Modes** | Table missing, lock timeout, data corruption |
| **Severity** | HIGH |
| **Test Files** | `tests/Integration/Database/QueueTableTest.php` |

### Translation Versions Table (`wp_fpml_translation_versions`)

| Aspect | Details |
|--------|---------|
| **Schema** | id, translation_id, version, content, created_at |
| **Operations** | INSERT (version), SELECT (history), DELETE (cleanup) |
| **Failure Modes** | Table missing, version conflicts |
| **Severity** | MEDIUM |
| **Test Files** | `tests/Integration/Database/VersioningTableTest.php` |

### Logs Table (`wp_fpml_logs`)

| Aspect | Details |
|--------|---------|
| **Schema** | id, level, message, context, created_at |
| **Operations** | INSERT (log), SELECT (query), DELETE (cleanup) |
| **Failure Modes** | Table missing, log overflow |
| **Severity** | LOW |
| **Test Files** | `tests/Integration/Database/LogsTableTest.php` |

## Test Coverage Summary

| Module | Unit Tests | Integration Tests | E2E Tests | Total |
|--------|-----------|-------------------|-----------|-------|
| Kernel | 5 | 3 | 0 | 8 |
| Foundation | 15 | 5 | 0 | 20 |
| Domain | 10 | 8 | 0 | 18 |
| Admin | 0 | 12 | 3 | 15 |
| Frontend | 0 | 8 | 2 | 10 |
| REST API | 0 | 10 | 1 | 11 |
| CLI | 0 | 6 | 0 | 6 |
| Queue | 0 | 8 | 0 | 8 |
| Translation | 0 | 15 | 2 | 17 |
| Integrations | 0 | 20 | 2 | 22 |
| Database | 0 | 6 | 0 | 6 |
| **Total** | **30** | **101** | **10** | **141** |

## Test Execution Priority

1. **Critical (P0)**: Kernel, Queue, Translation Manager, Database
2. **High (P1)**: Foundation, Domain, REST API, Admin Core
3. **Medium (P2)**: Frontend, CLI, Integrations
4. **Low (P3)**: Widgets, Utilities, Helpers














