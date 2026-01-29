# 🔄 FP-SEO-Manager Integration

**Versione**: 0.9.0+  
**Coverage**: 100%  
**File**: `src/Integrations/FpSeoSupport.php`

---

## 📋 Panoramica

Integrazione profonda con FP-SEO-Manager (v0.9.0+), sincronizzando tutti i meta fields SEO, AI features, GEO data, social meta e schema.org.

---

## 🎯 Meta Fields Sincronizzati

### 1. Core SEO (5 campi)

```php
'_fp_seo_title'        // Meta title (TRADOTTO)
'_fp_seo_description'  // Meta description (TRADOTTA)
'_fp_seo_keywords'     // Keywords (TRADOTTE)
'_fp_seo_focus_kw'     // Focus keyword (TRADOTTO)
'_fp_seo_canonical'    // Canonical URL (MAPPATO /en/)
```

**Gestione**:
- **Title**: Marcato `[PENDING TRANSLATION]` → tradotto via AI
- **Description**: Marcato `[PENDING TRANSLATION]` → tradotto via AI
- **Keywords**: Marcate `[PENDING TRANSLATION]` → tradotte via AI
- **Focus KW**: Marcato `[PENDING TRANSLATION]` → tradotto via AI
- **Canonical**: URL riscritto automaticamente con `/en/`

---

### 2. AI Features (5 campi)

```php
'_fp_seo_ai_title'        // AI-generated title disponibile
'_fp_seo_ai_description'  // AI-generated description disponibile
'_fp_seo_ai_suggestions'  // AI suggestions
'_fp_seo_score'           // SEO score (copiato)
'_fp_seo_auto_optimize'   // Auto-optimize enabled (copiato)
```

**Gestione**:
- **ai_title/description**: Flag copiati (l'AI può rigenerare per EN)
- **Score**: Copiato come baseline (può essere ricalcolato)
- **Auto-optimize**: Impostazione copiata

**UI Hint**:
```php
// Mostra hint in metabox se AI features disponibili
if ( $ai_title_available ) {
    echo '💡 Puoi rigenerare title SEO con AI per la versione inglese';
}
```

---

### 3. GEO & Freshness (4 campi)

```php
'_fp_seo_geo_target'     // Target geografico (COPIATO)
'_fp_seo_publish_date'   // Data pubblicazione (COPIATA)
'_fp_seo_update_date'    // Data aggiornamento (COPIATA)
'_fp_seo_freshness'      // Freshness score (COPIATO)
```

**Gestione**:
- **Geo target**: Copiato (stesso target geografico)
- **Dates**: Copiate (sincronizzate con post IT)
- **Freshness**: Score copiato

---

### 4. Social Meta (6 campi)

```php
// Open Graph
'_fp_seo_og_title'        // OG title (TRADOTTO)
'_fp_seo_og_description'  // OG description (TRADOTTA)
'_fp_seo_og_image'        // OG image ID (COPIATO)

// Twitter Card
'_fp_seo_twitter_title'       // Twitter title (TRADOTTO)
'_fp_seo_twitter_description' // Twitter description (TRADOTTA)
'_fp_seo_twitter_image'       // Twitter image ID (COPIATO)
```

**Gestione**:
- **Titles/Descriptions**: Tradotti via AI
- **Images**: ID copiati (stessa immagine social)

---

### 5. Schema.org (4 campi)

```php
'_fp_seo_schema_type'        // Tipo schema (Article, Product, etc.) (COPIATO)
'_fp_seo_schema_properties'  // Proprietà schema custom (COPIATO)
'_fp_seo_breadcrumbs'        // Breadcrumbs custom (TRADOTTI)
'_fp_seo_faq_schema'         // FAQ schema (TRADOTTO)
```

**Gestione**:
- **Type**: Copiato (stesso tipo di contenuto)
- **Properties**: Copiate (configurazione identica)
- **Breadcrumbs**: Tradotti
- **FAQ**: Tradotto (domande e risposte)

---

## 🔄 Processo Sincronizzazione

```php
add_action( 'fpml_after_translation_saved', array( $this, 'sync_seo_meta_to_translation' ), 10, 2 );
```

**Flow**:
1. Post IT tradotto → EN post creato
2. Hook `fpml_after_translation_saved` triggered
3. `sync_seo_meta_to_translation()` chiamato
4. Esegue 6 metodi specializzati:
   - `sync_core_seo_meta()` - 5 campi
   - `sync_keywords_meta()` - Keywords processing
   - `sync_ai_features_meta()` - 5 campi
   - `sync_geo_freshness_meta()` - 4 campi
   - `sync_social_meta()` - 6 campi
   - `sync_schema_meta()` - 4 campi
5. Log: "SEO sync completed: X meta fields"

---

## 💡 Esempio Pratico

### Post IT con FP-SEO

```php
_fp_seo_title: "Guida Completa WooCommerce 2025"
_fp_seo_description: "Scopri come configurare WooCommerce in 10 minuti"
_fp_seo_focus_kw: "configurare woocommerce"
_fp_seo_keywords: "woocommerce, e-commerce, tutorial"
_fp_seo_canonical: "https://example.com/guida-woocommerce"
_fp_seo_ai_title: "1" (AI title disponibile)
_fp_seo_og_title: "Guida WooCommerce | Example.com"
_fp_seo_schema_type: "Article"
```

### Post EN (auto-sync)

```php
_fp_seo_title: "[PENDING TRANSLATION] Guida Completa..." (in queue)
_fp_seo_description: "[PENDING TRANSLATION] Scopri..." (in queue)
_fp_seo_focus_kw: "[PENDING TRANSLATION] configurare woocommerce" (in queue)
_fp_seo_keywords: "[PENDING TRANSLATION] woocommerce, e-commerce..." (in queue)
_fp_seo_canonical: "https://example.com/en/guida-woocommerce" (auto /en/)
_fp_seo_ai_title: "1" (flag copiato, può rigenerare in EN)
_fp_seo_og_title: "[PENDING TRANSLATION] Guida WooCommerce..." (in queue)
_fp_seo_schema_type: "Article" (copiato)
```

---

## 🎨 UI Enhancements

### 1. GSC Comparison Widget

Mostra confronto Google Search Console tra versione IT e EN:

```php
add_action( 'fpml_translation_metabox_after_status', 'render_gsc_comparison' );
```

**Display**:
```
📊 Google Search Console
IT: 1,234 clicks | 5,678 impressions | CTR 21.7%
EN: 567 clicks | 2,345 impressions | CTR 24.2%
```

### 2. AI SEO Hint

Mostra suggerimenti AI disponibili:

```php
add_action( 'fpml_translation_metabox_after_actions', 'render_ai_seo_hint' );
```

**Display**:
```
💡 AI SEO Features Disponibili:
✅ Auto-generate SEO Title
✅ Auto-generate Description
ℹ️ Puoi rigenerare questi campi per la versione inglese
```

---

## 🔗 Hook Disponibili

```php
// Prima sync SEO
do_action( 'fpml_before_seo_sync', $translated_id, $original_id );

// Dopo sync SEO
do_action( 'fpml_seo_meta_synced', $translated_id, $original_id, $synced_count );
```

---

## 🔧 Configurazione

Nessuna configurazione necessaria. L'integrazione si attiva automaticamente se FP-SEO-Manager è presente.

### Verifica Attivazione

```php
if ( defined( 'FP_SEO_VERSION' ) && version_compare( FP_SEO_VERSION, '0.9.0', '>=' ) ) {
    // Integration attiva (full features)
}
```

**Compatibilità**:
- FP-SEO-Manager < 0.9.0: Supporto parziale (core SEO fields)
- FP-SEO-Manager >= 0.9.0: Supporto completo (100% features)

---

## ⚠️ Note Importanti

### Canonical URL Rewriting

Il canonical URL viene automaticamente riscritto per la versione EN:

```php
// IT
"https://example.com/mio-articolo"

// EN (auto)
"https://example.com/en/mio-articolo"
```

### AI Features Re-generation

Anche se i flag AI sono copiati, l'utente può:
1. Rigenerare title/description AI specifici per EN
2. Ottimizzare per keywords EN diverse
3. Ottenere un nuovo SEO score per EN

---

## 📊 Coverage Dettagliata

| Categoria | Campi | Tradotti | Copiati | Coverage |
|-----------|-------|----------|---------|----------|
| Core SEO | 5 | 4 | 1 | 100% |
| AI Features | 5 | 0 | 5 | 100% |
| GEO & Freshness | 4 | 0 | 4 | 100% |
| Social Meta | 6 | 4 | 2 | 100% |
| Schema.org | 4 | 2 | 2 | 100% |
| **TOTALE** | **24** | **10** | **14** | **100%** |

---

## 🚀 Prossimi Sviluppi

- [ ] Sync GSC data (clicks, impressions) bidirezionale
- [ ] Auto-suggest keywords EN based on IT keywords + AI
- [ ] Schema.org advanced validation
- [ ] Hreflang tag auto-generation

---

**Documentazione aggiornata**: 2 Novembre 2025  
**Versione integrazione**: 0.9.0  
**Compatibilità FP-SEO-Manager**: 0.9.0+

