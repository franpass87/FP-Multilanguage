# Migrazione a PSR-4 - v0.5.0

## 📋 Riepilogo

Il plugin **FP-Multilanguage** è stato completamente refactorizzato da classmap a **PSR-4**.

## 🏗️ Struttura Namespace

```
FP\Multilanguage\
├── Core\               (Container, Plugin, Settings, Cache, Versioning)
├── Content\            (ContentIndexer, TranslationManager)
├── Translation\        (JobEnqueuer)
├── Providers\          (TranslatorInterface, ProviderGoogle, ProviderOpenAI, BaseProvider)
├── Diagnostics\        (Diagnostics, CostEstimator)
├── Admin\              (Admin)
├── Rest\               (RestAdmin)
├── CLI\                (CLI, QueueCommand)
└── [Root]              (Settings, Logger, Queue, Processor, Language, etc.)
```

## 📦 Composer Autoload

**composer.json:**
```json
{
    "autoload": {
        "psr-4": {
            "FP\\Multilanguage\\": "src/"
        }
    }
}
```

## ✅ Compatibilità Backward

Il file `src/compatibility.php` fornisce **alias** per tutte le vecchie classi:

```php
FPML_Container       → FP\Multilanguage\Core\Container
FPML_Plugin          → FP\Multilanguage\Core\Plugin
FPML_Settings        → FP\Multilanguage\Settings
FPML_Logger          → FP\Multilanguage\Logger
// ... tutti gli alias
```

## 🔧 Modifiche Principali

### 1. **File Principale** (`fp-multilanguage.php`)
- Rimosso autoload manuale
- Aggiunto `require vendor/autoload.php`
- Aggiunto `require src/compatibility.php`
- Usati namespace moderni con `use` statements

### 2. **Tutte le Classi**
- Aggiunto `namespace FP\Multilanguage\...`
- Nomi classi senza prefisso `FPML_`
- File rinominati senza `class-` prefix

### 3. **Classi WordPress**
- Tutte le classi WordPress con backslash globale: `\WP_Error`, `\WP_Query`, `\WP_Widget`

### 4. **Alias Backward**
- Tutti i riferimenti a `FPML_*` dentro namespace usano `\FPML_*` (namespace globale)
- Gli alias garantiscono compatibilità con codice esistente

## 📁 Struttura File

```
FP-Multilanguage/
├── fp-multilanguage.php        (file principale v0.5.0)
├── src/                        (PSR-4 - 48 classi)
│   ├── Core/
│   ├── Content/
│   ├── Translation/
│   ├── Providers/
│   ├── Diagnostics/
│   ├── Admin/
│   ├── Rest/
│   ├── CLI/
│   └── compatibility.php
├── admin/views/                (template admin ancora necessari)
├── assets/                     (CSS/JS)
├── vendor/                     (Composer autoload)
├── composer.json               (PSR-4)
└── [altri file config/docs]
```

## 🚀 Benefici

1. ✅ **Standard moderno**: PSR-4 compliance
2. ✅ **Autoload ottimizzato**: Composer gestisce tutto
3. ✅ **Namespace puliti**: No più conflitti globali
4. ✅ **Manutenibilità**: Struttura organizzata
5. ✅ **Backward compatible**: Codice esistente funziona
6. ✅ **48 classi** caricate automaticamente

## ⚠️ Note per Sviluppatori

- **NON usare più** `require_once` per le classi PSR-4
- **Usare sempre** `use` statements all'inizio del file
- **Classi WordPress**: Sempre con `\` davanti (es: `\WP_Error`)
- **Alias FPML_**: Disponibili ma deprecati, usare namespace moderno

## 🎯 Versione

- **Prima**: v0.4.1 (classmap)
- **Dopo**: v0.5.0 (PSR-4)

---

**Data**: 26 Ottobre 2025  
**Autore**: Francesco Passeri

