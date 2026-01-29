# FP Multilanguage - Duplicate Code Audit

## Codice Duplicato Identificato

Questo documento elenca il codice duplicato identificato durante il refactoring. Questi elementi possono essere consolidati in futuro quando la compatibilità all'indietro non sarà più necessaria.

## 🔍 Settings Implementations

### Implementazioni Multiple

1. **`src/Settings.php`** - Implementazione principale (mantenuta)
2. **`src/Core/SimpleSettings.php`** - Implementazione semplificata (deprecata)
3. **`src/Core/SecureSettings.php`** - Implementazione con crittografia (deprecata)
4. **`src/Foundation/Options/Options.php`** - Nuova implementazione (attiva)

**Stato:** 
- `Settings.php` → Wrappato da `SettingsAdapter` che usa `Foundation\Options\Options`
- `SimpleSettings` e `SecureSettings` → Non più utilizzati, possono essere rimossi in futuro

**Azione Consigliata:**
- Mantenere `Settings.php` per compatibilità
- Rimuovere `SimpleSettings.php` e `SecureSettings.php` in versione futura (2.0+)

## 🔍 Cache Implementations

### Implementazioni Multiple

1. **`src/Core/TranslationCache.php`** - Cache specifica per traduzioni
2. **`src/Cache/TranslationCache.php`** - Duplicato (se esiste)
3. **`src/Foundation/Cache/TransientCache.php`** - Nuova implementazione generica

**Stato:**
- `TranslationCache` → Specifica per traduzioni, mantenere
- `Foundation\Cache\TransientCache` → Cache generica, usare per nuovi sviluppi

**Azione Consigliata:**
- Verificare se esiste duplicato in `src/Cache/`
- Consolidare in futuro se necessario

## 🔍 Container Implementations

### Implementazioni Multiple

1. **`src/Core/Container.php`** - Container legacy (statico)
2. **`src/Kernel/Container.php`** - Container PSR-11 (nuovo)
3. **`src/Core/ContainerBridge.php`** - Bridge per compatibilità

**Stato:**
- `Core\Container` → Mantenuto per compatibilità
- `Kernel\Container` → Container principale PSR-11
- `ContainerBridge` → Bridge tra vecchio e nuovo

**Azione Consigliata:**
- Mantenere tutti e tre per compatibilità
- Gradualmente migrare codice a `Kernel\Container`
- Rimuovere `Core\Container` in versione futura (2.0+)

## 🔍 Logger Implementations

### Implementazioni Multiple

1. **`src/Logger.php`** - Logger legacy (metodi statici)
2. **`src/Foundation/Logger/Logger.php`** - Logger PSR-3 (nuovo)
3. **`src/Foundation/Logger/LoggerAdapter.php`** - Adapter per compatibilità

**Stato:**
- `Logger.php` → Wrappato da `LoggerAdapter`
- `Foundation\Logger\Logger` → Implementazione principale
- `LoggerAdapter` → Mantiene compatibilità con metodi statici

**Azione Consigliata:**
- Mantenere tutti per compatibilità
- Gradualmente migrare a `LoggerInterface`
- Rimuovere `Logger.php` in versione futura (2.0+)

## 🔍 Helper Functions

### Funzioni Globali in `src/helpers.php`

**Funzioni Identificate:**
- `fpml_safe_update_post()` → Dovrebbe essere in `Core\Content\Post\SafePostUpdater`
- `fpml_get_current_language()` → Dovrebbe essere in `Language\LanguageResolver`
- `fpml_get_translation_id()` → Dovrebbe essere in `Content\TranslationManager`
- `fpml_is_translation()` → Dovrebbe essere in `Content\TranslationDetector`
- Altre 20+ funzioni helper

**Stato:**
- Funzioni mantenute per compatibilità
- Alcune già refactorizzate in classi

**Azione Consigliata:**
- Creare classi wrapper per ogni funzione
- Mantenere funzioni globali che chiamano i wrapper
- Rimuovere funzioni globali in versione futura (2.0+)

## 📋 Piano di Rimozione (Futuro)

### Versione 2.0+ (Breaking Changes)

1. **Rimuovere:**
   - `src/Core/SimpleSettings.php`
   - `src/Core/SecureSettings.php`
   - `src/Core/Container.php` (dopo migrazione completa)
   - `src/Logger.php` (dopo migrazione completa)
   - Funzioni globali in `helpers.php` (dopo migrazione completa)

2. **Consolidare:**
   - Tutte le implementazioni Settings → `Foundation\Options\Options`
   - Tutte le implementazioni Logger → `Foundation\Logger\Logger`
   - Tutte le implementazioni Container → `Kernel\Container`

3. **Mantenere:**
   - `SettingsAdapter` e `LoggerAdapter` per compatibilità estesa
   - `ContainerBridge` per compatibilità estesa
   - Legacy aliases per classi vecchie

## ⚠️ Note Importanti

- **NON rimuovere** codice duplicato finché non si è certi che non sia utilizzato
- **Testare** sempre dopo rimozione di codice legacy
- **Documentare** breaking changes nel changelog
- **Fornire** migration guide per sviluppatori

---

*Audit completato: Fase 4 - Identificazione duplicati*









