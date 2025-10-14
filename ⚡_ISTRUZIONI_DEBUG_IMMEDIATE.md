# ⚡ ISTRUZIONI DEBUG IMMEDIATE

## 🎯 OBIETTIVO

Identificare **esattamente** cosa causa l'errore 500.

## 📦 PACCHETTO

**`FP-Multilanguage-DEBUG-COMPLETE.zip`** - Include plugin + script debug

## 🔧 COSA FARE ADESSO (5 MINUTI)

### 1️⃣ CARICA IL PACCHETTO

Via FTP:
```
1. Elimina /wp-content/plugins/FP-Multilanguage/ (se esiste)
2. Carica FP-Multilanguage-DEBUG-COMPLETE.zip
3. Estrai in /wp-content/plugins/
```

### 2️⃣ ESEGUI 3 TEST (in ordine)

#### TEST 1: Test Minimal (più semplice)
```
https://viterboantica7576.live-website.com/wp-content/plugins/FP-Multilanguage/test-minimal.php?test=minimal
```

**Cosa aspettarsi:**
- ✅ Se vedi "TEST MINIMAL COMPLETATO ✓" → Le classi base funzionano
- ❌ Se vedi errore → Problema nelle classi core

**COPIA L'OUTPUT E INVIAMELO**

---

#### TEST 2: Test Caricamento Completo
```
https://viterboantica7576.live-website.com/wp-content/plugins/FP-Multilanguage/test-loading.php?test=1
```

**Cosa aspettarsi:**
- ✅ Se vedi "TEST COMPLETATO CON SUCCESSO ✓" → Tutti i file funzionano
- ❌ Se si ferma → Ti dirà quale file causa l'errore

**COPIA L'OUTPUT E INVIAMELO**

---

#### TEST 3: Diagnostica Server
```
https://viterboantica7576.live-website.com/wp-content/plugins/FP-Multilanguage/diagnostic.php?fpml_diag=check
```

**COPIA L'OUTPUT E INVIAMELO**

---

### 3️⃣ INVIAMI I 3 OUTPUT

Con questi 3 test saprò **esattamente** qual è il problema.

## 📊 INTERPRETAZIONE RISULTATI

### Scenario A: Tutti i test OK ✓
```
Test 1: ✓ OK
Test 2: ✓ OK  
Test 3: ✓ OK

DIAGNOSI: Il plugin funziona!
CAUSA ERRORE 500: Problema durante ATTIVAZIONE o CONFLITTO con altro plugin
SOLUZIONE: Modificare metodo activate() o disattivare altri plugin
```

### Scenario B: Test 1 OK, Test 2 FAIL
```
Test 1: ✓ OK (classi base funzionano)
Test 2: ✗ FAIL su file XYZ

DIAGNOSI: Problema in un file specifico
SOLUZIONE: Correggere o escludere quel file
```

### Scenario C: Test 1 FAIL
```
Test 1: ✗ FAIL sulle classi core

DIAGNOSI: Problema grave - classi base corrotte o incompatibili
SOLUZIONE: Ricostruire classi core
```

### Scenario D: Test 2 FAIL su vendor
```
Test 2: ✗ FAIL su vendor/autoload.php

DIAGNOSI: Composer dependencies mancanti/corrotte
SOLUZIONE: Reinstallare dependencies o rimuovere dipendenza da Composer
```

## 🚨 SE NON RIESCI AD ACCEDERE AI TEST

Se anche i test danno errore 500:

### Opzione A: Via SSH
```bash
cd /wp-content/plugins/FP-Multilanguage
php test-minimal.php  # Esegui via CLI
```

### Opzione B: Controlla Log Server
```
1. Abilita error_log in PHP
2. Controlla /var/log/apache2/error.log o equivalente
3. Cerca errori relativi a FP-Multilanguage
```

### Opzione C: Controlla wp-content/debug.log
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Poi controlla: /wp-content/debug.log
```

## 💡 NOTA IMPORTANTE

**NON ATTIVARE IL PLUGIN!**

Esegui prima i test. L'attivazione può causare problemi irreversibili al database.

I test sono sicuri e non modificano nulla.

## ✅ CHECKLIST

- [ ] Caricato FP-Multilanguage-DEBUG-COMPLETE.zip via FTP
- [ ] Estratto in /wp-content/plugins/
- [ ] Eseguito test-minimal.php?test=minimal
- [ ] Copiato output Test 1
- [ ] Eseguito test-loading.php?test=1
- [ ] Copiato output Test 2
- [ ] Eseguito diagnostic.php?fpml_diag=check
- [ ] Copiato output Test 3
- [ ] **INVIATO TUTTI E 3 GLI OUTPUT**

## 🎯 DOPO I TEST

Con i 3 output potrò:

1. **Identificare il file/funzione esatta** che causa l'errore
2. **Capire se è un problema di Composer** (vendor/)
3. **Vedere la configurazione del server** (PHP version, memory, etc.)
4. **Creare un fix mirato** per il TUO caso specifico

---

## ⚡ FAI SUBITO

1. 📥 Carica `FP-Multilanguage-DEBUG-COMPLETE.zip`
2. 🔧 Esegui i 3 test
3. 📋 Inviami gli output
4. ✅ Risolveremo il problema!

**Con questi test troveremo SICURAMENTE la causa dell'errore 500!** 🎯

---

*Pacchetto: FP-Multilanguage-DEBUG-COMPLETE.zip*  
*Include: Plugin + 3 script di debug + Diagnostica completa*

