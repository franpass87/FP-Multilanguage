<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\Providers\DeepLProvider;
use FPMultilanguage\Services\Providers\GoogleProvider;
use FPMultilanguage\Services\TranslationService;
use FPMultilanguage\Tests\Helpers\SettingsFactory;
use PHPUnit\Framework\TestCase;

class TranslationServiceTest extends TestCase
{
    private TranslationService $service;

    private Logger $logger;

    private AdminNotices $notices;

    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();
        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_test_filters, $wp_remote_post_failures, $wp_remote_post_invalid_json, $wp_test_actions, $wp_test_textdomains, $wp_remote_post_requests;
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_test_filters = [];
        $wp_remote_post_failures = [];
        $wp_remote_post_invalid_json = [];
        $wp_test_actions = [];
        $wp_test_textdomains = [];
        $wp_remote_post_requests = [];

        update_option(Settings::OPTION_NAME, [
            'source_language' => 'en',
            'fallback_language' => 'en',
            'target_languages' => ['it'],
            'auto_translate' => true,
            'providers' => [
                'google' => [
                    'enabled' => true,
                    'api_key' => 'test-google-key',
                    'timeout' => 20,
                ],
                'deepl' => [
                    'enabled' => true,
                    'api_key' => 'test-deepl-key',
                    'endpoint' => 'https://api.deepl.com/v2/translate',
                ],
            ],
            'seo' => [
                'hreflang' => true,
                'canonical' => true,
                'open_graph' => true,
            ],
            'quote_tracking' => [],
        ]);
        Settings::clear_cache();

        $this->logger = new Logger();
        $this->notices = new AdminNotices($this->logger);
        $this->settings = SettingsFactory::create($this->logger, $this->notices);
        $this->assertContains('google', Settings::get_enabled_providers(), 'Il provider Google deve essere abilitato per i test.');
        $this->service = new TranslationService($this->logger, $this->notices, $this->settings, [
            new GoogleProvider($this->logger),
            new DeepLProvider($this->logger),
        ]);
    }

    public function test_uses_cache_after_first_translation(): void
    {
        $first = $this->service->translate_text('Hello world', 'en', 'it');
        $second = $this->service->translate_text('Hello world', 'en', 'it');

        global $wp_remote_post_calls;
        $host = 'translation.googleapis.com';

        $this->assertSame('google:Hello world', $first);
        $this->assertSame($first, $second, 'Le traduzioni successive devono provenire dalla cache.');
        $this->assertSame(1, $wp_remote_post_calls[$host] ?? 0, 'La chiamata HTTP deve essere eseguita una sola volta.');
    }

    public function test_switches_to_deepl_when_google_not_configured(): void
    {
        update_option(Settings::OPTION_NAME, [
            'source_language' => 'en',
            'fallback_language' => 'en',
            'target_languages' => ['it'],
            'auto_translate' => true,
            'providers' => [
                'google' => [
                    'enabled' => false,
                    'api_key' => '',
                    'timeout' => 20,
                ],
                'deepl' => [
                    'enabled' => true,
                    'api_key' => 'deepl-key',
                    'endpoint' => 'https://api.deepl.com/v2/translate',
                ],
            ],
            'seo' => [],
            'quote_tracking' => [],
        ]);

        TranslationService::flush_cache();
        Settings::clear_cache();
        $result = $this->service->translate_text('Test string', 'en', 'it');

        $this->assertSame('deepl:Test string', $result);
    }

    public function test_uses_fallback_provider_when_json_response_is_invalid(): void
    {
        global $wp_remote_post_invalid_json;
        $wp_remote_post_invalid_json['translation.googleapis.com'] = 3;

        TranslationService::flush_cache();
        Settings::clear_cache();
        $result = $this->service->translate_text('Trigger invalid JSON', 'en', 'it');

        $this->assertSame('deepl:Trigger invalid JSON', $result, 'Il servizio deve passare al provider successivo quando la risposta JSON è non valida.');
    }

    public function test_manual_translation_is_returned_when_available(): void
    {
        $text = 'Custom string';
        $hash = hash('sha1', $text);
        Settings::update_manual_string($hash, 'it', 'Manuale personalizzato');

        update_option(Settings::OPTION_NAME, [
            'source_language' => 'en',
            'fallback_language' => 'en',
            'target_languages' => ['it'],
            'auto_translate' => false,
            'providers' => [
                'google' => [
                    'enabled' => false,
                    'api_key' => '',
                    'timeout' => 20,
                ],
                'deepl' => [
                    'enabled' => false,
                    'api_key' => '',
                    'endpoint' => 'https://api.deepl.com/v2/translate',
                ],
            ],
            'seo' => [],
            'quote_tracking' => [],
        ]);

        TranslationService::flush_cache();
        Settings::clear_cache();
        $result = $this->service->translate_text($text, 'en', 'it');

        $this->assertSame('Manuale personalizzato', $result);
    }

    public function test_retries_provider_after_transient_failure(): void
    {
        global $wp_remote_post_failures, $wp_remote_post_calls;

        $wp_remote_post_failures['translation.googleapis.com'] = 1;

        $first = $this->service->translate_text('Temporary issue', 'en', 'it');
        $second = $this->service->translate_text('Temporary issue', 'en', 'it');

        $this->assertSame('google:Temporary issue', $first, 'Il servizio deve riprovare il provider e restituire il risultato.');
        $this->assertSame('google:Temporary issue', $second, 'Il secondo tentativo deve provenire dalla cache.');
        $this->assertSame(2, $wp_remote_post_calls['translation.googleapis.com'] ?? 0, 'Il provider deve essere chiamato due volte dopo un errore temporaneo.');
    }

    public function test_google_payload_includes_glossary_config(): void
    {
        update_option(Settings::OPTION_NAME, [
            'source_language' => 'en',
            'fallback_language' => 'en',
            'target_languages' => ['it'],
            'auto_translate' => true,
            'providers' => [
                'google' => [
                    'enabled' => true,
                    'api_key' => 'with-glossary',
                    'glossary_id' => 'projects/demo/locations/global/glossaries/catalog',
                    'glossary_ignore_case' => true,
                ],
                'deepl' => [
                    'enabled' => false,
                    'api_key' => '',
                    'endpoint' => 'https://api.deepl.com/v2/translate',
                ],
            ],
            'seo' => [],
            'quote_tracking' => [],
        ]);

        TranslationService::flush_cache();
        Settings::clear_cache();
        $result = $this->service->translate_text('Glossary content', 'en', 'it');

        $this->assertSame('google:Glossary content', $result);

        global $wp_remote_post_requests;
        $request = $wp_remote_post_requests['translation.googleapis.com'][0] ?? [];
        $decoded = $request['decoded_body'] ?? [];

        $this->assertArrayHasKey('glossaryConfig', $decoded, 'La richiesta deve includere la configurazione del glossario.');
        $this->assertSame('projects/demo/locations/global/glossaries/catalog', $decoded['glossaryConfig']['glossary'] ?? '');
        $this->assertTrue($decoded['glossaryConfig']['ignoreCase'] ?? false);
    }

    public function test_uses_strlen_when_mb_string_extension_missing(): void
    {
        $service = new class ($this->logger, $this->notices, $this->settings) extends TranslationService {
            public function __construct(Logger $logger, AdminNotices $notices, Settings $settings)
            {
                parent::__construct($logger, $notices, $settings, [new GoogleProvider($logger)]);
            }

            protected function get_text_length(string $text): int
            {
                return strlen($text);
            }
        };

        $text = '😄';
        $result = $service->translate_text($text, 'en', 'it');

        $this->assertSame('google:' . $text, $result);

        $quotaKey = 'fp_multilanguage_quota';
        $quota = get_option($quotaKey);

        $this->assertIsArray($quota, 'La quota deve essere salvata come array.');
        $this->assertSame(1, $quota['google']['it']['requests'] ?? 0, 'Il numero di richieste deve aumentare.');
        $this->assertSame(strlen($text), $quota['google']['it']['characters'] ?? 0, 'La lunghezza deve usare strlen come fallback.');
    }

    public function test_deepl_payload_includes_glossary_and_formality(): void
    {
        update_option(Settings::OPTION_NAME, [
            'source_language' => 'en',
            'fallback_language' => 'en',
            'target_languages' => ['it'],
            'auto_translate' => true,
            'providers' => [
                'google' => [
                    'enabled' => false,
                    'api_key' => '',
                ],
                'deepl' => [
                    'enabled' => true,
                    'api_key' => 'deepl-glossary',
                    'endpoint' => 'https://api.deepl.com/v2/translate',
                    'glossary_id' => '1234-5678',
                    'formality' => 'more',
                ],
            ],
            'seo' => [],
            'quote_tracking' => [],
        ]);

        TranslationService::flush_cache();
        Settings::clear_cache();
        $result = $this->service->translate_text('DeepL request', 'en', 'it');

        $this->assertSame('deepl:DeepL request', $result);

        global $wp_remote_post_requests;
        $request = $wp_remote_post_requests['api.deepl.com'][0] ?? [];
        $decoded = $request['decoded_body'] ?? [];

        $this->assertSame('1234-5678', $decoded['glossary_id'] ?? '', 'La richiesta deve includere l\'ID del glossario.');
        $this->assertSame('more', $decoded['formality'] ?? '');
    }

    public function test_preserves_html_tags_when_format_is_html(): void
    {
        $googleHtml = '<p>Un <strong>test</strong> semplice</p>';
        $googleTranslation = $this->service->translate_text($googleHtml, 'en', 'it', ['format' => 'html']);

        $this->assertSame('google:' . $googleHtml, $googleTranslation, 'Google deve mantenere i tag HTML quando richiesto.');

        update_option(Settings::OPTION_NAME, [
            'source_language' => 'en',
            'fallback_language' => 'en',
            'target_languages' => ['it'],
            'auto_translate' => true,
            'providers' => [
                'google' => [
                    'enabled' => false,
                    'api_key' => '',
                    'timeout' => 20,
                ],
                'deepl' => [
                    'enabled' => true,
                    'api_key' => 'test-deepl-key',
                    'endpoint' => 'https://api.deepl.com/v2/translate',
                ],
            ],
            'seo' => [],
            'quote_tracking' => [],
        ]);

        TranslationService::flush_cache();
        Settings::clear_cache();

        $deeplHtml = '<div>Altro <em>contenuto</em> di prova</div>';
        $deeplTranslation = $this->service->translate_text($deeplHtml, 'en', 'it', ['format' => 'html']);

        $this->assertSame('deepl:' . $deeplHtml, $deeplTranslation, 'DeepL deve mantenere i tag HTML quando richiesto.');
	}

	public function test_stale_quota_entries_do_not_trigger_rate_limit(): void
	{
		update_option('fp_multilanguage_quota', [
			'google' => [
				'it' => [
					'requests' => 5000,
					'characters' => 999999,
					'updated_at' => time() - (86400 * 3),
				],
			],
		]);

		TranslationService::flush_cache();

		$result = $this->service->translate_text('Quota scaduta', 'en', 'it');

		$this->assertSame('google:Quota scaduta', $result, 'Le quote scadute non devono bloccare il provider principale.');

                $usage = TranslationService::get_usage_stats();

                $this->assertSame(1, $usage['google']['it']['requests'] ?? 0, 'Le quote devono essere ripristinate dopo la scadenza.');
                $this->assertArrayHasKey('updated_at', $usage['google']['it'], 'L\'aggiornamento deve essere presente dopo una traduzione.');
                $this->assertGreaterThan(
                        time() - 5,
                        (int) $usage['google']['it']['updated_at'],
                        'La data di aggiornamento deve riflettere la nuova richiesta.'
                );
	}

	public function test_exposes_quota_usage_stats(): void
	{
		$this->service->translate_text('Quota check', 'en', 'it');

		$usage = TranslationService::get_usage_stats();

		$this->assertArrayHasKey('google', $usage, 'Il provider Google deve essere tracciato nelle quote.');
		$this->assertArrayHasKey('it', $usage['google'], 'La lingua di destinazione deve essere registrata.');
		$this->assertSame(1, $usage['google']['it']['requests'], 'Il numero di richieste deve essere incrementato.');
		$this->assertSame(strlen('Quota check'), $usage['google']['it']['characters'], 'I caratteri utilizzati devono essere conteggiati.');
		$this->assertGreaterThan(0, $usage['google']['it']['updated_at'], 'La data di aggiornamento deve essere valorizzata.');
	}

	public function test_settings_include_quote_tracking_usage(): void
	{
		$this->service->translate_text('Settings quota', 'en', 'it');

		$options = Settings::get_options();

		$this->assertSame(1, $options['quote_tracking']['google']['it']['requests'] ?? 0, 'Le impostazioni devono esporre la quota aggregata.');
	}
}
