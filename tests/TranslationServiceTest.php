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
        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_test_filters, $wp_remote_post_failures, $wp_test_actions, $wp_test_textdomains;
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_test_filters = [];
        $wp_remote_post_failures = [];
        $wp_test_actions = [];
        $wp_test_textdomains = [];

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

    public function test_retries_provider_after_transient_failure(): void
    {
        global $wp_remote_post_failures, $wp_remote_post_calls;

        $wp_remote_post_failures['translation.googleapis.com'] = 1;

        update_option(Settings::OPTION_NAME, [
            'providers' => ['google'],
            'google_api_key' => 'test-google-key',
            'deepl_api_key' => '',
            'target_languages' => ['it'],
            'source_language' => 'en',
            'fallback_language' => 'en',
            'auto_translate' => true,
        ]);

        $service = new TranslationService();

        $first = $service->translate('Temporary issue', 'en', 'it');
        $second = $service->translate('Temporary issue', 'en', 'it');

        $this->assertSame('Temporary issue', $first, 'La prima traduzione deve usare il fallback originale dopo un errore.');
        $this->assertSame('google:Temporary issue', $second, 'Il secondo tentativo deve raggiungere nuovamente il provider.');
        $this->assertSame(2, $wp_remote_post_calls['translation.googleapis.com'] ?? 0, 'Il provider deve essere chiamato due volte dopo un errore temporaneo.');
    }

    public function test_uses_strlen_when_mb_string_extension_missing(): void
    {
        $service = new class () extends TranslationService {
            protected function is_mb_string_available(): bool
            {
                return false;
            }
        };

        $text = '😄';
        $result = $service->translate($text, 'en', 'it');

        $this->assertSame('google:' . $text, $result);

        $quotaKey = 'fp_multilanguage_quota_google';
        $quota = get_transient($quotaKey);

        $this->assertIsArray($quota, 'La quota deve essere salvata come array.');
        $this->assertSame(1, $quota['requests'] ?? 0, 'Il numero di richieste deve aumentare.');
        $this->assertSame(strlen($text), $quota['characters'] ?? 0, 'La lunghezza deve usare strlen come fallback.');
    }

    public function test_preserves_html_tags_when_format_is_html(): void
    {
        $service = new TranslationService();
        $html = '<p>Un <strong>test</strong> semplice</p>';

        $googleTranslation = $service->translate($html, 'en', 'it', ['format' => 'html']);

        $this->assertSame('google:' . $html, $googleTranslation, 'Google deve mantenere i tag HTML quando richiesto.');

        update_option(Settings::OPTION_NAME, [
            'providers' => ['deepl'],
            'google_api_key' => '',
            'deepl_api_key' => 'test-deepl-key',
            'target_languages' => ['it'],
            'source_language' => 'en',
            'fallback_language' => 'en',
            'auto_translate' => true,
        ]);

        TranslationService::flush_cache();

        $service = new TranslationService();
        $deeplHtml = '<div>Altro <em>contenuto</em> di prova</div>';

        $deeplTranslation = $service->translate($deeplHtml, 'en', 'it', ['format' => 'html']);

        $this->assertSame('deepl:' . $deeplHtml, $deeplTranslation, 'DeepL deve mantenere i tag HTML quando richiesto.');
    }
}
