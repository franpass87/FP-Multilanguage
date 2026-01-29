# 📖 Come Funziona il Plugin - Guida Completa

## 🎯 **Comportamento Tipo WPML**

Il plugin **FP Multilanguage v0.5.0** funziona esattamente come WPML:

---

## 🌐 **Due Versioni del Sito**

### Versione Italiana (Default)
```
https://tuosito.it/              → Homepage IT
https://tuosito.it/chi-siamo/    → Pagina IT
https://tuosito.it/blog/post-1/  → Post IT
```

### Versione Inglese (Prefisso /en/)
```
https://tuosito.it/en/           → Homepage EN
https://tuosito.it/en/chi-siamo/ → Pagina EN (stesso slug!)
https://tuosito.it/en/blog/post-1/ → Post EN (stesso slug!)
```

**IMPORTANTE**: Non sono directory fisiche, ma **URL virtuali** gestiti da WordPress rewrites.

---

## 🔄 **Processo di Traduzione**

### Scenario 1: Nuovo Post

1. **Crei post italiano** in `/wp-admin/post-new.php`
2. **Salvi come Pubblicato**
3. **Plugin automaticamente**:
   - Crea post inglese (status: publish)
   - Imposta slug uguale
   - Inserisce placeholder content
   - Aggiunge a queue di traduzione
4. **Queue processa** (entro 5 minuti):
   - Traduce titolo
   - Traduce contenuto
   - Traduce excerpt
   - Aggiorna post EN

### Scenario 2: Forza Traduzione Immediata

1. **Modifica post** italiano
2. **Sidebar destra** → Metabox "🌍 Traduzioni"
3. **Click bottone** "🚀 Traduci in Inglese ORA"
4. **Traduzione immediata** (sync, no coda)
5. **Feedback** con toast notification

### Scenario 3: Modifica Post Esistente

1. **Modifichi contenuto** post IT
2. **Salvi**
3. **Plugin automaticamente**:
   - Rileva differenze con hash MD5
   - Aggiunge solo parti cambiate a queue
   - Processa incrementalmente

---

## 🎛️ **Admin Bar Switcher**

### In Frontend
```
+---------------------------+
| Toolbar WP                |
| ...                       |
| [🇮🇹 Italiano ▼]          | ← Click qui
|   ✓ 🇮🇹 Italiano (corrente)|
|     🇬🇧 English           |
+---------------------------+
```

Click su "English" → Vai alla versione EN dello stesso contenuto

### In Admin (Editor)
```
+---------------------------+
| [🇮🇹 Italiano ▼]          |
|   ✓ 🇮🇹 Italiano (corrente)|
|     🇬🇧 English           |
|   ✏️ Modifica Traduzione EN|  ← Link diretto all'editor EN
+---------------------------+
```

---

## 📝 **Metabox Traduzioni nell'Editor**

### Quando NON è tradotto:
```
+---------------------------+
| 🌍 Traduzioni             |
+---------------------------+
| ⚪ Non Tradotto            |
| Clicca "Traduci ORA" per  |
| creare la versione inglese|
|                           |
| [🚀 Traduci in Inglese ORA]| ← Bottone grande
+---------------------------+
```

### Quando è tradotto:
```
+---------------------------+
| 🌍 Traduzioni             |
+---------------------------+
| ✓ Traduzione Completata   |
| Aggiornato: 2 ore fa      |
|                           |
| [🇬🇧 Visualizza Inglese]  |
| [✏️ Modifica Inglese]      |
| [🔄 Ritraduci ORA]         |
+---------------------------+
```

### Quando è in coda:
```
+---------------------------+
| 🌍 Traduzioni             |
+---------------------------+
| ⏳ Traduzione in Corso... |
|                           |
| [⏳ In coda di traduzione]|
| Il contenuto sarà tradotto|
| nei prossimi minuti.      |
+---------------------------+
```

---

## 🔧 **Setup Iniziale (IMPORTANTE!)**

### Passo 1: Attivazione Plugin
```bash
1. Vai su /wp-admin/plugins.php
2. Attiva "FP Multilanguage"
3. ✅ Rewrites /en/ configurati automaticamente
```

### Passo 2: Configurazione
```bash
1. Vai su FP Multilanguage → Settings
2. Tab "General"
3. Inserisci OpenAI API Key
4. Salva impostazioni
```

### Passo 3: Verifica Rewrites
```bash
# Vai su Settings → Permalinks
# Click "Salva modifiche" (anche senza cambiare nulla)
# Questo forza il flush dei rewrites
```

### Passo 4: Test
```bash
1. Crea un post "Test Traduzione"
2. Pubblica
3. Sidebar → Metabox "🌍 Traduzioni"
4. Click "🚀 Traduci in Inglese ORA"
5. Aspetta 10-30 secondi
6. Toast: "✓ Traduzione completata!"
7. Click "🇬🇧 Visualizza Inglese"
8. URL sarà: https://tuosito.it/en/test-traduzione/
```

---

## ❓ **Domande Frequenti**

### Q: Perché non vedo la cartella /en/ nell'FTP?
**A**: NON è una cartella fisica! È un URL virtuale gestito da WordPress rewrites (come `/category/` o `/tag/`).

### Q: Come forzo la traduzione di un post?
**A**: Editor post → Sidebar → Metabox "🌍 Traduzioni" → Bottone "🚀 Traduci ORA"

### Q: Quanto tempo ci vuole?
**A**: 
- **Sync (immediata)**: 10-30 secondi per post
- **Queue (asincrona)**: 5-15 minuti (processa automaticamente)

### Q: Come vedo la versione EN da admin?
**A**: Admin Bar (top) → Click su "🇬🇧 English" o "✏️ Modifica Traduzione EN"

### Q: Posso modificare manualmente la traduzione?
**A**: SÌ! Click "Modifica Inglese" e edita normalmente. Le modifiche manuali vengono preservate.

### Q: Come funziona la queue?
**A**: 
1. Salvi post IT → Aggiunto a queue
2. WP-Cron processa ogni 5 minuti
3. Oppure: `wp fpml queue run` (manuale)
4. Oppure: Bottone "Traduci ORA" (immediato)

### Q: Gli URL /en/ non funzionano!
**A**: 
```bash
# Soluzione:
1. Vai su Impostazioni → Permalinks
2. Click "Salva modifiche"
3. Prova di nuovo
```

---

## 🎨 **Customizzazioni**

### Cambia Routing Mode
```php
// wp-admin → FP Multilanguage → Settings → General
Routing Mode:
- "Segment" (default): /en/post-slug/
- "Query String": ?lang=en
```

### Disabilita Auto-Translate
```php
// wp-admin → FP Multilanguage → Settings → Content
[ ] Auto-translate on publish
```

### Escludi Post Types
```php
// functions.php del tema
add_filter('fpml_translatable_post_types', function($types) {
    return array('post', 'page'); // Solo post e pagine
});
```

---

## 🚀 **Workflow Consigliato**

### Per Blog/News
1. Scrivi articolo italiano
2. Pubblica
3. Lascia che la queue traduca automaticamente (5-15 min)
4. Rivedi traduzione EN se necessario

### Per Siti E-commerce
1. Crea prodotto WooCommerce italiano
2. Metabox → "🚀 Traduci ORA" (immediato)
3. Verifica traduzione
4. Pubblica

### Per Landing Page
1. Crea pagina con page builder
2. "🚀 Traduci ORA" (immediato)
3. Modifica EN manualmente per SEO
4. Pubblica entrambe

---

## 🔗 **Link Utili**

- **Settings**: `/wp-admin/admin.php?page=fpml-settings`
- **Bulk Translation**: `/wp-admin/admin.php?page=fpml-bulk-translate`
- **Diagnostics**: `/wp-admin/admin.php?page=fpml-settings&tab=diagnostics`

---

## 📞 **Supporto**

Se il routing /en/ non funziona:
1. Disattiva e riattiva il plugin
2. Settings → Permalinks → Salva
3. Prova: https://tuosito.it/en/
4. Dovrebbe mostrare homepage inglese

Se ancora non funziona:
- GitHub: https://github.com/francescopasseri/FP-Multilanguage/issues
- Email: info@francescopasseri.com

---

**Happy Translating!** 🌍🚀

