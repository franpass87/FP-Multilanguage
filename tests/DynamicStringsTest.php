<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Dynamic\DynamicStrings;
use FPMultilanguage\Services\TranslationService;
use PHPUnit\Framework\TestCase;

class DynamicStringsTest extends TestCase
{
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

        Settings::bootstrap_defaults();
    }

    public function test_translate_dynamic_string_accepts_three_arguments(): void
    {
        $hash = hash('sha1', 'Widget title');
        Settings::update_manual_string($hash, 'it', 'Titolo widget');

        add_filter('fp_multilanguage_current_language', static function ($language) {
            unset($language);

            return 'it';
        });

        $dynamicStrings = new DynamicStrings(new TranslationService(), new Settings());

        $result = $dynamicStrings->translate_dynamic_string('Widget title', ['instance'], 'widget-id');

        $this->assertSame('Titolo widget', $result);
    }

    public function test_translate_gettext_preserves_existing_wordpress_translation(): void
    {
        $translationService = new class extends TranslationService {
            public array $calls = [];

            public function translate(string $text, string $source, string $target, array $args = []): string
            {
                $this->calls[] = [$text, $source, $target, $args];

                return 'service:' . $text;
            }
        };

        add_filter('fp_multilanguage_current_language', static function ($language) {
            unset($language);

            return 'it';
        });

        $dynamicStrings = new DynamicStrings($translationService, new Settings());

        $result = $dynamicStrings->translate_gettext('Ciao', 'Hello', 'default');

        $this->assertSame('Ciao', $result);
        $this->assertSame([], $translationService->calls);
    }

    public function test_translate_gettext_allows_manual_override_on_translated_string(): void
    {
        $hash = hash('sha1', 'Hello');
        Settings::update_manual_string($hash, 'it', 'Override manuale');

        $translationService = new class extends TranslationService {
            public array $calls = [];

            public function translate(string $text, string $source, string $target, array $args = []): string
            {
                $this->calls[] = [$text, $source, $target, $args];

                return 'service:' . $text;
            }
        };

        add_filter('fp_multilanguage_current_language', static function ($language) {
            unset($language);

            return 'it';
        });

        $dynamicStrings = new DynamicStrings($translationService, new Settings());

        $result = $dynamicStrings->translate_gettext('Traduzione WP', 'Hello', 'default');

        $this->assertSame('Override manuale', $result);
        $this->assertSame([], $translationService->calls);
    }

    public function test_translate_gettext_can_be_forced_via_filter(): void
    {
        $translationService = new class extends TranslationService {
            public array $calls = [];

            public function translate(string $text, string $source, string $target, array $args = []): string
            {
                $this->calls[] = [$text, $source, $target, $args];

                return 'service:' . $text;
            }
        };

        add_filter('fp_multilanguage_current_language', static function ($language) {
            unset($language);

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

        $dynamicStrings = new DynamicStrings($translationService, new Settings());

        $result = $dynamicStrings->translate_gettext('Traduzione WP', 'Hello', 'default');

        $this->assertSame('service:Hello', $result);
        $this->assertSame([
            ['Hello', 'en', 'it', ['origin' => 'filter']],
        ], $translationService->calls);
    }
}
