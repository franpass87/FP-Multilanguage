# FP Multilanguage - Assets Modulari

## 📁 Struttura

```
assets/
├── css/                    # Moduli CSS separati
│   ├── _variables.css      # Variabili CSS custom properties
│   ├── layout.css          # Layout e griglia
│   ├── forms.css           # Stili per form
│   ├── tables.css          # Stili per tabelle
│   ├── cards.css           # Componenti card
│   └── diagnostics.css     # UI diagnostica
│
├── js/                     # Moduli JavaScript ES6
│   ├── utils.js            # Utility functions
│   ├── template-engine.js  # Template engine per sostituzioni
│   ├── toggle.js           # Gestione toggle UI
│   ├── api-client.js       # Client API REST
│   ├── diagnostics.js      # UI diagnostica
│   └── action-buttons.js   # Handler pulsanti azione
│
├── admin.css               # Entry point CSS (con @import)
├── admin.js                # Entry point JavaScript (ES6 modules)
├── admin-compiled.css      # CSS compilato per produzione
├── admin-compiled.js       # JavaScript compilato per produzione
│
├── build.sh                # Script build completo
├── build-css.sh            # Build solo CSS
└── build-js.sh             # Build solo JavaScript
```

## 🚀 Utilizzo

### Modalità Sviluppo

Per lavorare in modalità sviluppo con i moduli separati:

1. Aggiungi questa costante in `wp-config.php`:
   ```php
   define('FPML_DEV_MODE', true);
   ```

2. I browser moderni caricheranno i moduli ES6 nativamente
3. Modifica i file nella cartella `css/` e `js/`

### Modalità Produzione

1. Dopo le modifiche, esegui il build:
   ```bash
   cd fp-multilanguage/assets
   bash build.sh
   ```

2. Rimuovi `FPML_DEV_MODE` da `wp-config.php` (o impostalo a `false`)
3. Il plugin caricherà automaticamente i file compilati

## 🔨 Build

### Build Completo
```bash
bash build.sh
```

### Build Solo CSS
```bash
bash build-css.sh
```

### Build Solo JavaScript
```bash
bash build-js.sh
```

## 📝 Convenzioni

### CSS

- **Variabili**: Usa le CSS custom properties definite in `_variables.css`
- **Naming**: Prefisso `fpml-` per tutte le classi
- **Organizzazione**: Un file per ogni area funzionale
- **BEM**: Usa la convenzione BEM dove appropriato

Esempio:
```css
.fpml-component {}
.fpml-component__element {}
.fpml-component--modifier {}
```

### JavaScript

- **ES6 Modules**: Tutti i file usano import/export
- **Naming**: camelCase per funzioni e variabili
- **Export**: Esporta solo funzioni pubbliche
- **Documentazione**: JSDoc per funzioni complesse

Esempio:
```javascript
/**
 * Descrizione funzione
 * @param {string} param Descrizione parametro
 * @returns {boolean}
 */
export const myFunction = (param) => {
    // implementazione
};
```

## 🎯 Vantaggi della Modularizzazione

1. **Manutenibilità**: Codice organizzato e facile da trovare
2. **Riusabilità**: Moduli indipendenti riutilizzabili
3. **Performance**: File compilati ottimizzati per produzione
4. **Developer Experience**: Hot reload in dev mode
5. **Scalabilità**: Facile aggiungere nuove funzionalità

## 🔧 PHP Autoloader

Il plugin include anche un autoloader PSR-4 per le classi PHP:

- `includes/class-autoloader.php` - Autoloader custom
- Caricamento lazy delle classi
- Fallback al sistema di caricamento legacy

## 📦 Estensibilità

Per aggiungere nuovi moduli:

### CSS
1. Crea un nuovo file in `css/nome-modulo.css`
2. Aggiungilo a `build-css.sh` nell'ordine corretto
3. Ricostruisci con `bash build-css.sh`

### JavaScript
1. Crea un nuovo file in `js/nome-modulo.js`
2. Usa `export` per le funzioni pubbliche
3. Importalo in `admin.js` o in altri moduli
4. Aggiungilo a `build-js.sh`
5. Ricostruisci con `bash build-js.sh`

## 🐛 Debug

In modalità sviluppo puoi:
- Ispezionare i singoli moduli nel browser
- Usare source maps (se configurate)
- Vedere errori specifici per modulo
- Testare modifiche senza rebuild

## 📚 Riferimenti

- [CSS Custom Properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties)
- [ES6 Modules](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules)
- [BEM Methodology](http://getbem.com/)
- [PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)