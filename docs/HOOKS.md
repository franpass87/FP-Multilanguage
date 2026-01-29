# FP Multilanguage - Hook Pubblici

**Versione:** 0.9.0+  
**Data:** Novembre 2025

Questo documento elenca tutti gli hook pubblici (actions e filters) disponibili per sviluppatori che vogliono estendere o personalizzare il plugin FP Multilanguage.

---

## 📋 Indice

- [Actions](#actions)
- [Filters](#filters)
- [Esempi di Utilizzo](#esempi-di-utilizzo)

---

## Actions

### Plugin Lifecycle

#### `fpml_activate`
Eseguito dopo l'attivazione del plugin.

```php
add_action('fpml_activate', function() {
    // Logica personalizzata dopo attivazione
});
```

**Parametri:** Nessuno  
**Versione:** 0.5.0+

---

#### `fpml_deactivate`
Eseguito dopo la disattivazione del plugin.

```php
add_action('fpml_deactivate', function() {
    // Logica personalizzata dopo disattivazione
});
```

**Parametri:** Nessuno  
**Versione:** 0.5.0+

---

#### `\FPML_after_initialization`
Eseguito dopo l'inizializzazione completa del plugin.

```php
add_action('\FPML_after_initialization', function() {
    // Logica personalizzata dopo inizializzazione
});
```

**Parametri:** Nessuno  
**Versione:** 0.4.0+

---

#### `\FPML_before_activation`
Eseguito prima dell'attivazione del plugin.

```php
add_action('\FPML_before_activation', function() {
    // Logica personalizzata prima dell'attivazione
});
```

**Parametri:** Nessuno  
**Versione:** 0.4.0+

---

### Traduzione Contenuti

#### `\FPML_post_translated`
Eseguito dopo che un campo di un post è stato tradotto.

```php
add_action('\FPML_post_translated', function($target_post, $field, $translated_value, $job) {
    // $target_post: WP_Post object (post tradotto)
    // $field: string (nome campo: 'post_content', 'post_title', etc.)
    // $translated_value: string (valore tradotto)
    // $job: object (job queue)
    
    // Logica personalizzata dopo traduzione post
}, 10, 4);
```

**Parametri:**
- `$target_post` (WP_Post) - Post tradotto
- `$field` (string) - Nome del campo tradotto
- `$translated_value` (string) - Valore tradotto
- `$job` (object) - Job della queue

**Versione:** 0.5.0+

---

#### `fpml_after_translation_saved`
Eseguito dopo che una traduzione completa è stata salvata.

```php
add_action('fpml_after_translation_saved', function($translated_id, $original_id) {
    // $translated_id: int (ID post tradotto)
    // $original_id: int (ID post originale)
    
    // Logica personalizzata dopo salvataggio traduzione
}, 10, 2);
```

**Parametri:**
- `$translated_id` (int) - ID del post tradotto
- `$original_id` (int) - ID del post originale

**Versione:** 0.6.0+

---

#### `\FPML_term_translated`
Eseguito dopo che un termine (taxonomy) è stato tradotto.

```php
add_action('\FPML_term_translated', function($target_term, $field, $translated, $job) {
    // $target_term: WP_Term object
    // $field: string (nome campo: 'name', 'description')
    // $translated: string (valore tradotto)
    // $job: object (job queue)
    
    // Logica personalizzata dopo traduzione termine
}, 10, 4);
```

**Parametri:**
- `$target_term` (WP_Term) - Termine tradotto
- `$field` (string) - Nome del campo
- `$translated` (string) - Valore tradotto
- `$job` (object) - Job della queue

**Versione:** 0.5.0+

---

#### `\FPML_comment_translated`
Eseguito dopo che un commento è stato tradotto.

```php
add_action('\FPML_comment_translated', function($target_comment, $field, $translated, $job) {
    // $target_comment: WP_Comment object
    // $field: string (nome campo: 'comment_content')
    // $translated: string (valore tradotto)
    // $job: object (job queue)
    
    // Logica personalizzata dopo traduzione commento
}, 10, 4);
```

**Parametri:**
- `$target_comment` (WP_Comment) - Commento tradotto
- `$field` (string) - Nome del campo
- `$translated` (string) - Valore tradotto
- `$job` (object) - Job della queue

**Versione:** 0.9.1+

---

#### `\FPML_menu_item_translated`
Eseguito dopo che un item di menu è stato tradotto.

```php
add_action('\FPML_menu_item_translated', function($target_item, $field, $translated, $job) {
    // $target_item: array (menu item data)
    // $field: string (nome campo: 'title')
    // $translated: string (valore tradotto)
    // $job: object (job queue)
    
    // Logica personalizzata dopo traduzione menu item
}, 10, 4);
```

**Parametri:**
- `$target_item` (array) - Dati dell'item menu
- `$field` (string) - Nome del campo
- `$translated` (string) - Valore tradotto
- `$job` (object) - Job della queue

**Versione:** 0.9.0+

---

#### `\FPML_widget_translated`
Eseguito dopo che un widget è stato tradotto.

```php
add_action('\FPML_widget_translated', function($widget_id, $field, $translated, $job) {
    // $widget_id: string (ID widget)
    // $field: string (nome campo)
    // $translated: string (valore tradotto)
    // $job: object (job queue)
    
    // Logica personalizzata dopo traduzione widget
}, 10, 4);
```

**Parametri:**
- `$widget_id` (string) - ID del widget
- `$field` (string) - Nome del campo
- `$translated` (string) - Valore tradotto
- `$job` (object) - Job della queue

**Versione:** 0.9.0+

---

#### `\FPML_save_post_field_value`
Eseguito prima di salvare un valore di campo tradotto.

```php
add_action('\FPML_save_post_field_value', function($post, $field, $new_value) {
    // $post: WP_Post object
    // $field: string (nome campo)
    // $new_value: mixed (nuovo valore)
    
    // Logica personalizzata prima del salvataggio
}, 10, 3);
```

**Parametri:**
- `$post` (WP_Post) - Post
- `$field` (string) - Nome del campo
- `$new_value` (mixed) - Nuovo valore

**Versione:** 0.5.0+

---

### Integrazioni

#### `fpml_experiences_meta_synced`
Eseguito dopo la sincronizzazione dei meta fields di FP-Experiences.

```php
add_action('fpml_experiences_meta_synced', function($translated_id, $original_id, $synced_count) {
    // $translated_id: int (ID post tradotto)
    // $original_id: int (ID post originale)
    // $synced_count: int (numero campi sincronizzati)
    
    // Logica personalizzata dopo sync Experiences
}, 10, 3);
```

**Parametri:**
- `$translated_id` (int) - ID post tradotto
- `$original_id` (int) - ID post originale
- `$synced_count` (int) - Numero campi sincronizzati

**Versione:** 0.9.0+

---

#### `fpml_seo_meta_synced`
Eseguito dopo la sincronizzazione dei meta fields SEO.

```php
add_action('fpml_seo_meta_synced', function($translated_id, $original_id, $synced_count) {
    // $translated_id: int (ID post tradotto)
    // $original_id: int (ID post originale)
    // $synced_count: int (numero campi sincronizzati)
    
    // Logica personalizzata dopo sync SEO
}, 10, 3);
```

**Parametri:**
- `$translated_id` (int) - ID post tradotto
- `$original_id` (int) - ID post originale
- `$synced_count` (int) - Numero campi sincronizzati

**Versione:** 0.9.0+

---

### Cache

#### `fpml_cache_warmed`
Eseguito dopo il warm-up della cache.

```php
add_action('fpml_cache_warmed', function($cached_count, $texts, $source, $target) {
    // $cached_count: int (numero elementi cachati)
    // $texts: array (testi cachati)
    // $source: string (lingua sorgente)
    // $target: string (lingua target)
    
    // Logica personalizzata dopo cache warm-up
}, 10, 4);
```

**Parametri:**
- `$cached_count` (int) - Numero elementi cachati
- `$texts` (array) - Testi cachati
- `$source` (string) - Lingua sorgente
- `$target` (string) - Lingua target

**Versione:** 0.6.0+

---

### Queue

#### `\FPML_queue_after_cleanup`
Eseguito dopo la pulizia della queue.

```php
add_action('\FPML_queue_after_cleanup', function($states, $days, $total, $column) {
    // $states: array (stati rimossi)
    // $days: int (giorni di retention)
    // $total: int (numero job rimossi)
    // $column: string (colonna usata per cleanup)
    
    // Logica personalizzata dopo cleanup queue
}, 10, 4);
```

**Parametri:**
- `$states` (array) - Stati rimossi
- `$days` (int) - Giorni di retention
- `$total` (int) - Numero job rimossi
- `$column` (string) - Colonna usata

**Versione:** 0.6.0+

---

### Admin

#### `fpml_translation_metabox_after_actions`
Eseguito dopo il rendering delle azioni nel metabox traduzione.

```php
add_action('fpml_translation_metabox_after_actions', function($post_id, $translation_post_id) {
    // $post_id: int (ID post originale)
    // $translation_post_id: int (ID post tradotto)
    
    // Aggiungi azioni personalizzate al metabox
}, 10, 2);
```

**Parametri:**
- `$post_id` (int) - ID post originale
- `$translation_post_id` (int) - ID post tradotto

**Versione:** 0.8.0+

---

## Filters

### Configurazione Plugin

#### `fpml_service_providers`
Filtra la lista di Service Providers da registrare.

```php
add_filter('fpml_service_providers', function($providers) {
    $providers[] = MyCustomServiceProvider::class;
    return $providers;
});
```

**Parametri:**
- `$providers` (array) - Array di classi Service Provider

**Ritorna:** (array) Array modificato di Service Providers

**Versione:** 0.5.0+

---

### Post Types e Taxonomies

#### `\FPML_translatable_post_types`
Filtra i post types traducibili.

```php
add_filter('\FPML_translatable_post_types', function($post_types) {
    $post_types[] = 'my_custom_post_type';
    return $post_types;
});
```

**Parametri:**
- `$post_types` (array) - Array di post type names

**Ritorna:** (array) Array modificato di post types

**Versione:** 0.4.0+

---

#### `\FPML_translatable_taxonomies`
Filtra le taxonomies traducibili.

```php
add_filter('\FPML_translatable_taxonomies', function($taxonomies) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});
```

**Parametri:**
- `$taxonomies` (array) - Array di taxonomy names

**Ritorna:** (array) Array modificato di taxonomies

**Versione:** 0.4.0+

---

### Traduzione

#### `\FPML_pre_save_translation`
Filtra il valore prima di salvare una traduzione.

```php
add_filter('\FPML_pre_save_translation', function($new_value, $post, $field) {
    // $new_value: mixed (valore da salvare)
    // $post: WP_Post object
    // $field: string (nome campo)
    
    // Modifica il valore prima del salvataggio
    return $new_value;
}, 10, 3);
```

**Parametri:**
- `$new_value` (mixed) - Valore da salvare
- `$post` (WP_Post) - Post
- `$field` (string) - Nome campo

**Ritorna:** (mixed) Valore modificato

**Versione:** 0.5.0+

---

#### `\FPML_get_post_field_value`
Filtra il valore di un campo post prima della traduzione.

```php
add_filter('\FPML_get_post_field_value', function($value, $post, $field) {
    // $value: string (valore corrente, default: '')
    // $post: WP_Post object
    // $field: string (nome campo)
    
    // Fornisci un valore personalizzato
    return $value;
}, 10, 3);
```

**Parametri:**
- `$value` (string) - Valore corrente
- `$post` (WP_Post) - Post
- `$field` (string) - Nome campo

**Ritorna:** (string) Valore modificato

**Versione:** 0.5.0+

---

#### `\FPML_translation_retry_delay`
Filtra il delay tra i retry di traduzione.

```php
add_filter('\FPML_translation_retry_delay', function($sleep, $domain) {
    // $sleep: int (secondi di delay, default: 2)
    // $domain: string (dominio traduzione)
    
    // Modifica il delay
    return 5; // 5 secondi invece di 2
}, 10, 2);
```

**Parametri:**
- `$sleep` (int) - Secondi di delay
- `$domain` (string) - Dominio traduzione

**Ritorna:** (int) Delay modificato

**Versione:** 0.5.0+

---

### Lingua

#### `fpml_current_language`
Filtra la lingua corrente rilevata.

```php
add_filter('fpml_current_language', function($lang) {
    // $lang: string (codice lingua: 'it', 'en', etc.)
    
    // Forza una lingua specifica
    return 'en';
});
```

**Parametri:**
- `$lang` (string) - Codice lingua corrente

**Ritorna:** (string) Codice lingua modificato

**Versione:** 0.5.0+

---

#### `\FPML_has_cookie_consent`
Filtra il consenso cookie per la gestione lingua.

```php
add_filter('\FPML_has_cookie_consent', function($has_consent, $cookie_name, $raw_value) {
    // $has_consent: bool (default: true)
    // $cookie_name: string (nome cookie)
    // $raw_value: mixed (valore cookie)
    
    // Verifica consenso personalizzato
    return $has_consent;
}, 10, 3);
```

**Parametri:**
- `$has_consent` (bool) - Consenso corrente
- `$cookie_name` (string) - Nome cookie
- `$raw_value` (mixed) - Valore cookie

**Ritorna:** (bool) Consenso modificato

**Versione:** 0.6.0+

---

### Cache

#### `\FPML_cache_ttl`
Filtra il TTL (Time To Live) della cache.

```php
add_filter('\FPML_cache_ttl', function($ttl) {
    // $ttl: int (secondi, default: 3600)
    
    // Modifica TTL cache
    return 7200; // 2 ore invece di 1
});
```

**Parametri:**
- `$ttl` (int) - TTL in secondi

**Ritorna:** (int) TTL modificato

**Versione:** 0.6.0+

---

### Processor

#### `\FPML_processor_lock_ttl`
Filtra il TTL del lock del processor.

```php
add_filter('\FPML_processor_lock_ttl', function($ttl) {
    // $ttl: int (secondi, default: 300)
    
    // Modifica TTL lock
    return 600; // 10 minuti invece di 5
});
```

**Parametri:**
- `$ttl` (int) - TTL in secondi

**Ritorna:** (int) TTL modificato

**Versione:** 0.5.0+

---

### Queue

#### `\FPML_queue_cleanup_states`
Filtra gli stati da rimuovere durante il cleanup della queue.

```php
add_filter('\FPML_queue_cleanup_states', function($states) {
    // $states: array (default: ['done', 'skipped', 'error'])
    
    // Aggiungi stati personalizzati
    $states[] = 'custom_state';
    return $states;
});
```

**Parametri:**
- `$states` (array) - Stati da rimuovere

**Ritorna:** (array) Stati modificati

**Versione:** 0.6.0+

---

#### `\FPML_queue_cleanup_batch_size`
Filtra la dimensione del batch per il cleanup della queue.

```php
add_filter('\FPML_queue_cleanup_batch_size', function($batch_size, $states, $days, $column) {
    // $batch_size: int (default: 500)
    // $states: array (stati da rimuovere)
    // $days: int (giorni retention)
    // $column: string (colonna usata)
    
    // Modifica batch size
    return 1000; // Processa 1000 alla volta
}, 10, 4);
```

**Parametri:**
- `$batch_size` (int) - Dimensione batch
- `$states` (array) - Stati da rimuovere
- `$days` (int) - Giorni retention
- `$column` (string) - Colonna usata

**Ritorna:** (int) Batch size modificato

**Versione:** 0.6.0+

---

### Content Indexer

#### `\FPML_reindex_summary`
Filtra il summary del reindex.

```php
add_filter('\FPML_reindex_summary', function($summary) {
    // $summary: array (statistiche reindex)
    
    // Modifica summary
    return $summary;
});
```

**Parametri:**
- `$summary` (array) - Statistiche reindex

**Ritorna:** (array) Summary modificato

**Versione:** 0.6.0+

---

### SEO

#### `\FPML_sitemap_post_types`
Filtra i post types inclusi nella sitemap.

```php
add_filter('\FPML_sitemap_post_types', function($post_types) {
    // $post_types: array (default: ['post', 'page'])
    
    // Aggiungi post types personalizzati
    $post_types[] = 'product';
    return $post_types;
});
```

**Parametri:**
- `$post_types` (array) - Post types

**Ritorna:** (array) Post types modificati

**Versione:** 0.7.0+

---

#### `\FPML_sitemap_taxonomies`
Filtra le taxonomies incluse nella sitemap.

```php
add_filter('\FPML_sitemap_taxonomies', function($taxonomies) {
    // $taxonomies: array (default: ['category', 'post_tag'])
    
    // Aggiungi taxonomies personalizzate
    $taxonomies[] = 'product_category';
    return $taxonomies;
});
```

**Parametri:**
- `$taxonomies` (array) - Taxonomies

**Ritorna:** (array) Taxonomies modificate

**Versione:** 0.7.0+

---

#### `\FPML_sitemap_entries`
Filtra le entries della sitemap.

```php
add_filter('\FPML_sitemap_entries', function($entries) {
    // $entries: array (entries sitemap)
    
    // Modifica entries
    return $entries;
});
```

**Parametri:**
- `$entries` (array) - Entries sitemap

**Ritorna:** (array) Entries modificate

**Versione:** 0.7.0+

---

### Plugin Detection

#### `\FPML_plugin_detection_rules`
Filtra le regole di rilevamento plugin.

```php
add_filter('\FPML_plugin_detection_rules', function($rules) {
    // $rules: array (regole di detection)
    
    // Aggiungi regole personalizzate
    $rules[] = array(
        'plugin' => 'my-plugin',
        'meta_keys' => array('_my_custom_field'),
    );
    return $rules;
});
```

**Parametri:**
- `$rules` (array) - Regole detection

**Ritorna:** (array) Regole modificate

**Versione:** 0.8.0+

---

### Logger

#### `fpml_log_max_entries`
Filtra il numero massimo di entries nel log.

```php
add_filter('fpml_log_max_entries', function($max_entries) {
    // $max_entries: int (default: 1000)
    
    // Modifica max entries
    return 5000; // Mantieni 5000 entries invece di 1000
});
```

**Parametri:**
- `$max_entries` (int) - Numero massimo entries

**Ritorna:** (int) Max entries modificato

**Versione:** 0.5.0+

---

### Site Translations

#### `fpml_filter_option_{$option}`
Filtra un'opzione specifica per le traduzioni del sito.

```php
add_filter('fpml_filter_option_blogname', function($value, $option) {
    // $value: mixed (valore opzione)
    // $option: string (nome opzione: 'blogname')
    
    // Fornisci traduzione personalizzata
    if (fpml_get_current_language() === 'en') {
        return 'My English Site Name';
    }
    return $value;
}, 10, 2);
```

**Parametri:**
- `$value` (mixed) - Valore opzione
- `$option` (string) - Nome opzione

**Ritorna:** (mixed) Valore modificato

**Versione:** 0.9.0+

---

## Esempi di Utilizzo

### Esempio 1: Aggiungere Post Type Personalizzato

```php
// Aggiungi un custom post type alla lista traducibili
add_filter('\FPML_translatable_post_types', function($post_types) {
    $post_types[] = 'my_custom_post_type';
    return $post_types;
});

// Aggiungi anche la taxonomy associata
add_filter('\FPML_translatable_taxonomies', function($taxonomies) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});
```

---

### Esempio 2: Log Personalizzato dopo Traduzione

```php
// Log personalizzato dopo ogni traduzione post
add_action('\FPML_post_translated', function($target_post, $field, $translated_value, $job) {
    error_log(sprintf(
        'Post %d tradotto: campo %s = %s',
        $target_post->ID,
        $field,
        substr($translated_value, 0, 50)
    ));
}, 10, 4);
```

---

### Esempio 3: Modificare Traduzione Prima del Salvataggio

```php
// Aggiungi prefisso a tutte le traduzioni
add_filter('\FPML_pre_save_translation', function($new_value, $post, $field) {
    if ($field === 'post_content') {
        $new_value = '[Traduzione] ' . $new_value;
    }
    return $new_value;
}, 10, 3);
```

---

### Esempio 4: Notifica Email dopo Traduzione

```php
// Invia email quando una traduzione è completata
add_action('fpml_after_translation_saved', function($translated_id, $original_id) {
    $original = get_post($original_id);
    $translated = get_post($translated_id);
    
    wp_mail(
        get_option('admin_email'),
        'Traduzione Completata',
        sprintf(
            'Il post "%s" è stato tradotto in "%s"',
            $original->post_title,
            $translated->post_title
        )
    );
}, 10, 2);
```

---

### Esempio 5: Cache Personalizzata

```php
// Estendi TTL cache per traduzioni specifiche
add_filter('\FPML_cache_ttl', function($ttl) {
    // Se siamo in modalità debug, usa cache più breve
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return 300; // 5 minuti
    }
    return 7200; // 2 ore in produzione
});
```

---

## Note Importanti

1. **Namespace**: Alcuni hook usano il namespace `\FPML_` (con backslash iniziale), altri usano `fpml_` (senza backslash). Usa esattamente il formato indicato.

2. **Priorità**: La maggior parte degli hook usa priorità 10. Puoi modificarla se necessario:
   ```php
   add_action('fpml_after_translation_saved', 'my_callback', 5); // Eseguito prima
   add_action('fpml_after_translation_saved', 'my_callback', 20); // Eseguito dopo
   ```

3. **Parametri**: Controlla sempre il numero di parametri passati all'hook. Usa il numero corretto nella callback:
   ```php
   add_action('hook_name', 'callback', 10, 4); // 4 parametri
   ```

4. **Performance**: Evita operazioni pesanti negli hook, specialmente quelli chiamati frequentemente (es. `\FPML_post_translated`).

5. **Testing**: Testa sempre i tuoi hook in un ambiente di sviluppo prima di usarli in produzione.

---

## Supporto

Per domande o problemi con gli hook:
- 📖 [Documentazione Completa](README.md)
- 🐙 [GitHub Issues](https://github.com/francescopasseri/FP-Multilanguage/issues)
- 📧 Email: info@francescopasseri.com

---

**Ultimo aggiornamento:** Novembre 2025  
**Versione plugin:** 0.9.0+














