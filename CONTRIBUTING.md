# Contributing to FP Multilanguage

Thank you for considering contributing to FP Multilanguage! 🎉

## Table of Contents
- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [How to Contribute](#how-to-contribute)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)

---

## Code of Conduct

This project follows a Code of Conduct. By participating, you are expected to uphold this code.

### Our Standards

- ✅ Be respectful and inclusive
- ✅ Welcome newcomers
- ✅ Focus on what is best for the community
- ✅ Show empathy towards others

---

## Getting Started

### Prerequisites

- PHP 7.4+ (8.2 recommended)
- Composer
- Node.js & npm
- Git
- WordPress 5.8+

### Setup Development Environment

```bash
# 1. Fork & clone
git clone https://github.com/YOUR_USERNAME/FP-Multilanguage.git
cd FP-Multilanguage

# 2. Install dependencies
composer install
npm install

# 3. Run tests
vendor/bin/phpunit

# 4. Setup pre-commit hooks
pip install pre-commit
pre-commit install
```

---

## How to Contribute

### Reporting Bugs

Use our [Bug Report template](.github/ISSUE_TEMPLATE/bug_report.md):

1. Check existing issues first
2. Use clear, descriptive title
3. Include steps to reproduce
4. Include plugin version, WordPress version, PHP version
5. Include relevant logs/screenshots

### Suggesting Features

Use our [Feature Request template](.github/ISSUE_TEMPLATE/feature_request.md):

1. Describe the problem you're trying to solve
2. Describe your proposed solution
3. Consider alternative solutions
4. Explain why this benefits other users

### Code Contributions

1. **Small fixes:** Fork → Fix → PR
2. **Large features:** Open issue first → Discuss → Implement
3. **Documentation:** Always welcome!

---

## Development Workflow

### Branching Strategy

- `main` - Production-ready code
- `develop` - Development branch
- `feature/name` - New features
- `fix/name` - Bug fixes
- `docs/name` - Documentation

### Creating a Feature Branch

```bash
# Start from develop
git checkout develop
git pull origin develop

# Create feature branch
git checkout -b feature/amazing-feature

# Make changes
# ...

# Commit with conventional commits
git commit -m "feat: add amazing feature"

# Push
git push origin feature/amazing-feature

# Open PR on GitHub
```

---

## Coding Standards

### PHP

**Follow WordPress Coding Standards:**

```bash
# Check code
vendor/bin/phpcs fp-multilanguage/ --standard=WordPress

# Auto-fix
vendor/bin/phpcbf fp-multilanguage/ --standard=WordPress

# Static analysis
vendor/bin/phpstan analyze
```

### Code Style

```php
// ✅ Good
class FPML_My_Feature {
    protected $setting;

    public function __construct() {
        $this->setting = FPML_Settings::instance();
    }

    public function do_something( $param ) {
        $validated = sanitize_text_field( $param );

        if ( empty( $validated ) ) {
            return new WP_Error( 'invalid_param', 'Parameter required' );
        }

        return $this->process( $validated );
    }
}

// ❌ Bad
class my_feature {
    function doSomething($param) { //CamelCase, no docblock
        return $this->process($param); //No validation
    }
}
```

---

### Documentation

**PHPDoc required for all:**
- Classes
- Methods (public and protected)
- Functions
- Complex logic blocks

```php
/**
 * Process translation job.
 *
 * @since 0.3.2
 *
 * @param object $job Queue job object.
 *
 * @return string|WP_Error Translated text or error.
 */
public function process_job( $job ) {
    // Implementation
}
```

---

## Testing

### Writing Tests

**Every PR must include tests for:**
- New features
- Bug fixes
- Refactoring

```php
// tests/phpunit/MyFeatureTest.php
use PHPUnit\Framework\TestCase;

final class MyFeatureTest extends TestCase {
    public function test_feature_works(): void {
        $feature = new FPML_My_Feature();
        
        $result = $feature->do_something( 'input' );
        
        $this->assertEquals( 'expected', $result );
    }
}
```

---

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Specific file
vendor/bin/phpunit tests/phpunit/MyFeatureTest.php

# With coverage
vendor/bin/phpunit --coverage-html coverage/
```

---

### Test Requirements

- ✅ All tests must pass
- ✅ New code must have tests
- ✅ Coverage should not decrease
- ✅ Tests should be fast (<5s total)

---

## Pull Request Process

### Before Submitting

1. **Code Quality**
   ```bash
   vendor/bin/phpcs fp-multilanguage/
   vendor/bin/phpstan analyze
   ```

2. **Tests**
   ```bash
   vendor/bin/phpunit
   # All tests must pass ✅
   ```

3. **Documentation**
   - Update relevant docs
   - Add PHPDoc comments
   - Update CHANGELOG.md if needed

4. **Commit Messages**
   Use [Conventional Commits](https://www.conventionalcommits.org/):
   - `feat:` New feature
   - `fix:` Bug fix
   - `docs:` Documentation
   - `test:` Tests
   - `refactor:` Code refactoring
   - `perf:` Performance improvement
   - `chore:` Maintenance

---

### PR Template

```markdown
## Description
Brief description of changes.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] All tests pass
- [ ] Added tests for new code
- [ ] Manual testing completed

## Checklist
- [ ] Code follows WordPress standards
- [ ] Self-review completed
- [ ] Comments added for complex logic
- [ ] Documentation updated
- [ ] No breaking changes (or documented)
```

---

### Review Process

1. **Automated Checks**
   - GitHub Actions run tests
   - PHPStan static analysis
   - PHPCS code style check

2. **Code Review**
   - At least 1 approval required
   - Address reviewer comments
   - Re-request review after changes

3. **Merge**
   - Squash commits for clean history
   - Delete branch after merge

---

## Development Tips

### Local WordPress Setup

```bash
# Using wp-env (recommended)
npm install -g @wordpress/env
wp-env start

# Or using Local by Flywheel
# Download from: https://localwp.com
```

---

### Debugging

```php
// Enable debug logging
define( 'FPML_DEBUG', true );

// In code
if ( defined( 'FPML_DEBUG' ) && FPML_DEBUG ) {
    error_log( 'FPML: ' . print_r( $data, true ) );
}
```

---

### Performance Profiling

```bash
# Profile reindex
php tools/profiler.php reindex 100

# Profile queue
php tools/profiler.php queue 20

# Profile providers
php tools/profiler.php providers
```

---

## Recognition

Contributors will be:
- ✅ Listed in CONTRIBUTORS.md
- ✅ Mentioned in release notes
- ✅ Thanked in plugin credits

---

## Questions?

- **Docs:** Read `docs/developer-guide.md`
- **Issues:** Open a discussion issue
- **Email:** contribute@francescopasseri.com

---

**Thank you for contributing! 🙏**

Together we make FP Multilanguage better for everyone.
