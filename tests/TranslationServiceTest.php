<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Services\TranslationService;
use PHPUnit\Framework\TestCase;

class TranslationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_test_filters;
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_test_filters = [];

        update_option(Settings::OPTION_NAME, [
            'providers' => ['google', 'deepl'],
            'google_api_key' => 'test-google-key',
            'deepl_api_key' => 'test-deepl-key',
            'target_languages' => ['it'],
            'source_language' => 'en',
            'fallback_language' => 'en',
            'auto_translate' => true,
        ]);
    }

    public function test_uses_cache_after_first_translation(): void
    {
        $service = new TranslationService();

        $first = $service->translate('Hello world', 'en', 'it');
        $second = $service->translate('Hello world', 'en', 'it');

        global $wp_remote_post_calls;
        $host = 'translation.googleapis.com';

        $this->assertSame('google:Hello world', $first);
        $this->assertSame($first, $second, 'Le traduzioni successive devono provenire dalla cache.');
        $this->assertSame(1, $wp_remote_post_calls[$host] ?? 0, 'La chiamata HTTP deve essere eseguita una sola volta.');
    }

    public function test_switches_to_deepl_when_google_not_configured(): void
    {
        update_option(Settings::OPTION_NAME, [
            'providers' => ['google', 'deepl'],
            'google_api_key' => '',
            'deepl_api_key' => 'deepl-key',
            'target_languages' => ['it'],
            'source_language' => 'en',
            'fallback_language' => 'en',
            'auto_translate' => true,
        ]);

        $service = new TranslationService();
        $result = $service->translate('Test string', 'en', 'it');

        $this->assertSame('deepl:Test string', $result);
    }

    public function test_manual_translation_is_returned_when_available(): void
    {
        $text = 'Custom string';
        $hash = hash('sha1', $text);
        Settings::update_manual_string($hash, 'it', 'Manuale personalizzato');

        update_option(Settings::OPTION_NAME, [
            'providers' => [],
            'google_api_key' => '',
            'deepl_api_key' => '',
            'target_languages' => ['it'],
            'source_language' => 'en',
            'fallback_language' => 'en',
            'auto_translate' => false,
        ]);

        $service = new TranslationService();
        $result = $service->translate($text, 'en', 'it');

        $this->assertSame('Manuale personalizzato', $result);
    }
}
