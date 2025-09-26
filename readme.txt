=== FP Multilanguage ===
Contributors: francescopasseri
Tags: multilingual, translation, seo, deepl, google
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestione multilingua avanzata per contenuti, stringhe dinamiche e SEO con provider Google e DeepL.

== Author ==

Francesco Passeri – [francescopasseri.com](https://francescopasseri.com) – info@francescopasseri.com

== Description ==

FP Multilanguage offre:

* pagina impostazioni con sezione provider, SEO, quote e sincronizzazione REST.
* traduzione automatica di post/pagine, tassonomie e commenti con meta dedicati e filtri front-end.
* storage delle stringhe dinamiche con editor inline, pannello dedicato nel back-office e API REST.
* configurazione guidata in tre step con verifica credenziali dei provider prima del salvataggio.
* meta SEO multilingua (hreflang, canonical, og:locale) e sitemap alternate.
* widget, shortcode e blocco Gutenberg per il language switcher.
* CLI `wp fp-multilanguage translate`.

== Installation ==

1. Carica la cartella `fp-multilanguage` in `wp-content/plugins/`.
2. Attiva il plugin dalla schermata "Plugin" in WordPress.
3. Avvia la procedura in Impostazioni → FP Multilanguage → Configurazione guidata per completare i passaggi essenziali.
4. Configura le lingue, le tassonomie/post type da tradurre e le chiavi API in Impostazioni → FP Multilanguage.
5. Inserisci i codici lingua nel formato desiderato (ad es. `pt-BR`, `zh-Hant`): il plugin li normalizza automaticamente.

== Frequently Asked Questions ==

= È necessario possedere una chiave API? =
Sì, per utilizzare Google o DeepL è richiesta una chiave valida. In assenza di provider abilitati vengono utilizzate le traduzioni manuali o il fallback.

= Posso aggiungere provider personalizzati? =
Sì, tramite il filtro `fp_multilanguage_provider_sequence` e l’hook `fp_multilanguage_translate_with_{provider}`.

== Screenshots ==

1. Pagina impostazioni con tab Provider/SEO/Quote
2. Meta box SEO per lingua
3. Editor inline per stringhe dinamiche

== Changelog ==

= 1.2.0 =
* Hardening sicurezza REST/AJAX con convalida nonce centralizzata, sanitizzazione input e verifica capability.
* Migliorie performance: cache impostazioni/stringhe, hook di invalidazione e flush automatico dopo gli upgrade.
* Compatibilità WordPress 6.5: blocco language switcher basato su metadata e supporto `WP_Block` in render callback.
* Logger runtime per notice/exception e refactoring bootstrap che separa admin/public con container registrato.

= 1.1.0 =
* Allineamento della documentazione (README, docs/ e QA report) con panoramica storica delle versioni.
* Aggiornamento dei metadati del plugin e dei riferimenti all'autore ufficiale (Francesco Passeri).
* Bump versione asset JS e file principale per coerenza con la release documentale.

= 1.0.0 =
* Prima release pubblica con orchestratore, providers Google/DeepL, dynamic strings, SEO e CLI.

== Upgrade Notice ==

= 1.2.0 =
Aggiornamento consigliato: include hardening sicurezza REST/AJAX, cache per impostazioni/stringhe e routine di upgrade con flush automatico.

= 1.1.0 =
Aggiornamento consigliato per ottenere la nuova documentazione unificata e i riferimenti ufficiali all'autore.

= 1.0.0 =
Release iniziale.
