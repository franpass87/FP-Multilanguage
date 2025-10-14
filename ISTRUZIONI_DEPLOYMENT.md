# 🚀 Istruzioni per il Deployment - FP Multilanguage

## ✅ Problema Risolto

Il problema `Class "FPML_Plugin_Core" not found` è stato risolto modificando l'ordine di caricamento delle classi.

## 📦 File Modificati

1. **fp-multilanguage/fp-multilanguage.php**
   - Caricamento esplicito dei file core prima degli altri
   - Hook di attivazione più robusti
   - Gestione errori migliorata

2. **fp-multilanguage/diagnostic.php** (NUOVO)
   - Script diagnostico per debug sul server

## 🔧 Passi per il Deployment

### 1. Carica il Plugin sul Server

Hai due opzioni:

#### Opzione A: Upload via FTP/SFTP
1. Scarica il file: **`FP-Multilanguage-202510140921.zip`**
2. Estrai il contenuto localmente
3. Carica la cartella `fp-multilanguage` sul server in:
   ```
   /wp-content/plugins/FP-Multilanguage/
   ```
4. **IMPORTANTE:** Sovrascrivi tutti i file esistenti

#### Opzione B: Upload via WordPress Admin
1. Disattiva il plugin corrente (se attivo)
2. Elimina il plugin via WordPress Admin
3. Vai su **Plugin → Aggiungi nuovo → Carica plugin**
4. Carica il file **`FP-Multilanguage-202510140921.zip`**
5. Attiva il plugin

### 2. Verifica l'Attivazione

1. Vai su **Plugin** nell'admin di WordPress
2. Attiva **FP Multilanguage**
3. Se vedi la schermata admin senza errori: **✅ SUCCESSO!**

### 3. Se Ricevi Ancora Errore 500

Se l'attivazione causa ancora un errore 500, esegui lo script diagnostico:

1. Accedi tramite browser a:
   ```
   https://tuosito.com/wp-content/plugins/FP-Multilanguage/diagnostic.php?fpml_diag=check
   ```

2. Lo script mostrerà informazioni dettagliate su:
   - Versione PHP
   - Struttura delle directory
   - File presenti/mancanti
   - Test di caricamento delle classi
   - Configurazione del server

3. **Inviami l'output completo** dello script diagnostico per ulteriore assistenza

### 4. Possibili Cause di Errore Residuo

Se dopo il deployment ci sono ancora problemi, potrebbe essere dovuto a:

#### A. Dipendenze Composer Mancanti
Se il file `vendor/autoload.php` non esiste sul server:

1. Accedi via SSH al server
2. Vai nella directory del plugin:
   ```bash
   cd /homepages/20/d4299220163/htdocs/clickandbuilds/ViterboAntica/wp-content/plugins/FP-Multilanguage/
   ```
3. Esegui:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

#### B. Permessi File Errati
Verifica che i permessi siano corretti:
```bash
chmod 755 wp-content/plugins/FP-Multilanguage
chmod 644 wp-content/plugins/FP-Multilanguage/*.php
```

#### C. Versione PHP Troppo Vecchia
Il plugin richiede **PHP 7.4+**. Verifica la versione PHP del server.

## 📝 Note Tecniche

### Modifiche al Sistema di Autoload

Il nuovo sistema carica i file in questo ordine:

1. **File Core** (caricati esplicitamente):
   - `includes/core/class-container.php`
   - `includes/core/class-plugin.php`
   - `includes/core/class-secure-settings.php`
   - `includes/core/class-translation-cache.php`
   - `includes/core/class-translation-versioning.php`

2. **Altri File** (caricati automaticamente):
   - Tutti gli altri file in `includes/` e sottodirectory
   - I file core vengono saltati per evitare duplicati

### Perché Funziona Ora

- `FPML_Plugin_Core` viene caricata **prima** di `FPML_Plugin`
- `FPML_Plugin` estende `FPML_Plugin_Core` senza errori
- L'ordine è garantito indipendentemente dal sistema operativo del server

## 🆘 Supporto

Se hai bisogno di ulteriore assistenza:

1. Esegui `diagnostic.php?fpml_diag=check`
2. Invia l'output completo
3. Specifica:
   - Versione PHP del server
   - Eventuali messaggi di errore nel log di WordPress
   - Screenshot dell'errore (se visibile)

---

**Buon deployment! 🎉**

