<?php
namespace {
    if (! class_exists('WP_Post')) {
        #[\AllowDynamicProperties]
        class WP_Post
        {
            public int $ID = 0;
            public string $post_title = '';
            public string $post_content = '';
            public string $post_excerpt = '';
            public string $post_type = 'post';

            public function __construct(array $data = [])
            {
                foreach ($data as $key => $value) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    if (! class_exists('WP_Widget')) {
        class WP_Widget
        {
            public function __construct(string $id_base = '', string $name = '', array $widget_options = [], array $control_options = [])
            {
                unset($id_base, $name, $widget_options, $control_options);
            }
        }
    }

    if (! isset($GLOBALS['fpml_test_translations'])) {
        $GLOBALS['fpml_test_translations'] = [];
    }

    if (! array_key_exists('fpml_test_queried_object', $GLOBALS)) {
        $GLOBALS['fpml_test_queried_object'] = null;
    }
}

namespace FPMultilanguage\Widgets {
    if (! function_exists(__NAMESPACE__ . '\\fpml_set_test_queried_object')) {
        function fpml_set_test_queried_object(?object $object): void
        {
            $GLOBALS['fpml_test_queried_object'] = $object;
        }
    }

    if (! function_exists(__NAMESPACE__ . '\\fpml_reset_test_state')) {
        function fpml_reset_test_state(): void
        {
            $GLOBALS['fpml_test_queried_object'] = null;
            $GLOBALS['fpml_test_translations'] = [];
        }
    }

    if (! function_exists(__NAMESPACE__ . '\\get_queried_object')) {
        function get_queried_object(): ?object
        {
            return $GLOBALS['fpml_test_queried_object'] ?? null;
        }
    }

    if (! function_exists(__NAMESPACE__ . '\\fpml_set_post_translations')) {
        function fpml_set_post_translations(int $postId, array $translations): void
        {
            if (! isset($GLOBALS['fpml_test_translations'])) {
                $GLOBALS['fpml_test_translations'] = [];
            }

            $GLOBALS['fpml_test_translations'][$postId] = $translations;
        }
    }

    if (! function_exists(__NAMESPACE__ . '\\fp_multilanguage')) {
        function fp_multilanguage(): object
        {
            return new class() {
                public function get_container(): object
                {
                    return new class() {
                        public function get(string $id): ?object
                        {
                            if ($id === 'post_translation_manager') {
                                return new class() {
                                    public function get_post_translations(int $postId): array
                                    {
                                        return $GLOBALS['fpml_test_translations'][$postId] ?? [];
                                    }
                                };
                            }

                            return null;
                        }
                    };
                }
            };
        }
    }
}

namespace FPMultilanguage\Tests {

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Widgets\LanguageSwitcher;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LanguageSwitcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options;
        $wp_test_options = [];

        \FPMultilanguage\Widgets\fpml_reset_test_state();

        Settings::bootstrap_defaults();

        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/';
    }

    protected function tearDown(): void
    {
        \FPMultilanguage\Widgets\fpml_reset_test_state();

        $_GET = [];
        unset($_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    public function test_generates_permalink_urls_preserving_query_string(): void
    {
        $post = new \WP_Post(['ID' => 42]);
        \FPMultilanguage\Widgets\fpml_set_test_queried_object($post);

        $_SERVER['REQUEST_URI'] = '/post/42/?foo=bar&fp_lang=it';
        $_GET = [
            'foo' => 'bar',
            'fp_lang' => 'it',
        ];

        $switcher = new LanguageSwitcher();
        $languages = $this->invokeGetLanguages($switcher);

        $this->assertSame('https://example.com/post/42?foo=bar&fp_lang=en', $languages['en']);
        $this->assertSame('https://example.com/post/42?foo=bar&fp_lang=it', $languages['it']);
    }

    public function test_generates_home_urls_preserving_query_string_for_non_singular(): void
    {
        \FPMultilanguage\Widgets\fpml_set_test_queried_object(null);

        $_SERVER['REQUEST_URI'] = '/category/news/?paged=2&fp_lang=en';
        $_GET = [
            'paged' => '2',
            'fp_lang' => 'en',
        ];

        $switcher = new LanguageSwitcher();
        $languages = $this->invokeGetLanguages($switcher);

        $this->assertSame('https://example.com/category/news/?paged=2&fp_lang=en', $languages['en']);
        $this->assertSame('https://example.com/category/news/?paged=2&fp_lang=it', $languages['it']);
    }

    private function invokeGetLanguages(LanguageSwitcher $switcher): array
    {
        $reflection = new ReflectionClass(LanguageSwitcher::class);
        $method = $reflection->getMethod('get_languages');
        $method->setAccessible(true);

        return $method->invoke($switcher);
    }
}
}
