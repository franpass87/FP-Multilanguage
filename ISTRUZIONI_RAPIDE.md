# ⚡ ISTRUZIONI RAPIDE - Fix Errore 500

## 📦 USA QUESTO PACCHETTO

**File**: `FP-Multilanguage-SAFE.zip` ← NUOVO! 🆕

## 🚀 COME INSTALLARE

### Metodo 1: Via WordPress Admin (PIÙ SEMPLICE)

1. ✅ Disattiva il plugin corrente
2. ✅ Elimina il plugin dall'admin
3. ✅ Vai su **Plugin → Aggiungi nuovo → Carica plugin**
4. ✅ Carica `FP-Multilanguage-SAFE.zip`
5. ✅ Clicca **Installa ora**
6. ✅ Clicca **Attiva**

### Metodo 2: Via FTP

1. ✅ Disattiva il plugin
2. ✅ Estrai `FP-Multilanguage-SAFE.zip`
3. ✅ Carica la cartella `fp-multilanguage` via FTP in:
   ```
   /wp-content/plugins/FP-Multilanguage/
   ```
4. ✅ Sovrascrivi tutti i file
5. ✅ Attiva il plugin dall'admin

## ❓ SE HAI ANCORA ERRORE 500

### 1. Abilita il Debug

Modifica `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 2. Esegui Diagnostica

Vai su:
```
https://tuosito.com/wp-content/plugins/FP-Multilanguage/diagnostic.php?fpml_diag=check
```

### 3. Inviami

- Output della diagnostica
- Contenuto di `/wp-content/debug.log`

## 🔧 COSA HO CAMBIATO

**Prima**: Il plugin caricava tutto IMMEDIATAMENTE → CRASH  
**Dopo**: Il plugin carica tutto DOPO che WordPress è pronto → SICURO

## ✅ QUESTA VERSIONE È:

- ✅ Completamente sicura durante l'attivazione
- ✅ Carica i file solo quando WordPress è pronto
- ✅ Ha controlli di sicurezza su tutte le funzioni
- ✅ Gestisce gli errori senza bloccare il sito

## 📋 CHECKLIST

Prima di attivare, verifica che:
- [ ] PHP versione 7.4 o superiore
- [ ] Memory limit almeno 128MB
- [ ] La cartella `vendor` esista nel plugin
- [ ] I permessi siano corretti (644 per file, 755 per cartelle)

---

## 💡 IN BREVE

1. 📥 Scarica `FP-Multilanguage-SAFE.zip`
2. 🗑️ Elimina il plugin vecchio
3. ⬆️ Carica il nuovo
4. ✅ Attiva
5. 🎉 Funziona!

---

**Supporto**: Se hai problemi, esegui `diagnostic.php` e inviami l'output

*File: FP-Multilanguage-SAFE.zip*

