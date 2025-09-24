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
        $GLOBALS['wp_test_locale'] = 'en_US';

        global $wp_user_meta_storage;
        $wp_user_meta_storage = [];

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
        $GLOBALS['wp_test_locale'] = 'en_US';

        \FPMultilanguage\CurrentLanguage::clear_cache();

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

    public function test_remember_normalizes_language_code(): void
    {
        CurrentLanguage::remember('PT_br');

        $this->assertSame('pt-br', $_COOKIE['fp_multilanguage_lang'] ?? null);
        $this->assertSame('pt-br', CurrentLanguage::resolve());
        $this->assertSame('pt-br', get_user_meta(1, 'fp_multilanguage_language', true));
    }

    public function test_resolve_detects_regional_locale(): void
    {
        $GLOBALS['wp_test_locale'] = 'pt_BR';

        update_option(Settings::OPTION_NAME, [
            'source_language' => 'en',
            'fallback_language' => 'en',
            'target_languages' => ['pt-br'],
            'providers' => [],
            'seo' => [],
            'quote_tracking' => [],
            'auto_translate' => false,
        ]);

        Settings::clear_cache();
        $this->resetCurrentLanguageCache();

        $this->assertSame('pt-br', CurrentLanguage::resolve());
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
        CurrentLanguage::clear_cache();
    }
}
