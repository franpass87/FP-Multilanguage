# 🇮🇹 🇬🇧 Guida al Selettore di Lingua (Language Switcher)

Il plugin **FP Multilanguage** include un selettore di lingua completamente funzionale con supporto per le bandierine.

---

## 🚀 Metodi di Utilizzo

### 1. **Widget WordPress** (Consigliato)

Il modo più semplice per aggiungere il selettore di lingua al tuo sito:

1. Vai in **Aspetto → Widget** nel pannello WordPress
2. Cerca il widget **"Selettore Lingua FP"**
3. Trascinalo nella sidebar o area widget desiderata
4. Configura le opzioni:
   - **Titolo**: (opzionale) es. "Lingua / Language"
   - **Stile**: Scegli tra `Inline` o `Dropdown`
   - **Mostra bandierine**: Attiva per vedere 🇮🇹 🇬🇧

**Screenshot impostazioni widget:**
```
┌─────────────────────────────────┐
│ Titolo: Language                │
│                                 │
│ Stile: ▼ Inline                 │
│                                 │
│ ☑ Mostra bandierine 🇮🇹 🇬🇧      │
└─────────────────────────────────┘
```

---

### 2. **Shortcode**

Puoi inserire il selettore ovunque nel tuo sito usando lo shortcode:

#### Esempi base:

```php
// Stile inline con bandierine
[fp_lang_switcher style="inline" show_flags="1"]

// Dropdown con bandierine
[fp_lang_switcher style="dropdown" show_flags="1"]

// Solo testo (senza bandierine)
[fp_lang_switcher style="inline" show_flags="0"]
```

#### Dove inserire lo shortcode:

- **Nei post/pagine**: Direttamente nell'editor WordPress
- **Nei template**: `<?php echo do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]'); ?>`
- **In Gutenberg**: Usa il blocco "Shortcode"

---

### 3. **Codice PHP nel Template**

Se vuoi inserire il selettore direttamente nel tuo tema:

```php
// Nel tuo header.php, footer.php o altro template
<?php
if ( function_exists( 'FPML_Language' ) ) {
    $language = FPML_Language::instance();
    echo $language->render_switcher( array(
        'style'      => 'inline',
        'show_flags' => '1',
    ) );
}
?>
```

---

## 🎨 Stili Disponibili

### **Inline** (Link affiancati)

Mostra le lingue una accanto all'altra:

```
🇮🇹 Italiano  /  🇬🇧 English
```

**Caratteristiche:**
- Design compatto
- Ideale per header/footer
- Linguaggio corrente evidenziato in blu

### **Dropdown** (Menu a tendina)

Mostra un menu a discesa:

```
┌─────────────────┐
│ 🇮🇹 Italiano  ▼ │
└─────────────────┘
```

**Caratteristiche:**
- Risparmia spazio
- Ideale per mobile
- Cambio automatico al click

---

## 🎯 Parametri Shortcode

| Parametro | Valori | Default | Descrizione |
|-----------|--------|---------|-------------|
| `style` | `inline` o `dropdown` | `inline` | Stile di visualizzazione |
| `show_flags` | `1`, `true`, `yes` o `0` | `0` | Mostra/nascondi bandierine |

---

## 🎨 Personalizzazione CSS

Il selettore usa classi CSS che puoi personalizzare nel tuo tema:

### Classi principali:

```css
/* Container principale */
.fpml-switcher { }

/* Stile inline */
.fpml-switcher--inline { }

/* Singolo link */
.fpml-switcher__item { }

/* Lingua corrente */
.fpml-switcher__item--current { }

/* Bandierina emoji */
.fpml-switcher__flag { }

/* Separatore (/) */
.fpml-switcher__separator { }

/* Dropdown */
.fpml-switcher--dropdown { }
.fpml-switcher__select { }
```

### Esempio personalizzazione:

Aggiungi nel tuo tema (in `style.css` o Customizer):

```css
/* Cambia colore lingua corrente */
.fpml-switcher__item--current {
    background-color: #ff6b35;
    border-color: #ff6b35;
}

/* Ingrandisci le bandierine */
.fpml-switcher__flag {
    font-size: 24px;
}

/* Stile compatto per mobile */
@media (max-width: 768px) {
    .fpml-switcher__item {
        padding: 4px 8px;
        font-size: 12px;
    }
}
```

---

## 📱 Responsive & Dark Mode

Il selettore è completamente **responsive** e supporta il **dark mode** automatico:

- ✅ Ottimizzato per mobile (font-size più grande su touch)
- ✅ Dark mode automatico (se il tema lo supporta)
- ✅ Accessibilità completa (focus, outline, ARIA)

---

## 🔧 Esempi Pratici

### Esempio 1: Header del tema

In `header.php`:

```php
<header class="site-header">
    <div class="container">
        <nav class="main-nav">
            <a href="<?php echo home_url(); ?>">Logo</a>
            
            <!-- Selettore lingua nell'header -->
            <?php echo do_shortcode('[fp_lang_switcher style="inline" show_flags="1"]'); ?>
        </nav>
    </div>
</header>
```

### Esempio 2: Footer

In `footer.php`:

```php
<footer class="site-footer">
    <div class="footer-widgets">
        <!-- Altri widget -->
    </div>
    
    <div class="footer-bottom">
        <p>© 2024 Il Mio Sito</p>
        
        <!-- Selettore lingua nel footer -->
        <?php echo do_shortcode('[fp_lang_switcher style="dropdown" show_flags="1"]'); ?>
    </div>
</footer>
```

### Esempio 3: In Elementor/Visual Builder

1. Aggiungi un elemento **Shortcode** o **Codice HTML**
2. Inserisci: `[fp_lang_switcher style="inline" show_flags="1"]`
3. Salva e pubblica

---

## 🌐 Come Funziona

Il selettore mostra:

- **🇮🇹 Italiano** → Link alla versione italiana del contenuto corrente
- **🇺🇸 English** → Link alla versione inglese del contenuto corrente

**Comportamento intelligente:**
- Se sei su un post/pagina, il link porta alla traduzione di quel contenuto
- Se la traduzione non esiste, porta alla homepage nella lingua selezionata
- La lingua corrente è evidenziata visivamente

---

## ❓ FAQ

### Le bandierine non si vedono
- Assicurati che il parametro `show_flags` sia `"1"`, `"true"` o `"yes"`
- Verifica che il tuo browser supporti le emoji (tutti i browser moderni lo fanno)

### Il selettore non ha stile
- Svuota la cache del sito
- Verifica che il file `fp-multilanguage/assets/frontend.css` esista
- Controlla la console del browser per errori 404

### Voglio cambiare le bandierine
Le bandierine sono definite nel file `includes/class-language.php`:

```php
$flags = array(
    self::SOURCE => '🇮🇹',  // Italia
    self::TARGET => '🇬🇧',  // Regno Unito
);
```

Puoi cambiarle con altre emoji (es. 🇺🇸 per Stati Uniti, 🇦🇺 per Australia, 🇨🇦 per Canada).

### Posso aggiungere altre lingue?
Attualmente il plugin supporta solo italiano ↔ inglese. Per più lingue, dovresti estendere il codice.

---

## 🆘 Supporto

- **Documentazione**: [docs/](.)
- **Issues**: [GitHub Issues](https://github.com/francescopasseri/FP-Multilanguage/issues)
- **Email**: info@francescopasseri.com

---

**Creato con ❤️ da Francesco Passeri**
