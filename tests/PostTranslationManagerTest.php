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

    if (! function_exists(__NAMESPACE__ . '\\get_post')) {
        function get_post($post = null)
        {
            global $fp_test_posts;

            if ($post instanceof \WP_Post) {
                return $post;
            }

            if ($post === null && isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
                return $GLOBALS['post'];
            }

            $postId = is_object($post) && isset($post->ID) ? $post->ID : $post;

            if (is_numeric($postId)) {
                $postId = (int) $postId;

                if (isset($fp_test_posts[$postId])) {
                    return $fp_test_posts[$postId];
                }
            }

            return null;
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

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\Providers\GoogleProvider;
use FPMultilanguage\Services\TranslationService;
use FPMultilanguage\Tests\Helpers\SettingsFactory;
use PHPUnit\Framework\TestCase;

class PostTranslationManagerTest extends TestCase
{
    private TranslationService $service;

    private Settings $settings;

    private AdminNotices $notices;

    private Logger $logger;

    /**
     * @param array<string, mixed> $params
     */
    private function makeRestRequest(array $params): object
    {
        return new class($params) implements \ArrayAccess {
            /** @var array<string, mixed> */
            private array $params;

            /**
             * @param array<string, mixed> $params
             */
            public function __construct(array $params)
            {
                $this->params = $params;
            }

            public function offsetExists($offset): bool
            {
                return array_key_exists($offset, $this->params);
            }

            #[\ReturnTypeWillChange]
            public function offsetGet($offset)
            {
                return $this->params[$offset] ?? null;
            }

            #[\ReturnTypeWillChange]
            public function offsetSet($offset, $value): void
            {
                if (! is_string($offset)) {
                    return;
                }

                $this->params[$offset] = $value;
            }

            #[\ReturnTypeWillChange]
            public function offsetUnset($offset): void
            {
                if (! is_string($offset)) {
                    return;
                }

                unset($this->params[$offset]);
            }

            /**
             * @return mixed
             */
            public function get_param(string $key)
            {
                return $this->params[$key] ?? null;
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_test_filters, $wp_test_actions, $wp_test_textdomains, $fp_test_post_meta, $fp_test_posts;
        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_test_filters = [];
        $wp_test_actions = [];
        $wp_test_textdomains = [];
        $fp_test_post_meta = [];
        $fp_test_posts = [];

        Settings::bootstrap_defaults();
        \FPMultilanguage\CurrentLanguage::clear_cache();
        $options = Settings::get_options();
        $options['providers']['google']['enabled'] = true;
        $options['providers']['google']['api_key'] = 'unit-test-key';
        update_option(Settings::OPTION_NAME, $options);
        Settings::clear_cache();
        $this->assertContains('google', Settings::get_enabled_providers(), 'Il provider Google deve essere abilitato per i test.');

        $this->logger = new Logger();
        $this->notices = new AdminNotices($this->logger);
        $this->settings = SettingsFactory::create($this->logger, $this->notices);
        $this->service = new TranslationService($this->logger, $this->notices, $this->settings, [new GoogleProvider($this->logger)]);
    }

    public function test_translate_post_updates_metadata(): void
    {
        $postId = 42;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Original title',
            'post_content' => 'Original content',
            'post_excerpt' => 'Original excerpt',
        ]);
        global $fp_test_posts;
        $fp_test_posts[$postId] = $post;

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $manager->translate_post($postId);

        $translations = $manager->get_post_translations($postId);
        $this->assertArrayHasKey('it', $translations);
        $this->assertSame('google:Original title', $translations['it']['title']);
        $this->assertSame('google:Original content', $translations['it']['content']);
        $this->assertSame('google:Original excerpt', $translations['it']['excerpt']);
        $this->assertArrayHasKey('updated_at', $translations['it']);
    }

    public function test_handle_post_save_skips_translation_when_auto_translate_disabled(): void
    {
        $options = Settings::get_options();
        $options['auto_translate'] = false;
        update_option(Settings::OPTION_NAME, $options);
        Settings::clear_cache();

        $postId = 77;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Titolo',
            'post_content' => 'Contenuto',
            'post_excerpt' => 'Estratto',
        ]);
        global $fp_test_posts;
        $fp_test_posts[$postId] = $post;

        global $wp_remote_post_calls;
        $wp_remote_post_calls = [];

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $manager->handle_post_save($postId, $post, false);
        $manager->handle_post_save($postId, $post, true);

        $this->assertSame([], $manager->get_post_translations($postId));
        $this->assertArrayNotHasKey('translation.googleapis.com', $wp_remote_post_calls);
    }

    public function test_filter_content_uses_current_language(): void
    {
        global $GLOBALS;
        $postId = 99;
        $GLOBALS['post'] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Demo title',
            'post_content' => 'Demo content',
            'post_excerpt' => 'Demo excerpt',
        ]);
        global $fp_test_posts;
        $fp_test_posts[$postId] = $GLOBALS['post'];

        \FPMultilanguage\Content\update_post_meta($postId, PostTranslationManager::META_KEY, [
            'it' => [
                'content' => 'Contenuto salvato',
                'title' => 'Titolo salvato',
                'excerpt' => 'Estratto salvato',
                'updated_at' => time(),
            ],
        ]);

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $filtered = $manager->filter_content('Demo content');

        $this->assertSame('Contenuto salvato', $filtered);
    }

    public function test_translate_attachment_stores_alt_and_caption(): void
    {
        $postId = 123;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_type' => 'attachment',
            'post_title' => 'Hero image',
            'post_content' => 'Descrizione immagine',
            'post_excerpt' => 'Didascalia originale',
        ]);

        global $fp_test_posts, $fp_test_post_meta;
        $fp_test_posts[$postId] = $post;
        $fp_test_post_meta[$postId]['_wp_attachment_image_alt'] = 'Testo alternativo';

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $translations = $manager->translate_attachment($postId);

        $this->assertArrayHasKey('it', $translations);
        $this->assertSame('google:Hero image', $translations['it']['title']);
        $this->assertSame('google:Descrizione immagine', $translations['it']['content']);
        $this->assertSame('google:Didascalia originale', $translations['it']['excerpt']);
        $this->assertSame('google:Testo alternativo', $translations['it']['meta']['_wp_attachment_image_alt']);
    }

    public function test_filter_attachment_meta_returns_translated_alt(): void
    {
        $postId = 321;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_type' => 'attachment',
            'post_title' => 'Logo',
        ]);

        global $fp_test_posts;
        $fp_test_posts[$postId] = $post;

        \FPMultilanguage\Content\update_post_meta($postId, PostTranslationManager::META_KEY, [
            'it' => [
                'meta' => [
                    '_wp_attachment_image_alt' => 'Testo tradotto',
                ],
            ],
        ]);

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);

        $this->assertSame('Testo tradotto', $manager->filter_attachment_meta(null, $postId, '_wp_attachment_image_alt', true));
        $this->assertSame(['Testo tradotto'], $manager->filter_attachment_meta(null, $postId, '_wp_attachment_image_alt', false));
    }

    public function test_filter_attachment_caption_uses_stored_translation(): void
    {
        $postId = 654;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_type' => 'attachment',
            'post_title' => 'Screenshot',
        ]);

        global $fp_test_posts;
        $fp_test_posts[$postId] = $post;

        \FPMultilanguage\Content\update_post_meta($postId, PostTranslationManager::META_KEY, [
            'it' => [
                'excerpt' => 'Didascalia tradotta',
            ],
        ]);

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);

        $this->assertSame('Didascalia tradotta', $manager->filter_attachment_caption('Original caption', $postId));
    }

    public function test_rest_translate_post_returns_error_for_invalid_id(): void
    {
        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);

        $response = $manager->rest_translate_post($this->makeRestRequest(['id' => 999]));

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame('rest_post_invalid_id', $response->get_error_code());
        $this->assertSame(404, $response->get_error_data()['status']);
    }

    public function test_rest_translate_post_translates_attachments(): void
    {
        $postId = 777;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_type' => 'attachment',
            'post_title' => 'Gallery image',
            'post_content' => 'Descrizione galleria',
            'post_excerpt' => 'Didascalia galleria',
        ]);

        global $fp_test_posts, $fp_test_post_meta;
        $fp_test_posts[$postId] = $post;
        $fp_test_post_meta[$postId]['_wp_attachment_image_alt'] = 'Alt originale';

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);

        $response = $manager->rest_translate_post($this->makeRestRequest([
            'id' => $postId,
            'language' => 'it',
            'force' => '0',
        ]));

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('translations', $data);
        $translations = $data['translations'];
        $this->assertArrayHasKey('it', $translations);
        $this->assertSame('google:Gallery image', $translations['it']['title']);
        $this->assertSame('google:Descrizione galleria', $translations['it']['content']);
        $this->assertSame('google:Didascalia galleria', $translations['it']['excerpt']);
        $this->assertSame('google:Alt originale', $translations['it']['meta']['_wp_attachment_image_alt']);
    }
}
}
