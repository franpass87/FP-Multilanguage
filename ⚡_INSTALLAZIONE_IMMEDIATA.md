# ⚡ INSTALLAZIONE IMMEDIATA

## 📦 FILE DA USARE

**`FP-Multilanguage-v0.4.1-DEFINITIVO.zip`**

## 🚀 INSTALLAZIONE IN 3 PASSI

### PASSO 1: Elimina il Vecchio
- Vai su **Plugin** nell'admin WordPress
- **Disattiva** FP Multilanguage (se attivo)
- **Elimina** FP Multilanguage

### PASSO 2: Installa il Nuovo
- Vai su **Plugin → Aggiungi nuovo**
- Clicca **Carica plugin**
- Seleziona **`FP-Multilanguage-v0.4.1-DEFINITIVO.zip`**
- Clicca **Installa ora**

### PASSO 3: Attiva
- Clicca **Attiva plugin**

## ✅ FATTO!

Il plugin dovrebbe attivarsi senza errori.

---

## ❓ SE HAI ANCORA ERRORE 500

### Possibile Causa: Manca vendor/autoload.php

Il file `vendor/autoload.php` potrebbe non essere nel pacchetto ZIP. 

**Verifica:**

1. Via FTP, controlla se esiste:
   ```
   /wp-content/plugins/FP-Multilanguage/vendor/autoload.php
   ```

2. Se **NON esiste**, devi installare le dipendenze Composer:

   **Via SSH:**
   ```bash
   cd /wp-content/plugins/FP-Multilanguage
   composer install --no-dev --optimize-autoloader
   ```

   **Senza SSH:** Scarica il progetto localmente, esegui `composer install`, poi carica tutto via FTP.

### Altre Cause Possibili

1. **PHP < 7.4**: Verifica la versione PHP del server
2. **Memory Limit**: Aumenta a 256M in wp-config.php
3. **Errore in un file**: Esegui diagnostica

---

## 🔍 DIAGNOSTICA

Esegui questo URL:
```
https://tuosito.com/wp-content/plugins/FP-Multilanguage/diagnostic.php?fpml_diag=check
```

**Inviami l'output completo** per analisi.

---

## 📝 COSA HO CAMBIATO

**VERSIONE ATTUALE (v0.4.1-DEFINITIVO):**

✅ **ZERO esecuzione immediata**
- Il file principale definisce SOLO funzioni
- NESSUN codice eseguito al caricamento
- Tutto tramite hooks WordPress

✅ **Caricamento on-demand**
- File caricati solo quando servono
- Prima in `plugins_loaded`
- Poi in attivazione (se necessario)

✅ **Massima compatibilità**
- Funziona anche se vendor manca
- Gestione errori robusta
- Compatibile tutti i server

---

## 🆘 SUPPORTO RAPIDO

**Se l'errore 500 persiste dopo l'installazione:**

1. ✅ Verifica `vendor/autoload.php` esiste
2. ✅ Esegui `diagnostic.php`
3. ✅ Abilita debug WordPress
4. ✅ Controlla `/wp-content/debug.log`
5. ✅ Inviami i log

---

**Prova subito e fammi sapere!** 🚀

