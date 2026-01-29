# 🔌 Plugin Compatibility - Integrazioni Complete

**Versione**: 0.9.0+  
**Ultimo aggiornamento**: 2 Novembre 2025

## Panoramica

FP Multilanguage include **integrazioni native complete** per i plugin più popolari, con sincronizzazione automatica e copertura 98%+ dei casi d'uso.

## 🎯 Integrazioni Native Complete (v0.9.0)

### 🔄 FP-SEO-Manager - **100% Coverage**

**Status**: ✅ Integrazione Proprietaria Completa  
**File**: `src/Integrations/FpSeoSupport.php`  
**Documentazione**: [docs/integrations/FP-SEO-MANAGER.md](integrations/FP-SEO-MANAGER.md)

**Features**:
- ✅ 25+ meta fields sincronizzati
- ✅ Core SEO (title, description, keywords, focus keyword, canonical)
- ✅ AI Features (auto-title, auto-description, SEO score)
- ✅ GEO & Freshness (geo target, dates)
- ✅ Social Meta (OG, Twitter Card)
- ✅ Schema.org (type, properties)
- ✅ UI hints per AI features

**Coverage**: **100%** - Integrazione più profonda disponibile

---

### 🛒 WooCommerce - **98% Coverage**

**Status**: ✅ Integrazione Completa  
**File**: `src/Integrations/WooCommerceSupport.php`  
**Documentazione**: [docs/integrations/WOOCOMMERCE.md](integrations/WOOCOMMERCE.md)

**Prodotti Supportati**:
- ✅ Simple Products
- ✅ Variable Products (con varianti)
- ✅ Grouped Products
- ✅ External/Affiliate Products
- ✅ Downloadable Products
- ✅ Virtual Products

**Features**:
- ✅ Sincronizzazione varianti (attributi, prezzi, stock, immagini)
- ✅ Gallerie prodotto (con ALT text tradotto)
- ✅ Attributi globali e custom
- ✅ Relazioni prodotto (upsell/cross-sell mapping automatico)
- ✅ File scaricabili (nomi tradotti)
- ✅ Tab prodotto personalizzati
- ✅ Tassonomie (product_cat, product_tag, product_brand)

**Coverage**: **98%** - Gestione completa e-commerce

---

### 🧭 Menu Navigation - **100% Coverage**

**Status**: ✅ Integrazione Completa  
**File**: `src/MenuSync.php`  
**Documentazione**: [docs/integrations/MENU-NAVIGATION.md](integrations/MENU-NAVIGATION.md)

**Features**:
- ✅ Auto-sync bidirezionale menu IT ↔ EN
- ✅ Mapping gerarchie parent/child preservato
- ✅ Traduzione titoli, descrizioni, attributi
- ✅ Custom fields Salient (icone, mega menu, button styles)
- ✅ Auto-delete menu EN quando IT viene eliminato
- ✅ Frontend language switching automatico
- ✅ Admin UI con status menu e link rapidi

**Coverage**: **100%** - Sincronizzazione completa menu

---

### ✨ Salient Theme - **98% Coverage**

**Status**: ✅ Integrazione Completa  
**File**: `src/Integrations/SalientThemeSupport.php`  
**Documentazione**: [docs/integrations/SALIENT-THEME.md](integrations/SALIENT-THEME.md)

**Meta Fields Sincronizzati**: 70+ campi

**Categorie**:
- ✅ Page Header Settings (26 campi) - Background, overlays, parallax, video
- ✅ Portfolio Settings (12 campi) - Extra content, gallery, featured images
- ✅ Post Format Settings (15 campi) - Quote, audio, video, gallery
- ✅ Page Builder Settings (18 campi) - Visual Composer, layout, sidebar
- ✅ Navigation Settings (8 campi) - Transparent header, color schemes

**Custom Post Types**:
- ✅ Portfolio
- ✅ Team Members
- ✅ Nectar Slider

**Coverage**: **98%** - Supporto più completo per Salient

---

## 🔌 Integrazioni Parziali

### 🎨 Page Builders

| Plugin | Supporto | Coverage | Note |
|--------|----------|----------|------|
| **WPBakery** | ✅ Completo | 90% | Shortcodes + attributi translatable |
| **Elementor** | ⚠️ Parziale | 40% | JSON data parsing basic |
| **Beaver Builder** | ⚠️ Parziale | 40% | Data e draft |
| **Oxygen Builder** | ⚠️ Parziale | 40% | Shortcodes e JSON |

**WPBakery** (v0.9.0):
- ✅ Traduzione contenuto shortcodes
- ✅ Attributi translatable (title, subtitle, caption, button_text)
- ✅ Preservazione struttura shortcodes
- ✅ Supporto nested shortcodes

**Altri Page Builders**: Supporto generico via parsing JSON/shortcodes

---

### 🔍 SEO Plugins (Terze Parti)

| Plugin | Supporto | Coverage | Note |
|--------|----------|----------|------|
| **FP-SEO-Manager** | ✅ Nativo | 100% | Integrazione proprietaria completa |
| **Yoast SEO** | ⚠️ Basic | 30% | Title, Description via auto-detection |
| **Rank Math SEO** | ⚠️ Basic | 30% | Title, Description, Focus Keyword |
| **All in One SEO** | ⚠️ Basic | 30% | Title, Description, Open Graph |
| **SEOPress** | ⚠️ Basic | 30% | Title, Description, Social Meta |

**Raccomandazione**: Usa **FP-SEO-Manager** per integrazione completa (100% coverage)

### 🛒 Altri E-commerce

| Plugin | Supporto | Coverage | Note |
|--------|----------|----------|------|
| **WooCommerce** | ✅ Nativo | 98% | Integrazione completa (vedi sopra) |
| **Easy Digital Downloads** | ⚠️ Basic | 40% | Download info, pricing |
| **WooCommerce** | Prodotti, Attributi, Categorie, Tag | Supporto nativo completo |
| **Easy Digital Downloads** | Price, Files, Instructions, Notes | Auto-rilevamento |

**WooCommerce - Campi tradotti:**
- Nome e descrizione prodotto
- Descrizione breve
- Attributi personalizzati
- Categorie e tag
- Istruzioni acquisto
- Note prodotto

### 📝 Forms

| Plugin | Supporto | Campi |
|--------|----------|-------|
| **WPForms** | ✅ Base | Shortcode preservato |
| **Gravity Forms** | ✅ Avanzato | Confirmation, Button Text |
| **Ninja Forms** | ✅ Avanzato | Label, Placeholder, Help Text |
| **Contact Form 7** | ✅ Base | Shortcode preservato |

### ⚙️ Custom Fields

| Plugin | Rilevamento | Note |
|--------|-------------|------|
| **Advanced Custom Fields (ACF)** | 🤖 Automatico | Scansiona tutti i field groups |
| **Meta Box** | 🤖 Automatico | API integration |
| **Pods** | 🤖 Automatico | Dynamic field detection |

**ACF - Tipi di campo supportati:**
- Text, Textarea, WYSIWYG
- Email, URL
- Post Object, Relationship
- Taxonomy
- Repeater, Flexible Content
- Clone

### 📅 Altri Plugin Popolari

| Plugin | Campi | Note |
|--------|-------|------|
| **The Events Calendar** | Date, Venue, Organizer | Auto-detection |
| **LearnDash** | Courses, Lessons, Quiz | Auto-detection |

## Utilizzo

### Rilevamento Automatico

Il sistema rileva automaticamente i plugin all'attivazione:

```php
// Nessuna configurazione necessaria!
// Il plugin rileva automaticamente:
// - Yoast SEO
// - Rank Math
// - Elementor
// - WooCommerce
// - ACF
// ... e molti altri
```

### Visualizza Plugin Rilevati

1. Vai in **Impostazioni → FP Multilanguage → Compatibilità Plugin**
2. Vedi l'elenco dei plugin rilevati
3. Clicca "Mostra" per vedere i campi personalizzati

### Trigger Manuale Rilevamento

```php
$detector = FPML_Plugin_Detector::instance();
$summary = $detector->trigger_detection();

// Output: array(
//     'total' => 5,
//     'plugins' => array(
//         'yoast' => array('name' => 'Yoast SEO', 'fields' => 6),
//         'elementor' => array('name' => 'Elementor', 'fields' => 3),
//         ...
//     )
// )
```

### Verifica Plugin Specifico

```php
$detector = FPML_Plugin_Detector::instance();

if ( $detector->is_plugin_detected( 'rank_math' ) ) {
    $fields = $detector->get_plugin_fields( 'rank_math' );
    // array( 'rank_math_title', 'rank_math_description', ... )
}
```

## Aggiungere Plugin Personalizzati

### Metodo 1: Filtro WordPress

Aggiungi il supporto per qualsiasi plugin usando il filtro `fpml_plugin_detection_rules`:

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['my_plugin'] = array(
        'name'     => 'Il Mio Plugin',
        'check'    => array( 'class' => 'MyPlugin' ),
        'fields'   => array(
            '_my_custom_field_1',
            '_my_custom_field_2',
            '_my_seo_title',
        ),
        'priority' => 10,
    );
    
    return $rules;
} );
```

### Metodo 2: Detection Dinamica

Per plugin con campi dinamici:

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['my_advanced_plugin'] = array(
        'name'     => 'My Advanced Plugin',
        'check'    => array( 'class' => 'MyAdvancedPlugin' ),
        'fields'   => array(), // Vuoto per detection dinamica
        'priority' => 15,
        'callback' => function() {
            // Rileva campi dinamicamente
            $fields = array();
            
            // Esempio: leggi da database o API
            $custom_fields = get_option( 'my_plugin_fields' );
            
            foreach ( $custom_fields as $field ) {
                if ( $field['translatable'] ) {
                    $fields[] = $field['key'];
                }
            }
            
            return $fields;
        },
    );
    
    return $rules;
} );
```

## Tipi di Check Disponibili

### Check by Class

```php
'check' => array( 'class' => 'ClassName' )
```

Verifica se una classe PHP esiste. Utile per la maggior parte dei plugin.

### Check by Function

```php
'check' => array( 'function' => 'function_name' )
```

Verifica se una funzione esiste.

### Check by Constant

```php
'check' => array( 'constant' => 'CONSTANT_NAME' )
```

Verifica se una costante è definita.

### Check by Plugin File

```php
'check' => array( 'plugin' => 'plugin-folder/plugin-file.php' )
```

Verifica se un plugin specifico è attivo.

## Esempi Avanzati

### Esempio 1: Plugin con Callback

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['my_crm'] = array(
        'name'     => 'My CRM',
        'check'    => array( 'class' => 'MyCRM' ),
        'fields'   => array(),
        'priority' => 10,
        'callback' => function() {
            // Rileva campi CRM dinamicamente
            if ( ! class_exists( 'MyCRM' ) ) {
                return array();
            }
            
            $crm = new MyCRM();
            $fields = $crm->get_custom_fields();
            
            return array_column( $fields, 'meta_key' );
        },
    );
    
    return $rules;
} );
```

### Esempio 2: Plugin Multicheck

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['my_suite'] = array(
        'name'     => 'My Plugin Suite',
        // Questo plugin usa solo function check
        'check'    => array( 'function' => 'my_suite_init' ),
        'fields'   => array(
            '_suite_title',
            '_suite_content',
            '_suite_excerpt',
        ),
        'priority' => 10,
    );
    
    return $rules;
} );
```

### Esempio 3: Integrazione Complessa

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['booking_system'] = array(
        'name'     => 'Booking System Pro',
        'check'    => array( 'class' => 'BookingSystem' ),
        'fields'   => array(
            '_booking_title',
            '_booking_description',
            '_booking_terms',
        ),
        'priority' => 15,
        'callback' => function() {
            // Aggiungi campi per ogni tipo di booking
            $fields = array();
            $booking_types = get_option( 'booking_types', array() );
            
            foreach ( $booking_types as $type ) {
                $fields[] = "_booking_{$type}_name";
                $fields[] = "_booking_{$type}_desc";
            }
            
            return $fields;
        },
    );
    
    return $rules;
} );
```

## API Reference

### `FPML_Plugin_Detector`

#### Metodi Pubblici

##### `instance()`

Recupera l'istanza singleton.

```php
$detector = FPML_Plugin_Detector::instance();
```

##### `get_detected_plugins()`

Ottiene array dei plugin rilevati.

```php
$plugins = $detector->get_detected_plugins();
// array(
//     'yoast' => array( 'name' => 'Yoast SEO', 'fields' => [...] ),
//     ...
// )
```

##### `get_detection_summary()`

Ottiene riepilogo del rilevamento.

```php
$summary = $detector->get_detection_summary();
// array(
//     'total' => 5,
//     'plugins' => array(
//         'yoast' => array( 'name' => 'Yoast SEO', 'fields' => 6 )
//     )
// )
```

##### `trigger_detection()`

Forza il rilevamento manuale.

```php
$summary = $detector->trigger_detection();
```

##### `is_plugin_detected( $slug )`

Verifica se un plugin è stato rilevato.

```php
if ( $detector->is_plugin_detected( 'elementor' ) ) {
    // Elementor è attivo
}
```

##### `get_plugin_fields( $slug )`

Ottiene i campi di un plugin specifico.

```php
$fields = $detector->get_plugin_fields( 'rank_math' );
// array( 'rank_math_title', 'rank_math_description', ... )
```

## Hook e Filtri

### `fpml_plugin_detection_rules`

Modifica o aggiungi regole di rilevamento.

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    // Aggiungi nuove regole
    $rules['my_plugin'] = array( ... );
    
    // Modifica regole esistenti
    $rules['yoast']['fields'][] = '_custom_yoast_field';
    
    return $rules;
} );
```

**Parametri:**
- `$rules` (array) - Array delle regole di rilevamento

**Return:** Array modificato delle regole

## Best Practices

### 1. Usa il Plugin Detector per Compatibilità

```php
// ❌ Non farlo
add_filter( 'fpml_meta_whitelist', function( $whitelist ) {
    $whitelist[] = '_my_field_1';
    $whitelist[] = '_my_field_2';
    return $whitelist;
} );

// ✅ Fallo così
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['my_plugin'] = array(
        'name'   => 'My Plugin',
        'check'  => array( 'class' => 'MyPlugin' ),
        'fields' => array( '_my_field_1', '_my_field_2' ),
    );
    return $rules;
} );
```

### 2. Priorità Corrette

```php
// SEO e content: 10
'priority' => 10,

// Page builders e strutture complesse: 15
'priority' => 15,

// Relazioni e post-processing: 20
'priority' => 20,
```

### 3. Callback per Campi Dinamici

```php
// Usa callback solo se i campi cambiano dinamicamente
'callback' => function() {
    // Query database o API
    return $dynamic_fields;
},
```

### 4. Check Multipli

```php
// Se un plugin può essere rilevato in modi diversi
if ( class_exists( 'MyPlugin' ) || function_exists( 'my_plugin_init' ) ) {
    // OK
}
```

## Troubleshooting

### Plugin non rilevato

1. Verifica che il plugin sia attivo
2. Controlla i criteri di check (class, function, constant)
3. Forza il rilevamento manualmente
4. Verifica i log in `wp-content/uploads/fpml-logs/`

### Campi non tradotti

1. Verifica che i campi siano nella whitelist
2. Controlla il tipo di campo (alcuni tipi non sono traducibili)
3. Usa il filtro `fpml_meta_whitelist` come fallback

### Conflitti tra Plugin

Se due plugin usano lo stesso meta_key:

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    // Rimuovi campo da un plugin se causa conflitti
    unset( $rules['plugin1']['fields'][0] );
    return $rules;
} );
```

## FAQ

**D: Il rilevamento impatta le performance?**  
R: No, il rilevamento avviene una volta all'attivazione e i risultati sono cached.

**D: Posso disabilitare il rilevamento per alcuni plugin?**  
R: Sì, usa il filtro `fpml_plugin_detection_rules` e rimuovi le regole non desiderate.

**D: I custom fields vengono tradotti automaticamente?**  
R: Sì, tutti i campi rilevati vengono automaticamente aggiunti alla whitelist e tradotti.

**D: Supporta plugin custom/proprietari?**  
R: Sì, aggiungi le regole di rilevamento tramite il filtro apposito.

## Supporto

Per supporto aggiuntivo:
- **Issues**: [GitHub Issues](https://github.com/francescopasseri/FP-Multilanguage/issues)
- **Docs**: [Documentazione completa](../README.md)
- **Email**: info@francescopasseri.com

---

**Creato per FP Multilanguage v0.4.2+**
