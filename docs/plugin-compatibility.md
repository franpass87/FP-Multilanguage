# 🔌 Plugin Compatibility - Auto-Detection System

## Panoramica

FP Multilanguage include un **sistema di rilevamento automatico** che identifica i plugin installati e traduce automaticamente i loro campi personalizzati **senza configurazione manuale**.

## Come Funziona

Il sistema:
1. **Rileva automaticamente** i plugin WordPress comuni all'avvio
2. **Registra i loro custom fields** nella whitelist di traduzione
3. **Traduce i contenuti** senza intervento manuale
4. **Mostra notifiche** quando rileva nuovi plugin compatibili

## Plugin Supportati

### 🔍 SEO

| Plugin | Campi Auto-Rilevati | Note |
|--------|-------------------|------|
| **Yoast SEO** | Title, Description, Open Graph, Twitter Cards, Canonical | Supporto completo |
| **Rank Math SEO** | Title, Description, Focus Keyword, Social Meta | Supporto completo |
| **All in One SEO** | Title, Description, Open Graph, Twitter | Supporto completo |
| **SEOPress** | Title, Description, Social Meta | Supporto completo |

**Campi tradotti automaticamente:**
- Meta title e description
- Open Graph (Facebook)
- Twitter Cards
- URL canonici
- Focus keywords

### 🎨 Page Builders

| Plugin | Supporto | Note |
|--------|----------|------|
| **Elementor** | ✅ Completo | JSON data parsing |
| **WPBakery** | ✅ Completo | Shortcode parsing avanzato |
| **Beaver Builder** | ✅ Completo | Data e draft |
| **Oxygen Builder** | ✅ Completo | Shortcodes e JSON |

**Caratteristiche:**
- Preserva la struttura del page builder
- Traduce solo il contenuto testuale
- Mantiene gli stili e le impostazioni

### 🛒 E-commerce

| Plugin | Campi Auto-Rilevati | Note |
|--------|-------------------|------|
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
