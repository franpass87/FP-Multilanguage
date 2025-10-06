# Developer Guide - FP Multilanguage

## Table of Contents
- [Getting Started](#getting-started)
- [Architecture](#architecture)
- [Creating Custom Providers](#creating-custom-providers)
- [Extending Functionality](#extending-functionality)
- [Best Practices](#best-practices)
- [Common Patterns](#common-patterns)

---

## Getting Started

### Development Setup

```bash
# Clone repository
git clone https://github.com/francescopasseri/FP-Multilanguage.git
cd FP-Multilanguage

# Install dependencies
composer install
npm install

# Run tests
vendor/bin/phpunit

# Check code style
vendor/bin/phpcs fp-multilanguage/

# Fix code style
vendor/bin/phpcbf fp-multilanguage/
```

---

## Architecture

### Component Overview

```
fp-multilanguage/
├── includes/
│   ├── class-plugin.php          # Main controller
│   ├── class-queue.php            # Queue management
│   ├── class-processor.php        # Batch processor
│   ├── class-language.php         # Language routing
│   ├── class-logger.php           # Logging system
│   ├── class-rate-limiter.php     # API rate limiting
│   ├── class-webhooks.php         # Webhook notifications
│   └── providers/
│       ├── interface-translator.php    # Provider interface
│       ├── class-provider-openai.php
│       ├── class-provider-deepl.php
│       ├── class-provider-google.php
│       └── class-provider-libretranslate.php
├── admin/
│   └── class-admin.php           # Admin UI
├── rest/
│   └── class-rest-admin.php      # REST API
└── cli/
    └── class-cli.php             # WP-CLI commands
```

### Data Flow

```
User saves post
    ↓
handle_save_post() in FPML_Plugin
    ↓
ensure_post_translation() creates EN copy
    ↓
enqueue_post_jobs() adds to queue
    ↓
Cron runs wp_fpml_run_queue
    ↓
Processor claims jobs
    ↓
Provider translates text
    ↓
Translated content saved
    ↓
Job marked as done
    ↓
Webhook notification sent
```

---

## Creating Custom Providers

### Step 1: Create Provider Class

```php
<?php
// fp-multilanguage/includes/providers/class-provider-custom.php

require_once __DIR__ . '/interface-translator.php';

class FPML_Provider_Custom extends FPML_Base_Provider {
    const API_ENDPOINT = 'https://api.custom.com/translate';

    /**
     * {@inheritdoc}
     */
    public function get_slug() {
        return 'custom';
    }

    /**
     * {@inheritdoc}
     */
    public function is_configured() {
        $api_key = $this->get_option( 'custom_api_key' );
        return ! empty( $api_key );
    }

    /**
     * {@inheritdoc}
     */
    public function translate( $text, $source = 'it', $target = 'en', $domain = 'general' ) {
        if ( '' === trim( $text ) ) {
            return '';
        }

        if ( ! $this->is_configured() ) {
            return new WP_Error(
                'fpml_custom_missing_key',
                __( 'API key mancante', 'fp-multilanguage' )
            );
        }

        // Check rate limit
        if ( ! FPML_Rate_Limiter::can_make_request( $this->get_slug(), 60 ) ) {
            return new WP_Error(
                'fpml_custom_rate_limit',
                __( 'Rate limit raggiunto', 'fp-multilanguage' )
            );
        }

        // Chunk text
        $max_chars = (int) $this->get_option( 'max_chars', 5000 );
        $chunks = $this->chunk_text( $text, $max_chars );

        $translated = '';

        foreach ( $chunks as $chunk ) {
            // Apply glossary pre-translation
            $chunk_to_send = $this->apply_glossary_pre( $chunk, $source, $target, $domain );

            // Make API call
            $result = $this->request_translation( $chunk_to_send, $source, $target );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            // Apply glossary post-translation
            $translated .= $this->apply_glossary_post( $result, $source, $target, $domain );
        }

        // Record request for rate limiting
        FPML_Rate_Limiter::record_request( $this->get_slug() );

        return $translated;
    }

    /**
     * Make API request.
     *
     * @param string $text   Text to translate.
     * @param string $source Source language.
     * @param string $target Target language.
     *
     * @return string|WP_Error
     */
    protected function request_translation( $text, $source, $target ) {
        $api_key = $this->get_option( 'custom_api_key' );

        $payload = array(
            'text'   => $text,
            'source' => $source,
            'target' => $target,
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 45,
        );

        $max_attempts = 3;

        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            $response = wp_remote_post( self::API_ENDPOINT, $args );

            if ( is_wp_error( $response ) ) {
                if ( $attempt === $max_attempts ) {
                    return $response;
                }

                $this->backoff( $attempt );
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            // Retry on temporary errors
            if ( in_array( $code, array( 429, 500, 502, 503, 504 ), true ) ) {
                if ( $attempt === $max_attempts ) {
                    return new WP_Error(
                        'fpml_custom_temp_error',
                        sprintf( 'Temporary error: %d', $code )
                    );
                }

                FPML_Logger::instance()->log(
                    'warning',
                    "Custom API retry $attempt/$max_attempts",
                    array( 'http_code' => $code )
                );

                $this->backoff( $attempt );
                continue;
            }

            // Don't retry on client errors
            if ( $code >= 400 && $code < 500 ) {
                return new WP_Error(
                    'fpml_custom_client_error',
                    sprintf( 'Client error: %d', $code )
                );
            }

            // Success
            if ( $code >= 200 && $code < 300 ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( isset( $data['translation'] ) ) {
                    return $data['translation'];
                }

                return new WP_Error( 'fpml_custom_no_content', 'No translation in response' );
            }

            // Other errors
            return new WP_Error( 'fpml_custom_error', sprintf( 'HTTP %d', $code ) );
        }

        return new WP_Error( 'fpml_custom_max_retries', 'Max retries exceeded' );
    }

    /**
     * {@inheritdoc}
     */
    protected function get_rate_option_key() {
        return 'rate_custom';
    }
}
```

---

### Step 2: Register Provider

```php
// In functions.php or custom plugin
add_filter( 'fpml_available_providers', function( $providers ) {
    $providers['custom'] = array(
        'label' => 'Custom Provider',
        'class' => 'FPML_Provider_Custom',
    );
    return $providers;
});

// Load provider file
add_action( 'plugins_loaded', function() {
    require_once '/path/to/class-provider-custom.php';
}, 5 );
```

---

### Step 3: Add Settings Fields

```php
add_filter( 'fpml_settings_fields', function( $fields ) {
    $fields['custom_api_key'] = array(
        'label'       => 'Custom API Key',
        'type'        => 'text',
        'section'     => 'general',
        'description' => 'Enter your Custom provider API key',
    );
    
    $fields['rate_custom'] = array(
        'label'       => 'Custom Rate (per 1000 chars)',
        'type'        => 'text',
        'section'     => 'general',
        'description' => 'Cost per 1000 characters',
    );
    
    return $fields;
});
```

---

## Extending Functionality

### Custom Post Type Translation

```php
// Enable translation for custom post type
add_filter( 'fpml_translatable_post_types', function( $post_types ) {
    $post_types[] = 'book';
    $post_types[] = 'recipe';
    return $post_types;
});
```

---

### Custom Taxonomy Translation

```php
add_filter( 'fpml_translatable_taxonomies', function( $taxonomies ) {
    $taxonomies[] = 'book_genre';
    $taxonomies[] = 'recipe_category';
    return $taxonomies;
});
```

---

### Custom Meta Fields

```php
add_filter( 'fpml_meta_whitelist', function( $whitelist ) {
    // Add custom ACF fields
    $whitelist[] = '_custom_subtitle';
    $whitelist[] = '_product_tagline';
    $whitelist[] = '_event_description';
    
    return $whitelist;
});
```

---

### Post-Processing Translations

```php
// Modify translation before saving
add_filter( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
    // Add custom watermark
    if ( 'post_content' === $field ) {
        $translated_text .= "\n\n<!-- Translated by FP Multilanguage -->";
    }
    
    return $translated_text;
}, 10, 3 );
```

---

### Custom Glossary Rules

```php
// Add glossary rules programmatically
add_action( 'init', function() {
    $glossary = FPML_Glossary::instance();
    
    // Brand names
    $glossary->add_rule( 'MioBrand', 'MyBrand', 'general' );
    
    // Technical terms
    $glossary->add_rule( 'dashboard', 'dashboard', 'technical' );
});
```

---

### Custom Queue Cleanup Rules

```php
// Keep error jobs longer
add_filter( 'fpml_queue_cleanup_states', function( $states ) {
    // Remove 'error' from cleanup
    return array_diff( $states, array( 'error' ) );
});

// Custom retention logic
add_filter( 'fpml_queue_cleanup_retention', function( $days, $state ) {
    if ( 'error' === $state ) {
        return 30; // Keep errors for 30 days
    }
    
    return $days; // Default for others
}, 10, 2 );
```

---

## Best Practices

### 1. Error Handling

```php
// ✅ Good
$result = $provider->translate( $text );
if ( is_wp_error( $result ) ) {
    FPML_Logger::instance()->log_api_error(
        $provider->get_slug(),
        $result->get_error_code(),
        $result->get_error_message()
    );
    return $result;
}

// ❌ Bad
$result = $provider->translate( $text );
// No error check - could cause fatal error
echo $result;
```

---

### 2. Caching

```php
// ✅ Good - use WordPress object cache
$cache_key = 'fpml_custom_' . $post_id;
$cached = wp_cache_get( $cache_key, 'fpml_custom' );

if ( false !== $cached ) {
    return $cached;
}

$data = expensive_operation();
wp_cache_set( $cache_key, $data, 'fpml_custom', HOUR_IN_SECONDS );

// ❌ Bad - query database ogni volta
return get_option( 'fpml_custom_' . $post_id );
```

---

### 3. Batch Operations

```php
// ✅ Good - pre-load meta
$post_ids = array( 1, 2, 3, 4, 5 );
update_meta_cache( 'post', $post_ids );

foreach ( $post_ids as $post_id ) {
    $meta = get_post_meta( $post_id, '_key', true ); // Uses cache
}

// ❌ Bad - N+1 queries
foreach ( $post_ids as $post_id ) {
    $meta = get_post_meta( $post_id, '_key', true ); // Queries DB each time
}
```

---

### 4. Logging

```php
// ✅ Good - structured logging
FPML_Logger::instance()->log_translation_start( $job_id, 'openai', 500 );

// ❌ Bad - unstructured
error_log( 'Started translation' );
```

---

## Common Patterns

### Pattern 1: Singleton with Lazy Initialization

```php
class My_Component {
    protected static $instance = null;
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    protected function __construct() {
        // Initialize
    }
}

// Usage
$component = My_Component::instance();
```

---

### Pattern 2: Hook Registration

```php
class My_Feature {
    public function __construct() {
        $this->register_hooks();
    }
    
    protected function register_hooks() {
        add_action( 'save_post', array( $this, 'on_save_post' ), 10, 3 );
        add_filter( 'the_content', array( $this, 'filter_content' ) );
    }
    
    public function on_save_post( $post_id, $post, $update ) {
        // Implementation
    }
    
    public function filter_content( $content ) {
        // Implementation
        return $content;
    }
}
```

---

### Pattern 3: Settings Access

```php
class My_Component {
    protected $settings;
    
    public function __construct() {
        $this->settings = FPML_Settings::instance();
    }
    
    protected function get_setting( $key, $default = '' ) {
        return $this->settings ? $this->settings->get( $key, $default ) : $default;
    }
}
```

---

## Examples

### Example 1: Custom Translation Filter

```php
/**
 * Replace brand names before translation to preserve them.
 */
add_filter( 'fpml_glossary_pre_translate', function( $text, $source, $target, $domain, $provider ) {
    $brands = array(
        'WordPress' => '[BRAND_WP]',
        'Gutenberg' => '[BRAND_GB]',
    );
    
    foreach ( $brands as $original => $placeholder ) {
        $text = str_replace( $original, $placeholder, $text );
    }
    
    return $text;
}, 10, 5 );

add_filter( 'fpml_glossary_post_translate', function( $text, $source, $target, $domain, $provider ) {
    $brands = array(
        '[BRAND_WP]' => 'WordPress',
        '[BRAND_GB]' => 'Gutenberg',
    );
    
    foreach ( $brands as $placeholder => $original ) {
        $text = str_replace( $placeholder, $original, $text );
    }
    
    return $text;
}, 10, 5 );
```

---

### Example 2: Cost Tracking

```php
/**
 * Track translation costs per post.
 */
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
    $processor = FPML_Processor::instance();
    $translator = $processor->get_translator_instance();
    
    if ( is_wp_error( $translator ) ) {
        return;
    }
    
    // Calculate cost
    $cost = $translator->estimate_cost( $translated_text );
    
    // Store in post meta
    $total_cost = (float) get_post_meta( $post_id, '_fpml_total_cost', true );
    $total_cost += $cost;
    
    update_post_meta( $post_id, '_fpml_total_cost', $total_cost );
    update_post_meta( $post_id, '_fpml_last_translation_cost', $cost );
}, 10, 3 );

// Display in admin
add_filter( 'manage_posts_columns', function( $columns ) {
    $columns['fpml_cost'] = 'Translation Cost';
    return $columns;
});

add_action( 'manage_posts_custom_column', function( $column, $post_id ) {
    if ( 'fpml_cost' === $column ) {
        $cost = get_post_meta( $post_id, '_fpml_total_cost', true );
        echo $cost ? '€' . number_format( $cost, 4 ) : '-';
    }
}, 10, 2 );
```

---

### Example 3: Quality Assurance Workflow

```php
/**
 * Add "needs review" status for translations.
 */
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
    // Mark as needs review
    update_post_meta( $post_id, '_fpml_needs_review', 1 );
    
    // Set post status to draft until reviewed
    wp_update_post( array(
        'ID' => $post_id,
        'post_status' => 'draft',
    ));
}, 10, 3 );

// Admin column
add_filter( 'manage_posts_columns', function( $columns ) {
    $columns['fpml_review'] = 'Review Status';
    return $columns;
});

add_action( 'manage_posts_custom_column', function( $column, $post_id ) {
    if ( 'fpml_review' === $column ) {
        $needs_review = get_post_meta( $post_id, '_fpml_needs_review', true );
        
        if ( $needs_review ) {
            echo '⚠️ Needs Review';
            echo ' <a href="#" class="button button-small fpml-approve" data-post="' . $post_id . '">Approve</a>';
        } else {
            echo '✅ Approved';
        }
    }
}, 10, 2 );

// Approval handler
add_action( 'admin_post_fpml_approve_translation', function() {
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( 'Invalid request' );
    }
    
    delete_post_meta( $post_id, '_fpml_needs_review' );
    
    wp_update_post( array(
        'ID' => $post_id,
        'post_status' => 'publish',
    ));
    
    wp_safe_redirect( admin_url( 'edit.php' ) );
    exit;
});
```

---

### Example 4: Custom Analytics Dashboard

```php
/**
 * Add analytics meta box to dashboard.
 */
add_action( 'wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'fpml_analytics',
        'Translation Analytics',
        'fpml_render_analytics_widget'
    );
});

function fpml_render_analytics_widget() {
    $logger = FPML_Logger::instance();
    
    // Get recent translation events
    $translations = $logger->get_logs_by_event( 'translation.complete', 50 );
    
    $total_cost = 0;
    $total_chars = 0;
    $avg_duration = 0;
    
    foreach ( $translations as $log ) {
        if ( isset( $log['context']['cost'] ) ) {
            $total_cost += (float) $log['context']['cost'];
        }
        if ( isset( $log['context']['characters'] ) ) {
            $total_chars += (int) $log['context']['characters'];
        }
        if ( isset( $log['context']['duration'] ) ) {
            $avg_duration += (int) $log['context']['duration'];
        }
    }
    
    $count = count( $translations );
    $avg_duration = $count > 0 ? $avg_duration / $count : 0;
    
    ?>
    <div class="fpml-analytics">
        <h3>Ultime 50 Traduzioni</h3>
        <ul>
            <li><strong>Totale:</strong> <?php echo esc_html( $count ); ?></li>
            <li><strong>Caratteri:</strong> <?php echo esc_html( number_format_i18n( $total_chars ) ); ?></li>
            <li><strong>Costo:</strong> €<?php echo esc_html( number_format( $total_cost, 4 ) ); ?></li>
            <li><strong>Durata media:</strong> <?php echo esc_html( round( $avg_duration ) ); ?>ms</li>
        </ul>
    </div>
    <?php
}
```

---

## Advanced Topics

### Custom Queue Processing

```php
/**
 * Process queue with custom logic.
 */
function my_custom_queue_processor() {
    $queue = FPML_Queue::instance();
    $processor = FPML_Processor::instance();
    
    // Get only high-priority jobs
    $jobs = $queue->get_jobs_by_criteria( array(
        'state' => 'pending',
        'object_type' => 'post',
        'priority' => 'high', // Custom field
    ), 10 );
    
    foreach ( $jobs as $job ) {
        $result = $processor->process_job( $job );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'Job failed: ' . $result->get_error_message() );
        }
    }
}

// Run on custom schedule
add_action( 'my_custom_cron_event', 'my_custom_queue_processor' );
```

---

### Translation Memory

```php
/**
 * Implement simple translation memory.
 */
class FPML_Translation_Memory {
    const OPTION_KEY = 'fpml_translation_memory';
    
    public static function get_translation( $text, $source, $target ) {
        $memory = get_option( self::OPTION_KEY, array() );
        $hash = md5( $text . $source . $target );
        
        return isset( $memory[ $hash ] ) ? $memory[ $hash ] : null;
    }
    
    public static function store_translation( $text, $source, $target, $translation ) {
        $memory = get_option( self::OPTION_KEY, array() );
        $hash = md5( $text . $source . $target );
        
        $memory[ $hash ] = $translation;
        
        // Keep only last 1000 entries
        if ( count( $memory ) > 1000 ) {
            $memory = array_slice( $memory, -1000, null, true );
        }
        
        update_option( self::OPTION_KEY, $memory, false );
    }
}

// Use in provider
add_filter( 'fpml_glossary_pre_translate', function( $text, $source, $target ) {
    // Check translation memory
    $cached = FPML_Translation_Memory::get_translation( $text, $source, $target );
    
    if ( $cached ) {
        return $cached; // Return cached translation
    }
    
    return $text;
}, 5, 3 ); // Priority 5 - run before glossary
```

---

## Debugging

### Enable Debug Logging

```php
// In wp-config.php
define( 'FPML_DEBUG', true );

// Then in your code
if ( defined( 'FPML_DEBUG' ) && FPML_DEBUG ) {
    error_log( 'FPML Debug: ' . print_r( $data, true ) );
}
```

### Query Monitor Integration

```php
add_action( 'qm/collect/after', function() {
    $logger = FPML_Logger::instance();
    $logs = $logger->get_logs( 10 );
    
    // Add to Query Monitor
    do_action( 'qm/info', 'FP Multilanguage Logs: ' . count( $logs ) );
});
```

---

## Resources

- **WordPress Coding Standards:** https://developer.wordpress.org/coding-standards/
- **PHPDoc Standards:** https://docs.phpdoc.org/
- **Plugin Handbook:** https://developer.wordpress.org/plugins/
- **REST API Handbook:** https://developer.wordpress.org/rest-api/

---

**Maintainer:** Francesco Passeri  
**Last updated:** 2025-10-05  
**Version:** 0.3.2
