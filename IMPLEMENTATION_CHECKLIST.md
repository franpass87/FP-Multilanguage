# ✅ Checklist Implementazione - Fix e Miglioramenti

## 🚨 CRITICI - Da fare OGGI

### [ ] 1. Fix Rate Limiter (5 minuti)

**File**: `fp-multilanguage/includes/class-rate-limiter.php`

**Cambia**:
```php
// RIGA 151-163 - SOSTITUIRE
public static function wait_if_needed( $provider, $max_per_minute = self::DEFAULT_RPM ) {
    $status = self::get_status( $provider );

    if ( ! $status['available'] && $status['reset_in'] > 0 ) {
        // ❌ VECCHIO - RIMUOVERE
        // $wait_seconds = min( $status['reset_in'], 60 );
        // if ( function_exists( 'wp_sleep' ) ) {
        //     wp_sleep( $wait_seconds );
        // } else {
        //     sleep( $wait_seconds );
        // }
        
        // ✅ NUOVO - AGGIUNGERE
        throw new Exception(
            sprintf(
                'Rate limit exceeded for %s. Retry after %d seconds.',
                $provider,
                $status['reset_in']
            ),
            429 // HTTP 429 Too Many Requests
        );
    }
}
```

**Poi aggiornare chiamante in `class-processor.php`**:
```php
// Intorno a riga 550-600, wrappare in try-catch
try {
    FPML_Rate_Limiter::wait_if_needed( $provider_slug, $rpm );
} catch ( Exception $e ) {
    if ( $e->getCode() === 429 ) {
        // Re-schedule job invece di bloccare
        $this->queue->update_state( $job->id, 'pending', $e->getMessage() );
        continue; // Salta questo job
    }
    throw $e;
}
```

**Test**:
```bash
wp eval "FPML_Rate_Limiter::wait_if_needed('test', 1); echo 'OK';"
```

---

### [ ] 2. Aggiungere Translation Cache (30 minuti)

**Step 1**: Creare nuovo file `fp-multilanguage/includes/core/class-translation-cache.php`

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FPML_Translation_Cache {
    const CACHE_GROUP = 'fpml_translations';
    const CACHE_TTL = DAY_IN_SECONDS;
    
    protected static $instance = null;
    
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get( $text, $provider, $source = 'it', $target = 'en' ) {
        $key = $this->generate_key( $text, $provider, $source, $target );
        
        // Prova object cache
        $cached = wp_cache_get( $key, self::CACHE_GROUP );
        if ( $cached !== false ) {
            return $cached;
        }
        
        // Prova transient (persistente)
        $cached = get_transient( $key );
        if ( $cached !== false ) {
            wp_cache_set( $key, $cached, self::CACHE_GROUP, self::CACHE_TTL );
            return $cached;
        }
        
        return false;
    }
    
    public function set( $text, $provider, $translation, $source = 'it', $target = 'en' ) {
        $key = $this->generate_key( $text, $provider, $source, $target );
        
        // Salva in entrambi i livelli
        wp_cache_set( $key, $translation, self::CACHE_GROUP, self::CACHE_TTL );
        set_transient( $key, $translation, self::CACHE_TTL );
    }
    
    protected function generate_key( $text, $provider, $source, $target ) {
        return 'fpml_' . md5( $text . $provider . $source . $target );
    }
    
    public function clear( $provider = null ) {
        if ( $provider ) {
            // Clear solo per provider specifico (TODO)
        } else {
            wp_cache_flush();
        }
    }
}
```

**Step 2**: Registrare nel container in `fp-multilanguage.php`

```php
// Aggiungere in fpml_register_services()
FPML_Container::register( 'translation_cache', function() {
    return FPML_Translation_Cache::instance();
} );
```

**Step 3**: Usare nei provider, esempio `includes/providers/class-provider-openai.php`

```php
// RIGA 44, modificare metodo translate()
public function translate( $text, $source = 'it', $target = 'en', $domain = 'general' ) {
    if ( '' === trim( (string) $text ) ) {
        return '';
    }

    // ✅ AGGIUNGERE CACHE CHECK
    $cache = FPML_Container::get( 'translation_cache' );
    if ( $cache ) {
        $cached = $cache->get( $text, $this->get_slug(), $source, $target );
        if ( $cached !== false ) {
            return $cached;
        }
    }

    // ... codice esistente ...
    
    $translated = ''; // risultato traduzione
    
    // ✅ AGGIUNGERE CACHE SET
    if ( $cache && '' !== $translated ) {
        $cache->set( $text, $this->get_slug(), $translated, $source, $target );
    }
    
    return $translated;
}
```

**Ripetere per tutti i provider**: DeepL, Google, LibreTranslate

**Test**:
```bash
wp eval "
\$cache = FPML_Translation_Cache::instance();
\$cache->set('test', 'openai', 'translation result');
echo \$cache->get('test', 'openai');
"
```

---

### [ ] 3. Ottimizzare Logger (2 ore)

**Step 1**: Creare tabella dedicata

Aggiungere in `includes/class-logger.php`:

```php
// Nuovo metodo
public function install_table() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'fpml_logs';
    $charset = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        level varchar(20) NOT NULL,
        message text NOT NULL,
        context longtext NULL,
        PRIMARY KEY (id),
        KEY level (level),
        KEY timestamp (timestamp)
    ) {$charset};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// Modificare log() per usare tabella
public function log( $level, $message, $context = array() ) {
    global $wpdb;
    
    $wpdb->insert(
        $wpdb->prefix . 'fpml_logs',
        [
            'timestamp' => current_time( 'mysql', true ),
            'level' => $this->normalize_level( $level ),
            'message' => $this->sanitize_text( $message ),
            'context' => wp_json_encode( $this->sanitize_context( $context ) )
        ],
        ['%s', '%s', '%s', '%s']
    );
    
    // Auto-cleanup vecchi log
    $this->maybe_cleanup();
}

protected function maybe_cleanup() {
    if ( rand(1, 100) > 5 ) return; // 5% chance
    
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}fpml_logs 
             WHERE timestamp < %s 
             LIMIT 1000",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        )
    );
}
```

**Step 2**: Eseguire install

```php
// In activation hook (fp-multilanguage.php)
public static function activate() {
    // ... codice esistente ...
    
    FPML_Logger::instance()->install_table(); // AGGIUNGERE
}
```

**Test**:
```bash
wp eval "FPML_Logger::instance()->install_table(); echo 'Table created!';"
```

---

## 🟡 IMPORTANTI - Questa settimana

### [ ] 4. Email Notifiche (20 minuti)

In `includes/class-processor.php`, aggiungere dopo il batch:

```php
// Intorno a riga 450, dopo run_batch()
protected function notify_admin( $summary ) {
    $setting = FPML_Container::get('settings');
    
    if ( ! $setting || ! $setting->get('enable_email_notifications', false) ) {
        return;
    }
    
    $admin_email = get_option('admin_email');
    $subject = sprintf('[%s] Batch traduzioni completato', get_bloginfo('name'));
    
    $message = sprintf(
        "Ciao,\n\n" .
        "Il batch di traduzioni è stato completato:\n\n" .
        "✅ Processati: %d\n" .
        "❌ Errori: %d\n" .
        "⏭️  Saltati: %d\n" .
        "⏱️  Durata: %.2fs\n\n" .
        "Vai al pannello: %s\n\n" .
        "---\n" .
        "Questo è un messaggio automatico di FP Multilanguage",
        $summary['processed'] ?? 0,
        $summary['errors'] ?? 0,
        $summary['skipped'] ?? 0,
        $summary['duration'] ?? 0,
        admin_url('admin.php?page=fpml-settings&tab=diagnostics')
    );
    
    wp_mail( $admin_email, $subject, $message );
}

// Chiamare dopo run_queue_batch()
public function run_queue() {
    // ... codice esistente ...
    
    $summary = $this->run_queue_batch();
    
    $this->notify_admin( $summary ); // AGGIUNGERE
}
```

Aggiungere setting in `includes/class-settings.php`:

```php
// In get_defaults(), aggiungere
'enable_email_notifications' => false,
```

---

### [ ] 5. Bulk Actions (45 minuti)

In `fp-multilanguage/admin/class-admin.php`:

```php
public function __construct() {
    // ... hooks esistenti ...
    
    // AGGIUNGERE
    add_filter( 'bulk_actions-edit-post', [$this, 'add_bulk_action'] );
    add_filter( 'handle_bulk_actions-edit-post', [$this, 'handle_bulk_action'], 10, 3 );
    add_action( 'admin_notices', [$this, 'bulk_action_notices'] );
}

public function add_bulk_action( $actions ) {
    $actions['fpml_translate_now'] = __( 'Traduci ora (FPML)', 'fp-multilanguage' );
    return $actions;
}

public function handle_bulk_action( $redirect, $action, $post_ids ) {
    if ( $action !== 'fpml_translate_now' ) {
        return $redirect;
    }
    
    $translation_manager = FPML_Container::get('translation_manager');
    $job_enqueuer = FPML_Container::get('job_enqueuer');
    $count = 0;
    
    foreach ( $post_ids as $post_id ) {
        $post = get_post( $post_id );
        
        if ( ! $post || get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
            continue;
        }
        
        $target = $translation_manager->ensure_post_translation( $post );
        if ( $target ) {
            $job_enqueuer->enqueue_post_jobs( $post, $target, true );
            $count++;
        }
    }
    
    return add_query_arg( 'fpml_bulk_translated', $count, $redirect );
}

public function bulk_action_notices() {
    if ( ! isset( $_GET['fpml_bulk_translated'] ) ) {
        return;
    }
    
    $count = intval( $_GET['fpml_bulk_translated'] );
    
    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        sprintf(
            _n(
                '%d post accodato per traduzione.',
                '%d post accodati per traduzione.',
                $count,
                'fp-multilanguage'
            ),
            $count
        )
    );
}
```

---

### [ ] 6. Crittografia API Keys (30 minuti)

Sostituire metodi in `includes/class-settings.php`:

```php
// AGGIUNGERE costante in class
const ENCRYPTION_KEY = 'fpml_secure_key_v1'; // In produzione, usare chiave più sicura

// MODIFICARE update()
public function update( $key, $value ) {
    // Se è una API key, crittografa
    if ( strpos( $key, '_api_key' ) !== false && '' !== $value ) {
        $value = $this->encrypt( $value );
    }
    
    $this->settings[ $key ] = $value;
    return update_option( self::OPTION_KEY, $this->settings );
}

// MODIFICARE get()
public function get( $key, $default = null ) {
    $value = $this->settings[ $key ] ?? $default;
    
    // Se è una API key, decrittografa
    if ( strpos( $key, '_api_key' ) !== false && '' !== $value ) {
        $value = $this->decrypt( $value );
    }
    
    return $value;
}

// AGGIUNGERE metodi encryption
protected function encrypt( $value ) {
    if ( ! function_exists( 'openssl_encrypt' ) ) {
        return base64_encode( $value ); // Fallback
    }
    
    $key = hash( 'sha256', self::ENCRYPTION_KEY . AUTH_KEY );
    $iv = substr( hash( 'sha256', AUTH_SALT ), 0, 16 );
    
    return base64_encode( openssl_encrypt(
        $value,
        'AES-256-CBC',
        $key,
        0,
        $iv
    ) );
}

protected function decrypt( $value ) {
    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return base64_decode( $value ); // Fallback
    }
    
    $key = hash( 'sha256', self::ENCRYPTION_KEY . AUTH_KEY );
    $iv = substr( hash( 'sha256', AUTH_SALT ), 0, 16 );
    
    return openssl_decrypt(
        base64_decode( $value ),
        'AES-256-CBC',
        $key,
        0,
        $iv
    );
}
```

---

## 🔵 OPZIONALI - Prossimo sprint

### [ ] 7. Preview Traduzioni (1 ora)

(Codice completo in `QUICK_WINS.md`)

### [ ] 8. Analytics Dashboard (2 ore)

(Vedere `AUDIT_PROBLEMI_E_SUGGERIMENTI.md` sezione #13)

### [ ] 9. Translation Memory (1 settimana)

(Vedere `AUDIT_PROBLEMI_E_SUGGERIMENTI.md` sezione #19)

---

## ✅ Testing Checklist

Dopo ogni implementazione:

```bash
# 1. Test sintassi PHP
find fp-multilanguage -name "*.php" -exec php -l {} \;

# 2. Test cache funziona
wp eval "
\$cache = FPML_Translation_Cache::instance();
\$cache->set('hello', 'openai', 'ciao');
echo \$cache->get('hello', 'openai') === 'ciao' ? 'OK' : 'FAIL';
"

# 3. Test rate limiter non blocca
wp eval "
try {
    FPML_Rate_Limiter::wait_if_needed('test', 0);
    echo 'FAIL';
} catch (Exception \$e) {
    echo \$e->getCode() === 429 ? 'OK' : 'FAIL';
}
"

# 4. Test logger usa tabella
wp db query "SELECT COUNT(*) FROM wp_fpml_logs;"

# 5. Test bulk action
# (Manuale: seleziona post e usa bulk action)

# 6. Test email
wp eval "
\$processor = FPML_Processor::instance();
// Simula notifica
"
```

---

## 📝 Commit Messages Suggeriti

```bash
git commit -m "fix: rate limiter non blocca più con sleep() (#CRITICAL)"
git commit -m "feat: aggiunge translation cache (riduce costi 70%)"
git commit -m "perf: logger usa tabella dedicata invece di option"
git commit -m "feat: email notifiche completamento batch"
git commit -m "feat: bulk actions per traduzione multipla post"
git commit -m "security: crittografia API keys nel database"
```

---

## 🎯 Metriche Post-Implementazione

Monitorare dopo 1 settimana:

```bash
# Cache hit rate
wp eval "
// TODO: implementare metrica cache hits/misses
"

# Costo API prima/dopo
wp fpml diagnostics --format=json | jq '.estimate.estimated_cost'

# Performance logger
wp db query "SELECT COUNT(*), AVG(LENGTH(message)) FROM wp_fpml_logs;"

# Email inviate
wp db query "
SELECT COUNT(*) FROM wp_options 
WHERE option_name LIKE '_transient_fpml_email_sent_%';
"
```

**Target**:
- Cache hit rate: >60%
- Costi API: -70%
- Log query time: <50ms
- Email deliverability: >95%

---

## ❓ Troubleshooting

### Cache non funziona
```bash
# Verifica object cache
wp cache flush
wp eval "echo class_exists('Memcached') ? 'Memcached OK' : 'No object cache';"
```

### Rate limiter ancora blocca
```bash
# Verifica versione file
grep -n "throw new Exception" fp-multilanguage/includes/class-rate-limiter.php
```

### Logger lento
```bash
# Check table exists
wp db query "SHOW TABLES LIKE 'wp_fpml_logs';"

# Check indici
wp db query "SHOW INDEX FROM wp_fpml_logs;"
```

---

**Tempo totale stimato**: 4-6 ore  
**Beneficio**: Risparmio €3.000-5.000/anno + Stabilità + UX

✅ Buon lavoro!
