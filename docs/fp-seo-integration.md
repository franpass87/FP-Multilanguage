# FP SEO Manager Integration

## Overview

**FP-Multilanguage** integra automaticamente con **FP-SEO-Manager** per sincronizzare i metadati SEO, mostrare metriche Google Search Console per entrambe le lingue, e abilitare la generazione AI di meta SEO anche per le versioni tradotte.

---

## Features

### 1. ✅ **Auto-Sync SEO Meta**

Quando un post viene tradotto da IT → EN, i seguenti meta SEO vengono sincronizzati automaticamente:

| Meta Field | Comportamento |
|------------|---------------|
| **Meta Description** | Tradotto automaticamente (se Translation Manager attivo) o copiato |
| **Robots Directive** | Copiato identico (stesso per tutte le lingue) |
| **Canonical URL** | Impostato all'URL della versione EN |

#### Esempio

```php
// Post IT #123 ha:
// - Meta Description: "Questo è un articolo fantastico"
// - Robots: "index, follow"

// Quando viene tradotto in EN:
// Post EN #456 avrà:
// - Meta Description: "This is a fantastic article" (tradotto via AI)
// - Robots: "index, follow" (identico)
// - Canonical: "https://site.com/en/fantastic-article/" (auto-generato)
```

---

### 2. 📊 **GSC Metrics Comparison**

Nel metabox "🌍 Traduzioni" dell'editor WordPress, viene mostrato un confronto dei dati Google Search Console per IT vs EN:

```
┌─────────────────────────────────────────┐
│ 📊 Google Search Console (28 giorni)   │
├──────────────────┬──────────────────────┤
│ 🇮🇹 Italiano      │ 🇬🇧 English          │
│ 234 click        │ 189 click            │
│ 1,245 impression │ 987 impression       │
│ CTR: 18.8%       │ CTR: 19.1%           │
│ Pos: 5.3         │ Pos: 6.7             │
├──────────────────┴──────────────────────┤
│ Differenza EN vs IT: 📉 -45 click       │
└─────────────────────────────────────────┘
```

Questo ti permette di:
- ✅ Vedere performance IT vs EN a colpo d'occhio
- ✅ Identificare contenuti che performano meglio in una lingua
- ✅ Ottimizzare le versioni deboli

---

### 3. 🤖 **AI SEO Generation Hint**

Se FP-SEO-Manager ha l'AI abilitata, viene mostrato un suggerimento per generare meta SEO ottimizzati:

```
┌─────────────────────────────────────────┐
│ 🤖 AI SEO Disponibile                   │
│ Genera meta SEO ottimizzati per la      │
│ versione inglese con FP SEO Manager     │
│ [✨ Apri Editor EN → Genera SEO AI]     │
└─────────────────────────────────────────┘
```

Click sul pulsante per:
1. Aprire l'editor della versione EN
2. Usare il pulsante "🤖 Genera AI" di FP-SEO
3. Generare automaticamente:
   - SEO Title ottimizzato
   - Meta Description (155 chars)
   - Slug SEO-friendly
   - Focus Keyword suggestions

---

## Setup

### Requirements

1. **FP-Multilanguage**: v0.6.0+
2. **FP-SEO-Manager**: v0.9.0+
3. WordPress: 6.2+
4. PHP: 8.0+

### Installation

L'integrazione è **automatica**! Non serve configurazione.

1. Installa entrambi i plugin
2. Attiva entrambi i plugin
3. L'integrazione si attiverà automaticamente

### Verification

```bash
# WP-CLI
wp eval 'var_dump( defined("FP_SEO_PERFORMANCE_VERSION") && class_exists("FP\Multilanguage\Integrations\FpSeoSupport") );'
# Output: bool(true) ✅
```

---

## Usage

### Scenario 1: Nuovo Post con SEO

```php
// 1. Crea un post IT con SEO meta
$post_id = wp_insert_post([
    'post_title' => 'Guida WordPress',
    'post_content' => 'Contenuto...',
    'post_status' => 'publish'
]);

// 2. Aggiungi meta SEO (manualmente o via FP-SEO)
update_post_meta($post_id, '_fp_seo_meta_description', 'La migliore guida WordPress del 2025');
update_post_meta($post_id, '_fp_seo_meta_robots', 'index, follow');

// 3. Traduci (automaticamente o manualmente)
// FP-Multilanguage creerà il post EN e sincronizzerà i meta SEO

// 4. Verifica
$en_id = get_post_meta($post_id, '_fpml_pair_id', true);
$en_desc = get_post_meta($en_id, '_fp_seo_meta_description', true);
// Output: "The best WordPress guide of 2025" ✅
```

### Scenario 2: Confronto GSC Performance

1. Vai su **Post IT** → Editor
2. Scroll alla sidebar → **🌍 Traduzioni** metabox
3. Verifica la sezione **📊 Google Search Console**
4. Confronta IT vs EN clicks/impressions
5. Se EN performa male → Click su "✨ Apri Editor EN → Genera SEO AI"

### Scenario 3: Ottimizzazione SEO Cross-Language

```php
// Hook personalizzato per ottimizzazioni avanzate
add_action('fpml_seo_meta_synced', function($en_id, $it_id) {
    // Es: Notifica SEO Manager di rianalizzare
    do_action('fp_seo_analyze_post', $en_id);
    
    // Es: Aggiorna sitemap
    do_action('fp_seo_flush_sitemap');
}, 10, 2);
```

---

## Hooks

### Actions

#### `fpml_after_translation_saved`
Fires after a translation is created and saved.

```php
add_action('fpml_after_translation_saved', function($translated_id, $original_id) {
    // Custom logic
}, 10, 2);
```

**Parameters:**
- `$translated_id` (int): Translated post ID
- `$original_id` (int): Original post ID

---

#### `fpml_seo_meta_synced`
Fires after SEO meta is synchronized.

```php
add_action('fpml_seo_meta_synced', function($translated_id, $original_id) {
    error_log("SEO synced from #{$original_id} to #{$translated_id}");
}, 10, 2);
```

---

#### `fpml_translation_metabox_after_status`
Fires in translation metabox after status display.

```php
add_action('fpml_translation_metabox_after_status', function($post_id, $english_id) {
    echo '<div>Custom UI here</div>';
}, 10, 2);
```

---

#### `fpml_translation_metabox_after_actions`
Fires in translation metabox after action buttons.

```php
add_action('fpml_translation_metabox_after_actions', function($post_id, $english_id) {
    echo '<button>Custom Action</button>';
}, 10, 2);
```

---

## Filters

_(Coming soon)_

---

## API

### Check if Integration is Active

```php
if (class_exists('FP\Multilanguage\Integrations\FpSeoSupport')) {
    $integration = \FP\Multilanguage\Integrations\FpSeoSupport::instance();
    // Integration is active
}
```

### Manual Sync

```php
$integration = \FP\Multilanguage\Integrations\FpSeoSupport::instance();
$integration->sync_seo_meta_to_translation($en_post_id, $it_post_id);
```

---

## Troubleshooting

### Meta Description Not Translated

**Problema**: Meta description copiata in italiano invece di tradotta.

**Causa**: Translation Manager non attivo.

**Fix**:
```php
// Verifica che TranslationManager sia registrato
wp eval 'var_dump(class_exists("FP\Multilanguage\Content\TranslationManager"));'
```

---

### GSC Metrics Not Showing

**Problema**: Sezione GSC non appare nel metabox.

**Cause possibili**:
1. GSC non configurato in FP-SEO
2. Nessun dato per questo URL
3. Plugin FP-SEO non attivo

**Fix**:
1. Vai su **FP SEO** → **Google Search Console** → Settings
2. Verifica autenticazione Service Account
3. Click "Test Connection" → deve essere ✅

---

### AI Button Not Showing

**Problema**: Hint AI non visibile.

**Causa**: AI generation disabilitata in FP-SEO.

**Fix**:
1. Vai su **FP SEO** → **AI Settings**
2. Abilita "Enable AI Auto Generation"
3. Inserisci OpenAI API Key
4. Salva

---

## Performance

### Impact

L'integrazione ha un impatto minimo sulle performance:

| Operazione | Overhead | Note |
|------------|----------|------|
| Sync Meta | ~5ms | Solo alla creazione traduzione |
| GSC Display | 0ms | Dati cached da FP-SEO |
| Hook Registration | 0.1ms | Una volta all'init |

**Total**: < 10ms su una traduzione

### Optimization

Se hai migliaia di traduzioni:

```php
// Disabilita GSC display per post type non necessari
add_filter('fpml_seo_show_gsc_for_post_type', function($show, $post_type) {
    return in_array($post_type, ['post', 'page']);
}, 10, 2);
```

---

## Roadmap

### v0.7.0 (Q1 2025)
- [ ] Sync anche Focus Keyword
- [ ] AI Translation per Meta Description via FP-SEO AI
- [ ] Bulk SEO Sync tool

### v0.8.0 (Q2 2025)
- [ ] Multilingua (DE, FR, ES) + SEO
- [ ] Schema.org translation sync
- [ ] GEO Claims translation

---

## FAQ

**Q: I meta SEO vengono tradotti automaticamente?**  
A: Sì, se hai Translation Manager attivo. Altrimenti vengono copiati e puoi modificarli manualmente o rigenerarli con AI.

**Q: Posso disabilitare la sincronizzazione?**  
A: Sì:
```php
remove_action('fpml_after_translation_saved', [
    \FP\Multilanguage\Integrations\FpSeoSupport::instance(),
    'sync_seo_meta_to_translation'
]);
```

**Q: Funziona con altri plugin SEO (Yoast, RankMath)?**  
A: No, è specifico per FP-SEO-Manager. Per altri plugin, usa gli hook personalizzati.

---

## Support

- **GitHub Issues**: [francescopasseri/fp-multilanguage](https://github.com/francescopasseri/fp-multilanguage)
- **Docs**: [docs/](../docs/)
- **Email**: francesco@francescopasseri.com

---

**Integrazione sviluppata con ❤️ da Francesco Passeri**

