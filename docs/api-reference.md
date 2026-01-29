# API Reference - FP Multilanguage

**Version:** 0.10.0+  
**Last Updated:** 2025-01-XX

Documentazione completa delle API pubbliche disponibili in FP Multilanguage.

---

## 📋 Indice

- [TranslationManager](#translationmanager)
- [Queue](#queue)
- [TranslationCache](#translationcache)
- [LanguageManager](#languagemanager)
- [CLI Commands](#cli-commands)

---

## TranslationManager

Gestisce la creazione e sincronizzazione delle traduzioni di post e termini.

### `TranslationManager::instance()`

Restituisce l'istanza singleton del TranslationManager.

**Return:** `TranslationManager`

**Esempio:**
```php
$manager = \FP\Multilanguage\Content\TranslationManager::instance();
```

---

### `get_translation_id( $post_id, $target_lang = 'en' )`

Ottiene l'ID del post tradotto per una lingua specifica.

**Parametri:**
- `$post_id` (int) - ID del post sorgente
- `$target_lang` (string) - Codice lingua target (default: 'en')

**Return:** `int|false` - ID del post tradotto o false se non esiste

**Esempio:**
```php
$en_post_id = $manager->get_translation_id( 123, 'en' );
if ( $en_post_id ) {
    echo "Post EN: {$en_post_id}";
}
```

**Note:** Utilizza cache per performance ottimali.

---

### `get_all_translations( $post_id )`

Ottiene tutti gli ID delle traduzioni per un post.

**Parametri:**
- `$post_id` (int) - ID del post sorgente

**Return:** `array` - Array associativo `[lang_code => translation_id]`

**Esempio:**
```php
$translations = $manager->get_all_translations( 123 );
// Risultato: ['en' => 456, 'de' => 789]
```

---

### `create_post_translation( $post, $target_lang = 'en', $status = 'draft' )`

Crea una nuova traduzione di un post.

**Parametri:**
- `$post` (WP_Post) - Post oggetto sorgente
- `$target_lang` (string) - Codice lingua target
- `$status` (string) - Status del post tradotto (default: 'draft')

**Return:** `WP_Post|false` - Post tradotto o false in caso di errore

**Esempio:**
```php
$post = get_post( 123 );
$translation = $manager->create_post_translation( $post, 'en', 'draft' );
if ( $translation ) {
    echo "Traduzione creata: {$translation->ID}";
}
```

---

### `ensure_post_translation( $post, $target_lang = 'en' )`

Assicura che esista una traduzione, creandola se necessario.

**Parametri:**
- `$post` (WP_Post) - Post oggetto sorgente
- `$target_lang` (string) - Codice lingua target

**Return:** `WP_Post|false` - Post tradotto o false in caso di errore

**Esempio:**
```php
$post = get_post( 123 );
$translation = $manager->ensure_post_translation( $post, 'en' );
// La traduzione esiste già o viene creata automaticamente
```

---

## Queue

Gestisce la coda delle traduzioni asincrone.

### `Queue::instance()`

Restituisce l'istanza singleton della Queue.

**Return:** `Queue`

**Esempio:**
```php
$queue = \FPML_Queue::instance();
```

---

### `enqueue( $type, $id, $field, $hash )`

Aggiunge un job alla coda di traduzione.

**Parametri:**
- `$type` (string) - Tipo di contenuto ('post', 'term')
- `$id` (int) - ID del contenuto
- `$field` (string) - Campo da tradurre ('post_content', 'post_title', etc.)
- `$hash` (string) - Hash MD5 del contenuto per rilevare modifiche

**Return:** `int|WP_Error` - ID del job o WP_Error in caso di errore

**Esempio:**
```php
$queue->enqueue( 'post', 123, 'post_content', md5( $content ) );
$queue->enqueue( 'post', 123, 'post_title', md5( $title ) );
```

---

### `get_state_counts()`

Ottiene il conteggio dei job per stato.

**Return:** `array` - Array associativo `[state => count]`

**Esempio:**
```php
$counts = $queue->get_state_counts();
// Risultato: ['pending' => 10, 'translating' => 2, 'done' => 100]
```

**Note:** Utilizza transients per cache (TTL: 2 minuti).

---

### `get_next_jobs( $limit = 10 )`

Ottiene i prossimi job da processare.

**Parametri:**
- `$limit` (int) - Numero massimo di job da recuperare

**Return:** `array` - Array di job objects

**Esempio:**
```php
$jobs = $queue->get_next_jobs( 5 );
foreach ( $jobs as $job ) {
    // Processa job
}
```

---

## TranslationCache

Gestisce la cache delle traduzioni.

### `TranslationCache::instance()`

Restituisce l'istanza singleton del TranslationCache.

**Return:** `TranslationCache`

**Esempio:**
```php
$cache = \FP\Multilanguage\Core\TranslationCache::instance();
```

---

### `get( $text, $source, $target, $provider = null )`

Ottiene una traduzione dalla cache.

**Parametri:**
- `$text` (string) - Testo da tradurre
- `$source` (string) - Lingua sorgente
- `$target` (string) - Lingua target
- `$provider` (string|null) - Provider di traduzione (opzionale)

**Return:** `string|false` - Testo tradotto o false se non in cache

**Esempio:**
```php
$cached = $cache->get( 'Hello', 'en', 'it', 'openai' );
if ( $cached !== false ) {
    echo $cached; // "Ciao"
}
```

---

### `set( $text, $translated, $source, $target, $provider = null )`

Salva una traduzione nella cache.

**Parametri:**
- `$text` (string) - Testo originale
- `$translated` (string) - Testo tradotto
- `$source` (string) - Lingua sorgente
- `$target` (string) - Lingua target
- `$provider` (string|null) - Provider di traduzione (opzionale)

**Return:** `bool` - true se salvato con successo

**Esempio:**
```php
$cache->set( 'Hello', 'Ciao', 'en', 'it', 'openai' );
```

---

### `clear()`

Pulisce tutta la cache delle traduzioni.

**Return:** `bool` - true se pulito con successo

**Esempio:**
```php
$cache->clear();
```

**Note:** Usa `wp_cache_flush_group()` se disponibile per invalidare solo la cache del plugin.

---

### `invalidate_post_translations( $post_id, $fields = array() )`

Invalida la cache per le traduzioni di un post specifico.

**Parametri:**
- `$post_id` (int) - ID del post
- `$fields` (array) - Campi da invalidare (default: tutti)

**Return:** `void`

**Esempio:**
```php
$cache->invalidate_post_translations( 123, array( 'post_content', 'post_title' ) );
```

---

## LanguageManager

Gestisce le lingue abilitate e disponibili.

### `LanguageManager::instance()`

Restituisce l'istanza singleton del LanguageManager.

**Return:** `LanguageManager`

**Esempio:**
```php
$lang_manager = \FP\Multilanguage\MultiLanguage\LanguageManager::instance();
```

---

### `get_enabled_languages()`

Ottiene array dei codici lingua abilitati.

**Return:** `array` - Array di codici lingua

**Esempio:**
```php
$languages = $lang_manager->get_enabled_languages();
// Risultato: ['it', 'en']
```

---

### `get_all_languages()`

Ottiene informazioni su tutte le lingue disponibili.

**Return:** `array` - Array associativo `[code => info]`

**Esempio:**
```php
$all = $lang_manager->get_all_languages();
// Risultato: ['en' => ['name' => 'English', 'flag' => '🇬🇧'], ...]
```

---

### `get_language_info( $code )`

Ottiene informazioni su una lingua specifica.

**Parametri:**
- `$code` (string) - Codice lingua

**Return:** `array|false` - Informazioni lingua o false

**Esempio:**
```php
$info = $lang_manager->get_language_info( 'en' );
// Risultato: ['name' => 'English', 'flag' => '🇬🇧', 'code' => 'en']
```

---

## CLI Commands

Comandi WP-CLI disponibili.

### `wp fpml queue status`

Mostra lo status della coda di traduzione.

**Esempio:**
```bash
wp fpml queue status
```

---

### `wp fpml queue run [--progress] [--batch=<size>]`

Esegue un batch di traduzioni dalla coda.

**Opzioni:**
- `--progress` - Mostra progress bar
- `--batch=<size>` - Dimensione del batch

**Esempio:**
```bash
wp fpml queue run --progress --batch=10
```

---

### `wp fpml queue cleanup [--days=<days>] [--states=<states>] [--dry-run]`

Pulisce i job vecchi dalla coda.

**Opzioni:**
- `--days=<days>` - Giorni di retention
- `--states=<states>` - Stati da rimuovere (separati da virgola)
- `--dry-run` - Mostra cosa verrebbe rimosso senza eseguire

**Esempio:**
```bash
wp fpml queue cleanup --days=30 --states=done,skipped --dry-run
```

---

### `wp fpml test-translation <post_id> [--lang=<lang>] [--dry-run]`

Testa la traduzione di un singolo post.

**Parametri:**
- `<post_id>` - ID del post da testare

**Opzioni:**
- `--lang=<lang>` - Lingua target (default: en)
- `--dry-run` - Mostra cosa farebbe senza eseguire

**Esempio:**
```bash
wp fpml test-translation 123 --lang=en --dry-run
```

---

### `wp fpml sync-status [--post-type=<type>] [--taxonomy=<tax>]`

Verifica lo status di sincronizzazione delle traduzioni.

**Opzioni:**
- `--post-type=<type>` - Filtra per post type
- `--taxonomy=<tax>` - Filtra per taxonomy

**Esempio:**
```bash
wp fpml sync-status --post-type=post
```

---

### `wp fpml export-translations [--file=<file>] [--post-type=<type>] [--include-content]`

Esporta le traduzioni in formato JSON.

**Opzioni:**
- `--file=<file>` - File di output (default: timestamp)
- `--post-type=<type>` - Filtra per post type
- `--include-content` - Include contenuto post nell'export

**Esempio:**
```bash
wp fpml export-translations --file=translations.json --include-content
```

---

## 📝 Note

- Tutte le classi usano il pattern Singleton per garantire un'unica istanza
- La cache viene automaticamente invalidata quando necessario
- Le API sono thread-safe e possono essere usate in ambiente multi-thread
- Tutti i metodi gestiscono errori e restituiscono valori sicuri

---

## 🔗 Link Utili

- [Hooks and Filters](./hooks-and-filters.md)
- [Developer Guide](./developer-guide.md)
- [Architecture](./architecture.md)

---

**Ultimo aggiornamento:** 2025-01-XX  
**Versione Plugin:** 0.10.0+
