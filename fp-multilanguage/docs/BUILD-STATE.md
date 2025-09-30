# FP Multilanguage — Build State

- **Fase attuale:** 9 (Documentazione & Release) completata
- **Ultimo aggiornamento:** verifica del 30 settembre 2025 sulle fasi 1-9, senza ulteriori modifiche funzionali richieste.
- **File toccati (fix):** docs/BUILD-STATE.md

## Ripresa 2024-04-07

- **Fase 1 — Traduzione termini**
  - **Esito:** verifica delle routine esistenti per accodare i job su creazione/modifica termini e per sincronizzare le coppie IT↔EN. Nessuna modifica necessaria.
  - **File coinvolti:** includes/class-plugin.php, includes/class-queue.php, includes/class-processor.php, includes/class-language.php, includes/class-settings.php.
- **Fase 2 — Traduzione etichette menu**
  - **Esito:** confermata la sincronizzazione automatica delle voci e l’invio dei label custom al processore. Nessuna modifica necessaria.
  - **File coinvolti:** includes/class-menu-sync.php, includes/class-queue.php, includes/class-processor.php.
- **Fase 3 — Media ALT/CAPTION/TITLE + sostituzione ID frontend**
  - **Esito:** controllata la traduzione di title/caption/alt e la sostituzione ID nel frontend (contenuto, gallery, WPBakery). Nessuna modifica necessaria.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php, includes/class-media-front.php, includes/class-plugin.php.
- **Fase 4 — WooCommerce attributi**
  - **Esito:** verificata la traduzione ricorsiva dei metadati `_product_attributes` e il supporto agli attributi globali. Nessuna modifica necessaria.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php.
- **Fase 5 — Locale frontend EN**
  - **Esito:** confermato il forcing di `en_US` sul frontend quando fpml_lang=en. Nessuna modifica necessaria.
  - **File coinvolti:** includes/class-language.php.
- **Fase 6 — WPBakery hardening**
  - **Esito:** ricontrollata la precompilazione degli shortcode esclusi e la sostituzione ID in `[vc_single_image]`. Nessuna modifica necessaria.
  - **File coinvolti:** includes/class-settings.php, includes/class-media-front.php.
- **Fase 7 — Admin UX**
  - **Esito:** validata l’esperienza lato admin con colonna lingua, badge e notice configurabili. Nessuna modifica necessaria.
  - **File coinvolti:** admin/class-admin.php, admin/views/settings-general.php.
- **Fase 8 — Performance & KPI**
  - **Esito:** confermati il limite `max_chars_per_batch` e le metriche aggiuntive in diagnostica. Nessuna modifica necessaria.
  - **File coinvolti:** includes/class-processor.php, includes/class-settings.php, admin/views/settings-diagnostics.php.
- **Fase 9 — Documentazione & release**
  - **Esito:** verificata la documentazione aggiornata, inclusi changelog e versione plugin. Nessuna modifica necessaria.
  - **File coinvolti:** readme.txt, fp-multilanguage.php, docs/BUILD-STATE.md.

## Cronologia per fase

### Fase 1 — Traduzione termini
- Stato: completata
- Riepilogo: enqueue automatico su creazione/modifica, processore con traduzione name/description e persistenza mappature IT↔EN.
- File principali: includes/class-plugin.php, includes/class-queue.php, includes/class-processor.php, includes/class-language.php, includes/class-settings.php.

### Fase 2 — Traduzione etichette menu
- Stato: completata
- Riepilogo: sincronizzazione menu EN, traduzione label custom via queue e riuso titoli risorse EN.
- File principali: includes/class-menu-sync.php, includes/class-queue.php, includes/class-processor.php.

### Fase 3 — Media ALT/CAPTION/TITLE + sostituzione ID frontend
- Stato: completata
- Riepilogo: whitelist ALT di default (con fallback forzato per ALT/attributi), traduzione campi attachment e sostituzione ID IT→EN nel contenuto e shortcode.
- File principali: includes/class-settings.php, includes/class-processor.php, includes/class-media-front.php, includes/class-plugin.php.

### Fase 4 — WooCommerce attributi
- Stato: completata
- Riepilogo: supporto agli attributi globali pa_* e traduzione ricorsiva dei personalizzati `_product_attributes`.
- File principali: includes/class-settings.php, includes/class-processor.php.

### Fase 5 — Locale frontend EN
- Stato: completata
- Riepilogo: forzatura `en_US` sul frontend quando fpml_lang=en per caricare i textdomain inglesi.
- File principali: includes/class-language.php.

### Fase 6 — WPBakery hardening
- Stato: completata
- Riepilogo: popolamento automatico shortcodes esclusi e sostituzione ID in `[vc_single_image]`.
- File principali: includes/class-settings.php, includes/class-media-front.php.

### Fase 7 — Admin UX
- Stato: completata
- Riepilogo: colonna/filtro lingua nelle liste, badge titoli tradotti e avviso editor opzionale.
- File principali: admin/class-admin.php, admin/views/settings-general.php.

### Fase 8 — Performance & KPI
- Stato: completata
- Riepilogo: limite caratteri per batch e KPI aggiuntivi in diagnostica.
- File principali: includes/class-processor.php, includes/class-settings.php, admin/views/settings-diagnostics.php.

### Fase 9 — Documentazione & release
- Stato: completata
- Riepilogo: documentazione WooCommerce/WPBakery, media ALT e locale EN, bump versione 0.3.0.
- File principali: readme.txt, fp-multilanguage.php, docs/BUILD-STATE.md.

## Ripresa 2025-09-30

- **Fase 1 — Traduzione termini**
  - **Esito:** confermata la presenza dei job enqueue su creazione/modifica termini, la traduzione di name/description e l'aggiornamento delle mappature IT↔EN.
  - **File coinvolti:** includes/class-queue.php, includes/class-processor.php, includes/class-language.php.

- **Fase 2 — Traduzione etichette menu**
  - **Esito:** verificata la sincronizzazione delle label custom e il riuso dei titoli EN delle risorse abbinate.
  - **File coinvolti:** includes/class-menu-sync.php, includes/class-queue.php, includes/class-processor.php.

- **Fase 3 — Media ALT/CAPTION/TITLE + sostituzione ID frontend**
  - **Esito:** verificata la traduzione di title/caption/alt e la sostituzione degli ID IT→EN nel contenuto e nelle gallery.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php, includes/class-media-front.php, includes/class-plugin.php.

- **Fase 4 — WooCommerce attributi**
  - **Esito:** confermata la traduzione ricorsiva di `_product_attributes` e l'utilizzo dei termini globali pa_* tradotti.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php.

- **Fase 5 — Locale frontend EN**
  - **Esito:** confermata la forzatura `en_US` sul frontend quando fpml_lang=en così da caricare i textdomain inglesi.
  - **File coinvolti:** includes/class-language.php.

- **Fase 6 — WPBakery hardening**
  - **Esito:** confermata la precompilazione degli shortcode esclusi e la sostituzione degli ID nei blocchi `[vc_single_image]`.
  - **File coinvolti:** includes/class-settings.php, includes/class-media-front.php.

- **Fase 7 — Admin UX**
  - **Esito:** verificata la colonna/filtro lingua nelle liste, il badge "(EN)" e il notice editor personalizzabile.
  - **File coinvolti:** admin/class-admin.php, admin/views/settings-general.php.

- **Fase 8 — Performance & KPI**
  - **Esito:** verificato il rispetto del cap `max_chars_per_batch`, il tracking per job e l'esposizione dei KPI su termini/menu.
  - **File coinvolti:** includes/class-processor.php, includes/class-settings.php, admin/views/settings-diagnostics.php.

- **Fase 9 — Documentazione & release**
  - **Esito:** confermata la documentazione aggiornata con WooCommerce/WPBakery, media ALT/ID, locale EN e versione 0.3.0.
  - **File coinvolti:** readme.txt, fp-multilanguage.php, docs/BUILD-STATE.md.
