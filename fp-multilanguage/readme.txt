=== FP Multilanguage ===
Contributors: francescopasseri
Tags: translation, multilanguage, openai, deepl, google translate, seo
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress per tradurre automaticamente contenuti e SEO dall'italiano all'inglese con provider reali (OpenAI, DeepL, Google, LibreTranslate), gestione coda e routing /en/.

== Descrizione ==
FP Multilanguage duplica contenuti inglesi sincronizzati con l'originale italiano, include routing dedicato /en/ o query string, sitemap EN, gestione slug, hreflang/canonical e supporto per Gutenberg, ACF e campi personalizzati. Traduce automaticamente tassonomie (categorie, tag, attributi WooCommerce), label menu, ALT/caption/title dei media e mantiene sincronizzati gli ID attachment nel frontend EN, forzando il locale `en_US` per caricare stringhe tema/plugin.

== Installazione ==
1. Carica la cartella `fp-multilanguage` in `/wp-content/plugins/`.
2. Attiva il plugin tramite il menu "Plugin" in WordPress.
3. Configura i provider e le opzioni in **Impostazioni → FP Multilanguage**.

== Test rapidi ==
* Creare Pagina IT → verifica duplicato EN su /en/... con hreflang/canonical.
* Modificare titolo IT → EN marcata outdated → ritradotta solo parte modificata.
* Creare Categoria + assegnazione post → EN crea termine e associazione.
* Aggiungere attributo WooCommerce (globale o personalizzato) → traduzione EN aggiornata su prodotto/vetrina.
* Inserire immagine con ALT + pagina WPBakery → in EN sono sostituiti ID attachment e testi mantenendo il layout.
* Attivare provider con chiave → Test provider OK in Diagnostics.
* Verificare sitemap EN e meta OG/Twitter.
* Verificare redirect browser language e switcher.
* WP-CLI: `wp fpml queue run` processa N elementi (batch size).
* WP-CLI: `wp fpml queue cleanup --dry-run` mostra i job che verrebbero rimossi prima di confermare.
* Configurare la retention e verificare `wp fpml queue cleanup --days=7` per rimuovere job datati.
* Diagnostics: usa il pulsante "Pulisci coda" per eseguire la retention manuale via REST.


== Cron & Diagnostics ==
La scheda *Diagnostics* mette in evidenza KPI della coda (job in pending, completati, errori), stima parole/costi basata sulle tariffe configurate e riporta gli ultimi log e gli errori recenti. Dai pulsanti puoi lanciare un batch immediato, avviare un reindex completo, testare il provider oppure avviare la pulizia della coda rispettando la retention configurata, tutto via REST protetto.

Quando la retention è attiva, oltre alla pulizia post-batch il plugin programma l'evento giornaliero `fpml_cleanup_queue` per mantenere il database allineato anche in assenza di attività.

Se `DISABLE_WP_CRON` è impostato a `true`, configura un cron di sistema per mantenere attiva la pipeline. Esempio ogni 5 minuti con WP-CLI (sostituisci il percorso alla root WordPress):

```
*/5 * * * * cd /percorso/sito && wp cron event run --due-now >/dev/null 2>&1
```

In alternativa, puoi eseguire direttamente `wp-cron.php`:

```
*/5 * * * * php /percorso/sito/wp-cron.php >/dev/null 2>&1
```

== Compatibilità ==
* Se WPML o Polylang sono attivi, FP Multilanguage entra in modalità *assistita*: lascia disponibili provider, glossario, override stringhe ed export/import ma disattiva duplicazione automatica, routing /en/, sitemap EN e coda interna. La UI mostra un avviso e blocca comandi REST/WP-CLI sulla coda per evitare conflitti.
* WooCommerce: traduce categorie, tag prodotto, attributi globali `pa_*` e attributi personalizzati, sincronizza ALT/caption/title media collegati e mantiene il locale EN lato frontend.
* Page builder: sostituzione sicura di shortcode WPBakery (`vc_row`, `vc_column`, `vc_section`, `vc_tabs`, ecc.) e supporto per `[vc_single_image]` con ID inglesi.

== Changelog ==
= 0.3.1 =
- New: Impostazione "Pulizia automatica coda" con retention configurabile e sanificazione del cookie di consenso per il redirect.
- New: Pulizia automatica dei job completati dopo ogni batch con log dedicato e filtro `fpml_queue_cleanup_states`.
- New: Comando `wp fpml queue cleanup` per rimuovere manualmente i job oltre soglia, con opzioni `--days`, `--states` e `--dry-run` oltre a messaggi WP-CLI localizzati.
- New: Migliorie WP-CLI `status` con provider configurato, retention e anzianità dei job.
- New: `wp fpml queue estimate-cost` ora accetta `--states` e `--max-jobs` per analisi mirate.
- New: Snapshot diagnostico e interfaccia admin mostrano l'età dei job pending/completati, la retention attiva e includono il pulsante "Pulisci coda" via REST.
- Dev: Metodi helper (`FPML_Plugin::get_queue_age_summary`, `FPML_Queue::cleanup_old_jobs`) e uso di `current_time()` per i cutoff.
- Dev: Pulizia coda chunked con indice composito `(state, updated_at)`, hook `fpml_queue_after_cleanup` e filtro `fpml_queue_cleanup_batch_size` per personalizzare i batch.
- Dev: Sanitizzazione avanzata del cookie consenso per i redirect EN.

= 0.3.0 =
- New: Traduzione automatica per tassonomie, attributi WooCommerce globali/personalizzati e label menu sincronizzate.
- New: Media EN con ALT/caption/title tradotti e sostituzione ID attachment nel frontend (incluso shortcode gallery / WPBakery).
- New: Locale frontend forzato a `en_US` per caricare stringhe tema/plugin e colonna/filtro lingua nell'admin con badge/notice configurabili.
- New: Limite `max_chars_per_batch` per controllare il carico sui provider e KPI diagnostici dedicati (termini/menu tradotti).
- Tweak: Shortcode WPBakery esclusi precompilati e parsing `[vc_single_image]` per mantenere layout identici in EN.

= 0.2.1 =
- Fix: preservazione strutture ACF/repeater con traduzione ricorsiva delle sole stringhe.
- Fix: rispetto effettivo di “Shortcode esclusi” con masking/restore sicuro in diff e processor.
- Docs: BUILD-STATE aggiornato alla Fase 14.

= 0.2.0 =
* Versione iniziale in sviluppo.
* Dashboard diagnostica con KPI, test provider e stima costi dalla scheda Diagnostics.
* Notice WP-Cron con esempi di crontab quando DISABLE_WP_CRON è attivo.
