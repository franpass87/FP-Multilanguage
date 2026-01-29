# 🚨 PROBLEMA CRITICO - Sistema di Routing /en/

## Problema Rilevato

L'utente ha testato il plugin e:
1. ❌ NON crea directory `/en/`
2. ❌ NON traduce i contenuti
3. ❌ NON è chiaro come forzare la traduzione

## Root Cause Analysis

### 1. **Rewrites Non Flushed**
```php
// src/Rewrites.php line 61-81
// Registra regole ma NON fa flush_rewrite_rules()
add_rewrite_rule('^en/?$', 'index.php?\FPML_lang=en', 'top');
```

**Problema**: Le regole non vengono mai scritte in `.htaccess` o nel database WordPress

---

### 2. **Processo Traduzione Non Chiaro**

**Attuale flusso**:
1. Utente salva post IT
2. Plugin aggiunge a queue (forse?)
3. Queue processa in background (quando?)
4. Traduzione appare (mai?)

**Problema**: 
- Nessun feedback visivo
- Nessun bottone "Traduci ORA"
- Utente non sa se funziona

---

### 3. **Manca Admin Bar Switcher**

WPML ha: IT | EN nell'admin bar
FP-Multilanguage: ❌ Niente

**Problema**: Impossibile vedere versione EN dall'admin

---

## Soluzione Richiesta

### Comportamento Desiderato (stile WPML)

1. **Due "versioni" del sito**:
   - IT: `example.com/post-italiano/`
   - EN: `example.com/en/post-italiano/` (stesso slug!)

2. **Admin Bar Switcher**:
   ```
   [ IT | EN ]
   ```
   Click su EN → Vai alla versione inglese dello stesso post

3. **Editor Post**:
   ```
   +---------------------------+
   | Post Title (IT)           |
   +---------------------------+
   | [ Traduci in Inglese ORA ]| ← BOTTONE CHIARO
   +---------------------------+
   | Content...                |
   +---------------------------+
   
   Sidebar:
   +---------------------------+
   | 🌍 Traduzioni             |
   +---------------------------+
   | EN: [ Visualizza | Modifica ] ← Link diretto
   | Status: ✓ Tradotto         |
   | Ultimo aggiornamento: oggi |
   +---------------------------+
   ```

4. **Automatico su Publish**:
   - Salvo post IT → Crea automaticamente post EN (placeholder)
   - Queue processa → Traduce contenuto
   - Utente vede "✓ Traduzione completata"

---

## Fix Necessari

### 1. Flush Rewrites Hook
```php
// fp-multilanguage.php
register_activation_hook(__FILE__, 'fpml_flush_rewrites');

function fpml_flush_rewrites() {
    \FPML_Rewrites::instance()->register_rewrites();
    flush_rewrite_rules();
}
```

### 2. Admin Bar Switcher
```php
// src/Admin/AdminBarSwitcher.php (NUOVO)
add_action('admin_bar_menu', function($wp_admin_bar) {
    // Aggiungi: IT | EN con link versione corrente
}, 999);
```

### 3. Metabox Traduzioni Chiaro
```php
// src/Admin/TranslationMetabox.php
// - Status traduzione
// - Bottone "Traduci ORA"
// - Link "Visualizza EN" | "Modifica EN"
// - Progress bar se in coda
```

### 4. Auto-Create on Publish
```php
// src/Core/Plugin.php - handle_save_post()
// Quando post IT va in publish:
// 1. Crea subito post EN (placeholder)
// 2. Aggiunge a queue
// 3. Mostra admin notice "Traduzione in corso..."
```

---

## Implementazione Prioritaria

ORDINE:
1. ✅ Flush rewrites su attivazione
2. ✅ Admin Bar Switcher
3. ✅ Metabox traduzioni chiaro
4. ✅ Auto-create + queue su publish
5. ✅ Admin notice feedback

Tempo stimato: 3-4 ore

---

**QUESTO È CRITICO** - Il plugin non funziona senza questi fix!

