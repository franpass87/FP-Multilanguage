# Documentazione sviluppatore

Questa cartella raccoglie risorse dedicate a sviluppatori e integratori che desiderano estendere FP Multilanguage.

## Architettura

- **Container DI** (`FPMultilanguage\Support\Container`) registra i servizi core.
- **Hook principali**: `plugins_loaded` per bootstrap, `init` per textdomain, `widgets_init` per widget, REST e CLI registrati dinamicamente.
- **Service Locator**: `fp_multilanguage()->get_container()` permette di recuperare i servizi registrati (es. `translation_service`).

## Estensioni comuni

- **Provider di traduzione custom**: utilizzare il filtro `fp_multilanguage_provider_sequence` per aggiungere una chiave provider e agganciare `fp_multilanguage_translate_with_{provider}` per eseguire la richiesta.
- **Override lingua corrente**: `add_filter( 'fp_multilanguage_current_language', function( $lang ){ return 'de'; } );`
- **Quote personalizzate**: `add_filter( 'fp_multilanguage_quota_limits', fn( $limits, $provider ) => [ 'requests' => 2000, 'characters' => 1_000_000 ] );`
- **Custom SEO meta**: `add_filter( 'fp_multilanguage_seo_meta', 'my_callback', 10, 3 );`

## Strumenti CLI

```bash
wp fp-multilanguage translate <post_id> --language=fr
```

L’handler richiama `PostTranslationManager::translate_post()` e logga l’operazione.

## REST API

- `GET/POST wp-json/fp-multilanguage/v1/settings`
- `POST wp-json/fp-multilanguage/v1/posts/<id>/translate`
- `GET/POST wp-json/fp-multilanguage/v1/strings`

Tutte le route richiedono utenti con `manage_options` (o `edit_posts` per il translate endpoint).

## Testing

- PHPUnit: testare `TranslationService`, `PostTranslationManager`, `DynamicStrings`, `SEO`.
- Brain Monkey e gli stub WP inclusi in `tests/stubs/wordpress.php` simulano l’ambiente minimale.

## Best practice

- Avvolgere le stringhe con funzioni i18n (`__`, `_x`, `esc_html__`, ecc.).
- Seguire gli standard WordPress-Core (PHPCS già configurato via composer).
- Le chiamate HTTP dei provider utilizzano gli helper WP (`wp_remote_post`).

Per ulteriori dettagli vedere gli altri documenti in `docs/`.
