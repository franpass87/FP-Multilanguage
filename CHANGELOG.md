# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
- Planned documentation improvements and automation refinements.

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

[Unreleased]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.3.1...HEAD
[0.3.1]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/francescopasseri/FP-Multilanguage/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/francescopasseri/FP-Multilanguage/releases/tag/v0.2.0
