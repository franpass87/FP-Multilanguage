# 🍔 Integrazione Selettore Lingua nel Menù WordPress

Guida completa per inserire le bandierine 🇮🇹 🇬🇧 nel menù di navigazione di WordPress.

---

## 🎯 Metodo 1: Codice PHP nel functions.php (CONSIGLIATO)

### Passo 1: Trova il Theme Location del tuo menu

Apri `functions.php` del tuo tema e cerca la registrazione dei menu:

```php
register_nav_menus( array(
    'primary'   => __( 'Primary Menu', 'mytheme' ),
    'footer'    => __( 'Footer Menu', 'mytheme' ),
) );
```

Il **theme location** è la chiave (es. `'primary'`, `'footer'`).

### Passo 2: Aggiungi il codice

Copia questo nel `functions.php` del tuo tema (o in un plugin child theme):

```php
/**
 * Aggiunge selettore lingua al menu principale
 */
function fpml_add_language_switcher_to_menu( $items, $args ) {
    // Sostituisci 'primary' con il theme_location del tuo menu
    if ( $args->theme_location === 'primary' ) {
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        
        // Aggiungi alla FINE del menu
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    }
    
    return $items;
}
add_filter( 'wp_nav_menu_items', 'fpml_add_language_switcher_to_menu', 10, 2 );
```

### Passo 3: CSS per allineamento (opzionale)

Se il selettore non si allinea bene, aggiungi questo CSS nel tuo tema:

```css
/* Allinea il selettore con gli altri elementi del menu */
.menu-item-language-switcher {
    display: flex;
    align-items: center;
}

/* Rimuovi stili di lista se necessario */
.menu-item-language-switcher .fpml-switcher {
    list-style: none;
    padding: 0;
    margin: 0;
}

/* Centra verticalmente */
.menu-item-language-switcher .fpml-switcher--inline {
    display: flex;
    align-items: center;
}
```

---

## 🎯 Metodo 2: Aggiungere all'INIZIO del menu

Se vuoi le bandierine all'inizio invece che alla fine:

```php
function fpml_add_language_switcher_to_menu( $items, $args ) {
    if ( $args->theme_location === 'primary' ) {
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        
        // Aggiungi all'INIZIO del menu (prima degli altri elementi)
        $items = '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>' . $items;
    }
    
    return $items;
}
add_filter( 'wp_nav_menu_items', 'fpml_add_language_switcher_to_menu', 10, 2 );
```

---

## 🎯 Metodo 3: Aggiungere a TUTTI i menu

Se hai più menu e vuoi il selettore in tutti:

```php
function fpml_add_language_switcher_to_all_menus( $items, $args ) {
    $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
    $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    
    return $items;
}
add_filter( 'wp_nav_menu_items', 'fpml_add_language_switcher_to_all_menus', 10, 2 );
```

---

## 🎯 Metodo 4: Solo su Desktop (nascosto su mobile)

Se vuoi mostrare il selettore solo su desktop:

```php
function fpml_add_language_switcher_desktop_only( $items, $args ) {
    if ( $args->theme_location === 'primary' ) {
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        
        // Aggiungi classe per nascondere su mobile
        $items .= '<li class="menu-item menu-item-language-switcher hide-on-mobile">' . $switcher . '</li>';
    }
    
    return $items;
}
add_filter( 'wp_nav_menu_items', 'fpml_add_language_switcher_desktop_only', 10, 2 );
```

Poi aggiungi questo CSS:

```css
/* Nascondi su mobile */
@media (max-width: 768px) {
    .menu-item-language-switcher.hide-on-mobile {
        display: none;
    }
}
```

---

## 🎯 Metodo 5: Custom Link nel Menu (LIMITATO)

**⚠️ Nota:** Questo metodo ha limitazioni perché WordPress non processa shortcode nei menu di default.

### Passo 1: Abilita shortcode nei menu

Aggiungi in `functions.php`:

```php
// Abilita shortcode nelle voci di menu
add_filter( 'wp_nav_menu_items', 'do_shortcode' );
```

### Passo 2: Aggiungi Custom Link

1. Vai in **Aspetto → Menu**
2. Espandi "Link personalizzati"
3. URL: `#` (hashtag)
4. Testo del link: `[fp_lang_switcher style="inline" show_flags="1"]`
5. Clicca "Aggiungi al menu"
6. Salva il menu

**Problema:** Il link potrebbe essere cliccabile e non funzionare bene. Meglio usare il Metodo 1.

---

## 🎯 Metodo 6: Menu Walker Personalizzato (AVANZATO)

Per controllo totale sulla posizione, puoi creare un custom menu walker:

```php
class FPML_Menu_Walker extends Walker_Nav_Menu {
    function end_lvl( &$output, $depth = 0, $args = array() ) {
        // Aggiungi il selettore alla fine di ogni sotto-menu
        if ( $depth === 0 ) {
            $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
            $output .= '<li class="menu-item-language-switcher">' . $switcher . '</li>';
        }
        
        parent::end_lvl( $output, $depth, $args );
    }
}

// Usa il walker nel tema
wp_nav_menu( array(
    'theme_location' => 'primary',
    'walker'         => new FPML_Menu_Walker(),
) );
```

---

## 🎨 Stili CSS Comuni per Menu

### Stile 1: Allineamento verticale perfetto

```css
.menu-item-language-switcher {
    display: flex;
    align-items: center;
    padding: 0 15px;
}

.menu-item-language-switcher .fpml-switcher {
    margin: 0;
}

.menu-item-language-switcher .fpml-switcher__item {
    padding: 8px 12px;
}
```

### Stile 2: Separatore prima del selettore

```css
.menu-item-language-switcher {
    border-left: 1px solid rgba(255,255,255,0.2);
    margin-left: 15px;
    padding-left: 15px;
}
```

### Stile 3: Dropdown nel menu (stile compatto)

```php
// Nel functions.php, usa lo stile dropdown
$switcher = do_shortcode('[fp_lang_switcher style="dropdown" show_flags="1"]');
```

```css
.menu-item-language-switcher .fpml-switcher--dropdown {
    margin: 0;
}

.menu-item-language-switcher .fpml-switcher__select {
    border: none;
    background-color: transparent;
    color: inherit;
    padding: 8px 30px 8px 10px;
}
```

---

## 📱 Responsive - Adatta il Menu su Mobile

### Opzione 1: Mostra solo bandierine su mobile

```css
@media (max-width: 768px) {
    /* Nascondi il testo, mostra solo bandierine */
    .menu-item-language-switcher .fpml-switcher__item {
        font-size: 0;
    }
    
    .menu-item-language-switcher .fpml-switcher__flag {
        font-size: 20px;
    }
}
```

### Opzione 2: Sposta in fondo al menu mobile

```css
@media (max-width: 768px) {
    .menu-item-language-switcher {
        order: 999; /* Flexbox: metti per ultimo */
        width: 100%;
        justify-content: center;
        padding: 20px 0;
        border-top: 1px solid #ddd;
    }
}
```

---

## 🔍 Debug - Se non funziona

### 1. Verifica il theme_location

Aggiungi questo temporaneamente in `functions.php` per vedere tutti i menu:

```php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    // Mostra il theme_location nel menu (per debug)
    error_log( 'Menu location: ' . $args->theme_location );
    return $items;
}, 10, 2 );
```

Poi guarda i log di WordPress per vedere quale location usare.

### 2. Verifica che il filtro funzioni

Aggiungi un testo di test:

```php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( $args->theme_location === 'primary' ) {
        $items .= '<li>TEST FUNZIONA</li>';
    }
    return $items;
}, 10, 2 );
```

Se vedi "TEST FUNZIONA" nel menu, il filtro funziona e puoi sostituire con il selettore.

### 3. Controlla i conflitti CSS

Usa DevTools del browser (F12) per ispezionare il menu e vedere se il selettore è presente ma nascosto da CSS.

---

## 🎯 Esempi Pratici per Temi Popolari

### Astra Theme

```php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( $args->theme_location === 'primary' ) {
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    }
    return $items;
}, 10, 2 );
```

### GeneratePress

```php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( $args->theme_location === 'primary' ) {
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    }
    return $items;
}, 10, 2 );
```

### OceanWP

```php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( $args->theme_location === 'main_menu' ) { // OceanWP usa 'main_menu'
        $switcher = do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]');
        $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    }
    return $items;
}, 10, 2 );
```

### Elementor Header/Footer Builder

Se usi Elementor per l'header, **NON usare questo metodo**. Invece:

1. Modifica l'header in Elementor
2. Aggiungi un widget **Shortcode**
3. Inserisci: `[fp_lang_switcher style="inline" show_flags="1"]`

---

## 📝 Codice Completo Pronto all'Uso

Copia questo codice nel `functions.php` del tuo tema o in un plugin:

```php
/**
 * Aggiunge il selettore di lingua FP Multilanguage al menu di navigazione
 * 
 * @param string $items HTML degli elementi del menu
 * @param object $args  Argomenti del menu
 * @return string HTML modificato
 */
function fpml_add_language_switcher_to_nav_menu( $items, $args ) {
    // Configurazione: cambia 'primary' con il theme_location del tuo menu
    $menu_locations = array( 'primary' );
    
    // Verifica se siamo nel menu corretto
    if ( ! in_array( $args->theme_location, $menu_locations, true ) ) {
        return $items;
    }
    
    // Verifica che la funzione del plugin esista
    if ( ! function_exists( 'do_shortcode' ) ) {
        return $items;
    }
    
    // Genera il selettore di lingua
    $switcher = do_shortcode( '[fp_lang_switcher style="inline" show_flags="1"]' );
    
    // Opzioni di posizionamento:
    // 1. Alla fine del menu (default)
    $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>';
    
    // 2. All'inizio del menu (commentato)
    // $items = '<li class="menu-item menu-item-language-switcher">' . $switcher . '</li>' . $items;
    
    return $items;
}
add_filter( 'wp_nav_menu_items', 'fpml_add_language_switcher_to_nav_menu', 10, 2 );

/**
 * CSS personalizzato per il selettore nel menu
 */
function fpml_menu_switcher_custom_css() {
    ?>
    <style>
        /* Allineamento selettore lingua nel menu */
        .menu-item-language-switcher {
            display: flex;
            align-items: center;
        }
        
        .menu-item-language-switcher .fpml-switcher {
            margin: 0;
        }
        
        /* Rimuovi bullet points se presenti */
        .menu-item-language-switcher {
            list-style: none;
        }
        
        /* Separatore opzionale */
        .menu-item-language-switcher {
            border-left: 1px solid rgba(0,0,0,0.1);
            margin-left: 15px;
            padding-left: 15px;
        }
        
        /* Responsive: nascondi testo su mobile, mostra solo bandierine */
        @media (max-width: 768px) {
            .menu-item-language-switcher .fpml-switcher__item {
                font-size: 0;
                padding: 8px;
            }
            
            .menu-item-language-switcher .fpml-switcher__flag {
                font-size: 20px;
            }
            
            .menu-item-language-switcher .fpml-switcher__separator {
                font-size: 14px;
            }
        }
    </style>
    <?php
}
add_action( 'wp_head', 'fpml_menu_switcher_custom_css' );
```

---

## ✅ Checklist Finale

- [ ] Trovato il `theme_location` del menu
- [ ] Aggiunto codice in `functions.php`
- [ ] Cambiato `'primary'` con il tuo location
- [ ] Salvato e ricaricato il sito
- [ ] Verificato che il selettore appaia nel menu
- [ ] Aggiustato CSS se necessario
- [ ] Testato su mobile
- [ ] Testato cambio lingua cliccando

---

## 🆘 Problemi Comuni

### Non vedo il selettore nel menu
- Verifica il `theme_location` (vedi sezione Debug)
- Controlla che il menu sia assegnato a una posizione
- Svuota la cache se usi un plugin di cache

### Il selettore è presente ma disallineato
- Aggiungi il CSS personalizzato (vedi sezione CSS)
- Usa DevTools (F12) per ispezionare e debuggare

### Il menu mobile non mostra il selettore
- Alcuni temi usano JavaScript per il menu mobile
- Prova ad aggiungere il selettore anche lì (vedi esempi responsive)

---

**Hai bisogno di aiuto?** Scrivi un issue su GitHub con:
- Nome del tema WordPress
- Screenshot del menu
- Codice che hai provato

---

**Creato con ❤️ per FP Multilanguage**
