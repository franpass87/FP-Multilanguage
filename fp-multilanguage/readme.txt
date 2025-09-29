=== FP Multilanguage ===
Contributors: francescopasseri
Tags: translation, multilanguage, openai, deepl, google translate, seo
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress per tradurre automaticamente contenuti e SEO dall'italiano all'inglese con provider reali (OpenAI, DeepL, Google, LibreTranslate), gestione coda e routing /en/.

== Descrizione ==
FP Multilanguage duplica contenuti inglesi sincronizzati con l'originale italiano, include routing dedicato /en/ o query string, sitemap EN, gestione slug, hreflang/canonical e supporto per Gutenberg, ACF e campi personalizzati.

== Installazione ==
1. Carica la cartella `fp-multilanguage` in `/wp-content/plugins/`.
2. Attiva il plugin tramite il menu "Plugin" in WordPress.
3. Configura i provider e le opzioni in **Impostazioni → FP Multilanguage**.

== Test rapidi ==
* Creare Pagina IT → verifica duplicato EN su /en/... con hreflang/canonical.
* Modificare titolo IT → EN marcata outdated → ritradotta solo parte modificata.
* Creare Categoria + assegnazione post → EN crea termine e associazione.
* Attivare provider con chiave → Test provider OK in Diagnostics.
* Verificare sitemap EN e meta OG/Twitter.
* Verificare redirect browser language e switcher.
* WP-CLI: `wp fpml queue run` processa N elementi (batch size).


== Cron & Diagnostics ==
La scheda *Diagnostics* mette in evidenza KPI della coda (job in pending, completati, errori), stima parole/costi basata sulle tariffe configurate e riporta gli ultimi log e gli errori recenti. Dai pulsanti puoi lanciare un batch immediato, avviare un reindex completo o testare il provider direttamente via REST protetto.

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

== Changelog ==
= 0.2.1 =
- Fix: preservazione strutture ACF/repeater con traduzione ricorsiva delle sole stringhe.
- Fix: rispetto effettivo di “Shortcode esclusi” con masking/restore sicuro in diff e processor.
- Docs: BUILD-STATE aggiornato alla Fase 14.

= 0.2.0 =
* Versione iniziale in sviluppo.
* Dashboard diagnostica con KPI, test provider e stima costi dalla scheda Diagnostics.
* Notice WP-Cron con esempi di crontab quando DISABLE_WP_CRON è attivo.
