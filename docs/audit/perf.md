# Performance Audit (Phase 5)

## Summary
- Profiled settings accessors and manual string catalog builds to identify repeated option and metadata lookups executed on every request.
- Added WordPress object cache integration and runtime caches for settings, manual strings, and metadata to eliminate redundant database hits across admin and front-end flows.
- Ensured dynamic string persistence actively invalidates cached metadata so new strings surface immediately in manual translation UIs.

## Findings
- `Settings::get_manual_strings()` and catalog generation executed an uncached `get_option()` call per request, multiplying load on sites with many translated strings.
- Manual string metadata sourced from the `fp_multilanguage_strings` table was recomputed on every catalog access even when the underlying data remained unchanged.
- Dynamic string persistence could leave stale metadata in memory after inserting new records, delaying their visibility.

## Remediations
- Centralised cache keys within `Admin\Settings\Repository`, leveraging `wp_cache_get/set/delete` when available and falling back to in-process caching in other contexts.
- Propagated cache clearing hooks for both manual string option storage and fallback metadata storage to keep caches coherent during updates.
- Exposed static cache clearing helpers on `Admin\Settings` and invoked them from the dynamic string persistence workflow to invalidate metadata after writes to the database or option fallback.

## Next Steps
- Monitor cache hit/miss ratios during runtime logging follow-up to confirm real-world reduction in option queries.
- Evaluate object caching adoption on target hosting platforms to decide whether to add transient fallbacks for long-lived metadata between requests.
- Revisit translation service caching once performance testing covers translation scheduling workloads.
