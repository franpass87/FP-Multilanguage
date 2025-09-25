<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Install\Migrator;
use FPMultilanguage\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_actions, $wp_test_textdomains, $wp_test_options, $wp_test_cache, $wp_test_transients;
        $wp_test_actions = [];
        $wp_test_textdomains = [];
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];

        $resetInstance = \Closure::bind(
            static function (): void {
                self::$instance = null;
            },
            null,
            Plugin::class
        );
        $resetInstance();
    }

    public function test_loads_textdomain_when_wordpress_functions_available(): void
    {
        if (! defined('FP_MULTILANGUAGE_PATH')) {
            define('FP_MULTILANGUAGE_PATH', dirname(__DIR__) . '/fp-multilanguage/');
        }

        Plugin::instance()->init();

        do_action('init');

        global $wp_test_textdomains;

        $this->assertNotEmpty($wp_test_textdomains, 'Il dominio di testo deve essere caricato.');
        $this->assertSame('fp-multilanguage', $wp_test_textdomains[0]['domain']);
        $this->assertSame('fp-multilanguage/languages', $wp_test_textdomains[0]['plugin_rel_path']);
    }

    public function test_bootstrap_updates_version_and_flushes_cache_on_upgrade(): void
    {
        if (! defined('FP_MULTILANGUAGE_PATH')) {
            define('FP_MULTILANGUAGE_PATH', dirname(__DIR__) . '/fp-multilanguage/');
        }

        if (! defined('FP_MULTILANGUAGE_VERSION')) {
            define('FP_MULTILANGUAGE_VERSION', '1.0.0');
        }

        update_option('fp_multilanguage_version', '0.9.0');
        update_option('fp_multilanguage_cache_version', 1);

        Plugin::instance()->init();
        do_action('plugins_loaded');

        $this->assertSame(
            FP_MULTILANGUAGE_VERSION,
            get_option('fp_multilanguage_version'),
            'L\'upgrade deve aggiornare la versione memorizzata.'
        );

        $this->assertSame(
            2,
            get_option('fp_multilanguage_cache_version'),
            'Il flush della cache deve incrementare la versione interna.'
        );
    }

    public function test_bootstrap_skips_upgrade_when_version_matches(): void
    {
        if (! defined('FP_MULTILANGUAGE_PATH')) {
            define('FP_MULTILANGUAGE_PATH', dirname(__DIR__) . '/fp-multilanguage/');
        }

        if (! defined('FP_MULTILANGUAGE_VERSION')) {
            define('FP_MULTILANGUAGE_VERSION', '1.0.0');
        }

        update_option('fp_multilanguage_version', FP_MULTILANGUAGE_VERSION);
        update_option('fp_multilanguage_cache_version', 5);

        Plugin::instance()->init();
        do_action('plugins_loaded');

        $this->assertSame(
            5,
            get_option('fp_multilanguage_cache_version'),
            'Non devono esserci modifiche alla cache quando la versione coincide.'
        );
    }

    public function test_uninstall_cleans_options_and_drops_tables(): void
    {
        if (! defined('FP_MULTILANGUAGE_PATH')) {
            define('FP_MULTILANGUAGE_PATH', dirname(__DIR__) . '/fp-multilanguage/');
        }

        if (! defined('FP_MULTILANGUAGE_VERSION')) {
            define('FP_MULTILANGUAGE_VERSION', '1.0.0');
        }

        Plugin::instance()->init();

        $plugin     = Plugin::instance();
        $container  = $plugin->get_container();
        $dropper    = new class() extends Migrator {
            public int $dropCalls = 0;

            public function drop_tables(): void
            {
                $this->dropCalls++;
            }
        };

        $container->set('migrator', $dropper);

        update_option(Settings::OPTION_NAME, ['foo' => 'bar']);
        update_option(Settings::MANUAL_STRINGS_OPTION, ['manual' => []]);
        update_option('fp_multilanguage_quota', ['google' => []]);
        update_option('fp_multilanguage_version', '0.9.0');
        update_option('fp_multilanguage_slug_index', ['slug' => ['post_id' => 123]]);

        Plugin::uninstall();

        $this->assertSame('missing', get_option(Settings::OPTION_NAME, 'missing'));
        $this->assertSame('missing', get_option(Settings::MANUAL_STRINGS_OPTION, 'missing'));
        $this->assertSame('missing', get_option('fp_multilanguage_quota', 'missing'));
        $this->assertSame('missing', get_option('fp_multilanguage_version', 'missing'));
        $this->assertSame('missing', get_option('fp_multilanguage_slug_index', 'missing'));
        $this->assertSame(1, $dropper->dropCalls, 'La tabella personalizzata deve essere eliminata.');
    }
}
