# QA Tools Guide

Complete guide to all QA tools and scripts available for FP Multilanguage plugin.

## Available Tools

### 1. Hook Validator (`tools/qa-hook-validator.php`)

Validates all WordPress hooks registered by the plugin.

**Checks:**
- Hook priorities
- Duplicate hook registrations
- Lifecycle correctness
- Dangerous hooks (inside loops)
- Context-specific conditions
- Remove action/filter support

**Usage:**
```bash
php tools/qa-hook-validator.php
```

**Output:**
- List of all hooks found
- Issues grouped by severity (high, medium, low)
- Hook summary with registration counts

### 2. Security Scanner (`tools/qa-security-scanner.php`)

Scans plugin code for common security vulnerabilities.

**Checks:**
- SQL injection vulnerabilities
- XSS vulnerabilities
- Missing nonce validation
- Missing capability checks
- Unsafe output escaping

**Usage:**
```bash
php tools/qa-security-scanner.php
```

**Output:**
- Security issues grouped by severity
- File and line number for each issue
- Issue type and description

### 3. Compatibility Checker (`tools/qa-compatibility-checker.php`)

Checks plugin compatibility with environment.

**Checks:**
- PHP version (requires 8.0+)
- WordPress version (requires 5.8+)
- Required PHP extensions
- Optional plugins (WooCommerce, Elementor, WPBakery)
- Theme compatibility

**Usage:**
```bash
php tools/qa-compatibility-checker.php
```

**Output:**
- Compatibility status for each check
- Summary with pass/fail/warning counts
- Exit code 1 if critical checks fail

### 4. Performance Profiler (`tools/qa-performance-profiler.php`)

Profiles plugin performance metrics.

**Measures:**
- Plugin initialization time
- Memory usage
- Database query count
- Asset sizes (CSS/JS)
- Peak memory usage

**Usage:**
```bash
php tools/qa-performance-profiler.php
```

**Output:**
- Performance metrics
- Memory usage in human-readable format
- Query statistics
- Asset size breakdown

## Running All Tools

### Local Development
```bash
# Run all QA tools
php tools/qa-hook-validator.php
php tools/qa-security-scanner.php
php tools/qa-compatibility-checker.php
php tools/qa-performance-profiler.php
```

### CI/CD Pipeline
All tools are automatically run in GitHub Actions on:
- Push to main/develop branches
- Pull requests

## Integration with CI/CD

The GitHub Actions workflow (`.github/workflows/qa-tests.yml`) includes:
- Hook validation job
- Security scan job
- Compatibility check job
- Performance profiling (can be added)

## Best Practices

1. **Run before commits**: Use hook validator and security scanner before committing code
2. **Pre-release**: Run all tools before creating a release
3. **CI/CD**: Let automated pipeline catch issues
4. **Regular checks**: Schedule periodic security scans

## Troubleshooting

### Hook Validator Issues
- Ensure WordPress is loaded (for standalone execution)
- Check file paths are correct
- Verify regex patterns match your code style

### Security Scanner False Positives
- Some patterns may trigger false positives
- Review flagged code manually
- Update scanner patterns if needed

### Compatibility Checker
- Requires WordPress to be loaded
- May need database connection for plugin checks
- Theme detection relies on active theme

### Performance Profiler
- Enable SAVEQUERIES for query profiling
- Run in production-like environment for accurate results
- Compare metrics across versions

## Extending Tools

All tools are designed to be extensible:
- Add new check methods to scanner classes
- Update regex patterns for code analysis
- Add new compatibility checks
- Extend performance metrics

## Reporting Issues

When reporting issues with QA tools:
- Include tool name and version
- Provide sample code that triggers issue
- Include error messages or unexpected output
- Suggest improvements if applicable














