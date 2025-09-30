# FP Multilanguage — Build State

- **Fase attuale:** 9 (Documentazione & Release) completata
- **Ultimo aggiornamento:** riesecuzione completa del 30 settembre 2025 sulle fasi 1-9 (production-ready), confermate operative senza nuove modifiche funzionali.
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

## Ripresa 2025-09-30 (verifica supplementare)

- **Fase 1 — Traduzione termini**
  - **Esito:** riconfermata l'enqueue automatica dei job term:name/description su creazione e modifica, con aggiornamento delle coppie IT↔EN.
  - **File coinvolti:** includes/class-plugin.php, includes/class-queue.php, includes/class-processor.php, includes/class-language.php, includes/class-settings.php.

- **Fase 2 — Traduzione etichette menu**
  - **Esito:** verificata la sincronizzazione strutturale dei menu e la traduzione dei label custom via coda, riutilizzando i titoli EN delle risorse collegate.
  - **File coinvolti:** includes/class-menu-sync.php, includes/class-queue.php, includes/class-processor.php.

- **Fase 3 — Media ALT/CAPTION/TITLE + sostituzione ID frontend**
  - **Esito:** confermato il popolamento della whitelist con `_wp_attachment_image_alt`, la traduzione dei campi attachment e la sostituzione degli ID IT→EN nel contenuto, gallery e WPBakery.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php, includes/class-media-front.php, includes/class-plugin.php.

- **Fase 4 — WooCommerce attributi**
  - **Esito:** riconfermata la traduzione ricorsiva dei metadati `_product_attributes` e l'utilizzo dei termini globali pa_* tradotti.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php.

- **Fase 5 — Locale frontend EN**
  - **Esito:** verificata la forzatura del locale `en_US` sul frontend quando fpml_lang=en, con esclusione di admin/AJAX/REST.
  - **File coinvolti:** includes/class-language.php.

- **Fase 6 — WPBakery hardening**
  - **Esito:** confermata la precompilazione automatica degli shortcode esclusi e la sostituzione degli ID negli shortcode `[vc_single_image]`.
  - **File coinvolti:** includes/class-settings.php, includes/class-media-front.php.

- **Fase 7 — Admin UX**
  - **Esito:** validata la presenza della colonna lingua, del filtro e dei badge/notice configurabili nell'area editoriale.
  - **File coinvolti:** admin/class-admin.php, admin/views/settings-general.php.

- **Fase 8 — Performance & KPI**
  - **Esito:** confermato il rispetto del cap `max_chars_per_batch`, il tracking dei caratteri per job e l'esposizione dei KPI su termini e menu.
  - **File coinvolti:** includes/class-processor.php, includes/class-settings.php, admin/views/settings-diagnostics.php.

- **Fase 9 — Documentazione & release**
  - **Esito:** riconfermata la documentazione aggiornata con WooCommerce/WPBakery, media ALT/ID e locale EN, insieme al bump di versione 0.3.0.
  - **File coinvolti:** readme.txt, fp-multilanguage.php, docs/BUILD-STATE.md.

## Ripresa 2025-09-30 (ciclo production-ready)

- **Fase 1 — Traduzione termini**
  - **Esito:** controllato l'enqueue automatico su creazione/modifica termini, la traduzione name/description e l'allineamento delle coppie IT↔EN in archivio.
  - **File coinvolti:** includes/class-plugin.php, includes/class-queue.php, includes/class-processor.php, includes/class-language.php, includes/class-settings.php.
- **Fase 2 — Traduzione etichette menu**
  - **Esito:** verificata la sincronizzazione delle strutture EN, la traduzione dei label custom via coda e l'uso dei titoli EN delle risorse collegate.
  - **File coinvolti:** includes/class-menu-sync.php, includes/class-queue.php, includes/class-processor.php.
- **Fase 3 — Media ALT/CAPTION/TITLE + sostituzione ID frontend**
  - **Esito:** confermata la traduzione di title/caption/alt, l'inclusione della whitelist `_wp_attachment_image_alt` e la sostituzione degli ID IT→EN in contenuto, gallery e WPBakery.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php, includes/class-media-front.php, includes/class-plugin.php.
- **Fase 4 — WooCommerce attributi**
  - **Esito:** verificata la traduzione ricorsiva dei metadati `_product_attributes` e la coerenza dei termini globali pa_* tradotti.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php.
- **Fase 5 — Locale frontend EN**
  - **Esito:** confermata la forzatura condizionale del locale `en_US` sul frontend inglese, lasciando invariati admin, AJAX e REST.
  - **File coinvolti:** includes/class-language.php.
- **Fase 6 — WPBakery hardening**
  - **Esito:** controllata la precompilazione della lista shortcode esclusi e la sostituzione degli ID in `[vc_single_image]` tramite helper frontend.
  - **File coinvolti:** includes/class-settings.php, includes/class-media-front.php, includes/class-processor.php.
- **Fase 7 — Admin UX**
  - **Esito:** validati colonna e filtro lingua nelle liste, badge “(EN)” opzionale e notice editor configurabile nelle impostazioni.
  - **File coinvolti:** admin/class-admin.php, admin/views/settings-general.php.
- **Fase 8 — Performance & KPI**
  - **Esito:** confermato il cap `max_chars_per_batch`, il conteggio per job e l'esposizione delle metriche su termini/menu in diagnostica.
  - **File coinvolti:** includes/class-processor.php, includes/class-settings.php, admin/views/settings-diagnostics.php.
- **Fase 9 — Documentazione & release**
  - **Esito:** revisionati changelog/versione 0.3.0 e note README su WooCommerce, WPBakery, media ALT/ID e locale EN.
  - **File coinvolti:** readme.txt, fp-multilanguage.php, docs/BUILD-STATE.md.
## Ripresa 2025-09-30 (verifica correttiva)

- **Fase 1 — Traduzione termini**
  - **Esito:** ricontrollato l'accodamento automatico su created_term/edited_term, la traduzione name/description nel processor e l'aggiornamento delle coppie IT↔EN.
  - **File coinvolti:** includes/class-plugin.php, includes/class-queue.php, includes/class-processor.php, includes/class-language.php, includes/class-settings.php.

- **Fase 2 — Traduzione etichette menu**
  - **Esito:** verificata la sincronizzazione delle strutture EN con riuso dei titoli tradotti e la traduzione dei label custom via queue.
  - **File coinvolti:** includes/class-menu-sync.php, includes/class-queue.php, includes/class-processor.php.

- **Fase 3 — Media ALT/CAPTION/TITLE + sostituzione ID frontend**
  - **Esito:** confermate la whitelist `_wp_attachment_image_alt`, la traduzione dei campi attachment e la sostituzione degli ID IT→EN nel contenuto, gallery e WPBakery frontend.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php, includes/class-media-front.php, includes/class-plugin.php.

- **Fase 4 — WooCommerce attributi**
  - **Esito:** validata la traduzione ricorsiva dei metadati `_product_attributes` e la copertura degli attributi globali pa_*.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php.

- **Fase 5 — Locale frontend EN**
  - **Esito:** controllata la forzatura condizionale del locale `en_US` solo sul frontend inglese, lasciando invariati admin/AJAX/REST.
  - **File coinvolti:** includes/class-language.php.

- **Fase 6 — WPBakery hardening**
  - **Esito:** verificata la precompilazione degli shortcode esclusi e la sostituzione degli ID attachment negli shortcode `[vc_single_image]`.
  - **File coinvolti:** includes/class-settings.php, includes/class-media-front.php.

- **Fase 7 — Admin UX**
  - **Esito:** confermati colonna/filtro lingua, badge “(EN)” e notice editor configurabili dalle impostazioni.
  - **File coinvolti:** admin/class-admin.php, admin/views/settings-general.php.

- **Fase 8 — Performance & KPI**
  - **Esito:** ricontrollato il cap `max_chars_per_batch`, il tracking dei caratteri per job e le KPI su termini/menu in diagnostica.
  - **File coinvolti:** includes/class-processor.php, includes/class-settings.php, admin/views/settings-diagnostics.php.

- **Fase 9 — Documentazione & release**
  - **Esito:** verificata la documentazione aggiornata su WooCommerce/WPBakery, media ALT/ID e locale EN, con versione 0.3.0 coerente.
  - **File coinvolti:** readme.txt, fp-multilanguage.php, docs/BUILD-STATE.md.

## Ripresa 2025-10-08

- **Fase 1 — Traduzione termini**
  - **Esito:** rieseguita la verifica delle routine di enqueue su created_term/edited_term, della traduzione name/description lato processor e dell'allineamento delle coppie IT↔EN in archivio senza richiedere modifiche aggiuntive.
  - **File coinvolti:** includes/class-plugin.php, includes/class-queue.php, includes/class-processor.php, includes/class-language.php, includes/class-settings.php.

- **Fase 2 — Traduzione etichette menu**
  - **Esito:** riesaminata la sincronizzazione dei menu inglesi con traduzione dei label custom tramite queue e riuso dei titoli EN delle risorse collegate; nessuna ulteriore modifica necessaria.
  - **File coinvolti:** includes/class-menu-sync.php, includes/class-queue.php, includes/class-processor.php.

- **Fase 3 — Media ALT/CAPTION/TITLE + sostituzione ID frontend**
  - **Esito:** verificati la traduzione dei campi attachment, il popolamento della whitelist `_wp_attachment_image_alt` e la sostituzione IT→EN degli ID media su contenuto, gallery e WPBakery.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php, includes/class-media-front.php, includes/class-plugin.php.

- **Fase 4 — WooCommerce attributi**
  - **Esito:** controllata la traduzione ricorsiva dei metadati `_product_attributes` e la copertura degli attributi globali pa_* senza richiedere modifiche ulteriori.
  - **File coinvolti:** includes/class-settings.php, includes/class-processor.php.

- **Fase 5 — Locale frontend EN**
  - **Esito:** confermata la forzatura condizionale del locale `en_US` esclusivamente sul frontend quando fpml_lang=en, lasciando invariati admin, AJAX e REST.
  - **File coinvolti:** includes/class-language.php.

- **Fase 6 — WPBakery hardening**
  - **Esito:** riaffermata la precompilazione della lista shortcode esclusi e la sostituzione degli ID attachment negli shortcode `[vc_single_image]` attraverso il media helper frontend.
  - **File coinvolti:** includes/class-settings.php, includes/class-media-front.php.

- **Fase 7 — Admin UX**
  - **Esito:** confermata la presenza della colonna e del filtro lingua nelle liste, insieme ai badge "(EN)" e al notice editor configurabili dalle impostazioni.
  - **File coinvolti:** admin/class-admin.php, admin/views/settings-general.php.

- **Fase 8 — Performance & KPI**
  - **Esito:** verificato il rispetto del cap `max_chars_per_batch`, il conteggio dei caratteri per job e l'esposizione delle metriche su termini e menu nella diagnostica.
  - **File coinvolti:** includes/class-processor.php, includes/class-settings.php, admin/views/settings-diagnostics.php.

- **Fase 9 — Documentazione & release**
  - **Esito:** confermata la coerenza della documentazione aggiornata (WooCommerce, WPBakery, media ALT/ID, locale EN) e del bump di versione 0.3.0.
  - **File coinvolti:** readme.txt, fp-multilanguage.php, docs/BUILD-STATE.md.
