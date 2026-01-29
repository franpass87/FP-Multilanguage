# Troubleshooting Guide - FP Multilanguage

## Table of Contents
- [Queue Issues](#queue-issues)
- [Translation Errors](#translation-errors)
- [Performance Problems](#performance-problems)
- [Provider Issues](#provider-issues)
- [Database Issues](#database-issues)
- [Comment Translation Issues](#comment-translation-issues)
- [WooCommerce Issues](#woocommerce-issues)
- [Common Error Messages](#common-error-messages)

---

## Queue Issues

### La coda non processa i job

#### Sintomi
- Job rimangono in stato `pending` indefinitamente
- Nessun aggiornamento dopo ore/giorni
- Dashboard mostra alto numero di job in attesa

#### Diagnosi
```bash
# Check queue status
wp fpml queue status

# Check if processor is locked
wp option get fpml_queue_lock
```

#### Possibili Cause e Soluzioni

##### 1. WP-Cron Disabilitato

**Verifica:**
```php
// In wp-config.php
define('DISABLE_WP_CRON', true); // Questo disabilita WP-Cron!
```

**Soluzione:** Setup system cron
```bash
# Aggiungi a crontab (Linux/Mac)
crontab -e

# Aggiungi questa riga (esegui ogni 5 minuti)
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now
```

**O esegui manualmente:**
```bash
wp fpml queue run --batch=50
```

---

##### 2. Processor Bloccato

**Verifica:**
```bash
wp fpml queue status
# Output: "Lock processor: attivo"
```

**Quando succede:**
- Processo interrotto durante esecuzione
- Timeout PHP/server
- Fatal error durante traduzione

**Soluzione: Force Unlock**
```bash
# Remove lock manually
wp option delete fpml_queue_lock

# Or wait for auto-expiry (30 minutes default)
```

**Prevenzione:**
```php
// In wp-config.php
set_time_limit( 300 ); // 5 minuti timeout
ini_set( 'memory_limit', '512M' );
```

---

##### 3. Provider API Non Configurato

**Verifica:**
```bash
wp fpml queue status
# Output: "Provider configurato: No" or error message
```

**Soluzione:**
1. Vai a **Settings → FP Multilanguage → General**
2. Seleziona provider (OpenAI, DeepL, Google, LibreTranslate)
3. Inserisci API key valida
4. Test connection con **Test Provider** button

---

##### 4. Rate Limiting

**Verifica nei logs:**
```bash
wp eval "print_r(FPML_Logger::instance()->get_logs_by_event('api.error', 10));"
```

Se vedi errori `429` o `rate_limit`:

**Soluzione temporanea:**
```bash
# Reset rate limiter
wp eval "FPML_Rate_Limiter::reset('openai');"
```

**Soluzione permanente:**
```php
// In wp-config.php - riduce velocità processing
define( 'FPML_BATCH_SIZE', 10 ); // Default: 20
```

---

### Job in stato "error" non vengono ritentati

#### Comportamento atteso
- Job con errori temporanei (500, 503) → retry automatico
- Job con errori permanenti (401, 400) → rimangono in `error`

#### Soluzioni

**Opzione 1: Cleanup e Re-enqueue**
```bash
# 1. Cleanup job failed
wp fpml queue cleanup --states=error --days=0

# 2. Re-index per ricreare job
wp eval "FPML_Plugin::instance()->reindex_content();"
```

**Opzione 2: Cambia stato manualmente**
```php
// Via wp-admin → Tools → Export/Import
// O via database:
UPDATE wp_fpml_queue 
SET state = 'pending' 
WHERE state = 'error' 
  AND DATEDIFF(NOW(), updated_at) < 1;
```

---

## Translation Errors

### Traduzioni incomplete o troncate

#### Cause comuni
1. **Timeout PHP** - testo troppo lungo
2. **Limite caratteri provider** - chunk troppo grande
3. **Memory limit** - contenuto complesso

#### Soluzioni

**Riduci dimensione chunk:**
```php
// In Settings → FP Multilanguage → General
// O in wp-config.php
define( 'FPML_MAX_CHARS_PER_CHUNK', 2000 ); // Default: 4500
```

**Aumenta timeout:**
```php
// In wp-config.php
set_time_limit( 300 ); // 5 minuti
```

**Aumenta memory:**
```php
// In wp-config.php
define( 'WP_MEMORY_LIMIT', '512M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
```

---

### Le traduzioni perdono formattazione HTML

#### Causa
Provider rimuove o modifica tag HTML.

#### Soluzione

**Per OpenAI:**
Verifica prompt system in Settings → assicurati dica:
> "Preserve HTML tags, attributes, shortcodes exactly"

**Per DeepL:**
Assicurati che `tag_handling` sia attivo (gestito automaticamente dal plugin).

**Manuale: Pre/Post processing**
```php
// Proteggi tag custom prima della traduzione
add_filter( 'fpml_glossary_pre_translate', function( $text ) {
    // Proteggi shortcodes custom
    $text = preg_replace( '/\[my_shortcode([^\]]*)\]/', '[PROTECTED_SC$1]', $text );
    return $text;
});

add_filter( 'fpml_glossary_post_translate', function( $text ) {
    // Ripristina
    $text = str_replace( '[PROTECTED_SC', '[my_shortcode', $text );
    return $text;
});
```

---

### Traduzioni in lingua sbagliata

#### Sintomo
Ricevi traduzioni in spagnolo, francese invece di inglese.

#### Causa
- Provider confuso da testo input
- Prompt ambiguo

#### Soluzione

**Verifica target language nelle impostazioni**

**Per OpenAI - Rafforza il prompt:**
```php
add_filter( 'fpml_glossary_pre_translate', function( $text, $source, $target ) {
    // Prepend instruction
    return "TRANSLATE TO ENGLISH (United States) ONLY:\n\n" . $text;
}, 10, 3 );
```

---

## Performance Problems

### Reindex molto lento (>10 minuti per 100 posts)

#### Cause
- N+1 query problem (**FIXED in 0.3.2**)
- Database non ottimizzato
- Troppi post

#### Verifiche

**Check query count:**
```php
// Install Query Monitor plugin
// O aggiungi in wp-config.php:
define( 'SAVEQUERIES', true );

// Poi after reindex:
global $wpdb;
echo "Total queries: " . count( $wpdb->queries );
```

#### Soluzioni

**✅ Già implementato nella versione corrente:**
- `update_meta_cache()` per post e term
- Caching term translations

**Se ancora lento:**
```bash
# Process in smaller batches
wp eval "
\$plugin = FPML_Plugin::instance();
\$post_types = ['post', 'page'];
foreach (\$post_types as \$pt) {
    echo \"Processing \$pt...\\n\";
    // Custom batch logic here
}
"
```

**Ottimizza database:**
```sql
-- Add indexes (se non presenti)
ALTER TABLE wp_postmeta ADD INDEX meta_key_value (meta_key(191), meta_value(191));
ALTER TABLE wp_fpml_queue ADD INDEX state_created (state, created_at);
```

---

### Dashboard admin lento

#### Causa
Diagnostics dashboard fa query pesanti.

#### Soluzione

**Disabilita diagnostics temporaneamente:**
```php
// In wp-config.php
define( 'FPML_DISABLE_DIAGNOSTICS_QUERIES', true );
```

**O usa caching:**
```php
// Cache diagnostics snapshot
add_filter( 'fpml_diagnostics_cache_ttl', function() {
    return 5 * MINUTE_IN_SECONDS; // Cache 5 min
});
```

---

## Provider Issues

### OpenAI: "Error: Rate limit exceeded"

#### Causa
Troppe richieste in breve tempo.

#### Soluzioni

**Opzione 1: Upgrade piano OpenAI**
- Vai su https://platform.openai.com/account/billing
- Upgrade a paid plan con rate limits più alti

**Opzione 2: Riduci velocità processing**
```php
// In wp-config.php
define( 'FPML_BATCH_SIZE', 5 );
define( 'FPML_BATCH_DELAY', 10 ); // Secondi tra batch
```

**Opzione 3: Usa rate limiter (già integrato dalla 0.3.2)**
```php
// Il plugin ora gestisce automaticamente rate limiting
// Se necessario, puoi resettare:
wp eval "FPML_Rate_Limiter::reset('openai');"
```

---

### OpenAI: "Error: Invalid API key"

#### Diagnosi
```bash
# Test API key manually
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Soluzioni
1. Verifica key sia corretta (no spazi extra)
2. Key non scaduta
3. Key con permessi modelli GPT-5

---

### OpenAI: "You exceeded your current quota" (Errore Billing)

#### Sintomi
- Errore: "You exceeded your current quota, please check your plan and billing details"
- Il test del provider fallisce immediatamente
- L'errore si verifica anche senza aver mai usato il servizio

#### Causa
Dal 2024, OpenAI **non offre più crediti gratuiti** per i nuovi account. L'API richiede:
1. Un metodo di pagamento configurato
2. Crediti prepagati caricati sull'account
3. Un piano di billing attivo

Anche con una chiave API valida, se non hai crediti riceverai questo errore.

#### Come Verificare

**Opzione 1: Usa il pulsante "Verifica Billing" nel plugin**
1. Vai su FP Multilanguage → Impostazioni → Generali
2. Clicca su "Verifica Billing" accanto alla chiave OpenAI
3. Il sistema controllerà automaticamente lo stato del tuo account

**Opzione 2: Verifica manualmente sul dashboard OpenAI**
1. Vai su https://platform.openai.com/account/billing/overview
2. Verifica che ci siano crediti disponibili nel "Credit balance"
3. Controlla che ci sia un metodo di pagamento attivo

#### Soluzione

**1. Configura il Billing OpenAI**
```
1. Vai su https://platform.openai.com/account/billing/overview
2. Clicca su "Add payment details"
3. Aggiungi una carta di credito o debito
4. Clicca su "Add to credit balance"
5. Carica almeno $5 di crediti (consigliato: $10-20)
6. Attendi 1-2 minuti per l'attivazione
7. Torna al plugin e clicca "Test provider"
```

**2. Costi Reali**
Con gpt-5-nano (modello consigliato):
- ~$0.10 per 1000 caratteri tradotti (50% più economico)
- Con $5 puoi tradurre circa 50.000 caratteri
- Con $10 puoi tradurre circa 100.000 caratteri
- Con $20 puoi tradurre circa 200.000 caratteri

Un articolo medio (1000 parole = ~6000 caratteri) costa circa $0.90.

**3. Alternative Gratuite**
Se non vuoi usare OpenAI a pagamento:

**DeepL** (Consigliato per iniziare)
- 500.000 caratteri/mese **GRATIS**
- Qualità eccellente
- Configurazione in Settings → Provider → DeepL
- Registrazione: https://www.deepl.com/pro#developer

**LibreTranslate** (Massima privacy)
- Completamente gratuito se self-hosted
- Istanza pubblica disponibile
- Nessun limite di caratteri
- Configurazione in Settings → Provider → LibreTranslate

#### Verifica che Funzioni

Dopo aver caricato i crediti:
```bash
# Via WP-CLI
wp fpml test openai

# O dall'interfaccia
# Dashboard → FP Multilanguage → Diagnostica → "Test provider"
```

#### Errori Comuni

**"Billing setup is required"**
→ Non hai ancora aggiunto un metodo di pagamento

**"Insufficient quota" dopo aver caricato crediti**
→ Attendi 1-2 minuti e riprova

**"Invalid API key" dopo setup billing**
→ La chiave API è scaduta, creane una nuova

---

### DeepL: "Quota exceeded"

#### Causa
Raggiunto limite mensile caratteri.

#### Verifica quota:
```bash
# Via DeepL API
curl https://api.deepl.com/v2/usage \
  -H "Authorization: DeepL-Auth-Key YOUR_KEY"
```

#### Soluzioni
1. Upgrade piano DeepL
2. Passa temporaneamente a provider diverso
3. Attendi reset quota (1° del mese)

---

### LibreTranslate: "Connection timeout"

#### Causa
Server self-hosted lento o irraggiungibile.

#### Soluzioni

**Aumenta timeout:**
```php
add_filter( 'http_request_timeout', function( $timeout ) {
    if ( doing_filter( 'fpml_translate_text' ) ) {
        return 90; // 90 secondi
    }
    return $timeout;
});
```

**Verifica server sia online:**
```bash
curl https://your-libretranslate-url.com/translate \
  -H "Content-Type: application/json" \
  -d '{"q":"test","source":"it","target":"en"}'
```

---

## Database Issues

### Tabella wp_fpml_queue non esiste

#### Causa
- Plugin disattivato e riattivato
- Errore durante installazione

#### Soluzione
```bash
# Deactivate and reactivate plugin
wp plugin deactivate fp-multilanguage
wp plugin activate fp-multilanguage

# Or manually create table
wp eval "FPML_Queue::instance()->install();"
```

---

### Queue table troppo grande (>100MB)

#### Diagnosi
```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_name = 'wp_fpml_queue';
```

#### Soluzioni

**Cleanup regolare:**
```bash
# Cleanup job più vecchi di 7 giorni
wp fpml queue cleanup --days=7 --states=done,skipped

# O configura retention automatica in Settings
```

**Optimize table:**
```sql
OPTIMIZE TABLE wp_fpml_queue;
```

---

## Comment Translation Issues

### I commenti non vengono tradotti

#### Sintomi
- Commenti su post IT non creano versioni EN
- Nessun commento tradotto visibile su `/en/` posts

#### Possibili Cause e Soluzioni

##### 1. Post Non Ha Traduzione EN

**Verifica:**
```bash
# Check if post has translation
wp post meta get <POST_ID> _fpml_pair_id
```

**Soluzione:** Traduci prima il post
1. Vai al post IT in editor
2. Click "🚀 Traduci in Inglese ORA" nel metabox
3. Attendi completamento traduzione
4. I nuovi commenti verranno tradotti automaticamente

##### 2. Commenti Annidati Perdono Gerarchia

**Sintomi:**
- Reply commenti non mantengono parent corretto
- Gerarchia commenti persa tra IT e EN

**Causa:** Parent comment non tradotto o validazione fallita

**Soluzione:**
1. Verifica che il parent comment sia stato tradotto
2. Controlla meta `_fpml_pair_id` sul parent comment
3. Il sistema valida automaticamente che il parent appartenga al post corretto

**Debug:**
```php
// Check parent comment translation
$parent_id = get_comment_meta( $comment_id, '_fpml_pair_id', true );
$parent_translation = get_comment( $parent_id );
```

---

## WooCommerce Issues

### Attributi Prodotto Non Tradotti

#### Sintomi
- Attributi custom mostrano testo originale invece di traduzione
- Placeholder `[PENDING TRANSLATION]` visibili (versione < 0.9.1)

#### Possibili Cause e Soluzioni

##### 1. Queue Non Processata

**Verifica:**
```bash
wp fpml queue status
# Check for pending jobs with field "meta:_product_attributes"
```

**Soluzione:** Esegui queue
```bash
wp fpml queue run --batch=50
```

##### 2. Attributi Custom Non Riconosciuti

**Verifica:** Attributi devono essere custom (get_id() === 0)

**Soluzione:** Il sistema accoda automaticamente attributi custom per traduzione. Verifica che:
1. Attributo sia custom (non taxonomy-based)
2. Queue sia attiva
3. OpenAI API key configurata

**Debug:**
```php
// Check if attributes are queued
$queue = FPML_Queue::instance();
$jobs = $queue->get_jobs( 'post', $product_id, 'meta:_product_attributes' );
```

##### 3. Hash Non Cambia (Attributi Non Aggiornati)

**Causa:** Hash attributi identico, job non accodato

**Soluzione:** Modifica attributi sul prodotto IT per triggerare nuovo hash

---

## Common Error Messages

### "Modalità assistita attiva"

**Significato:** WPML o Polylang rilevato, funzioni coda disabilitate.

**Se non desiderato:**
1. Disattiva WPML/Polylang
2. Riattiva FP Multilanguage

---

### "Impossibile creare la traduzione per il post"

**Cause:**
- Permission issues
- Database error
- Post type non supportato

**Debug:**
```bash
wp eval "
\$post = get_post(123); // Your post ID
\$result = FPML_Plugin::instance()->ensure_post_translation(\$post);
var_dump(\$result);
"
```

---

### "Nonce non valido" (REST API)

**Causa:** Nonce scaduto o mancante.

**Soluzione:**
```javascript
// In JavaScript, usa wp.apiFetch:
wp.apiFetch({
    path: '/fpml/v1/queue/run',
    method: 'POST'
}).then(response => {
    console.log(response);
});

// O includi nonce:
fetch('/wp-json/fpml/v1/queue/run', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});
```

---

## Debug Mode

### Attiva debug logging

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Logs salvati in: wp-content/debug.log
```

### Logs plugin specifici

```bash
# View last 50 plugin logs
wp eval "print_r(FPML_Logger::instance()->get_logs(50));"

# View only errors
wp eval "
\$logs = FPML_Logger::instance()->get_logs(100);
\$errors = array_filter(\$logs, function(\$l) { return \$l['level'] === 'error'; });
print_r(\$errors);
"
```

---

## Getting Help

Se i problemi persistono:

1. **Raccogli informazioni:**
```bash
wp fpml queue status > debug-info.txt
wp eval "print_r(FPML_Logger::instance()->get_logs(25));" >> debug-info.txt
wp --info >> debug-info.txt
```

2. **Apri issue su GitHub:**
   https://github.com/francescopasseri/FP-Multilanguage/issues

3. **Includi:**
   - Versione plugin
   - Versione WordPress
   - Provider usato
   - Debug info raccolto
   - Passi per riprodurre

---

## Maintenance Commands

### Pulizia completa (ATTENZIONE!)

```bash
# Backup prima!
wp db export backup.sql

# Clear queue
wp db query "TRUNCATE TABLE wp_fpml_queue"

# Clear logs
wp option delete fpml_logs

# Clear lock
wp option delete fpml_queue_lock

# Re-index
wp eval "print_r(FPML_Plugin::instance()->reindex_content());"
```

### Reset plugin (mantiene contenuti tradotti)

```bash
# 1. Clear queue and logs only
wp option delete fpml_logs
wp db query "TRUNCATE TABLE wp_fpml_queue"

# 2. Re-sync
wp eval "FPML_Plugin::instance()->reindex_content();"
```

---

**Last updated:** 2025-11-XX  
**Plugin version:** 0.9.1+  
**Maintainer:** Francesco Passeri
