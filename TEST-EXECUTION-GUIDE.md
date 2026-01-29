# Guida Esecuzione Test FP-Multilanguage

Questo documento fornisce istruzioni per eseguire tutti i test del plugin FP-Multilanguage.

## Struttura Test

### Test Automatici (Script PHP)

Il plugin include script di test automatici che possono essere eseguiti via WP-CLI o da WordPress admin:

1. **test-plugin-structure.php** - Verifica struttura base plugin
2. **test-rest-api-endpoints.php** - Verifica endpoint REST API
3. **test-ajax-handlers.php** - Verifica AJAX handlers
4. **test-frontend-routing.php** - Verifica routing frontend

#### Esecuzione Script di Test

```bash
# Via WP-CLI
cd wp-content/plugins/FP-Multilanguage
wp eval-file tests/test-plugin-structure.php
wp eval-file tests/test-rest-api-endpoints.php
wp eval-file tests/test-ajax-handlers.php
wp eval-file tests/test-frontend-routing.php

# O eseguire tutti insieme
wp eval-file tests/test-plugin-structure.php && \
wp eval-file tests/test-rest-api-endpoints.php && \
wp eval-file tests/test-ajax-handlers.php && \
wp eval-file tests/test-frontend-routing.php
```

### Test Manuali

I test manuali sono descritti in dettaglio in `TEST-REPORT-EXECUTION.md`.

## Ordine Esecuzione Consigliato

### Fase 1: Test Struttura Base
1. Eseguire `test-plugin-structure.php`
2. Verificare che tutte le classi siano caricabili
3. Verificare che tutte le pagine admin esistano

### Fase 2: Test Backend
1. Eseguire `test-rest-api-endpoints.php`
2. Eseguire `test-ajax-handlers.php`
3. Test manuale pagine admin (vedi TEST-REPORT-EXECUTION.md sezione 1)
4. Test manuale AJAX handlers (browser DevTools)
5. Test manuale REST API (Postman/curl)

### Fase 3: Test Frontend
1. Eseguire `test-frontend-routing.php`
2. Test manuale routing `/en/` URLs
3. Test manuale language switching
4. Test manuale display contenuti

### Fase 4: Test Workflow Traduzione
1. Creare post IT di test
2. Verificare traduzione automatica
3. Verificare queue processing
4. Verificare link tra post IT e EN

### Fase 5: Test Integrazioni
1. WooCommerce (se plugin attivo)
2. Salient Theme (se tema attivo)
3. FP-SEO-Manager (se plugin attivo)
4. Menu sync

### Fase 6: Test Sicurezza
1. Nonce verification
2. Permission checks
3. Input sanitization
4. API key encryption

### Fase 7: Test Edge Cases
1. Error handling
2. Edge cases
3. Conflitti plugin

## PHPUnit Test Suite

Il plugin include anche una suite PHPUnit:

```bash
cd wp-content/plugins/FP-Multilanguage
composer install
vendor/bin/phpunit
```

## E2E Playwright Tests

Per test end-to-end con browser:

```bash
cd wp-content/plugins/FP-Multilanguage
npm install
npm test
```

## Checklist Completa

Vedi `TEST-REPORT-EXECUTION.md` per checklist dettagliata di tutti i test da eseguire.

## Documentazione Test

- **TEST-PLAN-EXECUTION.md** - Piano esecuzione test con checklist
- **TEST-REPORT-EXECUTION.md** - Report dettagliato test con istruzioni
- **TEST-EXECUTION-GUIDE.md** - Questa guida (overview esecuzione)

## Risoluzione Problemi

### Script di Test Falliscono

1. Verificare che WordPress sia caricato correttamente
2. Verificare che il plugin sia attivato
3. Verificare che Composer autoload sia installato (`composer install`)
4. Verificare errori PHP in error log

### Test Manuali Non Funzionano

1. Verificare permalink structure (`Settings → Permalinks → Save Changes`)
2. Verificare rewrite rules flush
3. Verificare cache del browser (usare modalità incognito)
4. Verificare console browser per errori JavaScript

### Test Integrazioni Falliscono

1. Verificare che plugin/tema integrati siano attivi
2. Verificare versioni compatibili
3. Verificare che dati di test esistano (es. prodotti WooCommerce)

## Supporto

Per problemi o domande sui test, consultare:
- Documentazione plugin: `README.md`
- Troubleshooting: `docs/troubleshooting.md`
- FAQ: `docs/faq.md`





