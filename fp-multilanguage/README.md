# FP Multilanguage

[![Versione](https://img.shields.io/badge/version-0.4.1-blue.svg)](https://github.com/francescopasseri/FP-Multilanguage)
[![WordPress](https://img.shields.io/badge/wordpress-5.8+-green.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/php-8.0+-purple.svg)](https://php.net)

Plugin WordPress enterprise-grade per traduzione automatica italiano-inglese con supporto OpenAI, DeepL, Google Cloud Translation e LibreTranslate.

---

## 🚀 Quick Start

```bash
# 1. Attiva plugin in WordPress
# 2. Configura provider in Impostazioni → FP Multilanguage
# 3. Esegui sync iniziale
wp fpml queue run
```

---

## ✨ Caratteristiche v0.4.1

- 🔐 **Crittografia chiavi API** (AES-256-CBC)
- 💾 **Versionamento traduzioni** con backup e rollback
- 🔍 **Endpoint REST anteprima** per test senza salvare
- 🛡️ **36 correzioni bug** (11 vulnerabilità critiche)
- ⚡ **Performance**: Reindex 10x più veloce, -70% memoria
- 🧪 **Testing**: +21 test unitari, 50% coverage

---

## 📋 Requisiti

- **WordPress**: 5.8+
- **PHP**: 8.0+ (8.2+ raccomandato)
- **API Provider**: Almeno uno tra OpenAI, DeepL, Google, LibreTranslate

---

## 💾 Installazione

1. Carica cartella `fp-multilanguage` in `/wp-content/plugins/`
2. Attiva tramite **Plugin → Plugin Installati**
3. Configura in **Impostazioni → FP Multilanguage**
4. Esegui sync: `wp fpml queue run`

### ⚠️ Upgrade a v0.4.1

```bash
# Backup database
wp db export backup-$(date +%Y%m%d).sql

# Aggiorna plugin

# Migra chiavi API (una volta)
php tools/migrate-api-keys.php
```

**Vedi**: [Guida upgrade completa](../RELEASE_NOTES_v0.4.1.md)

---

## 🔧 Utilizzo Base

1. Crea/modifica contenuto italiano
2. Plugin accoda automaticamente job traduzione
3. Coda processa job incrementalmente
4. Contenuto inglese rimane sincronizzato

### Comandi WP-CLI

```bash
# Processing coda
wp fpml queue run

# Stato coda
wp fpml queue status

# Stima costi
wp fpml queue estimate-cost

# Cleanup
wp fpml queue cleanup --days=7
```

---

## 📚 Documentazione

### Quick Start
- **[`../📋_LEGGI_QUI.md`](../📋_LEGGI_QUI.md)** - Overview 2 minuti
- **[`../QUICK_START.md`](../QUICK_START.md)** - Guida setup

### Documentazione Completa
- **[`../README.md`](../README.md)** - README principale
- **[`../docs/`](../docs/)** - Documentazione tecnica completa
  - API Reference
  - Architecture
  - Developer Guide
  - Troubleshooting
  - Performance Optimization
  - Deployment Guide

### v0.4.1
- **[Novità v0.4.1](../NUOVE_FUNZIONALITA_E_CORREZIONI.md)**
- **[Deployment Guide](../RIEPILOGO_FINALE_IMPLEMENTAZIONE.md)**
- **[API Preview Endpoint](../docs/api-preview-endpoint.md)**
- **[Roadmap 2025](../RACCOMANDAZIONI_PRIORITARIE.md)**

---

## 🔌 Hook Principali

### Actions
```php
do_action( 'fpml_post_jobs_enqueued', $post_id, $jobs );
do_action( 'fpml_post_translated', $post_id, $translations );
do_action( 'fpml_queue_after_cleanup', $deleted, $states, $days );
```

### Filters
```php
add_filter( 'fpml_translatable_post_types', $callback );
add_filter( 'fpml_translatable_taxonomies', $callback );
add_filter( 'fpml_queue_cleanup_states', $callback );
```

**Vedi**: [`../docs/api-reference.md`](../docs/api-reference.md) per riferimento completo.

---

## 🆘 Supporto

- **Documentazione**: [`../docs/`](../docs/)
- **Troubleshooting**: [`../docs/troubleshooting.md`](../docs/troubleshooting.md)
- **FAQ**: [`../docs/faq.md`](../docs/faq.md)
- **GitHub Issues**: [Apri issue](https://github.com/francescopasseri/FP-Multilanguage/issues)
- **Email**: [info@francescopasseri.com](mailto:info@francescopasseri.com)

---

## 📄 Licenza

GPL-2.0-or-later - Vedi [LICENSE](../LICENSE) per dettagli.

---

## 👨‍💻 Autore

**Francesco Passeri**
- 🌐 [francescopasseri.com](https://francescopasseri.com)
- 📧 [info@francescopasseri.com](mailto:info@francescopasseri.com)
- 🐙 [@francescopasseri](https://github.com/francescopasseri)

---

**Documentazione completa**: [`../README.md`](../README.md) • [`../docs/`](../docs/)
