# Hooks and Filters - FP Multilanguage

**Version:** 0.10.0+  
**Last Updated:** 2025-01-XX

Questo documento elenca tutti gli hook e i filter disponibili in FP Multilanguage per sviluppatori che vogliono estendere le funzionalità del plugin.

---

## 📋 Indice

- [Actions (Hooks)](#actions-hooks)
- [Filters](#filters)
- [Esempi d'uso](#esempi-duso)

---

## Actions (Hooks)

### `\FPML_after_initialization`

**Descrizione:** Chiamato dopo che il plugin è stato completamente inizializzato.

**Parametri:**
- Nessuno

**Esempio:**
```php
add_action( '\FPML_after_initialization', function() {
    // Il plugin è pronto
    error_log( 'FP Multilanguage initialized' );
});
```

**Definito in:** `src/Core/Plugin.php:165`

---

### `\FPML_before_activation`

**Descrizione:** Chiamato prima che il plugin venga attivato.

**Parametri:**
- Nessuno

**Esempio:**
```php
add_action( '\FPML_before_activation', function() {
    // Preparazione prima dell'attivazione
});
```

**Definito in:** `src/Core/Plugin.php:204`

---

### `fpml_after_translation_saved`

**Descrizione:** Chiamato dopo che una traduzione è stata salvata.

**Parametri:**
- `$target_post_id` (int) - ID del post tradotto
- `$source_post_id` (int) - ID del post sorgente

**Esempio:**
```php
add_action( 'fpml_after_translation_saved', function( $target_post_id, $source_post_id ) {
    // Notifica o logging quando una traduzione viene salvata
    error_log( "Translation saved: {$source_post_id} -> {$target_post_id}" );
}, 10, 2 );
```

**Definito in:** `src/Content/TranslationManager.php:294`

---

### `fpml_translation_metabox_after_actions`

**Descrizione:** Chiamato dopo le azioni nella metabox di traduzione.

**Parametri:**
- `$post_id` (int) - ID del post sorgente
- `$translation_id` (int) - ID del post tradotto

**Esempio:**
```php
add_action( 'fpml_translation_metabox_after_actions', function( $post_id, $translation_id ) {
    // Aggiungi link personalizzato o informazioni extra
    if ( $translation_id ) {
        echo '<a href="#">Link personalizzato</a>';
    }
}, 10, 2 );
```

**Definito in:** `src/Admin/TranslationMetabox.php:207`

---

### `fpml_cache_warmed`

**Descrizione:** Chiamato dopo che la cache è stata "riscaldata" (pre-popolata).

**Parametri:**
- `$cached_count` (int) - Numero di traduzioni cachate
- `$texts` (array) - Array di testi cachati
- `$source` (string) - Lingua sorgente
- `$target` (string) - Lingua target

**Esempio:**
```php
add_action( 'fpml_cache_warmed', function( $cached_count, $texts, $source, $target ) {
    error_log( "Cache warmed: {$cached_count} translations for {$source}->{$target}" );
}, 10, 4 );
```

**Definito in:** `src/Core/TranslationCache.php:386`

---

### `\FPML_queue_after_cleanup`

**Descrizione:** Chiamato dopo che la pulizia della coda è stata completata.

**Parametri:**
- `$states` (array) - Stati dei job rimossi
- `$days` (int) - Giorni di retention
- `$total` (int) - Numero totale di job rimossi
- `$column` (string) - Colonna usata per il filtro

**Esempio:**
```php
add_action( '\FPML_queue_after_cleanup', function( $states, $days, $total, $column ) {
    error_log( "Queue cleanup: {$total} jobs removed (states: " . implode( ',', $states ) . ")" );
}, 10, 4 );
```

**Definito in:** `src/Queue.php:470`

---

### `fpml_language_determined`

**Descrizione:** Chiamato quando la lingua corrente viene determinata.

**Parametri:**
- `$lang` (string) - Lingua corrente determinata
- `$previous_lang` (string|null) - Lingua precedente

**Esempio:**
```php
add_action( 'fpml_language_determined', function( $lang, $previous_lang ) {
    // Traccia cambiamenti di lingua
    if ( $previous_lang !== $lang ) {
        setcookie( 'last_language', $lang, time() + YEAR_IN_SECONDS, '/' );
    }
}, 10, 2 );
```

**Definito in:** `src/Language.php:942`

---

### `\FPML_reindex_post_type`

**Descrizione:** Hook per schedulare reindex di un post type.

**Parametri:**
- `$post_type` (string) - Nome del post type

**Esempio:**
```php
// Schedulare reindex
wp_schedule_single_event( time() + 10, '\FPML_reindex_post_type', array( 'product' ) );
```

**Definito in:** `src/AutoDetection.php:507`

---

## Filters

### `\FPML_cache_ttl`

**Descrizione:** Filtra il TTL (Time To Live) della cache delle traduzioni.

**Parametri:**
- `$ttl` (int) - TTL di default in secondi (3600 = 1 ora)

**Esempio:**
```php
add_filter( '\FPML_cache_ttl', function( $ttl ) {
    // Estendi cache a 2 ore per siti con traffico alto
    return 2 * HOUR_IN_SECONDS;
});
```

**Definito in:** `src/Core/TranslationCache.php:202`

**Default:** `3600` (1 ora)

---

### `\FPML_translatable_post_types`

**Descrizione:** Filtra i post types traducibili.

**Parametri:**
- `$post_types` (array) - Array di nomi di post types

**Esempio:**
```php
add_filter( '\FPML_translatable_post_types', function( $post_types ) {
    // Aggiungi post type personalizzato
    $post_types[] = 'custom_post_type';
    return $post_types;
});
```

**Definito in:** `src/Core/Plugin.php:1290`

**Default:** `array( 'post', 'page' )`

---

### `\FPML_translatable_taxonomies`

**Descrizione:** Filtra le taxonomies traducibili.

**Parametri:**
- `$taxonomies` (array) - Array di nomi di taxonomies

**Esempio:**
```php
add_filter( '\FPML_translatable_taxonomies', function( $taxonomies ) {
    // Aggiungi taxonomy personalizzata
    $taxonomies[] = 'custom_taxonomy';
    return $taxonomies;
});
```

**Definito in:** `src/Core/Plugin.php:1117, 1233`

**Default:** `array( 'category', 'post_tag' )`

---

### `\FPML_queue_cleanup_states`

**Descrizione:** Filtra gli stati dei job da rimuovere durante la pulizia della coda.

**Parametri:**
- `$states` (array) - Array di stati ('done', 'skipped', 'error')

**Esempio:**
```php
add_filter( '\FPML_queue_cleanup_states', function( $states ) {
    // Non rimuovere mai i job con errori
    return array_filter( $states, function( $state ) {
        return $state !== 'error';
    } );
});
```

**Definito in:** `src/Core/Plugin.php:1436`

**Default:** `array( 'done', 'skipped', 'error' )`

---

### `\FPML_queue_cleanup_batch_size`

**Descrizione:** Filtra la dimensione del batch per la pulizia della coda.

**Parametri:**
- `$batch_size` (int) - Dimensione del batch di default
- `$states` (array) - Stati dei job da rimuovere
- `$days` (int) - Giorni di retention
- `$column` (string) - Colonna usata per il filtro

**Esempio:**
```php
add_filter( '\FPML_queue_cleanup_batch_size', function( $batch_size, $states, $days, $column ) {
    // Riduci batch size per server con poche risorse
    return 100;
}, 10, 4 );
```

**Definito in:** `src/Queue.php:411`

**Default:** `500`

---

### `fpml_enabled_languages`

**Descrizione:** Filtra le lingue abilitate.

**Parametri:**
- `$enabled` (array) - Array di codici lingua abilitati

**Esempio:**
```php
add_filter( 'fpml_enabled_languages', function( $enabled ) {
    // Forza sempre inglese e italiano
    return array_unique( array_merge( $enabled, array( 'en', 'it' ) ) );
});
```

**Definito in:** `src/MultiLanguage/LanguageManager.php:54`

---

### `fpml_current_language`

**Descrizione:** Filtra la lingua corrente determinata.

**Parametri:**
- `$lang` (string) - Codice lingua corrente

**Esempio:**
```php
add_filter( 'fpml_current_language', function( $lang ) {
    // Override lingua in base a parametro custom
    if ( isset( $_GET['lang_override'] ) ) {
        return sanitize_text_field( $_GET['lang_override'] );
    }
    return $lang;
});
```

**Definito in:** `src/Language.php:1146`

---

### `\FPML_has_cookie_consent`

**Descrizione:** Filtra se il consenso cookie è valido per salvare la lingua.

**Parametri:**
- `$has_consent` (bool) - Default true
- `$cookie_name` (string) - Nome del cookie
- `$raw_value` (string) - Valore raw del cookie

**Esempio:**
```php
add_filter( '\FPML_has_cookie_consent', function( $has_consent, $cookie_name, $raw_value ) {
    // Verifica consenso con plugin GDPR
    if ( function_exists( 'gdpr_consent_has_consent' ) ) {
        return gdpr_consent_has_consent( 'language_preference' );
    }
    return $has_consent;
}, 10, 3 );
```

**Definito in:** `src/Language.php:2842`

**Default:** `true`

---

### `fpml_filter_option_{$option}`

**Descrizione:** Filtro dinamico per le opzioni del plugin.

**Parametri:**
- `$value` (mixed) - Valore dell'opzione
- `$option` (string) - Nome dell'opzione

**Esempio:**
```php
add_filter( 'fpml_filter_option_provider', function( $value, $option ) {
    // Override provider per ambienti specifici
    if ( defined( 'WP_ENV' ) && WP_ENV === 'staging' ) {
        return 'openai';
    }
    return $value;
}, 10, 2 );
```

**Definito in:** `src/SiteTranslations.php:372`

---

### `\FPML_auto_delete_translation_on_source_delete`

**Descrizione:** Filtra se eliminare automaticamente le traduzioni quando il post sorgente viene eliminato.

**Parametri:**
- `$auto_delete` (bool) - Default false

**Esempio:**
```php
add_filter( '\FPML_auto_delete_translation_on_source_delete', '__return_true' );
```

**Definito in:** `src/Core/Plugin.php:1773`

**Default:** `false`

---

### `\FPML_auto_delete_translation_term_on_source_delete`

**Descrizione:** Filtra se eliminare automaticamente le traduzioni dei termini quando il termine sorgente viene eliminato.

**Parametri:**
- `$auto_delete` (bool) - Default false

**Esempio:**
```php
add_filter( '\FPML_auto_delete_translation_term_on_source_delete', '__return_true' );
```

**Definito in:** `src/Core/Plugin.php:1820, 1831`

**Default:** `false`

---

## Esempi d'uso

### Esempio 1: Aggiungere post type personalizzato

```php
/**
 * Rendi il post type "product" traducibile
 */
add_filter( '\FPML_translatable_post_types', function( $post_types ) {
    if ( ! in_array( 'product', $post_types, true ) ) {
        $post_types[] = 'product';
    }
    return $post_types;
});
```

---

### Esempio 2: Logging traduzioni salvate

```php
/**
 * Log tutte le traduzioni salvate
 */
add_action( 'fpml_after_translation_saved', function( $target_post_id, $source_post_id ) {
    $logger = wc_get_logger();
    $logger->info( 
        "Translation saved: Post #{$source_post_id} -> Post #{$target_post_id}",
        array( 'source' => 'fp-multilanguage' )
    );
}, 10, 2 );
```

---

### Esempio 3: Cache personalizzata per sviluppo

```php
/**
 * Disabilita cache in ambiente di sviluppo
 */
add_filter( '\FPML_cache_ttl', function( $ttl ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return 0; // Nessuna cache in debug mode
    }
    return $ttl;
});
```

---

### Esempio 4: Override lingua per test

```php
/**
 * Forza lingua per utenti amministratori in staging
 */
add_filter( 'fpml_current_language', function( $lang ) {
    if ( defined( 'WP_ENV' ) && WP_ENV === 'staging' && current_user_can( 'manage_options' ) ) {
        // Permetti override via query string
        if ( isset( $_GET['test_lang'] ) ) {
            return sanitize_text_field( $_GET['test_lang'] );
        }
    }
    return $lang;
});
```

---

## 📝 Note

- Tutti gli hook che iniziano con `\FPML_` usano il namespace completo
- Gli hook che iniziano con `fpml_` sono nello spazio globale WordPress
- Le priorità di default sono 10, ma possono essere modificate
- Alcuni hook accettano parametri multipli: verifica sempre la signature

---

## 🔗 Link Utili

- [WordPress Plugin API](https://developer.wordpress.org/plugins/hooks/)
- [API Reference](./api-reference.md)
- [Developer Guide](./developer-guide.md)

---

**Ultimo aggiornamento:** 2025-01-XX  
**Versione Plugin:** 0.10.0+







