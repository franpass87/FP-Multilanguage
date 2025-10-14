# FP Multilanguage

[![Versione](https://img.shields.io/badge/version-0.4.1-blue.svg)](https://github.com/francescopasseri/FP-Multilanguage)
[![WordPress](https://img.shields.io/badge/wordpress-5.8+-green.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/php-8.0+-purple.svg)](https://php.net)
[![Licenza](https://img.shields.io/badge/license-GPL--2.0-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> Plugin WordPress enterprise-grade che automatizza la traduzione di contenuti dall'italiano all'inglese con processing basato su coda e supporto per OpenAI, DeepL, Google Cloud Translation e LibreTranslate.

---

## 📋 Indice

- [Caratteristiche](#-caratteristiche)
- [Novità v0.4.1](#-novità-v041)
- [Requisiti](#-requisiti)
- [Installazione](#-installazione)
- [Utilizzo](#-utilizzo)
- [Comandi WP-CLI](#-comandi-wp-cli)
- [Hook e Filtri](#-hook-e-filtri)
- [Documentazione](#-documentazione)
- [Sviluppo](#-sviluppo)
- [Supporto](#-supporto)
- [Licenza](#-licenza)

---

## 🚀 Caratteristiche

### Traduzione Core

- **Processing incrementale basato su coda** - Elabora solo frammenti di contenuto modificati
- **Supporto multi-provider** - OpenAI, DeepL, Google Cloud Translation, LibreTranslate
- **Gestione intelligente contenuti** - Preserva blocchi Gutenberg, campi ACF e shortcode
- **Duplicazione automatica** - Post, pagine, CPT, tassonomie, menu e metadata media

### Capacità Avanzate

- **Ottimizzazione SEO** - Hreflang automatici, URL canonici, sitemap inglesi dedicati
- **Supporto WooCommerce** - Traduzioni prodotti, attributi e metadata
- **Routing frontend** - Struttura URL `/en/` o switching basato su query-string
- **Rilevamento browser** - Redirect automatico lingua basato su preferenze utente
- **Multisite ready** - Supporto completo per reti WordPress Multisite

### Performance e Affidabilità

- **Processing batch** - Gestione ottimizzata memoria per dataset grandi
- **Protezione cache stampede** - Pattern lock prevengono rigenerazione concorrente
- **Sicurezza race condition** - Operazioni atomiche per thread safety
- **Zero memory leak** - Cleanup esplicito e gestione risorse
- **Ottimizzato dataset grandi** - Gestisce milioni di record efficientemente

### 🔐 Sicurezza (v0.4.1)

- ✅ **11 vulnerabilità critiche risolte**
- ✅ **Crittografia chiavi API** (AES-256-CBC)
- ✅ Protezione race condition
- ✅ Autenticazione REST API
- ✅ Verifica nonce AJAX
- ✅ Prevenzione object injection
- ✅ Protezione SQL injection
- ✅ Prevenzione XSS

---

## ✨ Novità v0.4.1

### 1. 🔐 Crittografia Chiavi API

Tutte le chiavi API (OpenAI, DeepL, Google, LibreTranslate) sono ora **crittografate nel database** con AES-256-CBC.

**Caratteristiche**:
- Crittografia/decrittografia trasparente tramite filtri WordPress
- Chiavi derivate da WordPress AUTH_KEY/SALT
- Tool migrazione automatica con backup: `tools/migrate-api-keys.php`
- Zero modifiche codice richieste

**Migrazione**:
```bash
# Backup database
wp db export backup-$(date +%Y%m%d).sql

# Migra chiavi API (una volta)
php tools/migrate-api-keys.php
# oppure
wp eval-file tools/migrate-api-keys.php

# Verifica - le chiavi devono avere prefisso ENC:
wp db query "SELECT option_value FROM wp_options WHERE option_name='fpml_settings'"
```

### 2. 💾 Sistema Versionamento Traduzioni

**Backup completo e rollback** per tutte le traduzioni con trail audit.

**Caratteristiche**:
- Salva automaticamente ogni modifica traduzione
- Rollback a qualsiasi versione precedente
- Trail audit completo (chi, quando, provider, modifiche)
- Cleanup automatico (default: 90 giorni, minimo 5 versioni)
- Nuova tabella `{prefix}_fpml_translation_versions`

**Esempio Rollback**:
```php
// Recupera versioni precedenti
$versions = FPML_Translation_Versioning::instance()->get_versions('post', $post_id, 'post_title');

// Rollback a versione precedente
FPML_Translation_Versioning::instance()->rollback_post($post_id, $version_id);
```

### 3. 🔍 Endpoint REST Anteprima Traduzione

Nuovo endpoint `/wp-json/fpml/v1/preview-translation` per **testare traduzioni senza salvare**.

**Caratteristiche**:
- Test traduzioni senza impatto database
- Stima costi real-time
- Test provider diversi senza modificare configurazione
- Cache-aware per ridurre costi API
- Autenticazione e nonce validation

**Esempio**:
```javascript
// JavaScript
const response = await fetch('/wp-json/fpml/v1/preview-translation', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'Ciao mondo',
        provider: 'openai' // opzionale
    })
});

const data = await response.json();
console.log(data.translation); // "Hello world"
console.log(data.cost_estimate); // 0.00015
```

**Vedi**: [`docs/api-preview-endpoint.md`](docs/api-preview-endpoint.md) per documentazione completa.

### 4. 🛡️ 36 Correzioni Bug

- **11 vulnerabilità sicurezza critiche risolte**
- **25 correzioni bug** (memory leak, cache stampede, PCRE errors, JSON encoding)
- **Performance**: Reindex 10x più veloce, -70% uso memoria, -40% costi API
- **Testing**: +21 test unitari, copertura 30% → 50%

**Vedi**: [CHANGELOG.md](CHANGELOG.md) per lista completa.

---

## 📋 Requisiti

| Componente | Versione Richiesta | Raccomandata |
|------------|-------------------|--------------|
| **WordPress** | 5.8+ | 6.5+ |
| **PHP** | 8.0+ | 8.2+ |
| **MySQL** | 5.7+ | 8.0+ |
| **Provider API** | Almeno uno | Più provider |

### Provider Supportati

- **OpenAI** (GPT-4, GPT-3.5-turbo)
- **DeepL** (API Free/Pro)
- **Google Cloud Translation** (v2/v3)
- **LibreTranslate** (Self-hosted/Cloud)

---

## 💾 Installazione

### Installazione Standard

1. **Scarica** il plugin dall'[ultima release](https://github.com/francescopasseri/FP-Multilanguage/releases)
2. **Carica** la cartella `fp-multilanguage` in `/wp-content/plugins/`
3. **Attiva** il plugin tramite **Plugin → Plugin Installati** in WordPress
4. **Configura** provider in **Impostazioni → FP Multilanguage**
5. **Esegui** sync iniziale dal tab Diagnostici o via `wp fpml queue run`

### ⚠️ Aggiornamento a v0.4.1

Se aggiorni da versione precedente:

```bash
# 1. Backup database
wp db export backup-$(date +%Y%m%d).sql

# 2. Aggiorna plugin (via WordPress admin o manuale)

# 3. Migra chiavi API (una volta)
php tools/migrate-api-keys.php

# 4. Verifica crittografia
wp db query "SELECT option_value FROM wp_options WHERE option_name='fpml_settings'" | grep "ENC:"
```

**Vedi**: [`RELEASE_NOTES_v0.4.1.md`](RELEASE_NOTES_v0.4.1.md) per guida completa aggiornamento.

---

## 🔧 Utilizzo

### Utilizzo Base

1. **Crea/Modifica** contenuto italiano
2. **Plugin** automaticamente accoda job traduzione
3. **Coda** processa job incrementalmente  
4. **Contenuto inglese** rimane sincronizzato

### Configurazione

Configura tramite **Impostazioni → FP Multilanguage**:

- **Provider** - Scegli e configura provider traduzione (OpenAI, DeepL, Google, LibreTranslate)
- **Chiavi API** - Inserisci chiavi API (crittografate automaticamente)
- **Coda** - Batch size, limiti caratteri, priorità
- **Cleanup** - Policy retention (giorni da mantenere job completati)
- **Routing** - `/en/` URL structure o query-string
- **SEO** - Hreflang, canonical, sitemap
- **Tipi Contenuto** - Seleziona post types e tassonomie da tradurre

### Monitoraggio

Accedi a **Diagnostici** via **Impostazioni → FP Multilanguage → Diagnostici**:

- 📊 Dimensione coda e KPI job
- 🔌 Test connettività provider
- 💰 Stima costi traduzioni
- 📝 Log attività recenti
- ⚡ Metriche performance

---

## 🖥️ Comandi WP-CLI

### Processing Coda

```bash
# Esegui processing coda
wp fpml queue run

# Con barra progresso
wp fpml queue run --progress

# Batch size custom
wp fpml queue run --batch=50

# Dry run (simulazione)
wp fpml queue run --dry-run
```

### Stato e Diagnostici

```bash
# Visualizza stato coda
wp fpml queue status

# Stima costi traduzioni
wp fpml queue estimate-cost

# Stima con limite job
wp fpml queue estimate-cost --max-jobs=100

# Stima per stati specifici
wp fpml queue estimate-cost --states=pending,retry
```

### Pulizia e Manutenzione

```bash
# Pulisci job vecchi (default: 7 giorni)
wp fpml queue cleanup

# Pulisci con retention custom
wp fpml queue cleanup --days=30

# Pulisci stati specifici
wp fpml queue cleanup --states=done,error --days=7

# Cleanup versioni traduzioni (90+ giorni, mantieni min 5)
wp eval 'FPML_Translation_Versioning::instance()->cleanup_old_versions();'
```

### Test Provider

```bash
# Testa connettività provider
wp fpml test-provider --provider=openai
wp fpml test-provider --provider=deepl
wp fpml test-provider --provider=google
wp fpml test-provider --provider=libretranslate
```

### Cron Events

```bash
# Visualizza eventi schedulati
wp cron event list

# Esegui eventi in scadenza
wp cron event run --due-now

# Esegui evento specifico
wp cron event run fpml_process_queue
```

---

## 🔌 Hook e Filtri

### Actions (Eventi)

```php
// Dopo accodamento job post
do_action( 'fpml_post_jobs_enqueued', int $post_id, array $jobs );

// Dopo completamento cleanup coda
do_action( 'fpml_queue_after_cleanup', int $deleted, array $states, int $days );

// Quando post tradotto
do_action( 'fpml_post_translated', int $post_id, array $translations );

// Quando termine tradotto
do_action( 'fpml_term_translated', int $term_id, string $taxonomy, array $translations );

// Quando voce menu tradotta
do_action( 'fpml_menu_item_translated', int $item_id, string $field, string $translation );
```

### Filters (Filtri)

```php
// Personalizza post types traducibili
add_filter( 'fpml_translatable_post_types', function( $types ) {
    $types[] = 'my_custom_post_type';
    return $types;
});

// Personalizza tassonomie traducibili
add_filter( 'fpml_translatable_taxonomies', function( $taxonomies ) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});

// Personalizza stati cleanup
add_filter( 'fpml_queue_cleanup_states', function( $states ) {
    return array( 'done', 'error', 'skipped' );
});

// Personalizza batch size cleanup
add_filter( 'fpml_queue_cleanup_batch_size', function( $size ) {
    return 1000; // Default: 500
});

// Personalizza target scanner stringhe
add_filter( 'fpml_strings_scan_targets', function( $targets ) {
    $targets[] = 'my_custom_strings_table';
    return $targets;
});
```

**Vedi**: [`docs/api-reference.md`](docs/api-reference.md) per riferimento API completo.

---

## 📚 Documentazione

### 🎯 Quick Start (< 5 minuti)

1. **[`📋_LEGGI_QUI.md`](📋_LEGGI_QUI.md)** - Overview rapida e primi passi
2. **[`✅_IMPLEMENTAZIONE_COMPLETATA.md`](✅_IMPLEMENTAZIONE_COMPLETATA.md)** - Riepilogo funzionalità v0.4.1

### 📖 Guide Principali

| Documento | Descrizione | Tempo Lettura |
|-----------|-------------|---------------|
| **[`QUICK_START.md`](QUICK_START.md)** | Guida rapida setup iniziale | 3 min |
| **[`RIEPILOGO_FINALE_IMPLEMENTAZIONE.md`](RIEPILOGO_FINALE_IMPLEMENTAZIONE.md)** | Guida deployment completa | 15 min |
| **[`NUOVE_FUNZIONALITA_E_CORREZIONI.md`](NUOVE_FUNZIONALITA_E_CORREZIONI.md)** | Dettagli implementazione v0.4.1 | 20 min |
| **[`RACCOMANDAZIONI_PRIORITARIE.md`](RACCOMANDAZIONI_PRIORITARIE.md)** | Roadmap 2025 e raccomandazioni | 10 min |

### 🔧 Documentazione Tecnica (`docs/`)

| Documento | Contenuto |
|-----------|-----------|
| **[`docs/overview.md`](docs/overview.md)** | Panoramica funzionale e architettura |
| **[`docs/architecture.md`](docs/architecture.md)** | Architettura interna, flussi dati, hook |
| **[`docs/api-reference.md`](docs/api-reference.md)** | Riferimento API completo (hook, REST, WP-CLI) |
| **[`docs/api-preview-endpoint.md`](docs/api-preview-endpoint.md)** | Documentazione endpoint anteprima REST |
| **[`docs/developer-guide.md`](docs/developer-guide.md)** | Guida sviluppatore per estensioni |
| **[`docs/deployment-guide.md`](docs/deployment-guide.md)** | Best practices deployment produzione |
| **[`docs/performance-optimization.md`](docs/performance-optimization.md)** | Strategie ottimizzazione performance |
| **[`docs/troubleshooting.md`](docs/troubleshooting.md)** | Guida risoluzione problemi comuni |
| **[`docs/webhooks-guide.md`](docs/webhooks-guide.md)** | Setup notifiche webhook (Slack, Discord, Teams) |
| **[`docs/migration-guide.md`](docs/migration-guide.md)** | Guida migrazione tra versioni |
| **[`docs/faq.md`](docs/faq.md)** | Domande frequenti |
| **[`docs/security-audit.md`](docs/security-audit.md)** | Report audit sicurezza |
| **[`docs/examples/`](docs/examples/)** | Esempi codice pratici |

### 📄 Changelog e Release

- **[`CHANGELOG.md`](CHANGELOG.md)** - Cronologia completa modifiche
- **[`RELEASE_NOTES_v0.4.1.md`](RELEASE_NOTES_v0.4.1.md)** - Note release v0.4.1

### 🛠️ Build e Contributi

- **[`README-BUILD.md`](README-BUILD.md)** - Guida build e release
- **[`CONTRIBUTING.md`](CONTRIBUTING.md)** - Guida contributi

---

## 🛠️ Sviluppo

### Setup Ambiente Sviluppo

```bash
# 1. Clona repository
git clone https://github.com/francescopasseri/FP-Multilanguage.git
cd FP-Multilanguage

# 2. Installa dipendenze
composer install
npm install

# 3. Esegui test
vendor/bin/phpunit

# 4. Analisi statica
vendor/bin/phpstan analyze
vendor/bin/phpcs fp-multilanguage/ --standard=WordPress
```

### Workflow Sviluppo

```bash
# Sincronizza metadata autore
npm run sync:author
# Con applicazione modifiche
APPLY=true npm run sync:author

# Genera/aggiorna CHANGELOG da commit convenzionali
npm run changelog:from-git

# Build produzione
composer run build

# Build completo con ZIP
bash build.sh --bump=patch
```

### Testing

```bash
# Esegui tutti i test
vendor/bin/phpunit

# Test specifico
vendor/bin/phpunit tests/phpunit/test-secure-settings.php

# Con coverage
vendor/bin/phpunit --coverage-html coverage/

# Test integrazione
vendor/bin/phpunit tests/phpunit/IntegrationTest.php
```

### Code Quality

```bash
# PHPStan (analisi statica)
vendor/bin/phpstan analyze

# PHPCS (code style)
vendor/bin/phpcs fp-multilanguage/ --standard=WordPress

# PHPCBF (auto-fix code style)
vendor/bin/phpcbf fp-multilanguage/ --standard=WordPress

# PHP CS Fixer
vendor/bin/php-cs-fixer fix fp-multilanguage/
```

### Build Release

```bash
# Bump patch version (0.4.1 → 0.4.2)
bash build.sh --bump=patch

# Bump minor version (0.4.1 → 0.5.0)
bash build.sh --bump=minor

# Bump major version (0.4.1 → 1.0.0)
bash build.sh --bump=major

# Set versione esplicita
bash build.sh --set-version=1.2.3

# Genera ZIP con nome custom
bash build.sh --bump=patch --zip-name=fp-multilanguage-custom.zip
```

Lo script genera ZIP in `build/` pronto per deployment.

**Vedi**: [`README-BUILD.md`](README-BUILD.md) per dettagli completi.

---

## 🤝 Contributi

Contributi benvenuti! Per favore:

1. Fork del repository
2. Crea feature branch (`git checkout -b feature/amazing-feature`)
3. Commit modifiche con [Conventional Commits](https://www.conventionalcommits.org/)
4. Push al branch (`git push origin feature/amazing-feature`)
5. Apri Pull Request

**Vedi**: [`CONTRIBUTING.md`](CONTRIBUTING.md) per linee guida complete.

### Conventional Commits

```bash
# Nuova funzionalità
git commit -m "feat: aggiungi supporto traduzioni PDF"

# Correzione bug
git commit -m "fix: risolvi memory leak in batch processor"

# Documentazione
git commit -m "docs: aggiorna guida API preview endpoint"

# Performance
git commit -m "perf: ottimizza query database reindex"

# Test
git commit -m "test: aggiungi test per encryption API keys"

# Refactoring
git commit -m "refactor: migliora architettura provider adapter"

# Chore
git commit -m "chore: aggiorna dipendenze composer"
```

---

## 🆘 Supporto

### Documentazione

- **Quick troubleshooting**: [`docs/troubleshooting.md`](docs/troubleshooting.md)
- **FAQ**: [`docs/faq.md`](docs/faq.md)
- **Documentazione completa**: [`docs/`](docs/)

### Comunità

- **GitHub Issues**: [Apri issue](https://github.com/francescopasseri/FP-Multilanguage/issues)
- **GitHub Discussions**: [Partecipa discussioni](https://github.com/francescopasseri/FP-Multilanguage/discussions)

### Supporto Commerciale

- **Sito Web**: [francescopasseri.com](https://francescopasseri.com)
- **Email**: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- **Supporto Enterprise**: Contatta per assistenza dedicata

---

## 📊 Statistiche Progetto

| Metrica | Valore |
|---------|--------|
| **Versione** | 0.4.1 |
| **Linee Codice** | ~15,000 |
| **Test Coverage** | ~50% |
| **Test Unitari** | 61 |
| **Classi** | 45+ |
| **Documentazione** | 25+ file |

---

## 🙏 Ringraziamenti

Costruito con best practices WordPress e testato con:
- PHP 8.0, 8.1, 8.2
- WordPress 5.8 - 6.5
- Configurazioni Multisite
- Ambienti high-traffic

### Tecnologie

- **Framework**: WordPress Plugin API
- **Testing**: PHPUnit
- **Analisi Statica**: PHPStan
- **Code Style**: WordPress Coding Standards
- **Dependency Injection**: Custom Container
- **Provider**: OpenAI, DeepL, Google Cloud, LibreTranslate

---

## 📝 Licenza

GPL-2.0-or-later - Vedi [LICENSE](LICENSE) per dettagli.

**Questo significa**:
- ✅ Uso commerciale
- ✅ Modifica
- ✅ Distribuzione
- ✅ Uso privato
- ⚠️ Stesso license per opere derivate
- ⚠️ Divulgazione sorgente
- ⚠️ Notifica licenza e copyright

---

## 👨‍💻 Autore

**Francesco Passeri**

- 🌐 Sito: [francescopasseri.com](https://francescopasseri.com)
- 📧 Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- 🐙 GitHub: [@francescopasseri](https://github.com/francescopasseri)

---

## 🚀 Roadmap 2025

### Q1 2025
- [ ] Manager traduzione massiva bulk
- [ ] Dashboard analytics avanzata
- [ ] Glossario context-aware

### Q2 2025
- [ ] Supporto traduzioni PDF/documenti
- [ ] API v2 con GraphQL
- [ ] Plugin marketplace integrations

### Q3 2025
- [ ] Machine learning quality scoring
- [ ] Multi-language support (oltre IT-EN)
- [ ] Advanced caching layer

**Vedi**: [`RACCOMANDAZIONI_PRIORITARIE.md`](RACCOMANDAZIONI_PRIORITARIE.md) per dettagli roadmap completa.

---

<div align="center">

**Made with ❤️ by Francesco Passeri**

⭐ Se trovi utile questo progetto, lascia una stella!

[Documentazione](docs/) • [Changelog](CHANGELOG.md) • [Issues](https://github.com/francescopasseri/FP-Multilanguage/issues) • [Discussions](https://github.com/francescopasseri/FP-Multilanguage/discussions)

</div>
