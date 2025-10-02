=== FP Multilanguage ===
Contributors: francescopasseri
Tags: translation, multilanguage, openai, deepl, google translate, seo
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Plugin Homepage: https://francescopasseri.com

Automates Italian-to-English copies of content, taxonomies, menus, media, and SEO data with queue-based routing and trusted translation providers.

== Description ==
FP Multilanguage duplicates English content that stays synced with the original Italian post while enforcing `/en/` routing or query-string switches, EN-specific sitemaps, hreflang, canonical URLs, and browser language detection. It translates taxonomy terms, WooCommerce product data, menus, media metadata, and SEO/Open Graph/Twitter tags, keeping attachments and slug mappings aligned between languages.

= Key Features =
* Queue-driven incremental translation that only processes modified fragments and preserves complex Gutenberg, ACF, and shortcode layouts.
* Provider adapters for OpenAI, DeepL, Google Cloud Translation, and LibreTranslate with configurable pricing metrics.
* Automatic duplication for posts, pages, CPTs, taxonomies, WooCommerce attributes, menu labels, and media captions/ALT fields.
* Frontend locale override, dedicated English sitemap routing, and redirect rules based on browser preferences.
* Diagnostics dashboard with queue KPIs, REST tools, and WP-CLI commands for batch runs, cleanup, and cost estimation.

= Requirements =
* WordPress 5.8 or later
* PHP 7.4 or later (8.x recommended)
* API credentials for at least one translation provider (OpenAI, DeepL, Google, or LibreTranslate)

== Installation ==
1. Upload the `fp-multilanguage` folder to `/wp-content/plugins/` or install the packaged ZIP via the WordPress admin.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Configure providers and routing rules in **Settings → FP Multilanguage**.
4. Run an initial sync from the Diagnostics tab or via `wp fpml queue run`.

== Usage ==
* Create or update Italian content; the plugin enqueues differential jobs that translate the updated sections into English.
* Use the Diagnostics tab to monitor queue size, job age, estimated costs, and provider connectivity.
* Schedule regular queue processing with WP-Cron or WP-CLI (`wp cron event run --due-now`) when `DISABLE_WP_CRON` is true.
* Trigger manual cleanups or estimates via WP-CLI (`wp fpml queue cleanup`, `wp fpml queue estimate-cost`).
* Integrate assisted mode alongside WPML or Polylang to reuse glossary and overrides while disabling automatic duplication.

== Frequently Asked Questions ==
= Does the plugin support manual edits on the English copy? =
Yes. Manual changes persist; the queue only overwrites segments touched on the Italian source and highlights outdated sections in Diagnostics.

= How does assisted mode behave with WPML or Polylang? =
When either plugin is active, FP Multilanguage keeps provider, glossary, and override tools available but pauses automatic duplication, routing rules, and queue operations to avoid conflicts.

= Can I choose which post types and taxonomies are translated? =
Use the settings screen or the `fpml_translatable_post_types` and `fpml_translatable_taxonomies` filters to whitelist or exclude content types programmatically.

= How are API costs estimated? =
Diagnostics combines queue metadata with provider-specific pricing profiles to estimate total characters processed and expected costs per provider.

= What happens if the queue grows too large? =
Configure automatic cleanup retention or run `wp fpml queue cleanup --days=7` to purge processed jobs. Hooks such as `fpml_queue_cleanup_states` and `fpml_queue_cleanup_batch_size` allow fine-tuning.

= Does the plugin translate media files? =
It keeps using the original media binary but translates captions, ALT text, and titles. On the English frontend it replaces attachment IDs within shortcodes or builder structures to avoid layout regressions.

== Hooks ==
* `fpml_post_jobs_enqueued` — Fired after English duplicates are enqueued for a post update.
* `fpml_translatable_post_types` — Filter the list of post types processed by the queue.
* `fpml_translatable_taxonomies` — Filter the taxonomies mirrored into English.
* `fpml_queue_cleanup_states` — Customize the job states eligible for retention cleanup.
* `fpml_queue_after_cleanup` — Action triggered once a cleanup run finishes with totals.
* `fpml_queue_cleanup_batch_size` — Filter the batch size used during cleanup routines.
* `fpml_menu_item_translated` — Action executed when a menu field receives a translated value.
* `fpml_term_translated` — Action executed when a taxonomy term field is translated.
* `fpml_post_translated` — Action executed when post fields are persisted after translation.
* `fpml_strings_scan_targets` — Filter the targets inspected by the strings scanner.

== Support ==
Open GitHub issues at https://github.com/francescopasseri/FP-Multilanguage/issues or contact https://francescopasseri.com for commercial support.

== Changelog ==
For the complete history see [CHANGELOG.md](https://github.com/francescopasseri/FP-Multilanguage/blob/main/CHANGELOG.md).

= 0.3.1 - 2025-10-01 =
* Added automatic queue cleanup with retention controls and REST/WP-CLI actions.
* Improved diagnostics with job age summaries, consent cookie sanitization, and additional helpers.

= 0.3.0 - 2025-09-30 =
* Introduced translation for taxonomies, WooCommerce attributes, navigation menus, and media metadata.
* Forced frontend locale to `en_US` and extended diagnostics with batching KPIs.

= 0.2.1 - 2025-09-30 =
* Fixed recursive translation handling for ACF repeaters and shortcode exclusions.

= 0.2.0 - 2025-09-28 =
* Initial development release with Diagnostics dashboard, queue processor, and WP-Cron helpers.

== Upgrade Notice ==
= 0.3.1 =
Review the new cleanup retention options and configure retention days to keep the queue size under control.
