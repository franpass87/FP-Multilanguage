# 🔍 DEBUG ERRORE 500 - Identificazione Problema

## 🚨 SITUAZIONE

L'errore 500 persiste. Ora dobbiamo **identificare esattamente** quale file o funzione causa il problema.

## 📦 PACCHETTO DEBUG

**`FP-Multilanguage-DEBUG.zip`**

Questo pacchetto include uno **script di test** che caricherà i file uno alla volta e ti dirà **esattamente** dove si verifica l'errore.

## 🔧 PROCEDURA DEBUG

### PASSO 1: Carica il Pacchetto

1. **Via FTP**, carica `FP-Multilanguage-DEBUG.zip` sul server
2. **Estrai** il contenuto in `/wp-content/plugins/FP-Multilanguage/`
3. **NON attivare** il plugin ancora

### PASSO 2: Esegui lo Script di Test

Apri nel browser:
```
https://viterboantica7576.live-website.com/wp-content/plugins/FP-Multilanguage/test-loading.php?test=1
```

### PASSO 3: Analizza il Risultato

Lo script mostrerà **esattamente** quale file causa l'errore:

#### ✅ SE IL TEST COMPLETA CON SUCCESSO:
```
=== TEST COMPLETATO CON SUCCESSO ✓ ===
```
→ Il problema NON è nei file del plugin  
→ Potrebbe essere: conflitto plugin, database, configurazione server

#### ❌ SE IL TEST SI FERMA CON ERRORE:
```
✗ ERRORE FATALE: [messaggio errore]
File: [percorso file]
Linea: [numero linea]
=== ERRORE FATALE - Il file sopra causa il problema ===
```
→ **INVIAMI L'OUTPUT COMPLETO** dello script

### PASSO 4: Esegui Diagnostica Completa

Dopo il test di caricamento, esegui:
```
https://viterboantica7576.live-website.com/wp-content/plugins/FP-Multilanguage/diagnostic.php?fpml_diag=check
```

**INVIAMI L'OUTPUT DI ENTRAMBI GLI SCRIPT**

## 🔍 COSA CERCARE

Lo script di test verifica:

1. ✅ Esistenza `vendor/autoload.php`
2. ✅ Caricamento Composer autoloader
3. ✅ Caricamento file CORE (uno alla volta)
4. ✅ Verifica classi core esistono
5. ✅ Caricamento altri file (uno alla volta)
6. ✅ Verifica classe FPML_Plugin

**Si fermerà esattamente al file che causa l'errore!**

## 🆘 POSSIBILI CAUSE (da verificare)

### 1. Problema Composer
Se lo script si ferma su `vendor/autoload.php`:
```
→ Le dipendenze Composer non sono installate correttamente
→ Soluzione: Ricompila vendor/ con composer install
```

### 2. Problema File Core
Se lo script si ferma su un file in `includes/core/`:
```
→ Problema in una classe fondamentale
→ Soluzione: Inviarmi l'errore esatto per fix
```

### 3. Problema Altri File
Se lo script si ferma su un file in `includes/`:
```
→ Problema in una classe secondaria
→ Soluzione: Possiamo disabilitare temporaneamente quel file
```

### 4. Test Completa Ma Plugin Crasha
Se il test completa ma l'attivazione crasha:
```
→ Problema durante l'attivazione (database, rewrite rules, ecc.)
→ Soluzione: Modificare il metodo activate()
```

## 📋 CHECKLIST DEBUG

- [ ] Caricato FP-Multilanguage-DEBUG.zip via FTP
- [ ] Estratto in /wp-content/plugins/FP-Multilanguage/
- [ ] Eseguito test-loading.php?test=1
- [ ] Copiato l'output completo
- [ ] Eseguito diagnostic.php?fpml_diag=check
- [ ] Copiato l'output completo
- [ ] Inviato entrambi gli output per analisi

## 🎯 RISULTATI ATTESI

### Scenario A: Test OK, Attivazione CRASH
```
test-loading.php → ✓ Tutti i file caricati
Attivazione plugin → ✗ Errore 500

DIAGNOSI: Problema durante activate()
FIX: Modificare logica di attivazione
```

### Scenario B: Test CRASH su File Specifico
```
test-loading.php → ✗ Errore su class-xyz.php

DIAGNOSI: Problema in quel file specifico
FIX: Correggere il file o escluderlo
```

### Scenario C: Test CRASH su Vendor
```
test-loading.php → ✗ Errore su vendor/autoload.php

DIAGNOSI: Dipendenze Composer corrotte
FIX: Reinstallare Composer dependencies
```

## ⚡ AZIONE IMMEDIATA

**FAI SUBITO:**

1. Carica `FP-Multilanguage-DEBUG.zip` via FTP
2. Estrai in `/wp-content/plugins/FP-Multilanguage/`
3. Vai su: `https://viterboantica7576.live-website.com/wp-content/plugins/FP-Multilanguage/test-loading.php?test=1`
4. **COPIA TUTTO L'OUTPUT**
5. Inviamelo immediatamente

**CON L'OUTPUT DELLO SCRIPT POTRÒ DIRTI ESATTAMENTE COSA STA CAUSANDO L'ERRORE!** 🎯

---

*Debug Script incluso in FP-Multilanguage-DEBUG.zip*

