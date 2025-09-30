# FP Multilanguage — Build State

- **Fase attuale:** 9 (Documentazione & Release) completata
- **Ultimo aggiornamento:** forzato `_wp_attachment_image_alt` e `_product_attributes` nella whitelist meta per garantire la traduzione di ALT e attributi WooCommerce anche su installazioni preesistenti.
- **File toccati (fix):** includes/class-plugin.php, docs/BUILD-STATE.md

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
