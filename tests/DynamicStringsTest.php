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

        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls;
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];

        Settings::bootstrap_defaults();
    }

    public function test_translate_dynamic_string_accepts_three_arguments(): void
    {
        $dynamicStrings = new DynamicStrings(new TranslationService(), new Settings());

        $result = $dynamicStrings->translate_dynamic_string('Widget title', ['instance'], 'widget-id');

        $this->assertSame('Widget title', $result);
    }
}
