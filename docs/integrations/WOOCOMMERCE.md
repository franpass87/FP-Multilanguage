# 🛒 WooCommerce Integration

**Versione**: 0.9.0+  
**Coverage**: 98%  
**File**: `src/Integrations/WooCommerceSupport.php`

---

## 📋 Panoramica

L'integrazione WooCommerce fornisce supporto completo per la traduzione di tutti i tipi di prodotti e le loro caratteristiche.

### ✅ Prodotti Supportati

- ✅ **Simple Products** - Prodotti semplici
- ✅ **Variable Products** - Prodotti con varianti
- ✅ **Grouped Products** - Prodotti raggruppati
- ✅ **External/Affiliate** - Prodotti esterni
- ✅ **Downloadable** - Prodotti scaricabili
- ✅ **Virtual** - Prodotti virtuali

---

## 🔄 Sincronizzazione Automatica

### 1. Varianti Prodotto
```php
// Sync automatico dopo traduzione
add_action( 'fpml_after_translation_saved', 'sync_product_variations', 10, 2 );
```

**Cosa viene sincronizzato**:
- Attributi variante (colore, taglia, etc.)
- Prezzi (regolare, scontato)
- Stock e disponibilità
- SKU
- Immagine variante
- Descrizione variante (tradotta)

### 2. Galleria Prodotto
```php
add_action( 'fpml_after_translation_saved', 'sync_product_gallery', 15, 2 );
```

**Cosa viene sincronizzato**:
- Immagine in evidenza (`_thumbnail_id`)
- Galleria immagini (`_product_image_gallery`)
- ALT text immagini (tradotto)

### 3. Attributi Prodotto
```php
add_action( 'fpml_after_translation_saved', 'sync_product_attributes', 20, 2 );
```

**Cosa viene sincronizzato**:
- Attributi globali (pa_color, pa_size)
- Attributi custom con label e opzioni
- **Queue-based translation** - Attributi custom vengono accodati per traduzione AI
- Opzioni attributi tradotte automaticamente tramite queue
- Visibilità attributi
- Rimossi placeholder `[PENDING TRANSLATION]` - ora usa sistema queue integrato

### 4. Relazioni Prodotto
```php
add_action( 'fpml_after_translation_saved', 'sync_product_relations', 25, 2 );
```

**Cosa viene sincronizzato**:
- Upsell (`_upsell_ids`)
- Cross-sell (`_crosssell_ids`)
- Mapping automatico ID prodotti IT → EN

### 5. File Scaricabili
```php
add_action( 'fpml_after_translation_saved', 'sync_downloadable_files', 30, 2 );
```

**Cosa viene sincronizzato**:
- Array file scaricabili
- Nomi file (tradotti)
- URL file (copiati)
- Limiti download

### 6. Tab Personalizzati
```php
add_action( 'fpml_after_translation_saved', 'sync_product_tabs', 35, 2 );
```

**Cosa viene sincronizzato**:
- Titolo tab (tradotto)
- Contenuto tab (tradotto)
- Priorità tab

---

## 📦 Meta Fields Sincronizzati

### Campi Copiati (Non Tradotti)
```php
'_sku', '_regular_price', '_sale_price', '_price',
'_stock', '_stock_status', '_manage_stock',
'_sold_individually', '_virtual', '_downloadable',
'_weight', '_length', '_width', '_height',
'_product_attributes', '_thumbnail_id',
'_product_image_gallery', '_upsell_ids',
'_crosssell_ids', '_tax_status', '_tax_class'
```

### Campi Tradotti
```php
'_purchase_note',           // Nota acquisto
'_product_tab_title',       // Titolo tab custom
'_product_tab_content',     // Contenuto tab custom
'_variation_description',   // Descrizione variante
'_button_text',             // Testo pulsante (External)
```

---

## 🏷️ Tassonomie

```php
'product_cat'    // Categorie prodotto
'product_tag'    // Tag prodotto
'product_brand'  // Brand prodotto (se attivo)
```

---

## 💡 Esempi d'Uso

### Caso 1: Prodotto Variabile con Varianti

**Prodotto IT**:
- Nome: "T-Shirt Premium"
- Varianti: S, M, L, XL
- Prezzo: €29.99
- Galleria: 5 immagini

**Risultato EN (automatico)**:
- Nome: "Premium T-Shirt" (tradotto via AI)
- Varianti: S, M, L, XL (copiate)
- Prezzo: €29.99 (copiato)
- Galleria: 5 immagini (ID copiati, ALT text tradotti)

### Caso 2: Prodotto con Upsell/Cross-sell

**Prodotto IT**:
- ID: 123
- Upsell: [456, 789]
- Cross-sell: [101, 202]

**Risultato EN**:
- Upsell: [457, 790] (ID mappati automaticamente IT → EN)
- Cross-sell: [102, 203] (ID mappati automaticamente)

### Caso 3: Prodotto Scaricabile

**Prodotto IT**:
- File: "guida-utente.pdf"
- Nome: "Guida all'uso"

**Risultato EN**:
- File: "guida-utente.pdf" (stesso file)
- Nome: "User Guide" (tradotto)

---

## 🎯 Hook Disponibili

```php
// Prima della sincronizzazione WooCommerce
do_action( 'fpml_before_wc_sync', $translated_id, $original_id );

// Dopo la sincronizzazione WooCommerce
do_action( 'fpml_after_wc_sync', $translated_id, $original_id );
```

---

## 🔧 Configurazione

Nessuna configurazione necessaria. L'integrazione si attiva automaticamente se WooCommerce è presente.

### Verifica Attivazione
```php
if ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) {
    // Integration attiva
}
```

---

## ⚠️ Limitazioni Note

1. **Attributi Custom**: Le opzioni vengono accodate automaticamente per traduzione AI (v0.9.1+)
2. **Review Clienti**: Non vengono tradotte (UGC)
3. **Stock Locations**: Plugin terze parti non supportati

---

## 📊 Coverage Dettagliata

| Funzionalità | Coverage | Note |
|--------------|----------|------|
| Prodotti Simple | 100% | ✅ Completo |
| Prodotti Variable | 98% | 📌 Attributi custom opzioni pending |
| Prodotti Grouped | 100% | ✅ Mapping prodotti |
| Prodotti External | 100% | ✅ URL + button text |
| Prodotti Downloadable | 95% | ✅ Nome file tradotti |
| Gallerie | 100% | ✅ ALT text tradotti |
| Tassonomie | 100% | ✅ Cat + Tag + Brand |
| Upsell/Cross-sell | 100% | ✅ Mapping automatico |
| Tab Custom | 100% | ✅ Titolo + contenuto |

**Coverage Globale**: **98%**

---

## 🚀 Prossimi Sviluppi

- [ ] Queue dedicata per attributi custom opzioni
- [ ] Supporto Subscriptions
- [ ] Supporto Bookings
- [ ] Product Bundles translation

---

**Documentazione aggiornata**: 2 Novembre 2025  
**Versione integrazione**: 0.9.0

