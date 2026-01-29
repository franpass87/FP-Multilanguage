# Report Esecuzione Test FP-Multilanguage
**Data Esecuzione**: 2025-01-23  
**Versione Plugin**: 0.9.1  
**Ambiente**: WordPress Local Development

---

## Metodologia Test

### Test Automatici
- Script PHP per verifica struttura (`tests/test-plugin-structure.php`)
- PHPUnit test suite esistente (`tests/phpunit/`)
- E2E Playwright tests (`tests/e2e/`)

### Test Manuali
- Navigazione admin interface
- Test funzionalità frontend
- Verifica integrazioni

---

## 1. Test Backend - Admin Interface

### 1.1 Pagine Admin e Navigation

#### Dashboard (`settings-dashboard.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Dashboard`
2. Verificare che la pagina carichi senza errori PHP/JavaScript
3. Verificare presenza statistiche:
   - Post tradotti (count)
   - Job in coda (pending)
   - Job falliti (failed)
   - Costo mensile
4. Verificare chart attività settimanale (se presente)
5. Verificare quick actions funzionanti
6. Verificare alert se API key mancante/errata
7. Verificare system info display

**File da Verificare**: `admin/views/settings-dashboard.php`

#### Settings General (`settings-general.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Generale`
2. Verificare caricamento pagina
3. Modificare routing mode (segment/subdomain/domain)
4. Salvare e verificare salvataggio
5. Modificare language settings (default: it, source: it, target: en)
6. Toggle auto-translate on/off
7. Testare validazione campi (es. campi obbligatori)

**File da Verificare**: `admin/views/settings-general.php`

#### Settings Content (`settings-content.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Contenuto`
2. Verificare lista post types configurabili
3. Aggiungere/rimuovere post types dalla lista traduzione
4. Verificare tassonomie configurabili
5. Verificare meta fields whitelist (lista campi da tradurre)
6. Testare esclusione contenuti specifici

**File da Verificare**: `admin/views/settings-content.php`

#### Settings Provider
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a tab Provider (potrebbe essere in Settings → General)
2. Inserire OpenAI API key
3. Cliccare "Test Connection" e verificare risposta
4. Verificare che API key sia encrypted in database:
   ```sql
   SELECT option_value FROM wp_options WHERE option_name LIKE 'fpml%openai%';
   -- Dovrebbe essere encrypted, non plain text
   ```
5. Cliccare "Check Billing" e verificare risposta
6. Usare "Preview Translation" con testo di test

**File da Verificare**: Componenti Provider in `src/Providers/`

#### Settings SEO (`settings-seo.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → SEO`
2. Verificare opzioni hreflang tags
3. Verificare opzioni canonical URLs
4. Configurare meta tags
5. Salvare e verificare applicazione frontend

**File da Verificare**: `admin/views/settings-seo.php`

#### Settings Translations (`settings-translations.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Traduzioni`
2. Verificare lista traduzioni con colonne:
   - Post originale (IT)
   - Post tradotto (EN)
   - Status (pending/done/error)
   - Data traduzione
3. Testare filtri:
   - Per post type
   - Per status
   - Per data
4. Testare azioni bulk:
   - Regenerate selected
   - Delete selected
   - Re-translate selected
5. Testare regenerate singola traduzione
6. Verificare versioning/rollback per traduzione

**File da Verificare**: `admin/views/settings-translations.php`

#### Settings Site Parts (`settings-site-parts.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Site Parts` (o equivalente)
2. Verificare menu sync status
3. Testare traduzione stringhe sito
4. Verificare widget settings

**File da Verificare**: `admin/views/settings-site-parts.php`

#### Settings Glossary (`settings-glossary.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Glossario`
2. Aggiungere nuova regola glossary:
   - Termine IT → Termine EN
3. Rimuovere regola
4. Testare import CSV:
   - Creare file CSV con formato: `italian,english`
   - Importare e verificare regole aggiunte
5. Testare export CSV
6. Creare post con termine glossary e verificare traduzione applica regola

**File da Verificare**: `admin/views/settings-glossary.php`

#### Settings Diagnostics (`settings-diagnostics.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Diagnostica`
2. Verificare system check mostra:
   - PHP version
   - WordPress version
   - Plugin version
   - Database tables
   - API key status
3. Verificare queue status:
   - Pending jobs count
   - Processing jobs
   - Failed jobs
4. Verificare logs display (ultimi errori)
5. Testare export logs

**File da Verificare**: `admin/views/settings-diagnostics.php`

#### Settings Export (`settings-export.php`)
**Status**: ⏳ Da Eseguire

**Test da Eseguire**:
1. Accedere a `WP Admin → FP Multilanguage → Export/Import`
2. Testare export state:
   - Cliccare "Export State"
   - Verificare download file JSON
   - Verificare file contiene: settings, glossary, translations mapping
3. Testare import state:
   - Caricare file export precedente
   - Verificare import completo
4. Testare sandbox management (se presente)

**File da Verificare**: `admin/views/settings-export.php`

### 1.2 AJAX Handlers

**Status**: ⏳ Da Eseguire

**Metodo Test**: Aprire browser DevTools → Network → XHR, eseguire azioni e verificare chiamate AJAX

**Test da Eseguire**:

1. **fpml_refresh_nonce**
   - Trigger: Qualsiasi azione che richieda nonce refresh
   - Verificare: Response contiene nuovo nonce valido
   - Verificare: Nonce invalido restituisce error

2. **fpml_reindex_batch_ajax**
   - Trigger: Click "Reindex" in Diagnostics
   - Verificare: Progress updates durante batch processing
   - Verificare: Permission check (solo admin)
   - Verificare: Nonce verification

3. **fpml_cleanup_orphaned_pairs**
   - Trigger: Click "Cleanup Orphaned Pairs"
   - Verificare: Pairs orfani rimossi
   - Verificare: Response con count rimossi

4. **fpml_trigger_detection**
   - Trigger: Click "Trigger Detection" (se presente)
   - Verificare: Detection process avviato
   - Verificare: Response con risultati

5. **fpml_bulk_translate**
   - Trigger: Bulk translate da Translations page
   - Verificare: Jobs creati in queue
   - Verificare: Progress tracking
   - Verificare: Error handling se alcuni falliscono

6. **fpml_bulk_regenerate**
   - Trigger: Bulk regenerate da Translations page
   - Verificare: Traduzioni rigenerate
   - Verificare: Progress tracking

7. **fpml_bulk_sync**
   - Trigger: Bulk sync (se presente)
   - Verificare: Sync completato

8. **fpml_translate_single**
   - Trigger: "Translate Now" da metabox o post list
   - Verificare: Job creato
   - Verificare: Traduzione completata

9. **fpml_translate_site_part**
   - Trigger: Translate site part (menu, widget, etc.)
   - Verificare: Traduzione completata

**File da Verificare**: `src/Admin/Ajax/AjaxHandlers.php`

### 1.3 REST API Endpoints

**Status**: ⏳ Da Eseguire

**Metodo Test**: Usare tool come Postman, curl, o browser per chiamate REST

**Base URL**: `https://your-site.com/wp-json/fpml/v1/`

**Autenticazione**: Usare WordPress Application Password o cookie authentication

**Test da Eseguire**:

#### Queue Routes

1. **POST /fpml/v1/queue/run**
   ```bash
   curl -X POST "https://site.com/wp-json/fpml/v1/queue/run" \
     -H "Authorization: Basic ..." \
     -H "Content-Type: application/json"
   ```
   - Verificare: Permission check (403 se non autenticato)
   - Verificare: Queue processing avviato
   - Verificare: Response con jobs processati

2. **POST /fpml/v1/queue/cleanup**
   ```bash
   curl -X POST "https://site.com/wp-json/fpml/v1/queue/cleanup" \
     -H "Authorization: Basic ..."
   ```
   - Verificare: Old jobs rimossi
   - Verificare: Response con count

#### Provider Routes

3. **POST /fpml/v1/test-provider**
   ```bash
   curl -X POST "https://site.com/wp-json/fpml/v1/test-provider" \
     -H "Authorization: Basic ..." \
     -d '{"provider":"openai"}'
   ```
   - Verificare: Connection test eseguito
   - Verificare: Response con success/error

4. **POST /fpml/v1/preview-translation**
   ```bash
   curl -X POST "https://site.com/wp-json/fpml/v1/preview-translation" \
     -H "Authorization: Basic ..." \
     -d '{"text":"Ciao mondo","source":"it","target":"en"}'
   ```
   - Verificare: Traduzione preview restituita
   - Verificare: Sanitization input text
   - Verificare: Error se text vuoto

5. **POST /fpml/v1/check-billing**
   ```bash
   curl -X POST "https://site.com/wp-json/fpml/v1/check-billing" \
     -H "Authorization: Basic ..." \
     -d '{"provider":"openai"}'
   ```
   - Verificare: Billing status restituito

6. **GET /fpml/v1/refresh-nonce**
   ```bash
   curl "https://site.com/wp-json/fpml/v1/refresh-nonce" \
     -H "Authorization: Basic ..."
   ```
   - Verificare: Nuovo nonce restituito

#### Reindex Routes

7. **POST /fpml/v1/reindex**
   ```bash
   curl -X POST "https://site.com/wp-json/fpml/v1/reindex" \
     -H "Authorization: Basic ..."
   ```
   - Verificare: Reindex avviato
   - Verificare: Response con progress

8. **POST /fpml/v1/reindex-batch**
   ```bash
   curl -X POST "https://site.com/wp-json/fpml/v1/reindex-batch" \
     -H "Authorization: Basic ..." \
     -d '{"step":0}'
   ```
   - Verificare: Batch step eseguito
   - Verificare: Response con next step

#### System Routes

9. **GET /fpml/v1/health**
   ```bash
   curl "https://site.com/wp-json/fpml/v1/health" \
     -H "Authorization: Basic ..."
   ```
   - Verificare: Health status restituito
   - Verificare: Componenti verificati

10. **GET /fpml/v1/stats**
    ```bash
    curl "https://site.com/wp-json/fpml/v1/stats" \
      -H "Authorization: Basic ..."
    ```
    - Verificare: Statistics restituite
    - Verificare: Dati corretti

11. **GET /fpml/v1/logs**
    ```bash
    curl "https://site.com/wp-json/fpml/v1/logs?limit=10" \
      -H "Authorization: Basic ..."
    ```
    - Verificare: Logs restituiti
    - Verificare: Pagination funziona

#### Translation Routes

12. **GET /fpml/v1/translations**
    ```bash
    curl "https://site.com/wp-json/fpml/v1/translations?post_type=post&status=done" \
      -H "Authorization: Basic ..."
    ```
    - Verificare: Lista traduzioni restituita
    - Verificare: Filtri funzionano

13. **POST /fpml/v1/translations/bulk**
    ```bash
    curl -X POST "https://site.com/wp-json/fpml/v1/translations/bulk" \
      -H "Authorization: Basic ..." \
      -d '{"post_ids":[1,2,3]}'
    ```
    - Verificare: Jobs creati
    - Verificare: Validation post_ids

14. **POST /fpml/v1/translations/{id}/regenerate**
    ```bash
    curl -X POST "https://site.com/wp-json/fpml/v1/translations/123/regenerate" \
      -H "Authorization: Basic ..."
    ```
    - Verificare: Traduzione rigenerata
    - Verificare: Validation ID

15. **GET /fpml/v1/translations/{id}/versions**
    ```bash
    curl "https://site.com/wp-json/fpml/v1/translations/123/versions" \
      -H "Authorization: Basic ..."
    ```
    - Verificare: Versioni restituite

16. **POST /fpml/v1/translations/{id}/rollback**
    ```bash
    curl -X POST "https://site.com/wp-json/fpml/v1/translations/123/rollback" \
      -H "Authorization: Basic ..." \
      -d '{"version":2}'
    ```
    - Verificare: Rollback eseguito
    - Verificare: Validation version

**File da Verificare**: `src/Rest/RouteRegistrar.php`

### 1.4 Metabox e Post Editor

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Creare/modificare post italiano
2. Verificare metabox "FP Multilanguage" presente nel post editor
3. Verificare contenuto metabox:
   - Link "View Translation" (se esiste)
   - Link "Edit Translation" (se esiste)
   - Status traduzione (pending/done/error)
   - Button "Translate Now"
4. Cliccare "Translate Now" e verificare:
   - Job creato in queue
   - Status cambia a "processing"
   - Dopo completamento status "done"
5. Verificare notifiche dopo salvataggio post (se auto-translate attivo)
6. Andare a Posts list e verificare colonna "Translation Status"

**File da Verificare**: `src/Admin/TranslationMetabox.php`

### 1.5 Bulk Translator

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Accedere a pagina Bulk Translate (se separata) o da Translations page
2. Selezionare multiple post/page da tradurre
3. Cliccare "Bulk Translate"
4. Verificare progress bar/tracking durante processing
5. Verificare completion message
6. Testare scenario errori:
   - Alcuni post falliscono traduzione
   - Verificare error handling e reporting

**File da Verificare**: `src/Admin/BulkTranslator.php`

---

## 2. Test Frontend - Routing e Display

### 2.1 URL Routing

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. **Verifica Rewrite Rules**
   - Andare a `WP Admin → Settings → Permalinks`
   - Cliccare "Save Changes" per flush rewrite rules
   - Verificare in database che rules siano registrate:
     ```sql
     SELECT * FROM wp_options WHERE option_name = 'rewrite_rules';
     ```

2. **Routing Segment Mode (`/en/` prefix)**
   - Creare post IT con slug `test-post`
   - Tradurre in EN
   - Verificare:
     - URL IT: `https://site.com/test-post` → mostra post IT
     - URL EN: `https://site.com/en/test-post` → mostra post EN
     - URL EN inesistente: `https://site.com/en/non-existent` → 404
     - Redirect loop prevention: `/en/en/` → redirect corretto

3. **Query Filter e Post Resolution**
   - Visitare `/en/test-post` e verificare:
     - Query WordPress risolve post EN corretto
     - Language detection da URL funziona
     - Canonical URL corretto (dovrebbe essere URL EN)
     - Adjacent posts (prev/next) rimangono in lingua corrente

**File da Verificare**: `src/Frontend/Routing/Rewrites.php`

### 2.2 Language Switching

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. **Admin Bar Switcher**
   - Visitare qualsiasi pagina frontend
   - Verificare presenza language switcher in admin bar
   - Cliccare "English" → verificare redirect a `/en/current-page`
   - Cliccare "Italiano" → verificare redirect a `/current-page` (senza /en/)
   - Verificare mantenimento query string: `/page?param=value` → `/en/page?param=value`

2. **Widget Language Switcher** (se presente)
   - Verificare widget disponibile in Appearance → Widgets
   - Aggiungere widget a sidebar
   - Visitare frontend e verificare widget funzionante
   - Testare switch language da widget

**File da Verificare**: `src/Admin/AdminBarSwitcher.php`, `src/Frontend/Widgets/`

### 2.3 Content Display

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Creare post IT con:
   - Titolo: "Post di Test"
   - Contenuto: "Questo è un post di test con <strong>formattazione</strong>"
   - Excerpt: "Riassunto del post"
   - Featured image

2. Tradurre post in EN

3. Verificare post IT su URL base:
   - Titolo IT corretto
   - Contenuto IT corretto
   - Formattazione preservata
   - Featured image presente

4. Verificare post EN su `/en/` URL:
   - Titolo EN tradotto correttamente
   - Contenuto EN tradotto correttamente
   - Formattazione HTML preservata
   - Featured image presente (stessa immagine)

5. Verificare meta fields tradotti (se presenti):
   - Custom fields sincronizzati
   - Meta description tradotta

6. Testare commenti:
   - Aggiungere commento thread a post IT
   - Verificare commento appare anche su post EN
   - Verificare parent/child relationships mantenute

**File da Verificare**: `src/Content/TranslationManager.php`

### 2.4 Menu Navigation

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Creare menu IT con:
   - Home (link a homepage)
   - About (link a pagina About IT)
   - Contact (link a pagina Contact IT)
   - Nested item (sub-menu)

2. Verificare menu EN creato automaticamente:
   - Andare a `WP Admin → Appearance → Menus`
   - Verificare menu EN con stessi items
   - Verificare links puntano a pagine EN (`/en/about`, etc.)

3. Visitare frontend:
   - Pagina IT → menu IT visualizzato
   - Pagina `/en/about` → menu EN visualizzato

4. Modificare item menu IT:
   - Cambiare label o link
   - Verificare menu EN aggiornato automaticamente

5. Testare custom fields Salient (se tema Salient attivo):
   - Aggiungere icona a menu item IT
   - Verificare icona sincronizzata a menu EN

**File da Verificare**: `src/Menu/MenuSync.php`, `src/Menu/MenuSynchronizer.php`

### 2.5 SEO Tags

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Visitare post IT e verificare HTML source:
   ```html
   <link rel="alternate" hreflang="it" href="https://site.com/test-post" />
   <link rel="alternate" hreflang="en" href="https://site.com/en/test-post" />
   <link rel="canonical" href="https://site.com/test-post" />
   ```

2. Visitare post EN (`/en/test-post`) e verificare:
   ```html
   <link rel="alternate" hreflang="it" href="https://site.com/test-post" />
   <link rel="alternate" hreflang="en" href="https://site.com/en/test-post" />
   <link rel="canonical" href="https://site.com/en/test-post" />
   ```

3. Verificare meta description:
   - Post IT: meta description IT
   - Post EN: meta description EN

4. Verificare Open Graph tags:
   - og:locale corretto (it_IT / en_US)
   - og:title tradotto
   - og:description tradotto

**File da Verificare**: `src/SEO/` (se presente)

---

## 3. Test Translation Workflow

### 3.1 Traduzione Singolo Post

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. **Creare nuovo post italiano**
   - Titolo: "Nuovo Post Test"
   - Contenuto: "Questo è un nuovo post"
   - Pubblicare

2. **Verificare job accodato**
   - Se auto-translate attivo: job creato automaticamente
   - Verificare in queue: `wp fpml queue status`
   - O verificare in `WP Admin → FP Multilanguage → Diagnostics → Queue Status`

3. **Eseguire queue processing**
   - `wp fpml queue run`
   - O attendere cron job
   - O eseguire via REST API: `POST /fpml/v1/queue/run`

4. **Verificare post EN creato**
   - Andare a Posts list
   - Verificare post EN con titolo tradotto
   - Verificare link tra post IT e EN (metabox o meta field `_fpml_pair_id`)

5. **Modificare post IT**
   - Modificare contenuto
   - Salvare
   - Verificare job nuovo creato

6. **Verificare post EN aggiornato**
   - Eseguire queue
   - Verificare post EN aggiornato con nuova traduzione

**File da Verificare**: `src/Content/TranslationManager.php`, `src/Translation/JobEnqueuer.php`

### 3.2 Queue Management

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. **Job Enqueuing**
   - Creare 5 post IT
   - Verificare 5 job creati in queue
   - Verificare status "pending"

2. **Queue Processing**
   - Eseguire `wp fpml queue run`
   - Verificare jobs processati
   - Verificare status cambia a "done"

3. **Job Status Tracking**
   - Verificare status: pending → processing → done
   - Creare job con error (es. API key errata)
   - Verificare status "failed"

4. **Retry su Errori**
   - Job fallito
   - Eseguire retry (se funzionalità presente)
   - Verificare retry funziona

5. **Cleanup Jobs Vecchi**
   - Creare job vecchio (>30 giorni)
   - Eseguire cleanup: `wp fpml queue cleanup --days=7`
   - Verificare job rimosso

6. **Prioritizzazione Job**
   - Creare job con priorità diverse (se supportato)
   - Verificare processing ordine corretto

**File da Verificare**: `src/Queue/Queue.php`

### 3.3 Bulk Translation

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Creare 10 post IT
2. Andare a `WP Admin → FP Multilanguage → Translations` (o Bulk Translate)
3. Selezionare tutti i 10 post
4. Cliccare "Bulk Translate"
5. Verificare:
   - 10 job creati in queue
   - Progress tracking visualizzato
   - Completion message dopo finire

6. Testare error scenario:
   - Disabilitare API key temporaneamente
   - Eseguire bulk translate
   - Verificare alcuni falliscono
   - Verificare error reporting

**File da Verificare**: `src/Admin/BulkTranslator.php`

### 3.4 Translation Quality

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. **Formattazione Preservata**
   - Creare post con HTML: `<p>Paragrafo</p><strong>Bold</strong><em>Italic</em>`
   - Tradurre
   - Verificare HTML preservato in traduzione

2. **Shortcodes Preservati**
   - Creare post con shortcode: `[gallery ids="1,2,3"]`
   - Tradurre
   - Verificare shortcode preservato

3. **Caratteri Speciali**
   - Creare post con: `Café, naïve, résumé, Müll`
   - Tradurre
   - Verificare encoding corretto

4. **Glossary Applicato**
   - Aggiungere regola glossary: "sito web" → "website"
   - Creare post contenente "sito web"
   - Tradurre
   - Verificare "website" usato invece di traduzione automatica

5. **Context Preservation**
   - Creare post con contesto specifico (es. terminologia tecnica)
   - Tradurre
   - Verificare traduzione contestualmente appropriata

**File da Verificare**: `src/Providers/`, `src/Glossary.php`

---

## 4. Test Integrazioni

### 4.1 WooCommerce

**Status**: ⏳ Da Eseguire (Richiede WooCommerce attivo)

**Test da Eseguire**:

1. **Prodotto Semplice**
   - Creare prodotto IT: "Prodotto Test"
   - Descrizione, prezzo, immagine
   - Tradurre
   - Verificare prodotto EN creato con traduzione corretta

2. **Prodotto Variabile**
   - Creare prodotto variabile IT con attributi (Colore: Rosso, Blu)
   - Creare varianti per ogni attributo
   - Tradurre
   - Verificare varianti EN create
   - Verificare attributi tradotti (se configurabile)

3. **Gallery Immagini**
   - Aggiungere gallery a prodotto IT
   - Tradurre
   - Verificare gallery sincronizzata a prodotto EN
   - Verificare ALT text immagini tradotto

4. **Upsell/Cross-sell**
   - Creare prodotto A e B IT
   - Aggiungere B come upsell di A
   - Tradurre entrambi
   - Verificare relazione upsell mappata correttamente (A EN → B EN)

5. **Downloadable Files**
   - Creare prodotto downloadable IT con file
   - Tradurre
   - Verificare file sincronizzato a prodotto EN

6. **Custom Tabs**
   - Aggiungere custom tab a prodotto IT
   - Tradurre
   - Verificare tab tradotto in prodotto EN

7. **Tassonomie Prodotto**
   - Creare categoria prodotto IT
   - Assegnare prodotto a categoria
   - Tradurre prodotto
   - Verificare categoria tradotta (se supportato)

**File da Verificare**: `src/Integrations/WooCommerceSupport.php`

### 4.2 Salient Theme

**Status**: ⏳ Da Eseguire (Richiede Salient Theme attivo)

**Test da Eseguire**:

1. Creare pagina con Salient page builder
2. Configurare page headers (titolo, sottotitolo, background, etc.)
3. Configurare portfolio settings (se post type portfolio)
4. Tradurre pagina
5. Verificare tutti i meta fields Salient sincronizzati:
   - Page headers (26 campi)
   - Portfolio (12 campi)
   - Post formats (15 campi)
   - Page builder (18 campi)
   - Navigation (8 campi)

**File da Verificare**: `src/Integrations/SalientThemeSupport.php`

### 4.3 FP-SEO-Manager

**Status**: ⏳ Da Eseguire (Richiede FP-SEO-Manager attivo)

**Test da Eseguire**:

1. Creare post IT
2. Configurare SEO meta con FP-SEO-Manager:
   - Meta title, description
   - AI features
   - GEO data
   - Social meta (OG, Twitter)
   - Schema.org
3. Tradurre post
4. Verificare tutti i meta fields SEO sincronizzati a post EN

**File da Verificare**: `src/Integrations/FpSeoSupport.php`

### 4.4 Menu Navigation Sync

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Creare menu IT con gerarchia complessa:
   - Home
   - About (con sub-items)
     - Team
     - History
   - Services (con sub-items)
     - Service 1
     - Service 2
2. Verificare menu EN creato automaticamente
3. Verificare gerarchia mantenuta
4. Modificare item menu IT (label, link)
5. Verificare menu EN aggiornato
6. Eliminare item menu IT
7. Verificare item rimosso da menu EN

**File da Verificare**: `src/Menu/MenuSynchronizer.php`

---

## 5. Test Sicurezza

### 5.1 Nonce Verification

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Aprire form admin (es. Settings)
2. Modificare nonce value in HTML (DevTools)
3. Inviare form
4. Verificare errore "Security check failed" o simile

5. Per AJAX:
   - Eseguire chiamata AJAX senza nonce
   - Verificare error response

**File da Verificare**: Tutti i form in `admin/views/`, `src/Admin/Ajax/AjaxHandlers.php`

### 5.2 Permission Checks

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Creare user con ruolo "Editor" (non admin)
2. Login come Editor
3. Tentare accesso `WP Admin → FP Multilanguage`
4. Verificare accesso negato o pagina vuota (dovrebbe richiedere `manage_options`)

5. Per REST API:
   - Eseguire chiamata senza autenticazione
   - Verificare 401/403 response

**File da Verificare**: `src/Admin/Admin.php`, `src/Rest/PermissionChecker.php`

### 5.3 Input Sanitization

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. **XSS Attempts**
   - Inserire in form: `<script>alert('XSS')</script>`
   - Salvare
   - Verificare script non eseguito (sanitized)

2. **SQL Injection** (se applicabile)
   - Inserire: `' OR '1'='1`
   - Verificare query sicura (prepared statements)

3. **Output Escaping**
   - Creare post con HTML: `<strong>Test</strong>`
   - Visualizzare in admin
   - Verificare HTML escaped correttamente (se non dovuto essere renderizzato)

**File da Verificare**: Tutti i form handlers

### 5.4 API Key Security

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. Inserire API key in settings
2. Verificare in database:
   ```sql
   SELECT option_value FROM wp_options WHERE option_name LIKE '%openai%';
   ```
   - Dovrebbe essere encrypted, non plain text

3. Verificare API key non appare in:
   - Error logs
   - Debug output
   - JavaScript console (se usata in frontend)

**File da Verificare**: `src/Core/SecureSettings.php`

---

## 6. Test CLI Commands

**Status**: ⏳ Da Eseguire (Richiede WP-CLI)

**Test da Eseguire**:

1. **wp fpml queue run**
   ```bash
   wp fpml queue run
   ```
   - Verificare jobs processati
   - Verificare output mostra progress

2. **wp fpml queue status**
   ```bash
   wp fpml queue status
   ```
   - Verificare status queue visualizzato
   - Verificare counts corretti

3. **wp fpml queue estimate-cost**
   ```bash
   wp fpml queue estimate-cost
   ```
   - Verificare stima costi visualizzata

4. **wp fpml queue cleanup**
   ```bash
   wp fpml queue cleanup --days=7
   ```
   - Verificare jobs vecchi rimossi

**File da Verificare**: `src/CLI/CLI.php`

---

## 7. Test Edge Cases e Error Handling

**Status**: ⏳ Da Eseguire

**Test da Eseguire**:

1. **Post senza traduzione disponibile**
   - Visitare `/en/post-senza-traduzione`
   - Verificare 404 o redirect corretto

2. **Provider API Errors**
   - Disabilitare API key o usare key invalida
   - Tentare traduzione
   - Verificare error handling e logging

3. **Queue Jobs Falliti**
   - Creare job che fallisce
   - Verificare status "failed"
   - Verificare retry mechanism (se presente)

4. **Interruzioni durante Bulk Operations**
   - Avviare bulk translate su 100 post
   - Interrompere durante processing
   - Verificare stato corretto (alcuni completati, altri pending)

5. **Memory Limits**
   - Creare post molto grande (>1MB)
   - Tentare traduzione
   - Verificare gestione memory o chunking

6. **Conflitti Plugin Multilingua**
   - Attivare WPML o Polylang
   - Verificare detection e "assisted mode"
   - Verificare plugin non interferisce

**File da Verificare**: Vari, error handling in `src/Queue/`, `src/Content/`

---

## Note Esecuzione

### Prerequisiti
- WordPress installato e funzionante
- Plugin FP-Multilanguage attivato
- Composer autoload installato (`composer install`)
- API key OpenAI configurata (per test traduzione)
- WooCommerce attivo (per test integrazione WooCommerce)
- Salient Theme attivo (per test integrazione Salient)
- FP-SEO-Manager attivo (per test integrazione SEO)

### Ambiente Test Consigliato
- Ambiente di sviluppo/staging (non produzione)
- Database backup prima dei test
- WordPress debug mode attivo (`WP_DEBUG = true`)

### Ordine Esecuzione Consigliato
1. Test struttura base (script automatico)
2. Test pagine admin (manuale)
3. Test REST API (tool/curl)
4. Test workflow traduzione base
5. Test frontend routing
6. Test integrazioni (se plugin attivi)
7. Test sicurezza
8. Test edge cases

---

## Risultati Test

(Sarà compilato durante esecuzione)

### Errori Rilevati
- Nessun errore rilevato finora

### Warning Rilevati
- Nessun warning rilevato finora

### Issue Notevoli
- Nessuna issue notevole finora

---

**Ultimo Aggiornamento**: 2025-01-23  
**Stato**: ⏳ In Attesa Esecuzione





