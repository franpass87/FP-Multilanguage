# FP Multilanguage

> Automates Italian-to-English copies of content, taxonomies, menus, media, and SEO data with queue-based routing and trusted translation providers.

| Field | Value |
| --- | --- |
| **Plugin Name** | FP Multilanguage |
| **Version** | 0.4.1 |
| **Author** | [Francesco Passeri](https://francescopasseri.com) |
| **Author Email** | [info@francescopasseri.com](mailto:info@francescopasseri.com) |
| **Author URI** | https://francescopasseri.com |
| **Requires WordPress** | 5.8+ |
| **Tested up to** | 6.5 |
| **Requires PHP** | 7.4+ (8.x recommended) |
| **License** | GPLv2 or later |
| **Plugin Homepage** | https://francescopasseri.com |

## About
FP Multilanguage keeps an English copy of Italian content aligned across posts, pages, custom post types, taxonomies, menus, media metadata, and SEO tags. It enforces dedicated `/en/` routing, browser-based redirects, and English-specific sitemaps while integrating with real translation providers (OpenAI, DeepL, Google Cloud Translation, LibreTranslate). The plugin ships with a diagnostics dashboard, REST tools, and WP-CLI commands for queue management.

* **Text Domain:** `fp-multilanguage`
* **Domain Path:** `/languages`

## Features

### 🆕 New in v0.4.1
- **🔐 API Keys Encryption**: All API keys (OpenAI, DeepL, Google, LibreTranslate) are encrypted in database using AES-256-CBC.
- **💾 Translation Versioning**: Complete backup and rollback functionality for all translations with full audit trail.
- **🔍 Preview Endpoint**: REST API endpoint to test translations without saving, with cost estimation and multi-provider support.

### Core Features
- Differential translation queue that reprocesses only modified fragments and preserves Gutenberg, ACF, and shortcode structures.
- Automatic duplication for posts, CPTs, taxonomies, WooCommerce product attributes, navigation menus, and media text fields.
- Provider adapters with configurable pricing metrics to estimate OpenAI, DeepL, Google, and LibreTranslate costs.
- Frontend locale override, English sitemap generation, hreflang/canonical management, and browser language redirects.
- Assisted mode when WPML or Polylang is active to avoid conflicts while keeping glossary/override tooling available.
- Diagnostics snapshot with queue KPIs, REST actions, and WP-CLI commands for batch runs, cleanup, and queue retention.

## Installation
1. Upload the `fp-multilanguage` directory to `/wp-content/plugins/` or install the generated ZIP from the Releases tab.
2. Activate the plugin via **Plugins → Installed Plugins** in WordPress.
3. Visit **Settings → FP Multilanguage** to configure providers, glossary rules, routing, and automation options.
4. Run an initial sync from the Diagnostics tab or via `wp fpml queue run` to populate English copies.

### ⚠️ Upgrading to v0.4.1
If upgrading from a previous version:
1. **Backup your database**: `wp db export backup-$(date +%Y%m%d).sql`
2. **Update the plugin** via WordPress admin or manual upload
3. **Migrate API keys** (one-time): `php tools/migrate-api-keys.php` or `wp eval-file tools/migrate-api-keys.php`
4. **Verify encryption**: Check that your API keys now have the `ENC:` prefix in the database

See [RELEASE_NOTES_v0.4.1.md](RELEASE_NOTES_v0.4.1.md) for complete upgrade guide.

## Usage
- Create or edit Italian content; the queue enqueues incremental jobs that translate the touched sections into English.
- Monitor queue size, job age, provider health, and cost projections from **Diagnostics → Queue**.
- Configure WP-Cron or a system cron to execute `wp fpml queue run` and keep translations up to date when `DISABLE_WP_CRON` is enabled.
- Use REST diagnostics buttons or WP-CLI (`wp fpml queue cleanup`, `wp fpml queue estimate-cost`, `wp fpml queue status`) for maintenance tasks.
- Enable assisted mode automatically when WPML/Polylang is detected to retain overrides without duplicating content.

## Hooks & Filters
| Hook | Type | Description |
| --- | --- | --- |
| `fpml_post_jobs_enqueued` | action | Fires after English duplicates are enqueued for a source post. |
| `fpml_translatable_post_types` | filter | Adjust the list of post types processed by the queue. |
| `fpml_translatable_taxonomies` | filter | Control which taxonomies receive English counterparts. |
| `fpml_queue_cleanup_states` | filter | Define the job states eligible for cleanup retention. |
| `fpml_queue_after_cleanup` | action | Runs after a queue cleanup completes with statistics. |
| `fpml_queue_cleanup_batch_size` | filter | Override the batch size used while cleaning queue entries. |
| `fpml_menu_item_translated` | action | Fires when a navigation menu field is translated. |
| `fpml_term_translated` | action | Fires when taxonomy term metadata is translated. |
| `fpml_post_translated` | action | Fires when translated post fields are saved. |
| `fpml_strings_scan_targets` | filter | Allow customization of the strings scanner targets. |

## Command Line & Automation
- `wp fpml queue run` — Process pending translation jobs (batch size configurable by filter).
- `wp fpml queue cleanup [--days=<int>] [--states=<list>]` — Remove processed jobs respecting retention thresholds.
- `wp fpml queue estimate-cost [--states=<list>] [--max-jobs=<int>]` — Estimate provider cost exposure.
- `wp fpml queue status` — Display queue KPIs, configured provider, and retention state.
- `wp eval-file tools/migrate-api-keys.php` — **[v0.4.1]** Migrate existing API keys to encrypted format with automatic backup.

## Documentation

### 🆕 v0.4.1 Documentation
- [`📋_LEGGI_QUI.md`](📋_LEGGI_QUI.md) — **START HERE** - Quick overview and getting started (2 min read).
- [`✅_IMPLEMENTAZIONE_COMPLETATA.md`](✅_IMPLEMENTAZIONE_COMPLETATA.md) — Complete feature summary and quick start guide.
- [`RIEPILOGO_FINALE_IMPLEMENTAZIONE.md`](RIEPILOGO_FINALE_IMPLEMENTAZIONE.md) — Comprehensive deployment guide with troubleshooting.
- [`NUOVE_FUNZIONALITA_E_CORREZIONI.md`](NUOVE_FUNZIONALITA_E_CORREZIONI.md) — Detailed implementation guide with code examples.
- [`RACCOMANDAZIONI_PRIORITARIE.md`](RACCOMANDAZIONI_PRIORITARIE.md) — Top 5 feature recommendations and 2025 roadmap.
- [`docs/api-preview-endpoint.md`](docs/api-preview-endpoint.md) — Complete REST API reference for preview endpoint.
- [`RELEASE_NOTES_v0.4.1.md`](RELEASE_NOTES_v0.4.1.md) — Official release notes with upgrade guide.

### Core Documentation
Extended documentation lives in the [`docs/`](docs) directory:
- [`docs/overview.md`](docs/overview.md) — Functional overview and feature summary.
- [`docs/architecture.md`](docs/architecture.md) — Internal architecture, data flows, and hooks.
- [`docs/faq.md`](docs/faq.md) — Operational FAQ for administrators and developers.
- [`docs/api-reference.md`](docs/api-reference.md) — Complete API reference with hooks, filters, REST endpoints, and examples.
- [`docs/troubleshooting.md`](docs/troubleshooting.md) — Troubleshooting guide for common issues.
- [`docs/performance-optimization.md`](docs/performance-optimization.md) — Performance tuning and optimization strategies.
- [`docs/deployment-guide.md`](docs/deployment-guide.md) — Production deployment best practices.
- [`docs/developer-guide.md`](docs/developer-guide.md) — Developer guide for extending the plugin.
- [`docs/webhooks-guide.md`](docs/webhooks-guide.md) — Webhook notifications setup (Slack, Discord, Teams).
- [`docs/examples/`](docs/examples/) — Practical code examples for common integrations.

## Support
Create an issue at <https://github.com/francescopasseri/FP-Multilanguage/issues> or contact <https://francescopasseri.com> for enterprise assistance.

## Development Workflow
- Run `npm install` to install JavaScript tooling (conventional-changelog CLI).
- `npm run sync:author` — Synchronise author metadata across plugin headers, readme files, and manifests. Use `APPLY=true npm run sync:author` to write changes.
- `npm run sync:docs` — Align documentation references (currently reuses the author sync script with documentation mode).
- `npm run changelog:from-git` — Generate or update `CHANGELOG.md` based on conventional commits.
- `composer run build` — Produce a production-ready build in `fp-multilanguage/vendor`.

## Assumptions
- GitHub issues at <https://github.com/francescopasseri/FP-Multilanguage/issues> serve as the public support channel.
- Conventional commits (Angular preset) are used for changelog generation.

## Release Process
See [README-BUILD.md](README-BUILD.md) for full build prerequisites. Typical release flow:
1. Update the version with `bash build.sh --bump=patch` or `--set-version=X.Y.Z`.
2. Run `npm run changelog:from-git` and review `CHANGELOG.md`.
3. Execute `composer run build` to assemble the distributable ZIP in `build/`.
4. Upload the ZIP to WordPress or GitHub and tag the release as `vX.Y.Z`.
