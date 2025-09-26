# Phase 9 – Upgrade & Migrations

## Objectives
- Validate the automatic upgrade workflow after the bootstrap/service container refactor.
- Ensure database/options migrations initialise new stores introduced during earlier phases.
- Purge caches (object cache, runtime caches, OPcache) so new settings and translations load consistently after upgrades.
- Provide automated coverage validating the new upgrade orchestration.

## Actions
- Introduced an `Install\UpgradeManager` service registered in the container and invoked during activation/bootstrap upgrades.
- Consolidated cache invalidation (settings, manual strings, translation runtime) and optional `wp_cache_flush()` / `opcache_reset()` calls inside the upgrade routine.
- Guaranteed the legacy `fp_multilanguage_strings` store is initialised when missing to support the metadata cache introduced in phase 5.
- Logged upgrade completions with previous/current version context for operational visibility.
- Added a PHPUnit suite for the upgrade manager to confirm caches flush, manual stores persist, and logging occurs.
- Extended WordPress test doubles with a `wp_cache_flush()` shim and tracking counter used by the new tests.

## Results
- Upgrades now reinitialise all caches and manual stores deterministically, preventing stale configuration/translation data across deployments.
- Admins receive structured log entries documenting each upgrade transition.
- Regression coverage protects cache flushing and legacy store initialisation logic.

## Follow-up
- During release packaging (phase 10), document the new upgrade logging behaviour and highlight cache flush expectations for site owners.
