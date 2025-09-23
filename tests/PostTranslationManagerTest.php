<?php
namespace {
    if (! class_exists('WP_Post')) {
        class WP_Post
        {
            public $ID;
            public $post_title = '';
            public $post_content = '';
            public $post_excerpt = '';
            public $post_type = 'post';

            public function __construct(array $data = [])
            {
                foreach ($data as $key => $value) {
                    $this->{$key} = $value;
                }
            }
        }
    }
}

namespace FPMultilanguage\Content {
    if (! function_exists(__NAMESPACE__ . '\\get_post_meta')) {
        function get_post_meta($postId, $key = '', $single = false)
        {
            global $fp_test_post_meta;

            if (! isset($fp_test_post_meta[$postId][$key])) {
                return $single ? [] : [];
            }

            $value = $fp_test_post_meta[$postId][$key];

            return $single ? $value : [$value];
        }
    }

    if (! function_exists(__NAMESPACE__ . '\\update_post_meta')) {
        function update_post_meta($postId, $key, $value)
        {
            global $fp_test_post_meta;

            if (! isset($fp_test_post_meta)) {
                $fp_test_post_meta = [];
            }

            if (! isset($fp_test_post_meta[$postId])) {
                $fp_test_post_meta[$postId] = [];
            }

            $fp_test_post_meta[$postId][$key] = $value;

            return true;
        }
    }
}

namespace FPMultilanguage\Tests {

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Services\TranslationService;
use PHPUnit\Framework\TestCase;

class PostTranslationManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_test_filters, $wp_test_actions, $wp_test_textdomains, $fp_test_post_meta;
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_test_filters = [];
        $wp_test_actions = [];
        $wp_test_textdomains = [];
        $fp_test_post_meta = [];

        Settings::bootstrap_defaults();
    }

    public function test_updates_metadata_when_title_or_excerpt_changes(): void
    {
        $postId = 42;

        $existingTranslations = [
            'it' => [
                'title' => 'Titolo precedente',
                'content' => 'Contenuto tradotto',
                'excerpt' => 'Estratto precedente',
                'updated_at' => 1000,
            ],
        ];

        \FPMultilanguage\Content\update_post_meta($postId, PostTranslationManager::META_KEY, $existingTranslations);

        $post = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Post title',
            'post_content' => 'Post content',
            'post_excerpt' => 'Post excerpt',
            'post_type' => 'post',
        ]);

        $translationService = $this->createMock(TranslationService::class);
        $translationService->method('translate')->willReturnCallback(
            static function ($text, $source, $target, $args = []) {
                unset($source, $target, $args);

                switch ($text) {
                    case 'Post content':
                        return 'Contenuto tradotto';
                    case 'Post title':
                        return 'Titolo aggiornato';
                    case 'Post excerpt':
                        return 'Estratto aggiornato';
                    default:
                        return '';
                }
            }
        );

        $manager = new PostTranslationManager($translationService, new Settings());
        $manager->handle_post_save($postId, $post, true);

        $storedTranslations = $manager->get_post_translations($postId);

        $this->assertArrayHasKey('it', $storedTranslations);
        $this->assertSame('Titolo aggiornato', $storedTranslations['it']['title']);
        $this->assertSame('Contenuto tradotto', $storedTranslations['it']['content']);
        $this->assertSame('Estratto aggiornato', $storedTranslations['it']['excerpt']);
        $this->assertGreaterThan($existingTranslations['it']['updated_at'], $storedTranslations['it']['updated_at']);
    }
}
}
