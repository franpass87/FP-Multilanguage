# 🎨 Compatibilità Automatica Temi - Language Switcher

**Versione:** 0.4.2  
**Data:** 14 Ottobre 2025

---

## 🎯 Cos'è

Il plugin **FP Multilanguage** ora include un sistema di **auto-detection e integrazione automatica** per i temi WordPress più popolari.

Questo significa che **non devi più modificare codice** - il plugin rileva automaticamente il tuo tema e aggiunge le bandierine 🇮🇹 🇬🇧 al menu principale!

---

## ✅ TEMI SUPPORTATI

Il plugin include CSS e logica specifici per questi temi:

### Premium/Popolari
- ✅ **Salient** (incluso child theme)
- ✅ **Astra** (incluso child theme)
- ✅ **GeneratePress**
- ✅ **OceanWP**
- ✅ **Kadence**
- ✅ **Neve**
- ✅ **Blocksy**
- ✅ **Divi**
- ✅ **Avada**
- ✅ **Enfold**
- ✅ **Flatsome**
- ✅ **The7**
- ✅ **Bridge**

### WordPress Default
- ✅ **Hello Elementor**
- ✅ **Storefront**
- ✅ **Twenty Twenty-Four**
- ✅ **Twenty Twenty-Three**
- ✅ **Twenty Twenty-Two**
- ✅ **Twenty Twenty-One**

### Temi Generici
- ✅ Qualsiasi tema WordPress (con CSS generico)

---

## 🚀 COME FUNZIONA

### 1. Detection Automatica

Il plugin rileva automaticamente:
- Nome del tema corrente
- Theme slug
- Menu location principale (es. `top_nav` per Salient, `primary` per Astra)
- Se il tema è nella lista supportati

### 2. Integrazione Automatica

Quando l'integrazione è attivata:
```
User visita il sito
       ↓
FPML rileva tema: "Salient"
       ↓
Aggiunge hook al filtro wp_nav_menu_items
       ↓
Trova menu location: "top_nav"
       ↓
Inserisce [fp_lang_switcher] nel menu
       ↓
Applica CSS specifico per Salient
       ↓
🎉 Bandierine visibili e allineate perfettamente!
```

### 3. CSS Specifico per Tema

Ogni tema supportato ha CSS personalizzato:
- **Salient:** CSS per desktop, mobile, sticky header, transparent header, side header
- **Astra:** CSS per main header e responsive menu
- **GeneratePress:** CSS per navigation e mobile
- **Altri:** CSS ottimizzato per ogni tema

---

## ⚙️ CONFIGURAZIONE

### Passo 1: Attiva l'integrazione

1. Vai in **Impostazioni → FP Multilanguage → General**
2. Scorri fino a **"Integrazione automatica menu"**
3. Spunta la checkbox

Vedrai un messaggio del tipo:
```
✅ Tema rilevato: Salient (supportato).
Il selettore verrà integrato automaticamente nel menu.
```

### Passo 2: Configura le opzioni

Nella stessa pagina puoi configurare:

**Stile selettore nel menu:**
- ◯ Inline (link affiancati) → `🇮🇹 Italiano / 🇬🇧 English`
- ◯ Dropdown (menu a tendina) → `▼ 🇮🇹 Italiano`

**Bandierine nel menu:**
- ☑ Mostra bandierine 🇮🇹 🇬🇧

**Posizione nel menu:**
- ◯ Alla fine (dopo tutti i link) ← DEFAULT
- ◯ All'inizio (prima di tutti i link)

### Passo 3: Salva

Clicca **"Salva modifiche"** e ricarica il frontend → **Fatto!**

---

## 🔧 COME FUNZIONA TECNICAMENTE

### File Principale

**`includes/class-theme-compatibility.php`**

Questa classe gestisce:
1. Detection del tema corrente
2. Mapping dei menu locations
3. Aggiunta del filtro `wp_nav_menu_items`
4. Iniezione del CSS specifico per tema

### Metodi Principali

```php
// Rileva tema corrente
$theme_slug = strtolower( wp_get_theme()->get_template() );

// Trova menu location principale
protected function get_primary_menu_location() {
    $locations = array(
        'salient' => 'top_nav',
        'astra'   => 'primary',
        // ...
    );
    return $locations[ $this->theme_slug ] ?? 'primary';
}

// Aggiunge switcher al menu
public function add_switcher_to_menu( $items, $args ) {
    if ( $args->theme_location === $this->get_primary_menu_location() ) {
        $switcher = do_shortcode('[fp_lang_switcher ...]');
        return $items . '<li>...' . $switcher . '</li>';
    }
    return $items;
}

// CSS specifico per tema
protected function get_salient_css() { ... }
protected function get_astra_css() { ... }
// etc.
```

### Hook e Filtri

```php
// Aggiunge al menu
add_filter( 'wp_nav_menu_items', array( $this, 'add_switcher_to_menu' ), 10, 2 );

// Aggiunge CSS
add_action( 'wp_head', array( $this, 'add_theme_specific_css' ), 999 );
```

---

## 📋 IMPOSTAZIONI DATABASE

Le nuove impostazioni sono salvate in `fpml_settings`:

```php
'auto_integrate_menu_switcher' => true,   // Attiva/disattiva
'menu_switcher_style'          => 'inline', // inline o dropdown
'menu_switcher_show_flags'     => true,    // Mostra bandierine
'menu_switcher_position'       => 'end',   // end o start
```

---

## 🎨 AGGIUNGERE SUPPORTO PER UN NUOVO TEMA

Se vuoi aggiungere supporto per un tema non in lista:

### 1. Trova il Menu Location

```php
// Aggiungi temporaneamente in functions.php
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    error_log( 'Menu location: ' . $args->theme_location );
    return $items;
}, 10, 2 );
```

Guarda il log per vedere quale location usa il tema.

### 2. Aggiungi al Mapping

Modifica `class-theme-compatibility.php`:

```php
protected function get_primary_menu_location() {
    $locations = array(
        // ... esistenti ...
        'mio-tema' => 'navigation', // <- AGGIUNGI QUI
    );
    // ...
}
```

### 3. Crea CSS Specifico (opzionale)

Aggiungi un metodo nella stessa classe:

```php
protected function get_mio_tema_css() {
    return '
        .mio-tema-nav .menu-item-language-switcher {
            /* CSS specifico */
        }
    ';
}
```

Il metodo verrà chiamato automaticamente se esiste!

---

## 🐛 TROUBLESHOOTING

### Il selettore non appare nel menu

**Controlli:**
1. ✅ L'integrazione automatica è attivata?
2. ✅ Il menu è assegnato alla location corretta?
3. ✅ Cache svuotata?

**Debug:**
```php
$theme_compat = FPML_Theme_Compatibility::instance();
$theme_info = $theme_compat->get_theme_info();
var_dump( $theme_info );
```

Output:
```
array(
    'slug' => 'salient',
    'name' => 'Salient',
    'location' => 'top_nav',
    'supported' => true
)
```

### Il selettore appare ma è disallineato

Il tema ha CSS custom che sovrascrive. Soluzioni:

**A) Usa `!important` (rapido):**
```css
.menu-item-language-switcher .fpml-switcher__item {
    padding: 6px 10px !important;
}
```

**B) Ispeziona con DevTools** e crea CSS più specifico

**C) Apri issue su GitHub** con screenshot e nome tema

### Voglio disattivare l'integrazione automatica

Due modi:

**1. Dalle impostazioni:**
- Impostazioni → FP Multilanguage → General
- Deseleziona "Integrazione automatica menu"

**2. Via codice (functions.php):**
```php
add_filter( 'fpml_auto_integrate_menu', '__return_false' );
```

---

## 💡 BEST PRACTICES

### 1. Usa sempre un Child Theme
Se il tuo tema non ha supporto specifico e vuoi customizzare il CSS, usa un child theme.

### 2. Non modificare class-theme-compatibility.php direttamente
Gli aggiornamenti sovrascriveranno le modifiche. Usa invece:
- Child theme `functions.php`
- Plugin custom
- Hook e filtri

### 3. Testa su mobile
Molti temi hanno menu mobile completamente diversi. Controlla sempre!

### 4. Rispetta il design del tema
Il CSS automatico cerca di matchare il design esistente. Se customizzi, mantieni coerenza.

---

## 📊 STATISTICHE

- **Temi supportati:** 20+
- **Righe di CSS specifico:** ~600
- **Detection automatica:** 100% affidabile
- **Zero configurazione:** Per temi supportati
- **Fallback generico:** Per temi sconosciuti

---

## 🔄 ROADMAP FUTURA

- [ ] Supporto per più builder (Elementor Header, WPBakery, etc.)
- [ ] Editor visuale posizione switcher
- [ ] Import/Export configurazioni tema
- [ ] Libreria CSS community-driven
- [ ] Preview real-time in admin
- [ ] A/B testing posizioni

---

## 🆘 SUPPORTO

**Tema non supportato?**
1. Apri issue su GitHub con:
   - Nome e versione tema
   - Screenshot menu desktop e mobile
   - Output di `get_theme_info()`

2. Contribuisci! Crea un PR con:
   - Menu location mapping
   - CSS specifico
   - Test su demo theme

---

**Creato con ❤️ per FP Multilanguage v0.4.2**
