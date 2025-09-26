# FP Multilanguage – Code Map

## Overview
FP Multilanguage is a WordPress plugin that provides automatic and manual translations for posts, attachments, comments, terms and dynamic strings. The plugin bootstraps through a dependency container and exposes admin pages, REST endpoints, a shortcode, widgets, a Gutenberg block, CLI commands and background jobs to keep translations in sync. Key constants (`FP_MULTILANGUAGE_FILE`, `FP_MULTILANGUAGE_PATH`, `FP_MULTILANGUAGE_URL`, `FP_MULTILANGUAGE_VERSION`) are defined in `fp-multilanguage.php` and used throughout the codebase.

## Bootstrap & Service Container
- **Main entry (`fp-multilanguage/fp-multilanguage.php`)**: Loads Composer or fallback autoloader, registers activation/deactivation/uninstall hooks and boots the singleton `FPMultilanguage\Plugin`.
- **`FPMultilanguage\Plugin`**: Builds a `Support\Container`, registers services and hooks:
  - Hooks: `plugins_loaded` → `bootstrap`, `init` → load textdomain, `template_redirect` → remember language cookie, `widgets_init` → register widgets, `add_shortcode` for `[fp_language_switcher]`.
  - Activation triggers migrations/default settings (`Install\Migrator`, `Admin\Settings::bootstrap_defaults()`).
  - `bootstrap()` resolves the runtime logger and delegates admin wiring to `Bootstrap\\AdminBootstrap` (admin/ajax/REST contexts) and shared/frontend wiring to `Bootstrap\\PublicBootstrap`.

## Admin Interfaces (`includes/Admin`)
- **`Settings`**: Registers admin menu pages (main settings, onboarding, log viewer, manual strings), WP Settings API fields, enqueues admin scripts/styles and binds `admin_post_fp_multilanguage_save_strings`. Bootstraps `SettingsRestController` REST routes and wires repository cache hooks.
- **`Settings\Repository`**: Handles option storage, sanitization and caching. Manages manual string catalogs, provider credentials, translatable entities, quote tracking, etc. Uses option names `fp_multilanguage_options`, `fp_multilanguage_manual_strings` and the legacy cache option `fp_multilanguage_strings`, and exposes defaults.
- **`Settings\ManualStringsUI`**: Renders manual string editor table and handles POST saving (`manage_options` + nonce required).
- **`Settings\RestController`**: Registers REST namespace `fp-multilanguage/v1` for `/settings` CRUD and `/providers/test`. Requires `manage_options` capability and verifies nonce (`fp_multilanguage_settings`).
- **`AdminNotices`**: Collects transient admin notices (info/warning/error) and renders them in `admin_notices` hook.

## Content Translation (`includes/Content`)
- **`PostTranslationManager`**:
  - Hooks: `init` (registers `fp_lang` query var), `save_post` (queues translation job), `rest_api_init` (registers translation routes), scheduled event `fp_multilanguage_process_translation`, filters for `the_content`, `the_title`, `get_the_excerpt`, attachment metadata and `body_class` to inject localized values.
  - REST endpoints under `/posts/{id}/translate`, `/attachments/{id}/translate`, `/content/{type}/{id}/translate` (POST). Permission requires `current_user_can( 'edit_post', $id )`.
  - Stores translations in post meta `_fp_multilanguage_translations` and relationship metadata `_fp_multilanguage_relations`.
  - Schedules background jobs with `wp_schedule_single_event` and can process synchronously.
- **`CommentTranslationManager`**: Hooks `wp_insert_comment`, `edit_comment`, `deleted_comment` to sync comment translations into comment meta `_fp_multilanguage_comment_translations`.
- **`TermTranslationManager`**: Hooks `created_term`, `edited_term`, `delete_term` to manage term meta `_fp_multilanguage_term_translations` and relationships.

## Dynamic Strings (`includes/Dynamic`)
- **`DynamicStrings`**:
  - Hooks: `init` (register assets), `wp_enqueue_scripts` & `admin_enqueue_scripts` (enqueue JS), AJAX `wp_ajax_fp_multilanguage_save_string`, REST `/strings` endpoints, filters `gettext`, `ngettext`, `gettext_with_context`, widget/title/nav menu/thememod filters to capture and translate arbitrary strings.
  - Maintains runtime caches and interacts with manual string options.
  - AJAX requests require nonce `fp_multilanguage_manual_string` and `manage_options` capability.

## SEO Module (`includes/SEO/SEO.php`)
- Registers meta box (`add_meta_boxes`), handles `save_post` to persist SEO translations, cleans up on `delete_post`, and renders hreflang/canonical/open graph tags via `wp_head`. Stores language-specific slugs in option `fp_multilanguage_slug_index`.

## Widgets, Shortcodes & Blocks
- **Widget**: `Widgets\LanguageSwitcher` extends `WP_Widget`; provides frontend switcher markup and admin form.
- **Shortcode**: `[fp_language_switcher]` delegates to `LanguageSwitcher::render_shortcode()`.
- **Block**: `Blocks\LanguageSwitcherBlock` registers a dynamic block via `register_block_type` with JS from `assets/js/language-switcher-block.js`.

## Services & Support
- **`Services\TranslationService`**: Central translation engine with caching (object cache + transients), provider failover, manual overrides and quota tracking (`fp_multilanguage_quota`). Flushes cache by bumping `fp_multilanguage_cache_version`.
- **Providers**: `GoogleProvider`, `DeepLProvider` implement `TranslationProviderInterface`. They perform remote API calls using WordPress HTTP API with configurable options.
- **`Services\Logger`**: Writes to WooCommerce logger or PHP error log, mirrors entries into option `fp_multilanguage_logs` (latest 200). CLI aware.
- **`Support\Container`**: Lightweight dependency container used by `Plugin`.
- **`CurrentLanguage`**: Resolves current language via query param, cookie, user meta, locale or path. Persists preference via cookie/user meta.

## CLI & Tools
- **`CLI\Commands`**: Registers `wp fp-multilanguage translate <post_id> [--language=<code>]` when WP-CLI is available.
- **`Services\Logger`** exposes stored log entries for admin log page.

## Install & Database
- **`Install\Migrator`**: Creates table `{prefix}fp_multilanguage_strings` for manual string storage (`maybe_create_table`) and tracks DB version option `fp_multilanguage_db_version`. Drops table on uninstall.
- **`Install\UpgradeManager`**: Orchestrates activation/bootstrap upgrades by ensuring manual string stores exist, purging cached settings/runtime data (object cache, runtime caches, OPcache) and logging the transition before defaults reload.

## Assets & Build
- Source JS located in `assets/js/` (admin interface, frontend dynamic translations, block). Pre-built bundles in `assets/dist/`. Build tooling via Vite (`vite.config.js`) and npm scripts (`package.json`).

## Localization
- Text domain `fp-multilanguage`. MO/PO files expected under `languages/`. `Plugin::load_textdomain()` loads translations on `init`.

## Stored Options & Meta Overview
- Options: `fp_multilanguage_options`, `fp_multilanguage_manual_strings`, `fp_multilanguage_strings` (legacy metadata cache), `fp_multilanguage_version`, `fp_multilanguage_db_version`, `fp_multilanguage_quota`, `fp_multilanguage_slug_index`, `fp_multilanguage_logs`, `fp_multilanguage_cache_version`.
- Post Meta: `_fp_multilanguage_translations`, `_fp_multilanguage_relations`, `_fp_multilanguage_seo` (per SEO module).
- Comment Meta: `_fp_multilanguage_comment_translations`.
- Term Meta: `_fp_multilanguage_term_translations`.

## REST API Summary
- `fp-multilanguage/v1/settings` (GET/POST) – admin settings CRUD with nonce & `manage_options`.
- `fp-multilanguage/v1/providers/test` (POST) – provider credential tester.
- `fp-multilanguage/v1/strings` (GET/POST) – manual string management (requires `manage_options`).
- `fp-multilanguage/v1/posts/{id}/translate` (POST) – queue/sync translations for a post.
- `fp-multilanguage/v1/attachments/{id}/translate` (POST) – queue/sync attachment translations.
- `fp-multilanguage/v1/content/{type}/{id}/translate` (POST) – generic entry point for custom post types.

## Cron & Background Jobs
- Custom action `fp_multilanguage_process_translation` scheduled via `wp_schedule_single_event` for asynchronous translation tasks.

## Frontend Integration
- Filters for content/title/excerpt/meta ensure translated strings are displayed.
- `LanguageSwitcher` widget/shortcode/block output localized navigation.
- Dynamic script attaches to DOM elements with `data-fp-translatable` for manual override capture.

