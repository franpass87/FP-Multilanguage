# 🎨 Integrazione con Tema Salient

Guida completa per integrare il selettore di lingua FP Multilanguage con il tema **Salient**.

---

## ⚠️ IMPORTANTE: Child Theme

**Non modificare mai il tema Salient direttamente!** Usa sempre un **child theme**.

### Come creare un Child Theme per Salient

Se non hai già un child theme:

1. Vai in **Salient → General Settings → Performance**
2. Cerca l'opzione **"Install Child Theme"**
3. Clicca sul pulsante per installare automaticamente
4. Attiva il child theme da **Aspetto → Temi**

Oppure scarica il child theme ufficiale da ThemeForest.

---

## 🎯 Metodo 1: Codice nel Child Theme (CONSIGLIATO)

### Passo 1: Apri functions.php del child theme

Vai in **Aspetto → Editor file tema** oppure via FTP:
```
/wp-content/themes/salient-child/functions.php
```

### Passo 2: Aggiungi questo codice

```php
/**
 * Aggiungi selettore lingua FP Multilanguage al menu Salient
 */
add_filter( 'wp_nav_menu_items', 'salient_add_language_switcher', 10, 2 );
function salient_add_language_switcher( $items, $args ) {
    // Salient usa 'top_nav' per il menu principale
    if ( $args->theme_location === 'top_nav' ) {
        
        // Verifica che il plugin sia attivo
        if ( ! function_exists( 'do_shortcode' ) ) {
            return $items;
        }
        
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        
        // Aggiungi alla FINE del menu
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
        
        // Oppure all'INIZIO (commenta la riga sopra e usa questa):
        // $items = '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>' . $items;
    }
    
    return $items;
}

/**
 * CSS personalizzato per il selettore nel menu Salient
 */
add_action( 'wp_head', 'salient_language_switcher_css', 999 );
function salient_language_switcher_css() {
    ?>
    <style id="salient-fpml-switcher">
        /* === DESKTOP MENU === */
        
        /* Allineamento base */
        #header-outer #top nav > ul > li.menu-item-language-switcher {
            display: inline-flex;
            align-items: center;
            padding: 0 15px;
        }
        
        #header-outer .menu-item-language-switcher .fpml-switcher {
            margin: 0;
            line-height: normal;
        }
        
        #header-outer .menu-item-language-switcher .fpml-switcher--inline {
            display: inline-flex;
            align-items: center;
        }
        
        /* Match font del tema Salient */
        #header-outer .menu-item-language-switcher .fpml-switcher__item {
            font-size: inherit;
            font-family: inherit;
            font-weight: inherit;
            letter-spacing: inherit;
            text-transform: inherit;
            color: inherit;
            padding: 6px 10px;
        }
        
        /* Hover effect che matcha Salient */
        #header-outer .menu-item-language-switcher .fpml-switcher__item:hover {
            background-color: transparent;
            opacity: 0.7;
            color: inherit;
        }
        
        /* Lingua corrente */
        #header-outer .menu-item-language-switcher .fpml-switcher__item--current {
            font-weight: 700;
            background-color: transparent;
            border: none;
            color: inherit;
        }
        
        /* Separatore */
        #header-outer .menu-item-language-switcher .fpml-switcher__separator {
            color: inherit;
            opacity: 0.5;
        }
        
        /* Bandierine */
        #header-outer .menu-item-language-switcher .fpml-switcher__flag {
            font-size: 18px;
        }
        
        /* === HEADER STICKY (quando si scrolla) === */
        
        #header-outer.sticky-header .menu-item-language-switcher .fpml-switcher__item {
            padding: 4px 8px;
        }
        
        /* === HEADER TRASPARENTE (se usi transparent header) === */
        
        body.transparent-header #header-outer:not(.scrolled-down) .menu-item-language-switcher .fpml-switcher__item {
            color: #ffffff;
        }
        
        /* === MOBILE MENU === */
        
        @media only screen and (max-width: 999px) {
            /* Mobile menu standard */
            #header-outer #mobile-menu .menu-item-language-switcher {
                display: block;
                padding: 15px 20px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
                text-align: center;
            }
            
            #header-outer #mobile-menu .menu-item-language-switcher .fpml-switcher {
                justify-content: center;
            }
            
            #header-outer #mobile-menu .menu-item-language-switcher .fpml-switcher__item {
                font-size: 15px;
                color: inherit;
                padding: 8px 15px;
            }
            
            /* Bandierine più grandi su mobile */
            #header-outer #mobile-menu .menu-item-language-switcher .fpml-switcher__flag {
                font-size: 22px;
            }
        }
        
        /* === SLIDE-OUT SIDEBAR MENU (menu off-canvas) === */
        
        #slide-out-widget-area .menu-item-language-switcher {
            padding: 20px;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        #slide-out-widget-area .menu-item-language-switcher .fpml-switcher {
            justify-content: center;
        }
        
        /* === SALIENT CENTERED LOGO (logo centrato) === */
        
        body.using-mobile-browser #header-outer.centered-logo-between-menu .menu-item-language-switcher {
            display: inline-flex;
        }
    </style>
    <?php
}
```

### Passo 3: Salva e Verifica

1. Salva il file `functions.php`
2. Ricarica il frontend del sito
3. Dovresti vedere le bandierine 🇮🇹 🇬🇧 nel menu

---

## 🎯 Metodo 2: Plugin Custom (Se preferisci)

Se non vuoi modificare il child theme, crea un micro-plugin:

### Passo 1: Crea questa struttura

```
/wp-content/plugins/salient-language-switcher/
├── salient-language-switcher.php
```

### Passo 2: Contenuto del file

```php
<?php
/**
 * Plugin Name: Salient Language Switcher
 * Description: Aggiunge il selettore FP Multilanguage al menu Salient
 * Version: 1.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Aggiungi al menu
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( $args->theme_location === 'top_nav' ) {
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    }
    return $items;
}, 10, 2 );

// Aggiungi CSS
add_action( 'wp_head', function() {
    ?>
    <style>
        /* Copia qui il CSS della sezione precedente */
    </style>
    <?php
});
```

### Passo 3: Attiva il plugin

Vai in **Plugin → Plugin installati** e attiva "Salient Language Switcher".

---

## 🎨 Stili Specifici per Varianti Salient

### Se usi Header Builder di Salient

Se hai costruito l'header con **Salient Header Builder**:

1. Modifica l'header in **Salient → Header Builder**
2. Aggiungi un elemento **"Custom HTML"** o **"Text"**
3. Inserisci: `[fp_lang_switcher style="inline" show_flags="1"]`
4. Posizionalo dove vuoi nell'header
5. Salva e pubblica

### Se usi Side Header (menu laterale)

```php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( $args->theme_location === 'top_nav' ) {
        $switcher = do_shortcode('[fp_lang_switcher style="dropdown" show_flags="1"]');
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    }
    return $items;
}, 10, 2 );
```

CSS aggiuntivo:
```css
/* Side header - centered */
#header-outer.side-header .menu-item-language-switcher {
    text-align: center;
    padding: 20px 0;
    border-top: 1px solid rgba(255,255,255,0.1);
}

#header-outer.side-header .menu-item-language-switcher .fpml-switcher {
    justify-content: center;
}
```

---

## 🎨 Personalizzazioni per Colori Salient

### Match con il colore accent del tema

```php
add_action( 'wp_head', function() {
    // Ottieni il colore accent di Salient
    $accent_color = get_option('accent-color', '#3498db');
    ?>
    <style>
        #header-outer .menu-item-language-switcher .fpml-switcher__item--current {
            color: <?php echo esc_attr( $accent_color ); ?> !important;
        }
        
        #header-outer .menu-item-language-switcher .fpml-switcher__item:hover {
            color: <?php echo esc_attr( $accent_color ); ?> !important;
        }
    </style>
    <?php
});
```

---

## 🔍 Debug: Trova il Theme Location

Se `top_nav` non funziona, scopri quale location usa Salient:

```php
// Aggiungi temporaneamente questo in functions.php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    error_log( 'Salient menu location: ' . $args->theme_location );
    return $items;
}, 10, 2 );
```

Poi controlla i log di WordPress in `/wp-content/debug.log` (attiva WP_DEBUG).

---

## 📱 Mobile Menu Ottimizzato

### Opzione 1: Solo bandierine su mobile

```css
@media only screen and (max-width: 999px) {
    #header-outer #mobile-menu .menu-item-language-switcher .fpml-switcher__item {
        font-size: 0; /* Nascondi testo */
    }
    
    #header-outer #mobile-menu .menu-item-language-switcher .fpml-switcher__flag {
        font-size: 28px; /* Bandierine grandi */
    }
    
    #header-outer #mobile-menu .menu-item-language-switcher .fpml-switcher__separator {
        font-size: 18px; /* Separatore visibile */
    }
}
```

### Opzione 2: Dropdown su mobile

```php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( $args->theme_location === 'top_nav' ) {
        // Desktop: inline, Mobile: dropdown
        if ( wp_is_mobile() ) {
            $switcher = do_shortcode('[fp_lang_switcher style="dropdown" show_flags="1"]');
        } else {
            $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        }
        
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    }
    return $items;
}, 10, 2 );
```

---

## 🎯 Posizionamenti Alternativi

### In alto a destra (fuori dal menu principale)

Se vuoi mettere il selettore in alto a destra, fuori dal menu:

```php
add_action( 'nectar_hook_after_header_nav_item', function() {
    echo '<div class="fpml-header-switcher">';
    echo do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
    echo '</div>';
});
```

CSS:
```css
.fpml-header-switcher {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
}
```

---

## ✅ Checklist Finale Salient

- [ ] Child theme creato/attivato
- [ ] Codice aggiunto in `functions.php` del child theme
- [ ] `top_nav` verificato come theme location
- [ ] Salvato e ricaricato il sito
- [ ] Selettore visibile nel menu desktop
- [ ] Selettore visibile nel menu mobile
- [ ] CSS allineato con il design di Salient
- [ ] Testato hover e click
- [ ] Cache svuotata (se usi plugin di cache)

---

## 🆘 Problemi Comuni con Salient

### Il selettore non appare
- Verifica di usare il **child theme**
- Controlla che il menu sia assegnato alla location "Primary Navigation"
- Svuota cache di Salient: **Salient → General Settings → Performance**

### Il selettore è disallineato
- Usa il CSS fornito sopra
- Ispeziona con F12 per vedere conflitti CSS
- Salient usa molti CSS custom, potrebbe servire `!important`

### Il mobile menu non funziona
- Salient ha un mobile menu completamente separato
- Assicurati che il CSS mobile sia presente
- Testa su device reale, non solo resize browser

---

## 📞 Supporto Specifico Salient

Se hai problemi specifici con Salient:

1. **Screenshot dell'header** (desktop + mobile)
2. **Tipo di header** (standard, side, centered logo)
3. **Se usi Header Builder** (sì/no)
4. **Versione di Salient** (trova in Temi)

E ti darò una soluzione personalizzata!

---

**Creato per FP Multilanguage + Salient Theme** ❤️
