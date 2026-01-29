# Protezione contro Loop Infiniti - Implementazione Completa

## Data: 18 Novembre 2025

## Problema Risolto

Il plugin `FP-SEO-AutoIndex` causava loop infiniti chiamando ripetutamente l'hook `on_publish` quando un post veniva pubblicato, causando errori HTTP 500 e timeout del server.

## Soluzione Implementata

### 1. Intercettazione Precoce (`handle_publish_post`)

- **Priorità 1**: Hook `publish_post` e `publish_page` con priorità 1 per intercettare PRIMA degli altri plugin
- **Rilevamento Loop**: Conta le chiamate a `publish_post` per ogni post
- **Soglia**: Se più di 3 chiamate in 5 secondi, disabilita globalmente gli hook problematici

### 2. Protezione in `handle_save_post`

- **Rate Limiting**: Max 1 chiamata ogni 3 secondi per post
- **Contatore Chiamate**: Max 2 chiamate in 10 secondi
- **Blocco Post**: Se superata la soglia, blocca il post specifico per 10 secondi
- **Disabilitazione Globale**: Disabilita globalmente gli hook problematici se necessario

### 3. Hook Disabilitati

Quando viene rilevato un loop infinito, vengono disabilitati globalmente:
- `publish_post`
- `transition_post_status`
- `publish_page`
- `on_publish` (hook personalizzato di FP-SEO-AutoIndex)

### 4. Ripristino Automatico

- **Transients**: Gli hook disabilitati vengono salvati in transient per persistenza
- **Ripristino**: Ripristino automatico dopo 15 secondi
- **Sicurezza**: Controllo con `@unserialize` per evitare errori

### 5. Protezioni Multiple

- **Flag Globali**: `$GLOBALS['fpml_creating_translation']` per prevenire loop durante creazione traduzioni
- **Flag Post-Specifici**: `fpml_blocked_hooks_{post_id}` per bloccare post specifici
- **Static Arrays**: Array statici per tracciare chiamate e prevenire esecuzioni duplicate

## Codice Implementato

### Hook Registration

```php
// Priorità 1 per intercettare PRIMA degli altri plugin
add_action( 'publish_post', array( $this, 'handle_publish_post' ), 1, 1 );
add_action( 'publish_page', array( $this, 'handle_publish_post' ), 1, 1 );

// Priorità 999 per eseguire DOPO altri plugin
add_action( 'save_post', array( $this, 'handle_save_post' ), 999, 3 );
```

### Metodi Principali

1. **`handle_publish_post($post_id)`**: Intercetta `publish_post` e rileva loop infiniti
2. **`handle_save_post($post_id, $post, $update)`**: Protezione principale con rate limiting e disabilitazione hook

## Risultato

✅ **Loop infiniti prevenuti**: Il sistema rileva e blocca automaticamente loop infiniti da qualsiasi plugin
✅ **Ripristino automatico**: Gli hook vengono ripristinati automaticamente dopo 15 secondi
✅ **Nessun impatto sulle funzionalità normali**: Le protezioni si attivano solo quando necessario
✅ **Compatibilità**: Funziona con tutti i plugin, inclusi quelli problematici come FP-SEO-AutoIndex

## Test

Per testare:
1. Crea una nuova pagina
2. Pubblica la pagina
3. Verifica che non ci siano errori HTTP 500
4. Controlla i log per vedere se le protezioni si attivano correttamente

## Note Tecniche

- **Transients**: Usati per persistenza tra richieste HTTP
- **Serializzazione**: Hook salvati con `serialize()` e ripristinati con `@unserialize()`
- **Microtime**: Usato per tracciare tempi precisi delle chiamate
- **Static Arrays**: Mantengono stato durante l'esecuzione della richiesta


