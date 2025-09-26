# Phase 8 – Tests & Continuous Integration

## Summary
- Updated PHPUnit fixtures to accommodate the refactored settings dependencies by introducing a reusable `SettingsFactory` and extending WordPress test stubs with REST response helpers and cache deletion so providers remain configurable during isolated runs.
- Realigned translation, post, term, and comment manager tests to respect REST controller return types and the tightened caching rules introduced during earlier phases.
- Added assertions that guarantee provider enablement before translation-side effects to avoid silent regressions when defaults change.
- Provisioned a GitHub Actions workflow that runs coding standards, static analysis, and the PHPUnit suite across PHP 8.1–8.3 while toggling WordPress version targets through an environment matrix for forward-compatibility.

## Test Execution
- `composer test`
- `phpdbg -qrr vendor/bin/phpunit --coverage-html docs/coverage/html` *(fails to generate coverage because the container PHP build lacks a coverage driver)*

## Notes
- Code coverage could not be generated locally: both Xdebug and PCOV are unavailable for the PHP 8.4 binary supplied in the automation image. Install one of these extensions before running the coverage command to populate `docs/coverage/html`.
- The new CI pipeline expects `composer.lock` to remain committed so dependency resolution is deterministic across the PHP matrix.
