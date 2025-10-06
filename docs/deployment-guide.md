# Deployment Guide - FP Multilanguage

## Table of Contents
- [Pre-Deployment](#pre-deployment)
- [Staging Deployment](#staging-deployment)
- [Production Deployment](#production-deployment)
- [Post-Deployment](#post-deployment)
- [Rollback Procedure](#rollback-procedure)
- [Monitoring](#monitoring)

---

## Pre-Deployment

### 1. Pre-flight Checklist

```bash
# ✅ Run all tests
composer install
vendor/bin/phpunit

# ✅ Check code quality
vendor/bin/phpcs fp-multilanguage/
vendor/bin/phpstan analyze fp-multilanguage/

# ✅ Verify version numbers match
grep "Version:" fp-multilanguage/fp-multilanguage.php
grep "version" package.json
grep "Stable tag:" fp-multilanguage/readme.txt
```

**All should show same version (e.g., 0.3.2)**

---

### 2. Build Production Package

```bash
# Clean build
rm -rf fp-multilanguage/vendor

# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Create ZIP
bash scripts/build-zip.sh

# Verify ZIP
unzip -l build/fp-multilanguage-0.3.2.zip | head -20
```

---

### 3. Backup Current Installation

```bash
# Backup database
wp db export backup-$(date +%Y%m%d-%H%M%S).sql

# Backup files
tar -czf plugin-backup-$(date +%Y%m%d-%H%M%S).tar.gz \
    wp-content/plugins/fp-multilanguage/

# Backup queue data
wp option get fpml_settings > settings-backup.json
wp db query "SELECT * FROM wp_fpml_queue" > queue-backup.sql
```

---

## Staging Deployment

### 1. Upload to Staging

```bash
# Via WP-CLI
wp plugin install /path/to/fp-multilanguage-0.3.2.zip --force

# Or via SFTP/SCP
scp build/fp-multilanguage-0.3.2.zip user@staging:/tmp/
ssh user@staging
cd /var/www/html
wp plugin install /tmp/fp-multilanguage-0.3.2.zip --force
```

---

### 2. Verify Staging Installation

```bash
# Check version
wp plugin list | grep fp-multilanguage

# Test health endpoint
curl https://staging.example.com/wp-json/fpml/v1/health | jq

# Run queue test
wp fpml queue run --batch=5

# Check logs
wp eval "print_r(FPML_Logger::instance()->get_logs(10));"
```

---

### 3. Staging Smoke Tests

**Manual tests:**
1. ✅ Login to admin
2. ✅ Visit Settings → FP Multilanguage
3. ✅ Test provider connection
4. ✅ Create test post (Italian)
5. ✅ Verify English copy created
6. ✅ Run queue: `wp fpml queue run`
7. ✅ Check translation quality
8. ✅ Visit English page: `/en/test-post/`
9. ✅ Test language switcher
10. ✅ Check diagnostics dashboard

**Automated tests:**
```bash
# Run full test suite on staging
ssh user@staging "cd /var/www/html/wp-content/plugins/fp-multilanguage && vendor/bin/phpunit"
```

---

## Production Deployment

### 1. Maintenance Mode (Optional)

```bash
# Enable maintenance mode
wp maintenance-mode activate

# Or create .maintenance file
cat > /var/www/html/.maintenance << 'EOF'
<?php $upgrading = time(); ?>
EOF
```

---

### 2. Deploy Plugin

```bash
# Via WP-CLI (recommended)
wp plugin update fp-multilanguage --version=0.3.2

# Or manual upload
wp plugin install /path/to/fp-multilanguage-0.3.2.zip --force

# Activate
wp plugin activate fp-multilanguage
```

---

### 3. Database Migration (if needed)

```bash
# Queue table will auto-migrate
# Verify schema version
wp eval "echo FPML_Queue::SCHEMA_VERSION;"

# Force upgrade if needed
wp eval "FPML_Queue::instance()->maybe_upgrade();"
```

---

### 4. Flush Caches

```bash
# WordPress object cache
wp cache flush

# Redis (if used)
wp redis clear

# Opcache
wp eval "opcache_reset();"

# CDN (if used)
# Cloudflare purge, etc.
```

---

### 5. Verify Deployment

```bash
# 1. Health check
curl https://example.com/wp-json/fpml/v1/health | jq

# 2. Plugin version
wp plugin list | grep fp-multilanguage

# 3. Queue status
wp fpml queue status

# 4. Test translation
wp eval "
\$post = get_post(1);
if (\$post) {
    do_action('save_post', \$post->ID, \$post, true);
    echo 'Job enqueued';
}
"

# 5. Process queue
wp fpml queue run --batch=5
```

---

### 6. Disable Maintenance Mode

```bash
wp maintenance-mode deactivate

# Or remove file
rm /var/www/html/.maintenance
```

---

## Post-Deployment

### 1. Monitor for 24 Hours

**Check every 4 hours:**
```bash
# Health status
curl https://example.com/wp-json/fpml/v1/health

# Queue status  
wp fpml queue status

# Error logs
wp eval "
\$errors = array_filter(
    FPML_Logger::instance()->get_logs(50),
    function(\$l) { return \$l['level'] === 'error'; }
);
echo count(\$errors) . ' errors in last 50 logs';
"

# PHP error log
tail -50 /var/log/php/error.log | grep fpml
```

---

### 2. Performance Baseline

```bash
# Homepage load time
curl -o /dev/null -s -w "%{time_total}\n" https://example.com/

# English page load time  
curl -o /dev/null -s -w "%{time_total}\n" https://example.com/en/

# Queue processing time
time wp fpml queue run --batch=20
```

---

### 3. Set Up Monitoring Alerts

**UptimeRobot:**
```
Monitor: https://example.com/wp-json/fpml/v1/health
Alert when: Response doesn't contain "ok"
Frequency: Every 5 minutes
```

**Slack webhook:**
```bash
wp option patch insert fpml_settings webhook_url \
    "https://hooks.slack.com/services/YOUR/WEBHOOK/URL"
```

---

## Rollback Procedure

### If Issues Occur

**Quick rollback:**
```bash
# 1. Deactivate new version
wp plugin deactivate fp-multilanguage

# 2. Install previous version
wp plugin install /backups/fp-multilanguage-0.3.1.zip --force

# 3. Activate
wp plugin activate fp-multilanguage

# 4. Restore settings (if needed)
wp option set fpml_settings "$(cat settings-backup.json)" --format=json

# 5. Verify
wp fpml queue status
```

---

### Database Rollback

**If queue data corrupted:**
```bash
# 1. Backup current state
wp db export corrupted-state-$(date +%Y%m%d).sql

# 2. Restore from backup
wp db import queue-backup.sql

# 3. Verify
wp db query "SELECT COUNT(*) FROM wp_fpml_queue;"
```

---

## Zero-Downtime Deployment

### Using Blue-Green Strategy

**Setup:**
```bash
# 1. Clone production to staging
wp @production db export | wp @staging db import

# 2. Deploy to staging
wp @staging plugin update fp-multilanguage --version=0.3.2

# 3. Test staging thoroughly
# ... run tests ...

# 4. Switch DNS/load balancer to staging
# Staging becomes production

# 5. Old production becomes new staging
```

---

### Using Canary Deployment

```php
// In wp-config.php
// Route 10% of users to new version
define( 'FPML_CANARY_PERCENTAGE', 10 );

// Load balancer or:
if ( ! defined( 'FPML_USE_CANARY' ) ) {
    $user_id = get_current_user_id();
    define( 'FPML_USE_CANARY', ( $user_id % 100 ) < FPML_CANARY_PERCENTAGE );
}

if ( FPML_USE_CANARY ) {
    // Load new version
    require_once '/path/to/new-version/fp-multilanguage.php';
} else {
    // Load old version
    require_once '/path/to/old-version/fp-multilanguage.php';
}
```

---

## Production Best Practices

### 1. Separate Cron for Queue

Don't rely on WP-Cron in production:

```bash
# Disable WP-Cron
# In wp-config.php
define( 'DISABLE_WP_CRON', true );

# Add to system crontab
*/5 * * * * cd /var/www/html && wp cron event run --due-now > /dev/null 2>&1

# Separate cron for queue (more reliable)
*/10 * * * * cd /var/www/html && wp fpml queue run --batch=30 >> /var/log/fpml-queue.log 2>&1
```

---

### 2. Database Connection Pooling

```php
// In wp-config.php
define( 'DB_HOST', '127.0.0.1:3306' ); // Use IP instead of localhost
define( 'WP_USE_EXT_MYSQL', true );
```

---

### 3. Production Logging

```php
// In wp-config.php
define( 'WP_DEBUG', false ); // Disable debug in production
define( 'WP_DEBUG_LOG', true ); // But keep logging
define( 'WP_DEBUG_DISPLAY', false );

// Rotate logs
define( 'WP_DEBUG_LOG', '/var/log/wordpress/debug.log' );

// Logrotate config
// /etc/logrotate.d/wordpress
/var/log/wordpress/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
```

---

### 4. CDN Configuration

**Cloudflare:**
```
Page Rules:
- example.com/en/* → Cache Everything
- example.com/wp-json/fpml/* → Bypass Cache
```

**Cache headers:**
```php
add_action( 'template_redirect', function() {
    if ( FPML_Language::instance()->get_current_language() === 'en' ) {
        // Cache English pages for 1 hour
        header( 'Cache-Control: public, max-age=3600' );
    }
});
```

---

## Multi-Server Deployment

### Load Balancer Setup

```nginx
# Nginx upstream
upstream wordpress {
    server web1.example.com:80;
    server web2.example.com:80;
    server web3.example.com:80;
}

server {
    location / {
        proxy_pass http://wordpress;
    }
    
    # Cache health endpoint at LB level
    location /wp-json/fpml/v1/health {
        proxy_cache health_cache;
        proxy_cache_valid 200 1m;
        proxy_pass http://wordpress;
    }
}
```

---

### Shared Storage for Queue

**Option 1: Shared Database**
- All servers point to same MySQL server
- Queue table is shared
- ✅ Simple
- ⚠️ Database becomes bottleneck

**Option 2: External Queue (Redis)**
```php
// Custom queue implementation using Redis
class FPML_Queue_Redis extends FPML_Queue {
    protected function store_job( $data ) {
        $redis = new Redis();
        $redis->connect( '127.0.0.1', 6379 );
        $redis->rPush( 'fpml:queue:pending', json_encode( $data ) );
    }
}
```

---

## Deployment Automation

### GitHub Actions

`.github/workflows/deploy.yml`:
```yaml
name: Deploy to Production

on:
  push:
    tags:
      - 'v*'

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader
      
      - name: Run tests
        run: |
          composer install --dev
          vendor/bin/phpunit
      
      - name: Build ZIP
        run: bash scripts/build-zip.sh
      
      - name: Deploy to production
        env:
          SSH_KEY: ${{ secrets.PRODUCTION_SSH_KEY }}
        run: |
          ssh user@production "wp plugin install /tmp/build.zip --force"
          ssh user@production "wp cache flush"
```

---

### GitLab CI/CD

`.gitlab-ci.yml`:
```yaml
stages:
  - test
  - build
  - deploy

test:
  stage: test
  script:
    - composer install
    - vendor/bin/phpunit
  only:
    - tags

build:
  stage: build
  script:
    - composer install --no-dev --optimize-autoloader
    - bash scripts/build-zip.sh
  artifacts:
    paths:
      - build/
  only:
    - tags

deploy_production:
  stage: deploy
  script:
    - scp build/*.zip $PROD_SERVER:/tmp/
    - ssh $PROD_SERVER "wp plugin install /tmp/*.zip --force"
    - ssh $PROD_SERVER "wp cache flush"
  only:
    - tags
  when: manual
```

---

## Environment-Specific Configuration

### Development

```php
// wp-config.php (development)
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SAVEQUERIES', true );

// Plugin specific
define( 'FPML_DEBUG', true );
define( 'FPML_BATCH_SIZE', 5 ); // Small batches for testing
```

---

### Staging

```php
// wp-config.php (staging)
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Use cheaper provider for testing
define( 'FPML_FORCE_PROVIDER', 'libretranslate' );

// Shorter retention for testing cleanup
define( 'FPML_QUEUE_RETENTION_DAYS', 2 );
```

---

### Production

```php
// wp-config.php (production)
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Disable WP-Cron
define( 'DISABLE_WP_CRON', true );

// Optimize batch size
define( 'FPML_BATCH_SIZE', 40 );

// Set retention
define( 'FPML_QUEUE_RETENTION_DAYS', 7 );
```

---

## Post-Deployment Verification

### Automated Checks

```bash
#!/bin/bash
# post-deploy-check.sh

echo "Running post-deployment checks..."

# 1. Health endpoint
HEALTH=$(curl -s https://example.com/wp-json/fpml/v1/health | jq -r '.status')
if [ "$HEALTH" != "ok" ]; then
    echo "❌ Health check failed: $HEALTH"
    exit 1
fi
echo "✅ Health check passed"

# 2. Queue status
PENDING=$(wp fpml queue status --format=json | jq '.pending')
echo "✅ Queue pending: $PENDING"

# 3. Plugin version
VERSION=$(wp plugin get fp-multilanguage --field=version)
if [ "$VERSION" != "0.3.2" ]; then
    echo "❌ Wrong version: $VERSION"
    exit 1
fi
echo "✅ Version correct: $VERSION"

# 4. Test translation
wp fpml queue run --batch=1 > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Queue processing works"
else
    echo "❌ Queue processing failed"
    exit 1
fi

echo "✅ All post-deployment checks passed!"
```

---

## Monitoring

### 1. Set Up Uptime Monitoring

**UptimeRobot:**
```
Monitor Name: FP Multilanguage Health
Monitor Type: HTTP(s)
URL: https://example.com/wp-json/fpml/v1/health
Monitoring Interval: 5 minutes
Monitor Timeout: 30 seconds

Alert When: Response doesn't contain "ok"
Alert Contacts: Email, Slack, SMS
```

---

### 2. Set Up Error Alerts

**Slack webhook:**
```bash
wp option patch insert fpml_settings webhook_url \
    "https://hooks.slack.com/services/T00/B00/XXX"
```

**Email alerts:**
```php
add_action( 'fpml_queue_batch_complete', function( $summary ) {
    $errors = isset( $summary['errors'] ) ? (int) $summary['errors'] : 0;
    
    if ( $errors > 5 ) {
        wp_mail(
            'admin@example.com',
            'FP Multilanguage: High Error Rate',
            "Batch completed with $errors errors. Check logs."
        );
    }
});
```

---

### 3. Performance Monitoring

**Track key metrics:**
```php
// In functions.php
add_action( 'fpml_queue_batch_complete', function( $summary ) {
    // Send to analytics
    if ( function_exists( 'newrelic_custom_metric' ) ) {
        newrelic_custom_metric( 'Custom/FPML/Batch/Processed', $summary['processed'] );
        newrelic_custom_metric( 'Custom/FPML/Batch/Errors', $summary['errors'] );
    }
    
    // Or to custom endpoint
    wp_remote_post( 'https://metrics.example.com/fpml', array(
        'body' => json_encode( $summary ),
    ));
});
```

---

## Common Issues

### Issue 1: Queue Not Processing After Deploy

**Diagnose:**
```bash
wp fpml queue status
wp option get fpml_queue_lock
```

**Fix:**
```bash
wp option delete fpml_queue_lock
wp cron event run fpml_run_queue
```

---

### Issue 2: High Error Rate After Deploy

**Diagnose:**
```bash
wp eval "
\$errors = FPML_Logger::instance()->get_logs_by_event('api.error', 10);
foreach (\$errors as \$e) {
    echo \$e['message'] . \"\n\";
}
"
```

**Fix:**
```bash
# Reset rate limits
php tools/maintenance.php reset-rate-limits

# Test provider
wp eval "
\$processor = FPML_Processor::instance();
\$translator = \$processor->get_translator_instance();
if (is_wp_error(\$translator)) {
    echo 'Error: ' . \$translator->get_error_message();
}
"
```

---

### Issue 3: Increased Memory Usage

**Diagnose:**
```bash
# Check batch size
wp eval "echo 'Batch size: ' . apply_filters('fpml_batch_size', 20);"

# Monitor memory
wp eval "
ini_set('memory_limit', '512M');
echo 'Before: ' . round(memory_get_usage(true)/1024/1024, 2) . 'MB\n';
FPML_Processor::instance()->run_queue();
echo 'After: ' . round(memory_get_usage(true)/1024/1024, 2) . 'MB\n';
"
```

**Fix:**
```php
// Reduce batch size
define( 'FPML_BATCH_SIZE', 15 );

// Increase PHP memory
ini_set( 'memory_limit', '768M' );
```

---

## Documentation

### Update After Deploy

1. ✅ Update README.md with new version
2. ✅ Update CHANGELOG.md with release date
3. ✅ Tag release in git: `git tag v0.3.2`
4. ✅ Push tags: `git push --tags`
5. ✅ Create GitHub release with notes
6. ✅ Update WordPress.org readme (if published)

---

## Security

### Production Security Checklist

```bash
# 1. File permissions
chmod 644 fp-multilanguage/*.php
chmod 755 fp-multilanguage/
chmod 600 wp-config.php

# 2. Disable file editing
# In wp-config.php
define( 'DISALLOW_FILE_EDIT', true );

# 3. API keys in environment variables (not database)
define( 'FPML_OPENAI_KEY', getenv( 'OPENAI_API_KEY' ) );

# 4. HTTPS only
# In wp-config.php
define( 'FORCE_SSL_ADMIN', true );

# 5. Restrict REST API
add_filter( 'rest_authentication_errors', function( $result ) {
    if ( ! is_user_logged_in() ) {
        // Allow only health endpoint
        if ( strpos( $_SERVER['REQUEST_URI'], '/fpml/v1/health' ) !== false ) {
            return $result;
        }
        
        return new WP_Error( 'rest_forbidden', 'REST API disabled', array( 'status' => 403 ) );
    }
    
    return $result;
});
```

---

## Maintenance Schedule

### Daily
- ✅ Check health endpoint status
- ✅ Review error logs (>5 errors/day = investigate)

### Weekly  
- ✅ Queue cleanup: `wp fpml queue cleanup --days=7`
- ✅ Review cost analytics
- ✅ Check queue backlog

### Monthly
- ✅ Database optimization: `php tools/maintenance.php optimize-queue`
- ✅ Review and update glossary
- ✅ Analyze translation quality
- ✅ Update provider API keys if needed

### Quarterly
- ✅ Review and update plugin
- ✅ Full database vacuum
- ✅ Performance benchmarking
- ✅ Security audit

---

**Last updated:** 2025-10-05  
**Plugin version:** 0.3.2  
**Maintainer:** Francesco Passeri
