# 🧭 Menu Navigation Integration

**Versione**: 0.9.0+  
**Coverage**: 100%  
**File**: `src/MenuSync.php`

---

## 📋 Panoramica

Sincronizzazione automatica e completa dei menu di navigazione WordPress, con supporto per gerarchie, custom fields, e language switching frontend.

---

## ✨ Features Principali

### 1. Auto-Sync Bidirezionale
```php
add_action( 'wp_update_nav_menu', 'auto_sync_menu', 10, 2 );
add_action( 'wp_update_nav_menu_item', 'sync_single_menu_item', 10, 3 );
```

**Comportamento**:
- Salvi menu IT → menu EN creato/aggiornato automaticamente
- Aggiungi item IT → item EN aggiunto automaticamente
- Elimini menu IT → menu EN eliminato automaticamente

### 2. Mapping Gerarchie
```php
// Parent/Child relationships preservati
protected function prepare_menu_item_args( $source_item, $menu_id, $parent_map )
```

**Esempio**:
```
IT Menu:
├── Home
├── Shop
│   ├── Categorie
│   └── Offerte
└── Contatti

EN Menu (auto):
├── Home
├── Shop
│   ├── Categories (parent mapping preservato)
│   └── Deals (parent mapping preservato)
└── Contacts
```

### 3. Translation Queue Integration
```php
'menu-item-title'       => $source_item->title,  // Tradotto via queue
'menu-item-description' => '[PENDING TRANSLATION] ' . $description,
'menu-item-attr-title'  => '[PENDING TRANSLATION] ' . $attr_title,
```

**Cosa viene tradotto**:
- Titolo menu item
- Descrizione (tooltip)
- Attributo title (accessibility)

### 4. Custom Fields Salient
```php
protected function sync_menu_item_custom_fields( $en_item_id, $it_item_id )
```

**Campi sincronizzati**:
```php
'_menu_item_icon'              // Icona personalizzata
'_menu_item_mega_menu'         // Impostazioni mega menu
'_menu_item_button_style'      // Stile pulsante CTA
'_menu_item_hide_text'         // Nascondi testo (solo icona)
'_menu_item_icon_position'     // Posizione icona
'_menu_item_nolink'            // Disabilita link
'_menu_item_highlight'         // Evidenzia item
```

### 5. Frontend Language Switching
```php
add_filter( 'wp_get_nav_menu_items', 'filter_menu_items_by_language', 10, 3 );
```

**Comportamento**:
- URL IT (`/shop`) → mostra menu IT
- URL EN (`/en/shop`) → mostra menu EN automaticamente

### 6. Admin UI Enhancement
```php
add_action( 'admin_enqueue_scripts', 'enqueue_admin_scripts' );
```

**Features UI**:
- Widget status menu con link a menu EN
- Badge "🌍 Sincronizzato" se menu EN esiste
- Warning se menu EN non esiste
- Link diretto per editare menu EN

---

## 🔄 Menu Locations Management

### Problema Risolto (v0.9.0)

**Prima** (v0.8.0 - BUG):
```php
// Sovrascriveva direttamente le locations
set_theme_mod( 'nav_menu_locations', $locations ); // ❌ Rompeva menu IT
```

**Dopo** (v0.9.0 - FIX):
```php
// Salva associazioni in option separata
update_option( 'fpml_en_menu_locations', $en_locations ); // ✅ IT menu safe
```

**Risultato**:
- Menu IT rimane assegnato alle sue locations
- Menu EN viene recuperato quando richiesto (frontend `/en/`)
- Nessuna interferenza tra lingue

---

## 💡 Esempi d'Uso

### Caso 1: Menu Header Standard

**Menu IT**:
```
Menu: "Menu Principale"
Location: primary
Items:
  - Home (URL: /)
  - Chi Siamo (URL: /chi-siamo)
  - Servizi (URL: /servizi)
```

**Menu EN (auto)**:
```
Menu: "Menu Principale (EN)"
Location: primary (stored in fpml_en_menu_locations)
Items:
  - Home (URL: /) → Title: "Home" (tradotto)
  - Chi Siamo (URL: /chi-siamo) → Title: "[PENDING] Chi Siamo" → "About Us"
  - Servizi (URL: /servizi) → Title: "[PENDING] Servizi" → "Services"
```

### Caso 2: Menu con Mega Menu (Salient)

**Menu IT**:
```
Item: "Prodotti"
  _menu_item_mega_menu: "enabled"
  _menu_item_icon: "icon-cart"
  Child Items:
    - Categoria A
    - Categoria B
```

**Menu EN (auto)**:
```
Item: "Products" (tradotto)
  _menu_item_mega_menu: "enabled" (copiato)
  _menu_item_icon: "icon-cart" (copiato)
  Child Items:
    - Category A (tradotto, parent mapping OK)
    - Category B (tradotto, parent mapping OK)
```

### Caso 3: Custom Links

**Menu IT**:
```
Item: "Scarica Catalogo"
Type: Custom Link
URL: https://example.com/catalogo.pdf
```

**Menu EN (auto)**:
```
Item: "Download Catalog" (tradotto)
Type: Custom Link
URL: https://example.com/catalogo.pdf (copiato)
```

---

## 🎨 Admin UI Preview

### Menu Status Widget

Quando editi un menu IT:

```
┌─────────────────────────────────────────────┐
│ 🌍 Menu Inglese:                           │
│ Menu Principale (EN) (12 items) ✓ Sincronizzato │
│ [Modifica Menu EN →]                       │
└─────────────────────────────────────────────┘
```

Quando menu EN non esiste ancora:

```
┌─────────────────────────────────────────────┐
│ 🌍 Menu Inglese:                           │
│ Sarà creato automaticamente al salvataggio │
└─────────────────────────────────────────────┘
```

---

## 🔧 API & Hooks

### Actions

```php
// Prima della sincronizzazione menu
do_action( 'fpml_before_menu_sync', $menu_id_it, $menu_id_en );

// Dopo la sincronizzazione menu
do_action( 'fpml_after_menu_sync', $menu_id_it, $menu_id_en, $synced_items );

// Prima della sincronizzazione item
do_action( 'fpml_before_menu_item_sync', $item_id_it, $item_id_en );

// Dopo la sincronizzazione item
do_action( 'fpml_after_menu_item_sync', $item_id_it, $item_id_en );
```

### Filters

```php
// Modifica args prima di creare menu item EN
apply_filters( 'fpml_menu_item_args', $args, $source_item, $menu_id );

// Modifica custom fields da sincronizzare
apply_filters( 'fpml_menu_custom_fields', $custom_fields, $item_id );

// Modifica mapping parent
apply_filters( 'fpml_menu_parent_map', $parent_map, $menu_id );
```

---

## 🔍 AJAX Endpoints

### 1. Manual Sync

```javascript
POST /wp-admin/admin-ajax.php
action: fpml_sync_menu
menu_id: 123
nonce: xxxxx
```

**Response**:
```json
{
  "success": true,
  "data": {
    "message": "Menu sincronizzato con successo",
    "items_synced": 12,
    "en_menu_id": 456
  }
}
```

### 2. Get Menu Status

```javascript
POST /wp-admin/admin-ajax.php
action: fpml_get_menu_status
menu_id: 123
_wpnonce: xxxxx
```

**Response**:
```json
{
  "success": true,
  "data": {
    "has_en_menu": true,
    "en_menu_id": 456,
    "en_menu_name": "Menu Principale (EN)",
    "items_count": 12,
    "last_sync": "2025-11-02 20:30:15"
  }
}
```

---

## ⚙️ Configurazione

Nessuna configurazione necessaria. L'integrazione è sempre attiva.

### Storage

```php
// Menu EN IDs mapping
get_option( 'fpml_menu_mapping' );
// [123 => 456, 789 => 101]

// Menu locations EN
get_option( 'fpml_en_menu_locations' );
// ['primary' => 456, 'footer' => 101]

// Per singolo menu item
get_post_meta( $item_id, '_fpml_translation_id' );
```

---

## ⚠️ Limitazioni Note

1. **Custom Fields Terze Parti**: Solo Salient custom fields supportati (v0.9.0)
2. **Mega Menu Plugins**: Plugin terze parti non supportati (solo Salient native)
3. **Dynamic Menu Items**: Items generati dinamicamente via code non sincronizzati

---

## 📊 Coverage Dettagliata

| Funzionalità | Coverage | Note |
|--------------|----------|------|
| Menu Creation | 100% | ✅ Auto-create |
| Item Sync | 100% | ✅ Bi-directional |
| Hierarchies | 100% | ✅ Parent/child mapping |
| Titles | 100% | ✅ Translation queue |
| Descriptions | 100% | ✅ Translation queue |
| Custom Links | 100% | ✅ URL preserved |
| Post/Page Links | 100% | ✅ Auto-mapped to EN post |
| Term Links | 100% | ✅ Auto-mapped to EN term |
| Menu Locations | 100% | ✅ Separate storage (v0.9.0 fix) |
| Salient Custom Fields | 100% | ✅ All 7 fields |
| Auto-Delete | 100% | ✅ Cascade deletion |
| Frontend Switch | 100% | ✅ Language detection |
| Admin UI | 100% | ✅ Status widget + AJAX |

**Coverage Globale**: **100%**

---

## 🚀 Prossimi Sviluppi

- [ ] Supporto Mega Menu Builder plugin
- [ ] Visual menu item editor
- [ ] Menu preview side-by-side (IT vs EN)
- [ ] Bulk menu sync (tutti i menu in un click)

---

**Documentazione aggiornata**: 2 Novembre 2025  
**Versione integrazione**: 0.9.0  
**Compatibilità WordPress**: 5.8+

