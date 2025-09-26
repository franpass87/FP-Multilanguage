# Phase 7 – Refactoring

## Goals
- Separate admin and public bootstrapping so high-risk hooks only load in the appropriate contexts.
- Consolidate service wiring to remove duplicated registrations and make dependencies explicit for future maintenance.

## Changes
- Introduced `Bootstrap\\AdminBootstrap` to guard admin settings registration behind admin/ajax/REST contexts while preserving REST availability.
- Added `Bootstrap\\PublicBootstrap` to coordinate translation managers, dynamic strings, SEO integration and the block registration from a single entry point.
- Split the plugin service container registration into focused helpers (logging, admin, translation, support, bootstraps) to document responsibilities and ease testing.
- Updated documentation to reflect the new bootstrap flow.

## Follow-up Notes
- Review CLI command coverage during the testing/CI phase to ensure container changes are exercised.
- Confirm multisite bootstrap order after migrations are introduced in phase 9.
