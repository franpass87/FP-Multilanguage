# Phase 1 – Discovery Report

## Scope & Methodology
- Reviewed all PHP sources under `fp-multilanguage/`, including bootstrapping (`fp-multilanguage.php`), service container, admin UI, content/SEO managers, dynamic string filters, REST/AJAX handlers, CLI commands, installer and providers.
- Inspected JavaScript assets in `fp-multilanguage/assets/js/` for admin, frontend and block behavior.
- Surveyed build tooling (`composer.json`, `package.json`, `vite.config.js`), GitHub Actions workflow and existing documentation.
- Compiled a structural overview in `docs/code-map.md` to map hooks, services, options, REST endpoints, assets and data stores.

## Key Architecture Notes
- Plugin is service-oriented around `FPMultilanguage\Plugin` with a lightweight container that wires admin settings, translation managers, SEO helpers, dynamic string handlers, logger and providers.
- Translation data is persisted primarily in post/comment/term meta and serialized options. A custom table `{prefix}fp_multilanguage_strings` mirrors manual string metadata when available.
- Admin UI relies on vanilla JS (no build step) to hit REST routes for settings/provider testing and on AJAX for inline manual strings.
- Background processing is handled via a custom cron-like action `fp_multilanguage_process_translation` scheduled per post save.

## Initial Findings & Potential Risks

### Security & Hardening
- REST endpoints for manual strings and translation jobs (`DynamicStrings::register_rest_routes`, `PostTranslationManager::register_rest_routes`) only rely on capability checks. When triggered from the admin UI we should also validate nonces to tighten CSRF protection during the security phase.
- Direct database reads in `Settings\Repository::get_manual_string_metadata()` bypass `$wpdb->prepare()`. The current usage is limited to selecting static columns but still flags as a security linting concern to address.

### Performance & Scalability
- `DynamicStrings::enqueue_assets()` unconditionally enqueues both `dynamic-translations.js` and `frontend.js` on every admin and frontend page load, even for anonymous visitors. We should gate these enqueues to the pages that actually need manual string editing to avoid unnecessary requests.
- Manual string catalogs, logs and other large arrays are stored in autoloaded options (`fp_multilanguage_manual_strings`, `fp_multilanguage_logs`). This can bloat the `autoload` cache on large sites; we should evaluate migrating heavy datasets to non-autoloaded storage or the dedicated custom table.
- Translation jobs schedule a new single event five seconds after each save. There is clearing logic, but we should confirm there are no race conditions or duplicate cron entries under high frequency updates.

### Compatibility & Code Quality
- The codebase assumes PHP 7.4+ (typed properties). We need to document/verify minimum version requirements and test compatibility with current WordPress releases.
- Several modules (`DynamicStrings`, `PostTranslationManager`, `SEO`) hook into numerous filters; we will need targeted linting/static analysis to ensure no deprecated hooks/functions are used.

### Testing & Tooling Gaps
- No automated linting or testing workflows are configured beyond a packaging GitHub Action. We will introduce PHPCS/PHPStan and PHPUnit/CI in later phases.
- There is no runtime diagnostic logging surfaced for development environments; adding a temporary logger in Phase 3 will help capture notices/warnings.

## Next Steps
- Proceed to Phase 2 (Linters): set up PHPCS (WordPress standards) and PHPStan (>= level 5), fix auto-fixable issues and capture results in `docs/audit/linters.txt`.
- Continue to update `.codex-state.json` after each phase with status, commits and outstanding notes.
