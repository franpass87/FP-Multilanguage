# ⚠️ IMPORTANTE - Leggi Prima di Testare!

## 🚨 **AZIONE RICHIESTA**

Per attivare il routing `/en/` **DEVI**:

### 1. Disattiva e Riattiva il Plugin
```
/wp-admin/plugins.php
→ Disattiva "FP Multilanguage"
→ Attiva "FP Multilanguage"
```

Questo forza il **flush dei rewrites** necessario per /en/

### 2. Flush Permalinks (Opzionale ma raccomandato)
```
/wp-admin/options-permalink.php
→ Click "Salva modifiche" (anche senza cambiare nulla)
```

---

## ✅ **Dopo il Flush, Testa:**

### Test 1: Homepage EN
```
Vai su: https://tuosito.local/en/
Dovrebbe mostrare homepage in inglese
```

### Test 2: Admin Bar Switcher
```
1. Apri una pagina qualsiasi del sito
2. Guarda toolbar in alto
3. Dovresti vedere: [🇮🇹 Italiano ▼]
4. Click → Vedi dropdown IT | EN
```

### Test 3: Traduci un Post
```
1. Crea nuovo post "Test Traduzione"
2. Pubblica
3. Sidebar destra → Metabox "🌍 Traduzioni"
4. Click "🚀 Traduci in Inglese ORA"
5. Aspetta 10-30 secondi
6. Toast notification: "✓ Traduzione completata!"
7. Click "🇬🇧 Visualizza Inglese"
8. URL: https://tuosito.local/en/test-traduzione/
```

### Test 4: Shortcode
```
1. Crea pagina "Test Switcher"
2. Aggiungi: [fpml_language_switcher style="flags"]
3. Pubblica
4. Visualizza → Dovresti vedere: 🇮🇹 🇬🇧
```

---

## 🐛 **Se /en/ NON Funziona**

### Errore 404 su /en/
**Causa**: Rewrites non flushed
**Soluzione**: Disattiva/riattiva plugin + Settings → Permalinks → Salva

### Post EN non creato
**Causa**: Queue non attiva
**Soluzione**: Click bottone "Traduci ORA" nel metabox

### Traduzione non appare
**Causa**: OpenAI non configurato
**Soluzione**: Settings → Inserisci OpenAI API key

---

## 📊 **Features Principali**

### 🎛️ Admin Bar Switcher
- Top toolbar: IT | EN
- Click per cambiare lingua
- Link modifica traduzione

### 📝 Metabox Traduzioni
- Sidebar editor post
- Status traduzione chiaro
- Bottoni visualizza/modifica/ritraduci

### 📦 Bulk Translation
- Menu → Bulk Translation
- Seleziona 100 post
- Traduci tutti insieme

### 👁️ Preview Inline
- Editor post → Bottone "Anteprima"
- Modal IT | EN side-by-side

### 🔄 Shortcode
```
[fpml_language_switcher style="dropdown"]
[fpml_language_switcher style="flags"]
[fpml_language_switcher style="links"]
```

---

## 🎯 **Ultime Modifiche (v0.9.1+)**

### Commenti Annidati
- `src/Core/Plugin.php` - Gestione completa commenti threaded con mapping parent
- Supporto gerarchia commenti multi-livello tra lingue
- Validazione parent comment automatica

### Attributi WooCommerce
- `src/Integrations/WooCommerceSupport.php` - Queue-based translation per attributi
- Rimossi placeholder `[PENDING TRANSLATION]`
- Sistema traduzione integrato con queue esistente

### File Modificati Precedenti
| File | Modifica |
|------|----------|
| `fp-multilanguage.php` | + Flush rewrites hook |
| `src/Admin/AdminBarSwitcher.php` | ✨ NUOVO switcher admin bar |
| `src/Admin/TranslationMetabox.php` | ✨ NUOVO metabox chiaro |
| `src/LanguageSwitcherWidget.php` | + Shortcode |

---

## 📞 **Supporto**

Se hai problemi:
1. Controlla debug.log: `/wp-content/debug.log`
2. Vai su Diagnostics: `/wp-admin/admin.php?page=fpml-settings&tab=diagnostics`
3. Click "Test Provider" per verificare OpenAI
4. GitHub: https://github.com/francescopasseri/FP-Multilanguage/issues

---

**ORA PROCEDI**: Disattiva e riattiva il plugin! 🚀

