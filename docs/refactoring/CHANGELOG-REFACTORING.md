# Changelog - Refactoring Strutturale v1.0.0

**Data**: 2025-01-XX

---

## 🎉 Nuove Funzionalità

### Hook Handlers Dedicati
- ✅ `PostHooks` - Gestisce tutti gli hook sui post
- ✅ `TermHooks` - Gestisce tutti gli hook sui termini
- ✅ `CommentHooks` - Gestisce tutti gli hook sui commenti
- ✅ `WidgetHooks` - Gestisce tutti gli hook sui widget
- ✅ `AttachmentHooks` - Gestisce tutti gli hook sugli attachment

### PluginFacade
- ✅ Nuova classe `Core\Services\PluginFacade` per incapsulare operazioni complesse
- ✅ Gestisce reindex, diagnostics, cost estimation

### Service Providers
- ✅ `PluginServiceProvider` - Gestisce setup e assisted mode

---

## 🔄 Modifiche

### Architettura
- ✅ Sistema Kernel unificato (solo `Kernel\Plugin`)
- ✅ Container unificato (solo `Kernel\Container`)
- ✅ `Core\Container` convertito in adapter
- ✅ Routing consolidato (`Routing\*` → `Frontend\Routing\*`)

### Dependency Injection
- ✅ 9 classi core convertite per DI:
  - Settings
  - Logger
  - Queue
  - TranslationManager
  - JobEnqueuer
  - ContentIndexer
  - MenuSync
  - Glossary
  - CostEstimator

### Codice
- ✅ Plugin.php ridotto di ~230 righe
- ✅ Zero duplicazioni
- ✅ Log di debug rimossi
- ✅ Codice più pulito e organizzato

---

## ⚠️ Deprecazioni

### Classi Deprecate
- `Core\Plugin` - Usa `Kernel\Plugin` invece
- `Core\Container` - Usa `Kernel\Container` invece
- `ContentHandlers` - Usa hook handlers dedicati invece

### Metodi Deprecati
- `Settings::instance()` - Usa DI via container
- `Logger::instance()` - Usa DI via container
- `Queue::instance()` - Usa DI via container
- `TranslationManager::instance()` - Usa DI via container
- `JobEnqueuer::instance()` - Usa DI via container
- `ContentIndexer::instance()` - Usa DI via container
- `MenuSync::instance()` - Usa DI via container
- `Glossary::instance()` - Usa DI via container
- `CostEstimator::instance()` - Usa DI via container

**Nota**: Tutti i metodi deprecati sono ancora funzionanti ma mostrano deprecation notice. Verranno rimossi in v1.1.0.

---

## 🐛 Correzioni

- ✅ Rimossi log di debug da `get_diagnostics_snapshot()`
- ✅ Corretto namespace di `CostEstimator` in `PluginFacade`
- ✅ Aggiornati tutti i riferimenti per usare namespace corretti

---

## 📝 Note per Sviluppatori

### Migrazione
Vedi `MIGRATION-GUIDE.md` per dettagli su come migrare il codice.

### Backward Compatibility
Tutte le modifiche mantengono backward compatibility. Il codice esistente continua a funzionare.

### Breaking Changes
**Nessun breaking change in v1.0.0!**

Breaking changes previsti in v1.1.0:
- Rimozione di `Core\Plugin`
- Rimozione di `Core\Container`
- Rimozione di metodi `instance()` su classi core

---

## 📊 Statistiche

- **File creati**: 7 nuovi file
- **File modificati**: 40+ file
- **Righe di codice nuovo**: ~800 righe
- **Righe di codice refactorizzato**: ~300 righe
- **Plugin.php ridotto**: -230 righe (-16%)
- **Classi singleton convertite**: 9/9 (100%)

---

## ✅ Testing

Prima di usare in produzione, testare:
- ✅ Traduzione post/term/comment/attachment
- ✅ Queue processing
- ✅ Admin interface
- ✅ Frontend routing (/en/)
- ✅ Backward compatibility

---

**Versione**: 1.0.0  
**Data**: 2025-01-XX

