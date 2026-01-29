# Analisi Comunicazione con Altri Plugin - FP Multilanguage

**Data:** 19 Novembre 2025  
**Problema:** L'utente chiede se quando è su `/en/`, vede i contenuti degli altri plugin in inglese e se il plugin comunica agli altri plugin che devono fare la traduzione in inglese.

## 📊 Situazione Attuale

### ✅ Cosa Funziona

1. **Metodo Pubblico per Lingua Corrente**
   - `\FPML_Language::instance()->get_current_language()` restituisce `'it'` o `'en'`
   - Disponibile per altri plugin, ma richiede conoscenza della classe

2. **Filtri per Contenuti Specifici**
   - `SiteTranslations.php` filtra automaticamente:
     - Menu items (`wp_nav_menu_objects`, `nav_menu_item_title`)
     - Widget titles e text (`widget_title`, `widget_text`)
     - Opzioni tema Salient (`option_salient`)
     - Opzioni WooCommerce (`option_woocommerce_shop_page_title`, etc.)
     - Opzioni generiche (`option` filter)

3. **Integrazioni Specifiche**
   - WooCommerce: traduzione meta fields, attributi, variazioni
   - FP-SEO: traduzione meta SEO
   - Plugin popolari: whitelist meta fields traducibili

4. **Filtro Locale WordPress**
   - `filter_locale()` imposta `locale` a `'en_US'` quando su `/en/`
   - Altri plugin possono usare `get_locale()` per sapere la lingua

### ⚠️ Limitazioni Attuali

1. **Nessun Hook Pubblico Esplicito**
   - Non c'è un hook/filter che comunica esplicitamente la lingua corrente
   - Altri plugin devono conoscere la struttura interna del plugin

2. **Nessuna Funzione Helper Globale**
   - Non c'è una funzione globale tipo `fpml_is_english()` o `fpml_get_current_language()`
   - Altri plugin devono usare `\FPML_Language::instance()->get_current_language()`

3. **Filtri Limitati**
   - `SiteTranslations.php` filtra solo alcuni contenuti specifici
   - Non copre tutti i possibili contenuti di altri plugin

4. **Nessuna Documentazione Pubblica**
   - Non c'è documentazione su come altri plugin possono integrare

## 🔧 Miglioramenti Suggeriti

### 1. Aggiungere Hook/Filter Pubblici

```php
// In Language.php, aggiungere:

/**
 * Get current language with filter for other plugins.
 *
 * @since 0.9.6
 *
 * @return string Language code ('it' or 'en').
 */
public function get_current_language() {
    $lang = $this->current;
    
    // Allow other plugins to filter the current language
    return apply_filters( 'fpml_current_language', $lang );
}

/**
 * Check if current language is English.
 *
 * @since 0.9.6
 *
 * @return bool True if English, false if Italian.
 */
public function is_english() {
    return ( self::TARGET === $this->get_current_language() );
}
```

### 2. Aggiungere Funzioni Helper Globali

```php
// In helpers.php, aggiungere:

/**
 * Get current language code.
 *
 * @since 0.9.6
 *
 * @return string 'it' or 'en'
 */
function fpml_get_current_language() {
    if ( ! class_exists( '\FP\Multilanguage\Language' ) ) {
        return 'it';
    }
    return \FP\Multilanguage\Language::instance()->get_current_language();
}

/**
 * Check if current language is English.
 *
 * @since 0.9.6
 *
 * @return bool True if English, false if Italian.
 */
function fpml_is_english() {
    return ( 'en' === fpml_get_current_language() );
}

/**
 * Check if current language is Italian.
 *
 * @since 0.9.6
 *
 * @return bool True if Italian, false if English.
 */
function fpml_is_italian() {
    return ( 'it' === fpml_get_current_language() );
}
```

### 3. Aggiungere Hook per Notificare Altri Plugin

```php
// In Language.php, nel metodo determine_language():

public function determine_language( $query ) {
    // ... existing code ...
    
    // Notify other plugins about language change
    do_action( 'fpml_language_determined', $this->current, $this->previous_language ?? null );
    
    // ... rest of code ...
}
```

### 4. Estendere SiteTranslations per Plugin Generici

```php
// In SiteTranslations.php, aggiungere:

/**
 * Filter generic plugin content.
 *
 * @param mixed  $value  Original value.
 * @param string $option Option name.
 * @return mixed Filtered value.
 */
public function filter_generic_option( $value, $option ) {
    // Check if there's a translation stored
    $translated = get_option( '_fpml_en_option_' . $option );
    
    if ( $translated ) {
        return $translated;
    }
    
    // Allow other plugins to hook in
    return apply_filters( 'fpml_filter_option_' . $option, $value, $option );
}
```

### 5. Creare Documentazione Hook Pubblici

Creare file `HOOKS.md` con documentazione completa degli hook disponibili.

## 📝 Esempio di Utilizzo per Altri Plugin

### Esempio 1: Plugin Personalizzato

```php
// In un altro plugin:

add_action( 'init', function() {
    // Verifica se siamo su /en/
    if ( function_exists( 'fpml_is_english' ) && fpml_is_english() ) {
        // Mostra contenuti in inglese
        add_filter( 'my_plugin_content', 'my_plugin_translate_to_english' );
    }
});

function my_plugin_translate_to_english( $content ) {
    // Logica traduzione
    $translated = get_option( '_my_plugin_en_content' );
    return $translated ? $translated : $content;
}
```

### Esempio 2: Hook per Notifica

```php
// In un altro plugin:

add_action( 'fpml_language_determined', function( $current_lang, $previous_lang ) {
    if ( 'en' === $current_lang ) {
        // Carica traduzioni inglesi
        load_english_translations();
    } else {
        // Carica traduzioni italiane
        load_italian_translations();
    }
}, 10, 2 );
```

### Esempio 3: Filtro Opzioni

```php
// In un altro plugin:

add_filter( 'fpml_filter_option_my_plugin_setting', function( $value, $option ) {
    if ( fpml_is_english() ) {
        $translated = get_option( '_my_plugin_en_' . $option );
        return $translated ? $translated : $value;
    }
    return $value;
}, 10, 2 );
```

## ✅ Conclusione

**Situazione Attuale:**
- ✅ Il plugin filtra automaticamente alcuni contenuti (menu, widget, opzioni tema/WooCommerce)
- ✅ Altri plugin possono usare `get_locale()` per sapere la lingua
- ⚠️ Non c'è comunicazione esplicita tramite hook pubblici
- ⚠️ Non ci sono funzioni helper globali facili da usare

**Raccomandazione:**
Implementare i miglioramenti suggeriti per facilitare l'integrazione con altri plugin e rendere esplicita la comunicazione della lingua corrente.








