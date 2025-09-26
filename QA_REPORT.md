# QA Report – FP Multilanguage (v1.2.0)

Maintainer: Francesco Passeri – [francescopasseri.com](https://francescopasseri.com) – [info@francescopasseri.com](mailto:info@francescopasseri.com)

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

## Cronologia release monitorate

- **1.2.0 (ottobre 2024)** – Audit finale con hardening sicurezza REST/AJAX, caching impostazioni/stringhe, bootstrap modulare, logger runtime e routine upgrade con flush cache.
- **1.1.0 (settembre 2024)** – Revisione documentazione, aggiornamento metadati autore e verifica regressioni generali (nessun nuovo bug rilevato).
- **1.0.0 (giugno 2024)** – Validazione iniziale delle funzionalità core (traduzioni automatiche/manuali, SEO, CLI, dynamic strings).
