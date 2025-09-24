<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Dynamic\DynamicStrings;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use PHPUnit\Framework\TestCase;

class DynamicStringsTest extends TestCase
{
    private Logger $logger;

    private AdminNotices $notices;

    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_test_filters, $wp_test_actions, $wp_test_textdomains;
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_test_filters = [];
        $wp_test_actions = [];
        $wp_test_textdomains = [];
        $_GET = [];

        Settings::bootstrap_defaults();
        $this->logger = new Logger();
        $this->notices = new AdminNotices($this->logger);
        $this->settings = new Settings($this->logger, $this->notices);
    }

    private function createDynamicStrings(TranslationService $service): DynamicStrings
    {
        return new DynamicStrings($service, $this->settings, $this->notices, $this->logger);
    }

    public function test_translate_dynamic_string_accepts_three_arguments(): void
    {
        $hash = hash('sha1', 'generic|Widget title');
        Settings::update_manual_string($hash, 'it', 'Titolo widget');

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $service = $this->createMock(TranslationService::class);
        $dynamicStrings = $this->createDynamicStrings($service);

        $result = $dynamicStrings->translate_dynamic_string('Widget title', ['instance'], 'widget-id');

        $this->assertSame('Titolo widget', $result);
    }

    public function test_translate_gettext_preserves_existing_wordpress_translation(): void
    {
        $service = $this->createMock(TranslationService::class);
        $service->expects($this->never())->method('translate_text');
        $dynamicStrings = $this->createDynamicStrings($service);

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $result = $dynamicStrings->filter_gettext('Ciao', 'Hello', 'default');

        $this->assertSame('Ciao', $result);
    }

    public function test_translate_gettext_allows_manual_override_on_translated_string(): void
    {
        $hash = hash('sha1', 'default|Hello');
        Settings::update_manual_string($hash, 'it', 'Override manuale');

        $service = $this->createMock(TranslationService::class);
        $service->expects($this->never())->method('translate_text');
        $dynamicStrings = $this->createDynamicStrings($service);

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $result = $dynamicStrings->filter_gettext('Traduzione WP', 'Hello', 'default');

        $this->assertSame('Override manuale', $result);
    }

    public function test_translate_gettext_can_be_forced_via_filter(): void
    {
        $service = $this->getMockBuilder(TranslationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['translate_text'])
            ->getMock();

        $service->expects($this->once())->method('translate_text')->with('Hello', 'en', 'it', ['origin' => 'filter'])->willReturn('service:Hello');

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        add_filter(
            'fp_multilanguage_force_gettext_translation',
            static function ($payload) {
                $payload['force'] = true;
                $payload['service_args'] = ['origin' => 'filter'];

                return $payload;
            },
            10,
            1
        );

        $dynamicStrings = $this->createDynamicStrings($service);
        $result = $dynamicStrings->filter_gettext('Traduzione WP', 'Hello', 'default');

        $this->assertSame('service:Hello', $result);
    }

    public function test_filter_ngettext_preserves_existing_wordpress_translation(): void
    {
        $service = $this->createMock(TranslationService::class);
        $service->expects($this->never())->method('translate_text');
        $dynamicStrings = $this->createDynamicStrings($service);

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $result = $dynamicStrings->filter_ngettext('Traduzione WP', 'Singolare', 'Plurale', 2, 'default');

        $this->assertSame('Traduzione WP', $result);
    }

    public function test_filter_ngettext_triggers_automatic_translation_when_needed(): void
    {
        $service = $this->getMockBuilder(TranslationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['translate_text'])
            ->getMock();

        $service->expects($this->once())
            ->method('translate_text')
            ->with('Singolare', 'en', 'it', [])
            ->willReturn('service:Singolare');

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $dynamicStrings = $this->createDynamicStrings($service);
        $result = $dynamicStrings->filter_ngettext('Singolare', 'Singolare', 'Plurale', 1, 'default');

        $this->assertSame('service:Singolare', $result);
    }

    public function test_translates_requested_language_from_query_var(): void
    {
        $_GET['fp_lang'] = 'it';

        $service = $this->getMockBuilder(TranslationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['translate_text'])
            ->getMock();

        $service->expects($this->once())->method('translate_text')->with('Hello world', 'en', 'it', [])->willReturn('service:Hello world');

        $dynamicStrings = $this->createDynamicStrings($service);
        $result = $dynamicStrings->translate_dynamic_string('Hello world');

        $this->assertSame('service:Hello world', $result);
    }
}
