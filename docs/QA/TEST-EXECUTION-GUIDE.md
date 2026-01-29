# Test Execution Guide

Complete guide to executing all QA tests for FP Multilanguage plugin.

## Prerequisites

### PHP Requirements
- PHP 8.0 or higher
- Composer installed
- PHPUnit 10.5+

### Node.js Requirements (for E2E tests)
- Node.js 18 or higher
- npm or yarn

### WordPress Requirements
- WordPress 5.8 or higher
- Local development environment (for E2E tests)

## Installation

### Install PHP Dependencies
```bash
composer install
```

### Install Node.js Dependencies (for E2E tests)
```bash
npm install
# or
yarn install
```

### Install Playwright Browsers
```bash
npx playwright install
```

## Running Tests

### 1. PHPUnit Tests

#### Run All Tests
```bash
composer test
# or
vendor/bin/phpunit
```

#### Run Unit Tests Only
```bash
vendor/bin/phpunit --testsuite Unit
```

#### Run Integration Tests Only
```bash
vendor/bin/phpunit --testsuite Integration
```

#### Run with Coverage
```bash
vendor/bin/phpunit --coverage-html tests/coverage
```

### 2. E2E Tests (Playwright)

#### Run All E2E Tests
```bash
npx playwright test
```

#### Run Specific Test File
```bash
npx playwright test tests/e2e/translation-workflow.spec.js
```

#### Run Tests in Specific Browser
```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
```

#### Run Tests in UI Mode
```bash
npx playwright test --ui
```

#### View Test Report
```bash
npx playwright show-report
```

### 3. QA Tools

#### Run Hook Validator
```bash
php tools/qa-hook-validator.php
```

#### Run Security Scanner
```bash
php tools/qa-security-scanner.php
```

#### Run Compatibility Checker
```bash
php tools/qa-compatibility-checker.php
```

#### Run Performance Profiler
```bash
php tools/qa-performance-profiler.php
```

#### Run All QA Tools
```bash
php tools/qa-run-all.php
```

### 4. Code Quality Checks

#### PHP CodeSniffer
```bash
composer phpcs
```

#### PHPStan Analysis
```bash
composer phpstan
```

#### Auto-fix Code Style
```bash
composer phpcbf
```

## Test Configuration

### PHPUnit Configuration
Configuration file: `phpunit.xml.dist`

Key settings:
- Bootstrap: `tests/bootstrap.php`
- Test suites: Unit, Integration
- Coverage: HTML and text output

### Playwright Configuration
Configuration file: `tests/e2e/playwright.config.js`

Key settings:
- Base URL: `http://localhost:8888` (configurable via `WP_URL` env var)
- Browsers: Chromium, Firefox, WebKit
- Retries: 2 in CI, 0 locally
- Screenshots: On failure
- Videos: Retain on failure

### Environment Variables

For E2E tests, set these environment variables:
```bash
export WP_URL=http://localhost:8888
export WP_ADMIN_USER=admin
export WP_ADMIN_PASS=admin
```

## CI/CD Integration

All tests run automatically in GitHub Actions on:
- Push to `main` or `develop` branches
- Pull requests

See `.github/workflows/qa-tests.yml` for configuration.

## Test Coverage Goals

- **Unit Tests**: 80%+ coverage on core modules
- **Integration Tests**: All critical workflows covered
- **E2E Tests**: All user-facing features tested

## Troubleshooting

### PHPUnit Issues

**Problem**: Tests fail with "Class not found"
**Solution**: Run `composer dump-autoload`

**Problem**: WordPress functions not available
**Solution**: Ensure `tests/bootstrap.php` is correct and WordPress is loaded

### Playwright Issues

**Problem**: Browsers not installed
**Solution**: Run `npx playwright install`

**Problem**: Tests timeout
**Solution**: Increase timeout in `playwright.config.js` or check WordPress is running

**Problem**: Login fails
**Solution**: Verify `WP_ADMIN_USER` and `WP_ADMIN_PASS` environment variables

### QA Tools Issues

**Problem**: Scripts fail with "WordPress not loaded"
**Solution**: Ensure WordPress is accessible or run from WordPress root directory

## Best Practices

1. **Run tests before committing**: Use `composer test` and `npx playwright test`
2. **Fix failing tests immediately**: Don't commit broken tests
3. **Keep coverage high**: Aim for 80%+ on critical code
4. **Update tests with features**: Add tests for new functionality
5. **Review test output**: Check coverage reports and test results

## Next Steps

After running tests:
1. Review test results
2. Fix any failures
3. Check coverage reports
4. Update documentation if needed
5. Commit passing tests














