# Guida Testing FP-Multilanguage

Questa directory contiene gli script di test automatici e la documentazione completa per testare il plugin FP-Multilanguage.

## Script di Test Automatici

### 1. test-plugin-structure.php

Verifica la struttura base del plugin:
- Autoloader Composer
- Classi core
- Pagine admin e view files
- Integrazioni
- Database tables
- Costanti plugin

**Esecuzione**:
```bash
wp eval-file tests/test-plugin-structure.php
```

### 2. test-rest-api-endpoints.php

Verifica tutti gli endpoint REST API:
- 16 endpoint REST API registrati
- Permission callbacks
- Metodi HTTP corretti

**Esecuzione**:
```bash
wp eval-file tests/test-rest-api-endpoints.php
```

### 3. test-ajax-handlers.php

Verifica tutti gli AJAX handlers:
- 13+ AJAX actions registrate
- Nonce verification
- Permission checks
- Handler classes e methods

**Esecuzione**:
```bash
wp eval-file tests/test-ajax-handlers.php
```

### 4. test-frontend-routing.php

Verifica il routing frontend:
- Classe Rewrites
- Componenti routing
- Hooks WordPress
- LanguageManager

**Esecuzione**:
```bash
wp eval-file tests/test-frontend-routing.php
```

## Esecuzione Tutti i Test

Per eseguire tutti i test automatici in sequenza:

```bash
cd wp-content/plugins/FP-Multilanguage

wp eval-file tests/test-plugin-structure.php && \
wp eval-file tests/test-rest-api-endpoints.php && \
wp eval-file tests/test-ajax-handlers.php && \
wp eval-file tests/test-frontend-routing.php
```

## Test Manuali

I test manuali sono documentati in dettaglio in:
- `../TEST-REPORT-EXECUTION.md` - Report dettagliato con istruzioni per ogni test
- `../TEST-EXECUTION-GUIDE.md` - Guida esecuzione test

## PHPUnit Test Suite

Il plugin include anche una suite PHPUnit esistente:

```bash
composer install
vendor/bin/phpunit
```

## E2E Playwright Tests

Per test end-to-end con browser:

```bash
npm install
npm test
```

## Requisiti

- WordPress installato e funzionante
- Plugin FP-Multilanguage attivato
- Composer autoload installato (`composer install`)
- WP-CLI installato (per eseguire script automatici)

## Documentazione Test Completa

Vedi i documenti nella root del plugin:
- `TEST-PLAN-EXECUTION.md` - Piano esecuzione con checklist
- `TEST-REPORT-EXECUTION.md` - Report dettagliato test con istruzioni
- `TEST-EXECUTION-GUIDE.md` - Guida esecuzione test
- `TEST-COMPLETE-REPORT.md` - Report completo

## Note

Gli script di test automatici richiedono un ambiente WordPress completo per funzionare correttamente. Se WP-CLI non è disponibile, è possibile eseguire i test manualmente seguendo le istruzioni in `TEST-REPORT-EXECUTION.md`.





