# FP Multilanguage Overview

Automates Italian-to-English copies of content, taxonomies, menus, media, and SEO data with queue-based routing and trusted translation providers.

## What It Does
FP Multilanguage keeps an English mirror of Italian content synchronised across posts, pages, custom post types, WooCommerce product data, navigation menus, media metadata, and SEO/Open Graph/Twitter tags. It enforces `/en/` routing (or `?lang=` fallbacks), generates English sitemaps, applies hreflang/canonical tags, and performs browser language redirects. The queue processes only modified fragments, preserving Gutenberg blocks, ACF structures, and complex shortcodes.

## Feature Highlights
- Differential translation queue with prioritisation, retry logic, and job retention controls.
- Provider adapters for OpenAI, DeepL, Google Cloud Translation, and LibreTranslate with configurable pricing to estimate costs.
- Assisted mode that disables automatic duplication when WPML or Polylang is active while keeping glossary, overrides, and provider utilities.
- Diagnostics dashboard showing queue KPIs, provider connectivity, log excerpts, and manual REST actions.
- WP-CLI commands (`wp fpml queue run`, `cleanup`, `estimate-cost`, `status`) and REST endpoints for automation and observability.

## Quick Start
1. Install and activate the plugin, then configure providers in **Settings → FP Multilanguage**.
2. Define routing mode (`/en/` or query-string) and review glossary/override rules.
3. Publish or update Italian content to enqueue translation jobs; monitor progress from Diagnostics.
4. Schedule `wp fpml queue run` (via WP-Cron or server cron) to keep the queue draining.
5. Configure retention rules or run `wp fpml queue cleanup --days=<int>` to prune processed jobs regularly.

## Related Documentation
- [Architecture](architecture.md)
- [FAQ](faq.md)
- [CHANGELOG](../CHANGELOG.md)
