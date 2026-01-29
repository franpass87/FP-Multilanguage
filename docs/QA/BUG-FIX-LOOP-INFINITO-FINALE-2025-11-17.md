# 🐛 Bug Fix: Loop Infinito - Soluzione Finale - 2025-11-17

## 🔴 Problema Identificato

**Errore**: HTTP 500 / Errore Critico durante la pubblicazione di una nuova pagina

**Causa**: Loop infinito causato da hook `save_post`, `transition_post_status`, `publish_post` che si triggerano ripetutamente

**Sintomi**:
- Memoria esaurita (Allowed memory size exhausted)
- `on_publish` chiamato ripetutamente per lo stesso post (centinaia di volte)
- Server che va in timeout

## 🔍 Analisi Dettagliata

Il problema si verificava perché:

1. Utente pubblica una pagina → `save_post` viene triggerato
2. `handle_save_post` chiama `ensure_post_translation`
3. `ensure_post_translation` crea una nuova pagina tradotta usando `wp_insert_post` con status `publish`
4. `wp_insert_post` con status `publish` triggera:
   - `save_post` → loop infinito
   - `transition_post_status` → loop infinito
   - `publish_post` → loop infinito
   - Altri hook di altri plugin (FP-SEO-AutoIndex, WooCommerce, ecc.)
5. Loop infinito → memoria esaurita → errore 500

## ✅ Soluzione Finale Implementata

### 1. Creazione come 'draft'
La traduzione viene sempre creata come `draft` per evitare che gli hook di pubblicazione vengano triggerati:

```php
'post_status' => 'draft', // Sempre draft per evitare loop infiniti
```

### 2. Disabilitazione Temporanea Hook
Durante la creazione vengono temporaneamente disabilitati tutti gli hook critici:

```php
$hooks_to_disable = array( 
    'save_post', 
    'transition_post_status', 
    'publish_post', 
    'wp_insert_post_data',
    'publish_page'
);
```

### 3. Aggiornamento Status Diretto nel Database
Dopo la creazione, se il post originale era pubblicato, lo status viene aggiornato direttamente nel database senza triggerare hook:

```php
$wpdb->update(
    $wpdb->posts,
    array( 'post_status' => 'publish' ),
    array( 'ID' => $target_id ),
    array( '%s' ),
    array( '%d' )
);
```

### 4. Protezione Multipla
- Flag statico `$processing` per evitare doppia elaborazione
- Flag globale `$GLOBALS['fpml_creating_translation']` per saltare completamente durante la creazione
- Controllo `is_creating_translation()` come backup
- Ripristino hook anche in caso di errore

## 📝 File Modificati

1. **`src/Core/Plugin.php`**:
   - Aggiunto flag statico `$processing` in `handle_save_post`
   - Aggiunto controllo flag globale `$GLOBALS['fpml_creating_translation']`
   - Cleanup del flag alla fine del metodo

2. **`src/Content/TranslationManager.php`**:
   - Creazione sempre come `draft`
   - Disabilitazione temporanea di tutti gli hook critici
   - Aggiornamento status direttamente nel database
   - Ripristino hook anche in caso di errore

## ✅ Risultato

- ✅ Loop infinito risolto
- ✅ Memoria non più esaurita
- ✅ Pagine possono essere create senza errori 500
- ✅ Traduzioni vengono create correttamente
- ✅ Status pubblicato viene mantenuto senza triggerare hook

---

**Data Fix**: 2025-11-17  
**Status**: ✅ **RISOLTO DEFINITIVAMENTE**


