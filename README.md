# FP Multilanguage

[![Versione](https://img.shields.io/badge/version-0.9.1-blue.svg)](https://github.com/francescopasseri/FP-Multilanguage)
[![WordPress](https://img.shields.io/badge/wordpress-5.8+-green.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/php-8.0+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0+-orange.svg)](LICENSE)

Plugin WordPress enterprise-grade per traduzione automatica italiano-inglese con AI, integrazioni WooCommerce, Salient Theme, e architettura PSR-4.

---

## 🎯 Panoramica

**FP Multilanguage** è una soluzione completa per gestire contenuti multilingua (IT/EN) su WordPress con:

- ✅ **Traduzione AI** (OpenAI GPT-5 nano)
- ✅ **98% Coverage** traduzione automatica
- ✅ **WooCommerce** completo (prodotti, varianti, attributi con queue translation)
- ✅ **Commenti Annidati** - Supporto completo commenti threaded
- ✅ **Salient Theme** (70+ meta fields)
- ✅ **Menu Navigation** (sync bidirezionale)
- ✅ **FP-SEO-Manager** (25+ meta fields SEO)
- ✅ **WPBakery** shortcodes
- ✅ **Dashboard Overview** con statistiche real-time

---

## ✨ Novità v0.9.0 (Novembre 2025)

### 🛒 WooCommerce Integration (98% Coverage)
- Supporto completo prodotti: Simple, Variable, Grouped, External, Downloadable
- Sincronizzazione varianti (attributi, prezzi, stock, immagini)
- Gallerie prodotto con traduzione ALT text
- Relazioni prodotto (upsell/cross-sell) con mapping automatico
- Tab custom e file scaricabili

### 🧭 Menu Navigation (100% Coverage)
- Duplicazione automatica menu IT → EN
- Sincronizzazione real-time item menu
- Mapping gerarchie parent/child
- Custom fields Salient (icone, mega menu, button styles)
- Frontend language switching automatico
- UI admin con status e link rapidi

### ✨ Salient Theme (98% Coverage)
- 70+ meta fields sincronizzati (era 6 in v0.8.0)
- Page headers, portfolio, post formats
- WPBakery integration
- Navigation settings

### 🔄 FP-SEO-Manager (100% Coverage)
- 25+ meta fields sincronizzati
- Core SEO, AI features, GEO data
- Social meta (OG, Twitter)
- Schema.org

### 🛡️ Security & Bugfix
- Output escaping completo
- Nonce verification su tutti gli AJAX
- PHP 8.0+ version check
- Exception namespace fixes

**Vedi**: [CHANGELOG.md](CHANGELOG.md) per dettagli completi

---

## 📋 Requisiti

- **WordPress**: 5.8+
- **PHP**: 8.0+ (8.2+ raccomandato)
- **Composer**: Per autoload PSR-4
- **OpenAI API**: Chiave API per traduzioni

### Opzionali (per integrazioni)
- **WooCommerce**: 7.0+ (per e-commerce)
- **Salient Theme**: 15.0+ (per theme features)
- **FP-SEO-Manager**: 0.9.0+ (per SEO avanzato)

---

## 💾 Installazione

### 1. Via Plugin Manager
1. Carica cartella `FP-Multilanguage/` in `/wp-content/plugins/`
2. Attiva tramite **Plugin → Plugin Installati**

### 2. Composer Autoload
```bash
cd wp-content/plugins/FP-Multilanguage
composer install
```

### 3. Configurazione Base
1. Vai in **FP Multilanguage → Dashboard**
2. Inserisci OpenAI API key in **Impostazioni → Provider**
3. Configura opzioni traduzione
4. Salva

### 4. Test Iniziale
1. Crea un post italiano
2. Verifica che job traduzione venga accodato
3. Esegui coda: `wp fpml queue run` (o attendi cron)

---

## 🚀 Quick Start

### 1. Traduzione Singolo Post
```php
// WordPress Editor
1. Crea/modifica post in italiano
2. Salva
3. Plugin accoda automaticamente job traduzione
4. Post EN viene creato/aggiornato in background
```

### 2. Traduzione Bulk
```php
// Dashboard → Bulk Translate
1. Seleziona tipo contenuto (Post, Page, Product)
2. Scegli posts da tradurre
3. Click "Traduci Selezionati"
4. Monitora progresso in Dashboard
```

### 3. WP-CLI
```bash
# Esegui coda traduzioni
wp fpml queue run

# Status coda
wp fpml queue status

# Stima costi
wp fpml queue estimate-cost

# Cleanup vecchi job
wp fpml queue cleanup --days=7
```

---

## 📚 Documentazione

### 📖 Guide Principali
- **[Getting Started](docs/overview.md)** - Panoramica e configurazione
- **[Architecture](docs/architecture.md)** - Architettura plugin
- **[Developer Guide](docs/developer-guide.md)** - Guida sviluppatori
- **[API Reference](docs/api-reference.md)** - Hook e filters
- **[Comment Translation](docs/COMMENTS.md)** - Traduzione commenti e commenti annidati

### 🔌 Integrazioni
- **[WooCommerce Integration](docs/integrations/WOOCOMMERCE.md)** - E-commerce completo
- **[Salient Theme](docs/integrations/SALIENT-THEME.md)** - Theme meta fields
- **[Menu Navigation](docs/integrations/MENU-NAVIGATION.md)** - Menu sync
- **[FP-SEO-Manager](docs/integrations/FP-SEO-MANAGER.md)** - SEO avanzato

### 🛠️ Utility
- **[Troubleshooting](docs/troubleshooting.md)** - Risoluzione problemi
- **[FAQ](docs/faq.md)** - Domande frequenti
- **[Deployment Guide](docs/deployment-guide.md)** - Deploy in produzione
- **[Performance](docs/performance-optimization.md)** - Ottimizzazioni

### 📊 Changelog & History
- **[CHANGELOG.md](CHANGELOG.md)** - Changelog ufficiale
- **[Changelog History](docs/CHANGELOG-HISTORY.md)** - Storico completo versioni

---

## 🔌 Integrazioni Disponibili

### 🛒 WooCommerce (v0.9.0+)
- ✅ Tutti i tipi prodotto
- ✅ Varianti e attributi (queue-based translation)
- ✅ Gallerie e media
- ✅ Upsell/Cross-sell
- ✅ Downloadable files
- ✅ Custom tabs
- ✅ Tassonomie (cat, tag, brand)
- ✅ Attributi custom con traduzione AI automatica

**Coverage**: 98%

### ✨ Salient Theme (v0.9.0+)
- ✅ Page headers (26 campi)
- ✅ Portfolio (12 campi)
- ✅ Post formats (15 campi)
- ✅ Page builder (18 campi)
- ✅ Navigation (8 campi)

**Coverage**: 98%

### 🧭 Menu Navigation (v0.9.0+)
- ✅ Auto-sync bidirezionale
- ✅ Gerarchie parent/child
- ✅ Custom fields Salient
- ✅ Language switching frontend
- ✅ Admin UI status

**Coverage**: 100%

### 🔄 FP-SEO-Manager (v0.9.0+)
- ✅ Core SEO (5 campi)
- ✅ AI features (5 campi)
- ✅ GEO & Freshness (4 campi)
- ✅ Social meta (6 campi)
- ✅ Schema.org (4 campi)

**Coverage**: 100%

### 🎨 WPBakery Page Builder
- ✅ Shortcodes translation
- ✅ Translatable attributes
- ✅ Nested shortcodes

**Coverage**: 90%

---

## 🎨 Features Principali

### 🤖 Traduzione AI
- **Provider**: OpenAI GPT-5 nano (prestazioni ottimali)
- **Context-aware**: Traduzione contestuale intelligente
- **Quality**: Preserva formattazione, shortcodes, HTML
- **Memory**: Translation memory per ridurre costi

### 📊 Dashboard Overview
- Statistiche real-time (post tradotti, coda, costi)
- Quick actions (Crea Post, Bulk Translate)
- Weekly activity chart
- Alerts API key e errori
- System info

### 🔄 Translation Queue
- Processamento asincrono background
- Prioritizzazione job
- Retry automatico su errori
- Cost estimation
- Batch processing

### 🧭 URL Routing
- Pattern: `/en/slug-post` per versione inglese
- SEO-friendly
- Automatic language detection
- Canonical URLs

### 🔐 Security
- Nonce verification su tutti gli AJAX
- Input sanitization completa
- Output escaping
- API keys encrypted (AES-256-CBC)
- Role-based access control

---

## 🔌 Hook & API

### Actions
```php
// Dopo creazione traduzione
do_action( 'fpml_after_translation_saved', $translated_id, $original_id );

// Prima sync WooCommerce
do_action( 'fpml_before_wc_sync', $translated_id, $original_id );

// Prima sync menu
do_action( 'fpml_before_menu_sync', $menu_id_it, $menu_id_en );
```

### Filters
```php
// Aggiungi post types translatable
add_filter( 'fpml_translatable_post_types', function( $types ) {
    $types[] = 'my_custom_pt';
    return $types;
} );

// Aggiungi meta fields da tradurre
add_filter( 'fpml_meta_whitelist', function( $meta_keys ) {
    $meta_keys[] = '_my_custom_field';
    return $meta_keys;
} );

// Modifica prompt AI
add_filter( 'fpml_ai_prompt', function( $prompt, $content ) {
    return $prompt . ' Use formal tone.';
}, 10, 2 );
```

**Vedi**: [API Reference](docs/api-reference.md) per lista completa

---

## 🎯 Roadmap

### v0.10.0 (Q1 2025)
- [ ] Elementor Page Builder integration
- [ ] Polylang migration tool
- [ ] Multi-language admin UI
- [ ] Advanced Translation Memory

### v1.0.0 (Q2 2025)
- [ ] Supporto lingue aggiuntive (ES, FR, DE)
- [ ] Real-time collaborative translation
- [ ] Advanced SEO automation
- [ ] Performance dashboard

---

## 🆘 Supporto

### Documentazione
- 📖 [docs/](docs/) - Documentazione completa
- ❓ [FAQ](docs/faq.md) - Domande frequenti
- 🔧 [Troubleshooting](docs/troubleshooting.md) - Risoluzione problemi

### Community & Issues
- 🐙 [GitHub Issues](https://github.com/francescopasseri/FP-Multilanguage/issues)
- 📧 Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)

---

## 📊 Statistiche Plugin

- **Linee di codice**: ~15,000
- **Classi**: 47+
- **Integrazioni**: 5 (WooCommerce, Salient, Menu, FP-SEO, WPBakery)
- **Meta fields supportati**: 150+
- **Coverage traduzioni**: 98%
- **Test superati**: 100%
- **WordPress compatibility**: 5.8 - 6.4+
- **PHP compatibility**: 8.0 - 8.3

---

## 📄 Licenza

GPL-2.0-or-later - Vedi [LICENSE](LICENSE) per dettagli.

---

## 👨‍💻 Autore

**Francesco Passeri**
- 🌐 [francescopasseri.com](https://francescopasseri.com)
- 📧 [info@francescopasseri.com](mailto:info@francescopasseri.com)
- 🐙 [@francescopasseri](https://github.com/francescopasseri)

---

## 🙏 Contributi

Contributi, issues e feature requests sono benvenuti!

Vedi [CONTRIBUTING.md](CONTRIBUTING.md) per linee guida.

---

## ⭐ Credits

- **OpenAI** - GPT-5 nano AI translation
- **WordPress** - CMS platform
- **WooCommerce** - E-commerce platform
- **Salient Theme** - Premium WordPress theme

---

**Ultimo aggiornamento**: Novembre 2025  
**Versione corrente**: 0.9.1+  
**Status**: Production Ready ✅

### 🆕 Novità Recenti (v0.9.1+)
- ✨ **Commenti Annidati** - Supporto completo commenti threaded con mapping parent automatico
- 🛒 **Attributi WooCommerce** - Traduzione queue-based per attributi custom e opzioni
- 🔧 **Miglioramenti** - Rimossi placeholder `[PENDING TRANSLATION]`, workflow ottimizzato
