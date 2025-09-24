=== FP Multilanguage ===
Contributors: fp-team
Tags: multilingual, translation, seo, deepl, google
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestione multilingua avanzata per contenuti, stringhe dinamiche e SEO con provider Google e DeepL.

== Description ==

FP Multilanguage offre:

* pagina impostazioni con sezione provider, SEO, quote e sincronizzazione REST.
* traduzione automatica dei post/pagine con meta dedicati e filtri front-end.
* storage delle stringhe dinamiche con editor inline e API REST.
* meta SEO multilingua (hreflang, canonical, og:locale) e sitemap alternate.
* widget e shortcode per il language switcher.
* CLI `wp fp-multilanguage translate`.

== Installation ==

1. Carica la cartella `fp-multilanguage` in `wp-content/plugins/`.
2. Attiva il plugin dalla schermata "Plugin" in WordPress.
3. Configura le lingue e le chiavi API in Impostazioni → FP Multilanguage.
4. Inserisci i codici lingua nel formato desiderato (ad es. `pt-BR`, `zh-Hant`): il plugin li normalizza automaticamente.

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

= 1.0.0 =
* Prima release pubblica con orchestratore, providers Google/DeepL, dynamic strings, SEO e CLI.

== Upgrade Notice ==

= 1.0.0 =
Release iniziale.
