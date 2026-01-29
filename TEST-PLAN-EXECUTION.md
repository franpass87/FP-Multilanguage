# Piano Esecuzione Test FP-Multilanguage
**Data**: 2025-01-23  
**Versione Plugin**: 0.9.1

## Stato Esecuzione

### 1. Test Backend - Admin Interface

#### 1.1 Pagine Admin e Navigation
**Status**: ⏳ In Esecuzione

##### Dashboard (`settings-dashboard.php`)
- [ ] Caricamento pagina senza errori
- [ ] Statistiche visualizzate (post tradotti, coda, costi)
- [ ] Chart attività settimanale funzionante
- [ ] Quick actions funzionanti
- [ ] Alerts per API key mancante/errata
- [ ] System info display corretto

##### Settings General (`settings-general.php`)
- [ ] Caricamento pagina
- [ ] Salvataggio impostazioni funziona
- [ ] Routing mode (segment/subdomain/domain) configurabile
- [ ] Language settings (default, source, target) funzionano
- [ ] Auto-translate toggle funziona
- [ ] Validazione campi attiva

##### Settings Content (`settings-content.php`)
- [ ] Post types configurabili
- [ ] Tassonomie configurabili
- [ ] Meta fields whitelist funzionante
- [ ] Esclusione contenuti funziona

##### Settings Provider
- [ ] Configurazione OpenAI API key
- [ ] Test provider connection funziona
- [ ] Billing check funziona
- [ ] Preview translation funziona
- [ ] API key encrypted in database

##### Settings SEO (`settings-seo.php`)
- [ ] Opzioni SEO (hreflang, canonical) configurabili
- [ ] Meta tags configuration funziona

##### Settings Translations (`settings-translations.php`)
- [ ] Lista traduzioni visualizzata
- [ ] Filtri (post type, status) funzionano
- [ ] Azioni bulk funzionano
- [ ] Regenerate translation funziona
- [ ] Versioning/rollback funziona

##### Settings Site Parts (`settings-site-parts.php`)
- [ ] Menu sync status visualizzato
- [ ] Traduzione stringhe sito funziona
- [ ] Widget settings configurabili

##### Settings Glossary (`settings-glossary.php`)
- [ ] Aggiunta/rimozione regole funziona
- [ ] Import/export CSV funziona
- [ ] Applicazione glossary in traduzioni verifica

##### Settings Diagnostics (`settings-diagnostics.php`)
- [ ] System check visualizzato
- [ ] Queue status visualizzato
- [ ] Logs display funziona
- [ ] Export logs funziona

##### Settings Export (`settings-export.php`)
- [ ] Export state funziona
- [ ] Import state funziona
- [ ] Sandbox management funziona

#### 1.2 AJAX Handlers
**Status**: ⏳ Pending

- [ ] `fpml_refresh_nonce` - Nonce verification attiva
- [ ] `fpml_reindex_batch_ajax` - Permission check e validation
- [ ] `fpml_cleanup_orphaned_pairs` - Error handling
- [ ] `fpml_trigger_detection` - Sanitization input/output
- [ ] `fpml_bulk_translate` - Permission check e validation
- [ ] `fpml_bulk_regenerate` - Error handling
- [ ] `fpml_bulk_sync` - Permission check
- [ ] `fpml_translate_single` - Validation e error handling
- [ ] `fpml_translate_site_part` - Permission check

#### 1.3 REST API Endpoints
**Status**: ⏳ Pending

##### Queue Routes
- [ ] `POST /fpml/v1/queue/run` - Permission, validation, success response
- [ ] `POST /fpml/v1/queue/cleanup` - Permission, validation, success response

##### Provider Routes
- [ ] `POST /fpml/v1/test-provider` - Permission, validation, success response
- [ ] `POST /fpml/v1/preview-translation` - Permission, validation, sanitization
- [ ] `POST /fpml/v1/check-billing` - Permission, validation, success response
- [ ] `GET /fpml/v1/refresh-nonce` - Permission, success response

##### Reindex Routes
- [ ] `POST /fpml/v1/reindex` - Permission, validation, success response
- [ ] `POST /fpml/v1/reindex-batch` - Permission, validation, success response

##### System Routes
- [ ] `GET /fpml/v1/health` - Permission, success response
- [ ] `GET /fpml/v1/stats` - Permission, success response
- [ ] `GET /fpml/v1/logs` - Permission, success response

##### Translation Routes
- [ ] `GET /fpml/v1/translations` - Permission, success response
- [ ] `POST /fpml/v1/translations/bulk` - Permission, validation, success response
- [ ] `POST /fpml/v1/translations/{id}/regenerate` - Permission, validation, success response
- [ ] `GET /fpml/v1/translations/{id}/versions` - Permission, success response
- [ ] `POST /fpml/v1/translations/{id}/rollback` - Permission, validation, success response

#### 1.4 Metabox e Post Editor
**Status**: ⏳ Pending

- [ ] Metabox visualizzato nel post editor
- [ ] Link traduzione (view/edit) funziona
- [ ] Status traduzione (pending/done/error) visualizzato correttamente
- [ ] "Translate Now" button funziona
- [ ] Notifiche dopo salvataggio funzionano
- [ ] Post list column (translation status) visualizzata

#### 1.5 Bulk Translator
**Status**: ⏳ Pending

- [ ] Pagina bulk translate caricabile
- [ ] Selezione multipla post funziona
- [ ] Progress tracking funziona
- [ ] Error handling durante bulk operations funziona

### 2. Test Frontend - Routing e Display

#### 2.1 URL Routing
**Status**: ⏳ Pending

##### Routing Segment Mode (`/en/` prefix)
- [ ] Rewrite rules registrate correttamente
- [ ] Accesso URL `/en/post-slug` restituisce post EN
- [ ] URL `/post-slug` (IT default) funziona
- [ ] 404 handling per traduzioni inesistenti funziona
- [ ] Redirect loop prevention attiva

##### Query Filter e Post Resolution
- [ ] Query modificata correttamente per `/en/`
- [ ] Language detection da URL funziona
- [ ] Canonical URLs corretti
- [ ] Adjacent posts (prev/next) con language context funzionano

#### 2.2 Language Switching
**Status**: ⏳ Pending

- [ ] Admin bar switcher visibile e funzionante
- [ ] Switch IT → EN funziona
- [ ] Switch EN → IT funziona
- [ ] URL generation corretta
- [ ] Widget language switcher (se presente) funziona
- [ ] Mantenimento query string durante switch funziona

#### 2.3 Content Display
**Status**: ⏳ Pending

- [ ] Post IT visualizzato correttamente su URL base
- [ ] Post EN visualizzato correttamente su `/en/` URL
- [ ] Traduzione contenuto (titolo, contenuto, excerpt) corretta
- [ ] Meta fields tradotti (se applicabile) corretti
- [ ] Featured images sync funziona
- [ ] Commenti (threaded) con mapping corretto

#### 2.4 Menu Navigation
**Status**: ⏳ Pending

- [ ] Menu IT visibile su pagine IT
- [ ] Menu EN visibile su pagine `/en/`
- [ ] Sync automatico menu items funziona
- [ ] Gerarchie parent/child mantenute
- [ ] Custom fields Salient (icone, mega menu) sync funzionano

#### 2.5 SEO Tags
**Status**: ⏳ Pending

- [ ] Hreflang tags presenti e corretti
- [ ] Canonical URLs per entrambe le lingue corretti
- [ ] Meta description per lingua corretta
- [ ] Open Graph tags localizzati corretti

### 3. Test Translation Workflow

#### 3.1 Traduzione Singolo Post
**Status**: ⏳ Pending

- [ ] Creare nuovo post italiano
- [ ] Salvare post (trigger auto-translate se attivo)
- [ ] Job accodato nella queue
- [ ] Eseguire queue processing
- [ ] Post EN creato correttamente
- [ ] Link tra post IT e EN corretto
- [ ] Modificare post IT
- [ ] Post EN aggiornato dopo nuova traduzione

#### 3.2 Queue Management
**Status**: ⏳ Pending

- [ ] Job enqueuing funziona
- [ ] Queue processing funziona
- [ ] Job status tracking (pending/processing/done/error) funziona
- [ ] Retry su errori funziona
- [ ] Cleanup jobs vecchi funziona
- [ ] Prioritizzazione job funziona

#### 3.3 Bulk Translation
**Status**: ⏳ Pending

- [ ] Selezione multiple post/page funziona
- [ ] Bulk translation avvia correttamente
- [ ] Progress tracking funziona
- [ ] Error handling (alcuni falliscono) gestito correttamente
- [ ] Tutti i job completati verificati

#### 3.4 Translation Quality
**Status**: ⏳ Pending

- [ ] Formattazione preservata (HTML, shortcodes)
- [ ] Caratteri speciali e encoding corretti
- [ ] Glossary applicato correttamente
- [ ] Context preservation funziona
- [ ] Placeholder handling corretto

### 4. Test Integrazioni

#### 4.1 WooCommerce
**Status**: ⏳ Pending

- [ ] Creare prodotto semplice IT → traduzione EN verificata
- [ ] Prodotto variabile con attributi tradotto
- [ ] Varianti (attributi, prezzi, stock) tradotte
- [ ] Gallery immagini con ALT text tradotta
- [ ] Upsell/cross-sell mapping corretto
- [ ] Downloadable files sync funziona
- [ ] Custom tabs traduzione funziona
- [ ] Tassonomie prodotto (categorie, tag, attributi) tradotte

#### 4.2 Salient Theme
**Status**: ⏳ Pending

- [ ] Page headers meta (26 campi) sincronizzati
- [ ] Portfolio meta (12 campi) sincronizzati
- [ ] Post formats (15 campi) sincronizzati
- [ ] Page builder meta (18 campi) sincronizzati
- [ ] Navigation settings (8 campi) sincronizzati
- [ ] WPBakery shortcodes integration funziona

#### 4.3 FP-SEO-Manager
**Status**: ⏳ Pending

- [ ] Core SEO meta (5 campi) sincronizzati
- [ ] AI features meta (5 campi) sincronizzati
- [ ] GEO & Freshness (4 campi) sincronizzati
- [ ] Social meta (OG, Twitter - 6 campi) sincronizzati
- [ ] Schema.org (4 campi) sincronizzati

#### 4.4 Menu Navigation Sync
**Status**: ⏳ Pending

- [ ] Creare menu IT → menu EN creato
- [ ] Aggiungere item menu IT → sync EN verificata
- [ ] Modificare item menu IT → update EN verificato
- [ ] Eliminare item menu IT → rimozione EN verificata
- [ ] Gerarchie complesse (nested items) mantenute

### 5. Test Sicurezza

#### 5.1 Nonce Verification
**Status**: ⏳ Pending

- [ ] Tutti i form admin con nonce valido/invalido testati
- [ ] AJAX endpoints con nonce check verificati
- [ ] Expired nonce handling funziona

#### 5.2 Permission Checks
**Status**: ⏳ Pending

- [ ] Capability requirements per tutte le funzionalità admin verificate
- [ ] Accesso non autorizzato (user senza permessi) bloccato
- [ ] REST API permission callbacks verificati

#### 5.3 Input Sanitization
**Status**: ⏳ Pending

- [ ] Tutti i form con input maliziosi (XSS attempts) testati
- [ ] Sanitization output verificata
- [ ] SQL injection attempts (se applicabile) bloccati

#### 5.4 API Key Security
**Status**: ⏳ Pending

- [ ] Encryption API key in database verificata
- [ ] Secure settings storage verificato
- [ ] Non display API key in logs/errors verificato

### 6. Test CLI Commands
**Status**: ⏳ Pending

- [ ] `wp fpml queue run` - Eseguire queue processing
- [ ] `wp fpml queue status` - Verificare status queue
- [ ] `wp fpml queue estimate-cost` - Verificare stima costi
- [ ] `wp fpml queue cleanup` - Testare cleanup

### 7. Test Edge Cases e Error Handling
**Status**: ⏳ Pending

- [ ] Post senza traduzione disponibile gestito correttamente
- [ ] Provider API errors durante traduzione gestiti
- [ ] Queue jobs falliti e retry funzionano
- [ ] Interruzioni durante bulk operations gestite
- [ ] Memory limits durante processing pesante gestiti
- [ ] Conflitti con altri plugin multilingua (WPML/Polylang detection) gestiti

---

## Note Esecuzione

### Errori Rilevati
(Nessun errore rilevato finora)

### Issue Notevoli
(Nessuna issue notevole finora)

### Raccomandazioni
(Nessuna raccomandazione finora)





