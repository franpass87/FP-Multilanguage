# Test Suite - FP Multilanguage

## Overview

Il plugin include una comprehensive test suite con 40+ test cases per garantire affidabilità e prevenire regressioni.

---

## Quick Start

```bash
# 1. Install dependencies
composer install

# 2. Run all tests
vendor/bin/phpunit

# 3. Run specific test file
vendor/bin/phpunit tests/phpunit/LanguageTest.php
```

---

## Test Files

### LanguageTest.php (22 tests)
Test per gestione lingua, routing, URL handling.

**Coverage:**
- ✅ URL construction con proxy headers
- ✅ Host header sanitization (IPv6, ports, encoding)
- ✅ Forwarded header parsing (RFC 7239)
- ✅ Request URI sanitization
- ✅ Security (null byte, path traversal, encoding attacks)

**Run:**
```bash
vendor/bin/phpunit tests/phpunit/LanguageTest.php
```

---

### QueueTest.php (10 tests)
Test per queue operations.

**Coverage:**
- ✅ Job enqueuing (posts, terms)
- ✅ State management (pending, done, error)
- ✅ Job retrieval and filtering
- ✅ Cleanup operations
- ✅ Statistics and counts

**Run:**
```bash
vendor/bin/phpunit tests/phpunit/QueueTest.php
```

---

### ProcessorTest.php (8 tests)
Test per batch processor.

**Coverage:**
- ✅ Singleton pattern
- ✅ Lock mechanism
- ✅ Concurrent execution prevention
- ✅ Lock expiry
- ✅ Translator instance retrieval
- ✅ Batch processing

**Run:**
```bash
vendor/bin/phpunit tests/phpunit/ProcessorTest.php
```

---

### ProvidersTest.php (13 tests)
Test per translation providers.

**Coverage:**
- ✅ All 4 providers (OpenAI, DeepL, Google, LibreTranslate)
- ✅ Interface implementation
- ✅ Cost estimation
- ✅ Text chunking
- ✅ Configuration validation
- ✅ Empty text handling

**Run:**
```bash
vendor/bin/phpunit tests/phpunit/ProvidersTest.php
```

---

### GlossaryTest.php (10 tests)
Test per glossary functionality.

**Coverage:**
- ✅ Rule management (add, remove, clear)
- ✅ Import/Export
- ✅ CSV parsing
- ✅ Input sanitization
- ✅ Unicode handling

**Run:**
```bash
vendor/bin/phpunit tests/phpunit/GlossaryTest.php
```

---

### IntegrationTest.php (17 tests)
Test di integrazione completi.

**Coverage:**
- ✅ Plugin initialization
- ✅ All core classes loadable
- ✅ Provider classes loadable
- ✅ Constants definition
- ✅ Logger workflow
- ✅ Rate limiter integration
- ✅ Webhooks integration

**Run:**
```bash
vendor/bin/phpunit tests/phpunit/IntegrationTest.php
```

---

## Running Tests

### Run All Tests
```bash
vendor/bin/phpunit
```

**Expected output:**
```
PHPUnit 10.5.x by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.0

....................................................  50 / 80 (62%)
............................                          80 / 80 (100%)

Time: 00:02.123, Memory: 12.00 MB

OK (80 tests, 150 assertions)
```

---

### Run Specific Test
```bash
# Single file
vendor/bin/phpunit tests/phpunit/QueueTest.php

# Single test method
vendor/bin/phpunit --filter test_queue_enqueue_creates_job

# Test group
vendor/bin/phpunit --group integration
```

---

### With Coverage Report

**Requires:** Xdebug or PCOV

```bash
# HTML coverage report
vendor/bin/phpunit --coverage-html coverage/

# Open report
open coverage/index.html

# Text coverage
vendor/bin/phpunit --coverage-text
```

**Expected coverage:**
```
Code Coverage Report:     
  Classes: 75.00% (12/16)
  Methods: 65.00% (52/80)
  Lines:   60.00% (1200/2000)
```

---

### Verbose Output
```bash
# Detailed output
vendor/bin/phpunit --verbose

# Very detailed
vendor/bin/phpunit --debug
```

---

## Writing New Tests

### Test Template

```php
<?php
use PHPUnit\Framework\TestCase;

final class MyFeatureTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        // Setup before each test
    }

    protected function tearDown(): void {
        // Cleanup after each test
        parent::tearDown();
    }

    public function test_feature_works_correctly(): void {
        // Arrange
        $input = 'test data';
        
        // Act
        $result = my_function( $input );
        
        // Assert
        $this->assertEquals( 'expected', $result );
    }
}
```

### Best Practices

1. **Naming:** `test_method_does_what()`
2. **Arrange-Act-Assert:** Chiara struttura test
3. **One assertion per concept**
4. **Use data providers** per test multipli
5. **Mock external dependencies**

### Example with Data Provider

```php
/**
 * @dataProvider provideValidUrls
 */
public function test_url_validation( $url, $expected ): void {
    $result = validate_url( $url );
    $this->assertEquals( $expected, $result );
}

public function provideValidUrls(): array {
    return array(
        'valid http' => array( 'http://example.com', true ),
        'valid https' => array( 'https://example.com', true ),
        'invalid' => array( 'not-a-url', false ),
    );
}
```

---

## Continuous Integration

### GitHub Actions

Create `.github/workflows/tests.yml`:
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1', '8.2']
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run tests
        run: vendor/bin/phpunit --coverage-text
```

---

## Mocking WordPress Functions

Il file `tests/bootstrap.php` fornisce mock per funzioni WordPress comuni:

- `wp_unslash()`
- `wp_strip_all_tags()`
- `is_ssl()`
- `home_url()`
- `trailingslashit()`
- etc.

### Adding More Mocks

```php
// In tests/bootstrap.php
if ( ! function_exists( 'your_wp_function' ) ) {
    function your_wp_function( $arg ) {
        // Mock implementation
        return $arg;
    }
}
```

---

## Test Database

I test NON richiedono WordPress installato. Usano:
- Mock functions in `bootstrap.php`
- Reflection per testare metodi protected
- Dummy objects per simulare WP_Post, WP_Term

### Testing with Real WordPress

Per integration testing con WordPress reale:

```bash
# Setup WordPress test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run tests
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit
```

---

## Performance Testing

### Benchmark Specific Methods

```php
public function test_reindex_performance(): void {
    $start = microtime( true );
    
    FPML_Plugin::instance()->reindex_content();
    
    $elapsed = microtime( true ) - $start;
    
    // Should complete in under 5 seconds for 100 posts
    $this->assertLessThan( 5.0, $elapsed );
}
```

---

## Debugging Tests

### PHPUnit Debug
```bash
# Print debug info
vendor/bin/phpunit --debug

# Stop on failure
vendor/bin/phpunit --stop-on-failure

# Stop on error
vendor/bin/phpunit --stop-on-error
```

### Add Debug Output
```php
public function test_something(): void {
    $result = some_function();
    
    // Debug output
    fwrite( STDERR, print_r( $result, true ) );
    
    $this->assertTrue( $result );
}
```

---

## Test Metrics

### Current Stats (v0.3.2)

```
Total Test Files:    6
Total Test Cases:    80
Total Assertions:    150+
Execution Time:      ~2-3 seconds
Memory Usage:        ~12 MB
```

### Coverage Goals

| Component | Current | Target |
|-----------|---------|--------|
| Core Classes | 60% | 80% |
| Providers | 40% | 70% |
| Queue | 65% | 85% |
| Language | 90% | 95% |

---

## Contributing Tests

Quando contribuisci codice, aggiungi test per:

1. **Nuove feature** - almeno 1 happy path test
2. **Bug fix** - test che riproduce e verifica fix
3. **Refactoring** - test coverage esistente non deve diminuire

### Pull Request Checklist
- [ ] Tutti i test passano
- [ ] Nuovi test per nuove feature
- [ ] Coverage non diminuisce
- [ ] Test sono documentati

---

**Maintainer:** Francesco Passeri  
**Last updated:** 2025-10-05
