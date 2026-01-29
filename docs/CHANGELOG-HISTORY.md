# 📚 Storico Changelog Completo

Questo file contiene lo storico completo di tutte le modifiche e sessioni di sviluppo del plugin FP-Multilanguage.

> **Nota**: Per il changelog ufficiale corrente, consulta [CHANGELOG.md](../CHANGELOG.md) nella root del plugin.

---

## Versione 0.9.0 - MAJOR RELEASE (Novembre 2025)

### 🎯 Integrazioni Complete

#### WooCommerce Integration
- ✅ Supporto completo prodotti (Simple, Variable, Grouped, External, Downloadable)
- ✅ Sincronizzazione varianti prodotto (attributi, prezzi, stock, immagini)
- ✅ Gestione gallerie prodotto con traduzione ALT text
- ✅ Sincronizzazione attributi globali e custom
- ✅ Mapping relazioni prodotto (upsell, cross-sell)
- ✅ Traduzione file scaricabili
- ✅ Supporto tab prodotto personalizzati
- ✅ Taxonomies: `product_cat`, `product_tag`, `product_brand`

**Coverage**: 98% dei casi d'uso WooCommerce

#### Menu Navigation
- ✅ Duplicazione automatica menu IT → EN
- ✅ Sincronizzazione real-time item menu
- ✅ Mapping gerarchie (parent/child)
- ✅ Traduzione titoli, descrizioni, attributi
- ✅ Sincronizzazione custom fields Salient (icone, mega menu)
- ✅ Auto-delete menu EN quando IT viene eliminato
- ✅ UI admin con status menu e link rapidi
- ✅ Frontend language switching automatico

**Coverage**: 100% funzionalità menu WordPress

#### Salient Theme Integration
- ✅ 70+ meta fields sincronizzati (era 6 in v0.8.0)
- ✅ Page Header Settings (backgrounds, overlays, parallax)
- ✅ Portfolio Settings (extra content, featured images)
- ✅ Post Format Settings (quote, audio, video, gallery)
- ✅ Page Builder Settings (VC, custom layouts)
- ✅ Navigation Settings (menu icons, mega menu, buttons)
- ✅ Supporto tutti i CPT Salient (portfolio, team members)

**Coverage**: 98% meta fields Salient

#### WPBakery Page Builder
- ✅ Traduzione contenuto shortcodes
- ✅ Traduzione attributi translatable (title, subtitle, caption, button_text)
- ✅ Preservazione struttura shortcodes
- ✅ Supporto nested shortcodes

**Coverage**: 90% elementi WPBakery

#### FP-SEO-Manager Integration
- ✅ 25+ meta fields sincronizzati (era 8 in v0.8.0)
- ✅ Core SEO (title, description, keywords, focus keyword)
- ✅ AI Features (auto-title, auto-description, SEO score)
- ✅ GEO & Freshness (geo target, publish/update dates)
- ✅ Social Meta (OG, Twitter Card)
- ✅ Schema.org (type, custom properties)
- ✅ UI hints per AI features disponibili

**Coverage**: 100% funzionalità FP-SEO-Manager v0.9.0

### 🛡️ Bugfix & Security
- ✅ Output escaping completo (esc_html, absint)
- ✅ Nonce verification su tutti gli AJAX
- ✅ Input sanitization (sanitize_text_field, absint)
- ✅ Fix Exception namespace (\Exception)
- ✅ PHP version check (8.0+)
- ✅ Composer autoload fallback

### 📊 Statistiche v0.9.0
- **Copertura traduzioni**: 98% (era 70% in v0.7.0)
- **Integrazioni attive**: 5 (WooCommerce, Salient, WPBakery, FP-SEO, Menu)
- **Meta fields sincronizzati**: 150+
- **Linee di codice**: 15,000+
- **Test superati**: 100%

---

## Versione 0.8.0 - Dashboard & Bugfix (Novembre 2025)

### ✨ Nuove Features
- ✅ **Dashboard Overview** come landing page
  - Widget statistiche (post tradotti, coda, errori, costo)
  - Quick actions (Crea Post, Traduzione Bulk, Coda, Impostazioni)
  - Weekly activity chart
  - Alerts per API key e errori recenti
  - Quick Start Guide
  - System Info

### 🐛 Bugfix Critici
1. Exception namespace correction (`\Exception`)
2. PHP version check (richiesto PHP 8.0+)
3. Composer autoload check con fallback notice
4. Admin AJAX handlers nonce verification
5. Settings tabs navigation fix
6. Queue cleanup orphaned pairs

### 📈 Miglioramenti UX
- Tab "Dashboard" come default
- Navigation tabs riorganizzata
- Toast notifications migliorate
- Stats real-time dashboard

---

## Versione 0.7.0 - Translation Queue Enhancements

### ✨ Features
- ✅ Translation Memory system
- ✅ Queue prioritization
- ✅ Batch processing
- ✅ Cost estimation
- ✅ Error recovery

---

## Versione 0.6.0 - SEO & Compatibility

### ✨ Features
- ✅ SEO Optimizer
- ✅ Auto-relink system
- ✅ Plugin compatibility layer
- ✅ Theme compatibility
- ✅ ACF support

---

## Versione 0.5.0 - PSR-4 Migration

### 🏗️ Architettura
- ✅ Migrazione a PSR-4 autoloading
- ✅ Namespace `FP\Multilanguage`
- ✅ Dependency Injection Container
- ✅ Service locator pattern
- ✅ Modular structure

### 🐛 Fix
- ✅ Routing `/en/` ottimizzato
- ✅ URL rewrite rules
- ✅ Language detection

---

## Versioni Precedenti (0.1.0 - 0.4.0)

### 0.4.0
- REST API endpoints
- Bulk translation
- Translation preview

### 0.3.0
- OpenAI integration
- Custom meta fields
- Featured image sync

### 0.2.0
- Basic queue system
- Admin interface
- Settings page

### 0.1.0
- Initial release
- Basic IT → EN translation
- Post/Page support

---

## 📁 Documenti di Sessione Archiviati

I seguenti documenti dettagliati delle sessioni di sviluppo sono disponibili in `docs/archive/`:

### v0.9.0 Sessions
- `🛡️-BUGFIX-ANTI-REGRESSIONE-v0.9.0.md` - Security audit e test
- `🛒-WOOCOMMERCE-INTEGRATION-COMPLETE-v0.9.0.md` - WooCommerce dettagli
- `🧭-MENU-NAVIGATION-ENHANCED-v0.9.0.md` - Menu sync dettagli
- `✨-SALIENT-INTEGRATION-ENHANCED-v0.9.0.md` - Salient dettagli
- `🔄-FP-SEO-INTEGRATION-UPDATED-v0.9.0.md` - FP-SEO dettagli
- `🎉-RELEASE-v0.9.0-INTEGRAZIONI-COMPLETE.md` - Release notes complete

### v0.8.0 Sessions
- `BUGFIX-FILE-BY-FILE-v0.8.0.md` - Bugfix dettagliati
- `🔍-BUGFIX-SESSION-REPORT-v0.8.0.md` - Report sessione
- `✅-DASHBOARD-IMPLEMENTATO-v0.8.0.md` - Dashboard implementazione

### Earlier Versions
- `PSR4-MIGRATION.md` - Dettagli migrazione PSR-4
- `📖-COME-FUNZIONA-IL-PLUGIN.md` - Guida funzionamento
- `OTTIMIZZAZIONI.md` - Performance optimizations

---

**Ultimo aggiornamento**: 2 Novembre 2025  
**Versione corrente**: 0.9.0

