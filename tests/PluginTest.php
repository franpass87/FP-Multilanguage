<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_actions, $wp_test_textdomains;
        $wp_test_actions = [];
        $wp_test_textdomains = [];

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

        Plugin::instance();

        do_action('init');

        global $wp_test_textdomains;

        $this->assertNotEmpty($wp_test_textdomains, 'Il dominio di testo deve essere caricato.');
        $this->assertSame('fp-multilanguage', $wp_test_textdomains[0]['domain']);
        $this->assertSame('fp-multilanguage/languages', $wp_test_textdomains[0]['plugin_rel_path']);
    }
}
