# FP Multilanguage

[![Version](https://img.shields.io/badge/version-0.4.1-blue.svg)](https://github.com/francescopasseri/FP-Multilanguage)
[![WordPress](https://img.shields.io/badge/wordpress-5.8+-green.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/php-8.0+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Enterprise-grade WordPress multilingual plugin that automates Italian-to-English content translation with queue-based processing and support for OpenAI, DeepL, Google Cloud Translation, and LibreTranslate.

## 🚀 Features

### Core Translation
- **Queue-driven incremental translation** - Only processes modified content fragments
- **Multiple provider support** - OpenAI, DeepL, Google Cloud Translation, LibreTranslate
- **Smart content handling** - Preserves Gutenberg blocks, ACF fields, and shortcodes
- **Automatic duplication** - Posts, pages, CPTs, taxonomies, menus, and media metadata

### Advanced Capabilities
- **SEO optimization** - Automatic hreflang, canonical URLs, dedicated English sitemaps
- **WooCommerce support** - Product translations, attributes, and metadata
- **Frontend routing** - `/en/` URL structure or query-string based switching
- **Browser detection** - Automatic language redirect based on user preferences
- **Multisite ready** - Full support for WordPress Multisite networks

### Performance & Reliability
- **Batch processing** - Memory-optimized handling of large datasets
- **Cache stampede protection** - Lock patterns prevent concurrent regeneration
- **Race condition safe** - Atomic operations for thread safety
- **Memory leak free** - Explicit cleanup and resource management
- **Large dataset optimized** - Handles millions of records efficiently

## 📋 Requirements

- **WordPress**: 5.8 or later
- **PHP**: 8.0 or later (8.2+ recommended)
- **API Credentials**: At least one translation provider (OpenAI, DeepL, Google, or LibreTranslate)

## 💾 Installation

1. Upload the `fp-multilanguage` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Configure providers and routing in **Settings → FP Multilanguage**
4. Run initial sync from Diagnostics tab or via `wp fpml queue run`

## 🔧 Usage

### Basic Usage
1. Create or update Italian content
2. Plugin automatically enqueues translation jobs
3. Queue processes jobs incrementally
4. English content stays synchronized

### WP-CLI Commands
```bash
# Run queue processing
wp fpml queue run

# Estimate translation costs
wp fpml queue estimate-cost

# Cleanup old jobs
wp fpml queue cleanup --days=7

# View queue status
wp fpml queue status

# Run cron events
wp cron event run --due-now
```

### Configuration
Configure the plugin through **Settings → FP Multilanguage**:
- Translation providers and API keys
- Queue processing settings (batch size, character limits)
- Cleanup and retention policies
- Routing and SEO options
- Content type selections

## 🔌 Hooks & Filters

### Actions
```php
// After post jobs are enqueued
do_action( 'fpml_post_jobs_enqueued', $post_id, $jobs );

// After cleanup completes
do_action( 'fpml_queue_after_cleanup', $total_deleted, $states, $days );

// When content is translated
do_action( 'fpml_post_translated', $post_id, $translations );
do_action( 'fpml_term_translated', $term_id, $taxonomy, $translations );
do_action( 'fpml_menu_item_translated', $menu_item_id, $field, $translation );
```

### Filters
```php
// Customize translatable content types
add_filter( 'fpml_translatable_post_types', function( $types ) {
    $types[] = 'my_custom_post_type';
    return $types;
});

add_filter( 'fpml_translatable_taxonomies', function( $taxonomies ) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});

// Customize cleanup behavior
add_filter( 'fpml_queue_cleanup_states', function( $states ) {
    return array( 'done', 'error', 'skipped' );
});

add_filter( 'fpml_queue_cleanup_batch_size', function( $size ) {
    return 1000; // Batch size for cleanup
});
```

## 📊 Diagnostics & Monitoring

The Diagnostics dashboard provides:
- Queue size and job age statistics
- Provider connectivity tests
- Cost estimation tools
- Recent activity logs
- Performance metrics

Access via **Settings → FP Multilanguage → Diagnostics**

## 🔒 Security

Version 0.4.1 includes major security improvements:
- ✅ Fixed 11 critical security vulnerabilities
- ✅ Race condition protection
- ✅ REST API authentication
- ✅ AJAX nonce verification
- ✅ Object injection prevention
- ✅ SQL injection protection
- ✅ XSS prevention

## 🎯 Version 0.4.1 Highlights

### Critical Fixes (11)
- Race condition in translation creation
- Race condition in lock mechanism
- Multisite uninstall data cleanup
- Orphan reference cleanup
- REST API authentication
- Unsafe serialization
- Service registration errors

### Bug Fixes (25)
- Memory leak in batch processing
- Cache stampede in sitemap
- PCRE error handling
- JSON encoding/decoding
- Large dataset cleanup
- Hierarchical content mapping
- Cron event cleanup

See [CHANGELOG.md](CHANGELOG.md) for complete details.

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📝 License

GPL-2.0-or-later - See [LICENSE](LICENSE) for details

## 👨‍💻 Author

**Francesco Passeri**
- Website: [francescopasseri.com](https://francescopasseri.com)
- GitHub: [@francescopasseri](https://github.com/francescopasseri)

## 🆘 Support

- **GitHub Issues**: [Open an issue](https://github.com/francescopasseri/FP-Multilanguage/issues)
- **Commercial Support**: Contact via [francescopasseri.com](https://francescopasseri.com)

## 🙏 Acknowledgments

Built with WordPress best practices and tested with:
- PHP 8.0, 8.1, 8.2
- WordPress 5.8 - 6.5
- Multisite configurations
- High-traffic environments

---

Made with ❤️ by Francesco Passeri
