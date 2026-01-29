# Guida Traduzione Menu e Widget - FP Multilanguage

**Data:** 19 Novembre 2025  
**Versione:** 0.9.6

## 📋 Panoramica

Il plugin FP Multilanguage supporta la traduzione automatica di:
- **Menu**: Elementi di navigazione
- **Widget**: Titoli e contenuti widget
- **Opzioni Tema**: Testi del tema (es. Salient)
- **Opzioni Plugin**: Stringhe di plugin comuni (es. WooCommerce)

## 🔧 Come Funziona

### 1. Sistema di Memorizzazione

Le traduzioni vengono salvate come opzioni WordPress con prefissi specifici:

- **Menu**: `_fpml_en_menu_item_{ITEM_ID}_title`
- **Widget Title**: `_fpml_en_widget_{WIDGET_ID}_title`
- **Widget Text**: `_fpml_en_widget_{WIDGET_ID}_text`
- **Opzioni Tema**: `_fpml_en_theme_option_{FIELD_NAME}`
- **Opzioni Plugin**: `_fpml_en_option_{OPTION_NAME}`

### 2. Filtri Automatici

Quando l'utente è su `/en/`, il plugin applica automaticamente i filtri:
- `wp_nav_menu_objects` - Filtra elementi menu
- `nav_menu_item_title` - Filtra titoli menu
- `widget_title` - Filtra titoli widget
- `widget_text` - Filtra testo widget
- `option` - Filtra opzioni generiche

## 🎯 Metodi di Traduzione

### Metodo 1: Traduzione Automatica via Admin (Programmatica)

Il plugin include la classe `SitePartTranslator` che può tradurre automaticamente:

```php
// In Admin.php, tab Export
$translator = new \FP\Multilanguage\Admin\SitePartTranslator();

// Traduci menu
$result = $translator->translate( 'menus' );

// Traduci widget
$result = $translator->translate( 'widgets' );

// Traduci opzioni tema
$result = $translator->translate( 'theme-options' );

// Traduci plugin
$result = $translator->translate( 'plugins' );
```

**Dove si trova**: Attualmente questa funzionalità è disponibile programmaticamente, ma potrebbe non essere esposta nell'interfaccia admin.

### Metodo 2: Traduzione Manuale via Database

Puoi salvare manualmente le traduzioni usando `update_option()`:

```php
// Traduci un elemento menu
update_option( '_fpml_en_menu_item_123_title', 'English Menu Item' );

// Traduci titolo widget
update_option( '_fpml_en_widget_text-2_title', 'English Widget Title' );

// Traduci testo widget
update_option( '_fpml_en_widget_text-2_text', 'English widget content...' );
```

### Metodo 3: Traduzione via Code/CLI

Puoi creare uno script personalizzato:

```php
<?php
// Carica WordPress
require_once( 'wp-load.php' );

// Carica il translator
$translator = new \FP\Multilanguage\Admin\SitePartTranslator();

// Traduci menu
$result = $translator->translate( 'menus' );
echo $result['message'] . "\n";

// Traduci widget
$result = $translator->translate( 'widgets' );
echo $result['message'] . "\n";
```

### Metodo 4: Traduzione via WP-CLI (se disponibile)

```bash
wp fpml translate-menus
wp fpml translate-widgets
```

## 📝 Esempi Pratici

### Esempio 1: Tradurre un Menu Specifico

```php
// Ottieni tutti i menu
$menus = wp_get_nav_menus();

foreach ( $menus as $menu ) {
    $items = wp_get_nav_menu_items( $menu->term_id );
    
    foreach ( $items as $item ) {
        // Traduci il titolo
        $translated = translate_text_to_english( $item->title );
        
        // Salva traduzione
        update_option( '_fpml_en_menu_item_' . $item->ID . '_title', $translated );
    }
}
```

### Esempio 2: Tradurre un Widget Specifico

```php
// Ottieni tutti i widget
global $wp_registered_widgets;
$sidebars = wp_get_sidebars_widgets();

foreach ( $sidebars as $sidebar_id => $widgets ) {
    foreach ( $widgets as $widget_id ) {
        $widget = $wp_registered_widgets[ $widget_id ];
        $widget_instance = get_option( 'widget_' . $widget['callback'][0]->id_base );
        
        // Trova l'istanza
        $widget_number = $widget['params'][0]['number'];
        $instance = $widget_instance[ $widget_number ];
        
        // Traduci titolo
        if ( ! empty( $instance['title'] ) ) {
            $translated = translate_text_to_english( $instance['title'] );
            update_option( '_fpml_en_widget_' . $widget_id . '_title', $translated );
        }
        
        // Traduci testo (per widget di testo)
        if ( ! empty( $instance['text'] ) ) {
            $translated = translate_text_to_english( $instance['text'] );
            update_option( '_fpml_en_widget_' . $widget_id . '_text', $translated );
        }
    }
}
```

### Esempio 3: Usare il Translator del Plugin

```php
// Ottieni il provider configurato
$settings = \FPML_Settings::instance();
$provider_name = $settings->get( 'provider', 'openai' );

// Carica il provider
if ( 'openai' === $provider_name ) {
    $translator = \FP\Multilanguage\Providers\ProviderOpenAI::instance();
}

// Traduci testo
$translated = $translator->translate( 'Testo da tradurre', 'it', 'en', 'general' );

// Salva traduzione
update_option( '_fpml_en_menu_item_123_title', $translated );
```

## 🔍 Verifica Traduzioni

### Verificare se una traduzione esiste:

```php
// Menu
$translated = get_option( '_fpml_en_menu_item_123_title' );
if ( $translated ) {
    echo "Traduzione trovata: " . $translated;
}

// Widget
$translated = get_option( '_fpml_en_widget_text-2_title' );
if ( $translated ) {
    echo "Traduzione trovata: " . $translated;
}
```

### Verificare nel Frontend:

1. Vai su `/en/` nel frontend
2. Controlla se menu e widget sono tradotti
3. Se non lo sono, verifica che le opzioni siano salvate correttamente

## 🛠️ Interfaccia Admin (Da Implementare)

Attualmente, la traduzione di menu e widget può essere fatta:
1. **Programmaticamente** usando `SitePartTranslator`
2. **Manualmente** salvando le opzioni
3. **Via script** personalizzato

**Raccomandazione**: Aggiungere un'interfaccia admin dedicata nella pagina settings del plugin per facilitare la traduzione di menu e widget.

## 📌 Note Importanti

1. **ID Menu Items**: Gli ID degli elementi menu sono persistenti, quindi le traduzioni rimangono anche se modifichi il menu
2. **ID Widget**: Gli ID widget possono cambiare se rimuovi e riaggiungi widget
3. **Cache**: Potrebbe essere necessario pulire la cache dopo aver salvato traduzioni
4. **Auto-sync**: Il plugin `MenuSync` può sincronizzare automaticamente i menu, ma le traduzioni dei titoli devono essere salvate manualmente o via `SitePartTranslator`

## ✅ Checklist Traduzione

- [ ] Verificare che il provider di traduzione sia configurato
- [ ] Tradurre menu usando `SitePartTranslator` o manualmente
- [ ] Tradurre widget usando `SitePartTranslator` o manualmente
- [ ] Verificare traduzioni nel frontend su `/en/`
- [ ] Pulire cache se necessario
- [ ] Testare su dispositivi diversi

## 🎯 Prossimi Passi Suggeriti

1. **Aggiungere Interfaccia Admin**: Creare una pagina/tab dedicata per tradurre menu e widget
2. **Aggiungere WP-CLI Commands**: Comandi CLI per tradurre menu e widget
3. **Aggiungere Auto-translate**: Traduzione automatica quando si crea/modifica menu o widget
4. **Aggiungere Preview**: Anteprima traduzioni prima di salvare








