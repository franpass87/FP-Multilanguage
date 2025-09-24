# FP Multilanguage

FP Multilanguage è un plugin WordPress enterprise-ready per orchestrare contenuti multilingua, traduzioni automatiche/manuali e SEO avanzata. Fornisce un service container per il bootstrapping dei componenti principali (impostazioni, traduzione, gestione post, stringhe dinamiche, SEO, widget e CLI) e integra provider esterni come Google Cloud Translation e DeepL.

## Caratteristiche principali

- **Bootstrap modulare** con container PSR-4 e dependency injection per semplificare i test.
- **Gestione impostazioni** con pagina admin, salvataggio async via REST, schede (Generale/Provider/SEO/Quote) e script `admin.js`.
- **Servizio di traduzione** con caching multi-layer, rate limiting, quote aggregate (`fp_multilanguage_quota`), retry con backoff e fallback manuali.
- **Provider dedicati** (`GoogleProvider`, `DeepLProvider`) basati su `TranslationProviderInterface` e estensibilità tramite filtro `fp_multilanguage_provider_sequence`.
- **Glossari personalizzati** configurabili da backend per Google Cloud e DeepL (ID risorsa, ignore-case, livello di formalità).
- **Risoluzione lingua corrente** tramite query var, cookie, preferenze utente, URL rewriting e filtri.
- **Gestione contenuti** con traduzione automatica su `save_post`, esposizione REST/CLI, filtri front-end (`the_content`, `the_title`, `get_the_excerpt`, `wp_get_attachment_image_attributes`) e metadati persistiti.
- **Stringhe dinamiche** con storage in tabella custom (`wp_fp_multilanguage_strings`), API REST, AJAX editor inline e fallback JS.
- **SEO avanzato**: meta box per title/description/slug per lingua, tag `hreflang`, `canonical`, `og:*`, sitemap alternate, integrazione `robots.txt`.
- **Widget e shortcode** per lo switcher linguistico con localStorage/cookie per ricordare la scelta.
- **CLI** (`wp fp-multilanguage translate <post_id> [--language=xx]`) per rigenerare traduzioni.
- **Asset pipeline** basata su Vite (`npm run build`), con script frontend/admin dedicati.
- **Tooling**: PHPUnit, PHPStan, PHPCS (WordPress-Core), Mockery, Brain Monkey e composer scripts (`test`, `lint`, `stan`, `qa`).

## Requisiti

- PHP 7.4 o superiore (compatibile 8.0/8.1/8.2).
- WordPress 6.0+.
- Chiave API per i provider di traduzione desiderati.

## Installazione

1. Clona il repository e copia la cartella `fp-multilanguage/` dentro `wp-content/plugins/`.
2. Installa le dipendenze PHP (opzionale per sviluppo):
   ```bash
   composer install
   ```
3. (Opzionale) installa tool JS:
   ```bash
   npm install
   ```
4. Attiva **FP Multilanguage** dalla dashboard WordPress.

## Configurazione

- Vai in **Impostazioni → FP Multilanguage** per definire lingua sorgente/fallback, lingue di destinazione, provider (Google/DeepL), SEO e monitor quote.
- I codici lingua sono normalizzati automaticamente e supportano formati regionali (`pt-BR`, `es-ES`, `zh-Hant`).
- Il bottone “Sincronizza via REST” usa `admin.js` per inviare le opzioni all’endpoint `fp-multilanguage/v1/settings` (nonce `fp_multilanguage_settings`).
- Le traduzioni manuali delle stringhe sono gestite tramite AJAX (`wp_ajax_fp_multilanguage_save_string`) e REST (`fp-multilanguage/v1/strings`).

## Uso quotidiano

### Traduzione post e pagine

- Ogni salvataggio di post/pagine genera traduzioni (`_fp_multilanguage_translations`) per campi core e metadati custom definiti da `fp_multilanguage_custom_fields`.
- Front-end: la lingua corrente è selezionata con query var `fp_lang`, cookie `fp_multilanguage_lang`, preferenze utente o filtri. I contenuti localizzati sono restituiti dai filtri registrati.
- REST: i dati sono esposti nel campo `fp_multilanguage` per post, pagine e media.
- CLI: `wp fp-multilanguage translate 42 --language=it` forza la rigenerazione della lingua indicata.

### Stringhe dinamiche

- Filtri registrati: `gettext`, `gettext_with_context`, `ngettext`, `widget_title`, `widget_text_content`, `nav_menu_item_title`, `theme_mod_custom_logo`.
- `dynamic-translations.js` abilita la modifica inline (dblclick) per utenti con `manage_options` e salva via AJAX.
- Le stringhe sono persistite nella tabella `wp_fp_multilanguage_strings` (fallback su opzione) con meta dati JSON.

### SEO

- Meta box “SEO multilingua” per title/description/slug per ogni lingua.
- Output `hreflang`, `canonical`, `og:*`, `meta description` e sitemap alternate (anche con Yoast/Rank Math grazie ai filtri `wpseo_*`).
- `robots.txt` dinamico aggiunge link alla sitemap localizzata.

### Widget/Shortcode

- Widget **FP Multilanguage Switcher** e shortcode `[fp_language_switcher layout="inline"]`.
- `frontend.js` sincronizza cookie e localStorage per ricordare la lingua.

## Comandi utili

```bash
composer test     # PHPUnit
composer lint     # PHPCS
composer stan     # PHPStan
composer qa       # lint + stan + test
npm run dev       # Avvio Vite in modalità sviluppo
npm run build     # Build asset produzione
npm run make-pot  # Genera file .pot
```

## Struttura directory (estratto)

```
fp-multilanguage/
├── fp-multilanguage.php
├── includes/
│   ├── Plugin.php
│   ├── Support/Container.php
│   ├── Admin/{Settings,AdminNotices}.php
│   ├── Services/{Logger,TranslationService,TranslationResponse.php}
│   ├── Services/Providers/{GoogleProvider,DeepLProvider,TranslationProviderInterface}.php
│   ├── Content/PostTranslationManager.php
│   ├── Dynamic/DynamicStrings.php
│   ├── SEO/SEO.php
│   ├── Install/Migrator.php
│   ├── Widgets/LanguageSwitcher.php
│   └── CLI/Commands.php
├── assets/js/{frontend.js,admin.js,dynamic-translations.js}
├── languages/fp-multilanguage.pot
└── tests/
    ├── bootstrap.php
    ├── stubs/wordpress.php
    └── *Test.php
```

## Documentazione aggiuntiva

- `docs/` contiene guide estese, API e troubleshooting.
- `docs/ROADMAP.md` elenca le funzionalità previste (glossari, media, commenti, ecc.).
- `QA_REPORT.md` riassume le validazioni manuali/automatiche (PHP 7.4/8.x, WordPress ultime release, caching plugin).

## Supporto e estensioni

- I provider custom possono registrarsi via `fp_multilanguage_provider_register` / `fp_multilanguage_provider_sequence`.
- Per modificare i meta SEO generati usare `fp_multilanguage_seo_meta` o i filtri WordPress standard.
- Per contribuire apri una issue o proponi una pull request seguendo lo stile WordPress-Core (PHPCS).
