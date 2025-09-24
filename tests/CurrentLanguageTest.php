<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CurrentLanguageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_actions, $wp_test_textdomains;
        $wp_test_options = [];
        $wp_test_actions = [];
        $wp_test_textdomains = [];

        $_GET = [];
        $_COOKIE = [];
        $_SERVER['REQUEST_URI'] = '/';

        Settings::bootstrap_defaults();

        $this->resetPluginSingleton();
        $this->resetCurrentLanguageCache();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_COOKIE = [];
        unset($_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    public function test_remembers_language_on_template_redirect(): void
    {
        $_GET['fp_lang'] = 'it';

        $plugin = Plugin::instance();
        $plugin->init();

        do_action('template_redirect');

        $this->assertSame('it', $_COOKIE['fp_multilanguage_lang'] ?? null, 'La lingua selezionata deve essere memorizzata in un cookie.');
        $this->assertSame('it', CurrentLanguage::resolve(), 'La lingua corrente deve corrispondere a quella memorizzata.');
    }

    private function resetPluginSingleton(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    private function resetCurrentLanguageCache(): void
    {
        $reflection = new ReflectionClass(CurrentLanguage::class);
        $property = $reflection->getProperty('cached');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
