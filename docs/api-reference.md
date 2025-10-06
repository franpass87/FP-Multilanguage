# API Reference - FP Multilanguage

## Table of Contents
- [Hooks](#hooks)
  - [Actions](#actions)
  - [Filters](#filters)
- [REST API](#rest-api)
- [WP-CLI Commands](#wp-cli-commands)
- [PHP Classes](#php-classes)

---

## Hooks

### Actions

#### `fpml_post_jobs_enqueued`
Fired after translation jobs are enqueued for a post.

**Parameters:**
- `$source_post` (WP_Post) - Source Italian post
- `$target_post` (WP_Post) - Target English post  
- `$update` (bool) - Whether this is an update

**Example:**
```php
add_action( 'fpml_post_jobs_enqueued', function( $source, $target, $update ) {
    // Send notification
    if ( $update ) {
        do_action( 'custom_translation_updated', $source->ID );
    }
}, 10, 3 );
```

---

#### `fpml_post_translated`
Fired when translated post fields are saved.

**Parameters:**
- `$post_id` (int) - Target post ID
- `$field` (string) - Field that was translated (post_title, post_content, etc.)
- `$translated_text` (string) - The translated content

**Example:**
```php
add_action( 'fpml_post_translated', function( $post_id, $field, $text ) {
    error_log( "Post $post_id field $field translated" );
}, 10, 3 );
```

---

#### `fpml_term_translated`
Fired when a taxonomy term field is translated.

**Parameters:**
- `$term_id` (int) - Term ID
- `$taxonomy` (string) - Taxonomy slug
- `$field` (string) - Field translated (name or description)

**Example:**
```php
add_action( 'fpml_term_translated', function( $term_id, $taxonomy, $field ) {
    // Update custom cache
}, 10, 3 );
```

---

#### `fpml_menu_item_translated`
Fired when a navigation menu item is translated.

**Parameters:**
- `$menu_item_id` (int) - Menu item post ID
- `$field` (string) - Field translated

**Example:**
```php
add_action( 'fpml_menu_item_translated', function( $item_id, $field ) {
    wp_cache_delete( 'menu_' . $item_id, 'fpml_menus' );
}, 10, 2 );
```

---

#### `fpml_queue_after_cleanup`
Runs after queue cleanup completes.

**Parameters:**
- `$deleted` (int) - Number of jobs deleted
- `$states` (array) - States that were cleaned up
- `$days` (int) - Retention period in days

**Example:**
```php
add_action( 'fpml_queue_after_cleanup', function( $deleted, $states, $days ) {
    if ( $deleted > 100 ) {
        // Log significant cleanup
        error_log( "Cleaned up $deleted jobs older than $days days" );
    }
}, 10, 3 );
```

---

### Filters

#### `fpml_translatable_post_types`
Modify which post types are translated.

**Parameters:**
- `$post_types` (array) - Array of post type slugs

**Returns:** array

**Example:**
```php
add_filter( 'fpml_translatable_post_types', function( $post_types ) {
    // Remove 'attachment' from translation
    return array_diff( $post_types, array( 'attachment' ) );
});
```

---

#### `fpml_translatable_taxonomies`
Control which taxonomies receive English counterparts.

**Parameters:**
- `$taxonomies` (array) - Array of taxonomy slugs

**Returns:** array

**Example:**
```php
add_filter( 'fpml_translatable_taxonomies', function( $taxonomies ) {
    // Add custom taxonomy
    $taxonomies[] = 'custom_tax';
    return $taxonomies;
});
```

---

#### `fpml_queue_cleanup_states`
Define which job states are eligible for cleanup.

**Parameters:**
- `$states` (array) - Default: `['done', 'skipped', 'error']`

**Returns:** array

**Example:**
```php
add_filter( 'fpml_queue_cleanup_states', function( $states ) {
    // Don't cleanup error jobs
    return array_diff( $states, array( 'error' ) );
});
```

---

#### `fpml_queue_cleanup_batch_size`
Override the batch size used during cleanup.

**Parameters:**
- `$batch_size` (int) - Default: 500

**Returns:** int

**Example:**
```php
add_filter( 'fpml_queue_cleanup_batch_size', function( $size ) {
    return 1000; // Cleanup 1000 jobs at a time
});
```

---

#### `fpml_meta_whitelist`
Extend the list of meta keys that should be translated.

**Parameters:**
- `$whitelist` (array) - Current whitelist
- `$plugin` (FPML_Plugin) - Plugin instance

**Returns:** array

**Example:**
```php
add_filter( 'fpml_meta_whitelist', function( $whitelist, $plugin ) {
    $whitelist[] = '_custom_field';
    $whitelist[] = '_another_meta';
    return $whitelist;
}, 10, 2 );
```

---

#### `fpml_glossary_pre_translate`
Filter text before sending to translation provider (apply glossary).

**Parameters:**
- `$text` (string) - Text to process
- `$source` (string) - Source language code
- `$target` (string) - Target language code
- `$domain` (string) - Context domain
- `$provider` (FPML_Base_Provider) - Provider instance

**Returns:** string

**Example:**
```php
add_filter( 'fpml_glossary_pre_translate', function( $text, $source, $target, $domain, $provider ) {
    // Replace brand name before translation
    return str_replace( 'MyBrand', '[BRAND]', $text );
}, 10, 5 );
```

---

#### `fpml_glossary_post_translate`
Filter text after receiving from translation provider.

**Parameters:**
- `$text` (string) - Translated text
- `$source` (string) - Source language code
- `$target` (string) - Target language code
- `$domain` (string) - Context domain
- `$provider` (FPML_Base_Provider) - Provider instance

**Returns:** string

**Example:**
```php
add_filter( 'fpml_glossary_post_translate', function( $text, $source, $target, $domain, $provider ) {
    // Restore brand name
    return str_replace( '[BRAND]', 'MyBrand', $text );
}, 10, 5 );
```

---

## REST API

Base URL: `/wp-json/fpml/v1/`

All endpoints except `/health` require:
- **Authentication:** WordPress nonce or cookie
- **Capability:** `manage_options`

---

### POST `/queue/run`
Process pending translation jobs.

**Response:**
```json
{
  "success": true,
  "summary": {
    "claimed": 10,
    "processed": 9,
    "skipped": 0,
    "errors": 1
  }
}
```

**cURL Example:**
```bash
curl -X POST 'https://example.com/wp-json/fpml/v1/queue/run' \
  -H 'X-WP-Nonce: YOUR_NONCE' \
  -H 'Content-Type: application/json'
```

---

### POST `/test-provider`
Test configured translation provider.

**Response:**
```json
{
  "success": true,
  "provider": "openai",
  "sample": "Questo è un test",
  "translation": "This is a test",
  "characters": 17,
  "estimated_cost": 0.0001,
  "elapsed": 1.234
}
```

---

### POST `/reindex`
Reindex all content and enqueue translation jobs.

**Response:**
```json
{
  "success": true,
  "summary": {
    "posts_scanned": 150,
    "posts_enqueued": 145,
    "translations_created": 20,
    "terms_scanned": 45,
    "menus_synced": 3
  }
}
```

---

### POST `/queue/cleanup`
Clean up old queue jobs.

**Response:**
```json
{
  "success": true,
  "deleted": 532,
  "states": ["done", "skipped"],
  "days": 7
}
```

---

### GET `/health`
Health check endpoint (public, no auth required).

**Response:**
```json
{
  "status": "ok",
  "version": "0.3.1",
  "checks": {
    "database": {
      "accessible": true
    },
    "queue": {
      "accessible": true,
      "locked": false,
      "pending_jobs": 42,
      "error_jobs": 0
    },
    "provider": {
      "configured": true
    },
    "assisted_mode": false
  },
  "timestamp": "2025-10-05 12:34:56"
}
```

**Status Values:**
- `ok` - Everything working
- `warning` - High queue backlog or errors
- `error` - Database or provider issues

**cURL Example:**
```bash
curl 'https://example.com/wp-json/fpml/v1/health'
```

---

## WP-CLI Commands

### `wp fpml queue status`
Display queue status and scheduled events.

**Example:**
```bash
wp fpml queue status
```

**Output:**
```
+---------------+--------+
| stato         | totale |
+---------------+--------+
| pending       | 42     |
| translating   | 2      |
| done          | 1523   |
+---------------+--------+

fpml_run_queue: 2025-10-05 15:30:00
Lock processor: libero
Provider configurato: OpenAI
```

---

### `wp fpml queue run`
Process pending translation jobs.

**Options:**
- `--batch=<size>` - Number of jobs to process (default: 20)

**Example:**
```bash
wp fpml queue run --batch=50
```

**Output:**
```
Processing batch of 50 jobs...
✓ Processed 48 jobs
✗ Skipped 2 jobs
✓ Batch completed in 12.5s
```

---

### `wp fpml queue cleanup`
Remove processed jobs respecting retention thresholds.

**Options:**
- `--days=<int>` - Retention period in days (default: from settings)
- `--states=<list>` - Comma-separated states to cleanup (default: done,skipped,error)

**Example:**
```bash
wp fpml queue cleanup --days=7 --states=done,skipped
```

**Output:**
```
Cleaning up jobs older than 7 days with states: done,skipped
✓ Deleted 532 jobs
```

---

### `wp fpml queue estimate-cost`
Estimate provider cost for pending jobs.

**Options:**
- `--states=<list>` - States to estimate (default: pending,outdated)
- `--max-jobs=<int>` - Maximum jobs to analyze (default: 500)

**Example:**
```bash
wp fpml queue estimate-cost --max-jobs=1000
```

**Output:**
```
Analyzing 1000 jobs...
Characters: 245,830
Words: 42,150
Estimated cost: €1.23
```

---

## PHP Classes

### FPML_Plugin
Main plugin controller.

```php
$plugin = FPML_Plugin::instance();

// Check if in assisted mode
if ( $plugin->is_assisted_mode() ) {
    echo 'Assisted mode active: ' . $plugin->get_assisted_reason_label();
}

// Get diagnostics
$snapshot = $plugin->get_diagnostics_snapshot();

// Reindex content
$summary = $plugin->reindex_content();

// Estimate queue cost
$estimate = $plugin->estimate_queue_cost();
```

---

### FPML_Queue
Queue management.

```php
$queue = FPML_Queue::instance();

// Enqueue a job
$job_id = $queue->enqueue( 'post', 123, 'post_title', md5( 'content' ) );

// Get next jobs
$jobs = $queue->get_next_jobs( 20 );

// Update job state
$queue->update_state( $job_id, 'done' );

// Get state counts
$counts = $queue->get_state_counts();

// Cleanup old jobs
$deleted = $queue->cleanup_old_jobs( ['done'], 7, 'updated_at' );
```

---

### FPML_Logger
Logging system.

```php
$logger = FPML_Logger::instance();

// Log basic message
$logger->log( 'info', 'Translation started', ['job_id' => 123] );

// Structured logging (new in 0.3.2)
$logger->log_translation_start( 123, 'openai', 500 );
$logger->log_translation_complete( 123, 1200, 0.0012 );
$logger->log_api_error( 'openai', 'rate_limit', 'Too many requests', 429 );

// Get logs
$logs = $logger->get_logs( 50 );

// Get logs by event type
$translation_logs = $logger->get_logs_by_event( 'translation.complete', 100 );

// Get statistics
$stats = $logger->get_stats(); // ['info' => 150, 'warn' => 5, 'error' => 2]
```

---

### FPML_Rate_Limiter
Rate limiting (new in 0.3.2).

```php
// Check if request allowed
if ( FPML_Rate_Limiter::can_make_request( 'openai', 60 ) ) {
    // Make API call
    $result = $provider->translate( $text );
    
    // Record request
    FPML_Rate_Limiter::record_request( 'openai' );
}

// Get status
$status = FPML_Rate_Limiter::get_status( 'openai' );
// ['count' => 45, 'reset_in' => 15, 'available' => true]

// Reset rate limit
FPML_Rate_Limiter::reset( 'openai' );

// Wait if needed
FPML_Rate_Limiter::wait_if_needed( 'openai', 60 );
```

---

## Error Codes

### Common Error Codes

| Code | Description |
|------|-------------|
| `fpml_assisted_mode` | Plugin in assisted mode, operation disabled |
| `fpml_openai_auth_error` | OpenAI authentication failed |
| `fpml_openai_rate_limit` | OpenAI rate limit exceeded |
| `fpml_deepl_quota_exceeded` | DeepL quota exceeded |
| `fpml_queue_locked` | Queue processor locked |
| `fpml_rest_forbidden` | Insufficient permissions |

---

## Support

- **Issues:** https://github.com/francescopasseri/FP-Multilanguage/issues
- **Documentation:** https://github.com/francescopasseri/FP-Multilanguage/tree/main/docs
- **Website:** https://francescopasseri.com
