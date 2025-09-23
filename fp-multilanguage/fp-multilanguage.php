<?php
/**
 * Plugin Name: FP Multilanguage
 * Plugin URI: https://example.com/fp-multilanguage
 * Description: Gestione avanzata delle traduzioni multilingua per contenuti, stringhe dinamiche e SEO in WordPress.
 * Version: 1.0.0
 * Author: FP Team
 * Author URI: https://example.com
 * License: GPL2
 * Text Domain: fp-multilanguage
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('FP_MULTILANGUAGE_PATH')) {
    define('FP_MULTILANGUAGE_PATH', plugin_dir_path(__FILE__));
}

if (! defined('FP_MULTILANGUAGE_URL')) {
    define('FP_MULTILANGUAGE_URL', plugin_dir_url(__FILE__));
}

require_once __DIR__ . '/includes/Plugin.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/TranslationService.php';
require_once __DIR__ . '/includes/PostTranslationManager.php';
require_once __DIR__ . '/includes/DynamicStrings.php';
require_once __DIR__ . '/includes/SEO.php';

use FPMultilanguage\Plugin;

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);

Plugin::instance();
