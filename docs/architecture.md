# Architecture

## High-Level Components
- **FPML_Plugin** — Bootstrap singleton that wires admin UI, queue processor, translation providers, rewrites, and diagnostics.
- **FPML_Admin** — Manages settings pages, diagnostics dashboards, and assists with provider testing, glossary, overrides, and routing configuration.
- **FPML_Queue** — Owns the `{$wpdb->prefix}fpml_queue` table, handles schema upgrades, enqueues translation jobs, tracks retries, and orchestrates cleanup/retention.
- **FPML_Processor** — Consumes queue jobs, runs differential translations using providers, updates posts/terms/menus/media, and triggers `fpml_*_translated` actions.
- **FPML_Strings_Scanner / FPML_Strings_Override** — Index and override strings across plugins/themes for assisted translations.
- **Provider adapters** — `FPML_Provider_OpenAI`, `FPML_Provider_DeepL`, `FPML_Provider_Google`, `FPML_Provider_LibreTranslate` implement `FPML_Translator_Interface` for consistent API calls and pricing metadata.
- **FPML_REST_Admin** — Exposes REST endpoints for queue runs, provider tests, reindex, and cleanup (`fpml/v1/queue/run`, `/test-provider`, `/reindex`, `/queue/cleanup`).
- **FPML_CLI_Queue_Command** — Mirrors REST automation through WP-CLI commands (`wp fpml queue run|cleanup|estimate-cost|status`).

## Data Flow
1. Content changes trigger `FPML_Plugin::enqueue_post_translation()` or related helpers, which compute hashes of source data and enqueue jobs in the queue table.
2. Scheduled events (`fpml_process_queue`, `fpml_cleanup_queue`) or manual CLI/REST calls invoke `FPML_Processor` to lock, translate, and persist target fields.
3. Translated data is written to English posts/terms/menu items/media, with hooks like `fpml_post_translated`, `fpml_term_translated`, and `fpml_menu_item_translated` announcing updates.
4. Queue cleanup runs after processing, using `FPML_Queue::cleanup_old_jobs()` filtered by `fpml_queue_cleanup_states` and `fpml_queue_cleanup_batch_size` to keep the table lean.
5. Diagnostics aggregates queue metrics, provider status, estimated costs, and retention info for the admin dashboard and WP-CLI output.

## Database Schema
`FPML_Queue` installs a custom table with the following notable columns:
- `object_type`, `object_id`, `field` — Composite identifier for the content fragment being translated.
- `hash_source` — Hash of the source payload used to detect changes and skip redundant work.
- `state` — `pending`, `processing`, `done`, `skipped`, `error`.
- `retries`, `last_error` — Track failure recovery.
- `created_at`, `updated_at` — Support retention policies and diagnostics reporting.
Indexes exist for object lookup, state filtering, and `(state, updated_at)` cleanup sweeps.

## Cron & Scheduling
- **`fpml_process_queue`** — Runs through pending jobs in batches (default 100, filterable via `fpml_estimate_batch_size`).
- **`fpml_cleanup_queue`** — Daily cleanup of processed jobs when retention is enabled.
- Hooks integrate with WP-Cron by default; system cron can invoke `wp cron event run --due-now` when `DISABLE_WP_CRON` is true.

## Routing & SEO
- `FPML_Rewrites` registers `/en/` structures and fallbacks to `?lang=` query switches.
- `FPML_SEO` handles hreflang tags, canonical URLs, Open Graph/Twitter meta duplication, and English sitemap generation.
- Assisted mode detects WPML/Polylang and disables conflicting rewrite/queue features while keeping provider tooling available.

## Hooks Reference
Key filters/actions exposed by the plugin:
- `fpml_translatable_post_types`, `fpml_translatable_taxonomies` — Control which objects enter the queue.
- `fpml_queue_cleanup_states`, `fpml_queue_cleanup_batch_size` — Tune cleanup retention.
- `fpml_post_jobs_enqueued` — Observe new jobs for auditing.
- `fpml_post_translated`, `fpml_term_translated`, `fpml_menu_item_translated` — React to translated data persistence.
- `fpml_strings_scan_targets` — Extend strings scanning coverage for assisted translations.

## External Services
The plugin integrates with:
- **OpenAI** (ChatGPT / GPT models via API key)
- **DeepL** (REST API key)
- **Google Cloud Translation** (Service account or API key)
- **LibreTranslate** (Self-hosted or public endpoints)

Providers expose pricing metadata used in Diagnostics to estimate translation costs per job/state.

## Files & Entry Points
- Main plugin file: [`fp-multilanguage/fp-multilanguage.php`](../fp-multilanguage/fp-multilanguage.php)
- Admin bootstrap: [`fp-multilanguage/admin/class-admin.php`](../fp-multilanguage/admin/class-admin.php)
- Queue logic: [`fp-multilanguage/includes/class-queue.php`](../fp-multilanguage/includes/class-queue.php)
- Processor: [`fp-multilanguage/includes/class-processor.php`](../fp-multilanguage/includes/class-processor.php)
- REST layer: [`fp-multilanguage/rest/class-rest-admin.php`](../fp-multilanguage/rest/class-rest-admin.php)
- CLI commands: [`fp-multilanguage/cli/class-cli.php`](../fp-multilanguage/cli/class-cli.php)
