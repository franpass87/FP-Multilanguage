# QA Report – FP Multilanguage

## Ambiente di test

- PHP 8.1 + WordPress 6.5
- PHP 7.4 + WordPress 6.4
- PHP 8.2 + WordPress 6.6-beta
- Plugin caching testato con WP Super Cache (compatibilità confermata)

## Checklist manuale

- [x] Attivazione plugin senza errori fatali
- [x] Salvataggio impostazioni admin (tabs e REST sync)
- [x] Traduzione automatica post + fallback manuale
- [x] Widget/shortcode language switcher
- [x] Meta SEO per lingua, tag `hreflang`, `canonical`
- [x] Dynamic strings editor inline + AJAX salvataggio
- [x] CLI `wp fp-multilanguage translate` su contenuti differenti
- [x] Flush cache traduzioni tramite cambio impostazioni

## Test automatici

- `composer qa` (PHPCS + PHPStan + PHPUnit)
- `npm run build` (bundle JS)

## Note

Eventuali regressioni devono essere accompagnate da test unitari. Per nuove feature aggiornare `docs/ROADMAP.md` e `README.md`.
