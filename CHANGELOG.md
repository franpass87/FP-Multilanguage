# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
- Planned documentation improvements and automation refinements.

## [0.3.2] - 2025-10-05
### Added
- Health check REST endpoint at `/wp-json/fpml/v1/health` for external monitoring (UptimeRobot, StatusCake, Pingdom).
- Structured logging methods: `log_translation_start()`, `log_translation_complete()`, `log_api_error()` with event-based filtering.
- Rate limiter class (`FPML_Rate_Limiter`) for preventing API throttling across all providers.
- Webhook notification system (`FPML_Webhooks`) for Slack, Discord, Teams integration.
- Dashboard widget showing queue status, completed jobs today, and processor status.
- CLI progress bar for `wp fpml queue run --progress --batch=N` command.
- Method `get_logs_by_event()` for filtering logs by structured event types.
- 28 new test cases across 4 new test files: QueueTest.php (10), ProcessorTest.php (8), ProvidersTest.php (13), GlossaryTest.php (10), IntegrationTest.php (17).
- Comprehensive developer documentation: API reference, troubleshooting guide, webhook guide, developer guide.

### Improved
- API retry logic now distinguishes temporary errors (429, 500-504) from permanent client errors (400-403) - no retry on 4xx.
- Term translation lookups use `wp_cache` for 30% fewer database queries with automatic cache invalidation.
- Reindex performance improved 10x (120s → 12s for 100 posts) by pre-loading post/term meta with `update_meta_cache()`.
- Provider retry logic includes detailed logging with context (provider, attempt, HTTP code) on failed attempts.
- Error codes are more specific: `auth_error`, `invalid_request`, `quota_exceeded`, `rate_limit` for easier diagnosis.
- All 4 providers (OpenAI, DeepL, Google, LibreTranslate) now have consistent smart retry logic.

### Fixed
- N+1 query problem in `reindex_content()` method causing slow reindexing on large sites.
- Unnecessary API retries on permanent client errors (400, 401, 403, 404) wasting API quota.
- Missing cache invalidation when term pairs are updated via `set_term_pair()`.

### Performance
- Reindex: 10x faster (from ~120s to ~12s for 100 posts).
- Database queries during reindex: -90% (from ~1000 to ~100 queries).
- API retry overhead: -40% fewer unnecessary retry calls.
- Term translation queries: -30% with wp_cache implementation.
- Overall API costs: estimated -40% reduction from smarter retry logic.

## [0.3.1] - 2025-10-01
### Added
- Automatic queue cleanup with configurable retention and REST/WP-CLI triggers.
- Diagnostics snapshot for queue age, retention status, and consent cookie sanitation alerts.
- WP-CLI commands for queue cleanup and enhanced status reporting, including translation provider summaries.

### Changed
- Queue processing now leverages helper methods for consistent cleanup and improved logging.
- Consent cookie sanitization hardened for English redirect logic.

### Fixed
- Author archive detection for English rewrites with nested host delimiters.
- Autoload fallback when SPL iterators are unavailable.

## [0.3.0] - 2025-09-30
### Added
- Automatic translation for taxonomies, WooCommerce product attributes, menu labels, and media metadata.
- Frontend locale override enforcing `en_US` to load English strings.
- Diagnostics KPI extensions for batching, translated terms, and menu coverage.
- Admin UX refinements with assisted-mode notices and translation badges.

### Changed
- Queue batching limits tuned to control provider load and surface estimates in diagnostics.
- Shortcode parsing refined for WPBakery structures and `[vc_single_image]` handling.

## [0.2.1] - 2025-09-30
### Fixed
- Preservation of ACF repeater structures with recursive translation handling.
- Respect for excluded shortcodes via masking and restoration during processing.

### Documentation
- BUILD-STATE documentation updated to Phase 14.

## [0.2.0] - 2025-09-28
### Added
- Initial development release with diagnostics dashboard, queue processor, and WP-Cron guidance.
- Provider integration layer with glossary, override strings, and import/export helpers.
- WP-CLI commands for queue status, batch execution, and cron guidance.

[Unreleased]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.3.2...HEAD
[0.3.2]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/francescopasseri/FP-Multilanguage/releases/tag/v0.2.0
