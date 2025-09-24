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
use PHPUnit\Framework\TestCase;

class PostTranslationManagerTest extends TestCase
{
    private TranslationService $service;

    private Settings $settings;

    private AdminNotices $notices;

    private Logger $logger;

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
        $options = Settings::get_options();
        $options['providers']['google']['api_key'] = 'unit-test-key';
        update_option(Settings::OPTION_NAME, $options);

        $this->logger = new Logger();
        $this->notices = new AdminNotices($this->logger);
        $this->settings = new Settings($this->logger, $this->notices);
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
        $this->assertArrayHasKey('source', $translations['it']);
        $expectedSource = [
            'title' => hash('sha1', 'Original title'),
            'content' => hash('sha1', 'Original content'),
            'excerpt' => hash('sha1', 'Original excerpt'),
            'meta' => [],
        ];
        $this->assertSame($expectedSource, $translations['it']['source']);
        $this->assertSame('synced', $translations['it']['status'] ?? null);
    }

    public function test_translate_post_does_not_duplicate_work_when_source_is_unchanged(): void
    {
        $postId = 101;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Stable title',
            'post_content' => 'Stable content',
            'post_excerpt' => 'Stable excerpt',
        ]);
        global $fp_test_posts;
        $fp_test_posts[$postId] = $post;

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $firstTranslations = $manager->translate_post($postId);
        $initialTimestamp = $firstTranslations['it']['updated_at'] ?? 0;

        $secondTranslations = $manager->translate_post($postId);
        $this->assertSame($firstTranslations, $secondTranslations, 'Le traduzioni non devono cambiare se il contenuto è invariato');
        $this->assertSame($initialTimestamp, $secondTranslations['it']['updated_at'] ?? 0, 'Il timestamp deve rimanere identico quando non ci sono modifiche.');
    }

    public function test_translate_post_updates_only_changed_fields(): void
    {
        $postId = 202;
        $post = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Title A',
            'post_content' => 'Content A',
            'post_excerpt' => 'Excerpt A',
        ]);
        global $fp_test_posts;
        $fp_test_posts[$postId] = $post;

        $manager = new PostTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $manager->translate_post($postId);

        sleep(1);
        $fp_test_posts[$postId]->post_title = 'Title B';

        $translations = $manager->translate_post($postId);
        $this->assertSame('google:Title B', $translations['it']['title']);
        $this->assertSame('google:Content A', $translations['it']['content']);
        $this->assertSame(hash('sha1', 'Title B'), $translations['it']['source']['title']);
        $this->assertSame(hash('sha1', 'Content A'), $translations['it']['source']['content']);
        $this->assertSame(hash('sha1', 'Excerpt A'), $translations['it']['source']['excerpt']);
    }

    public function test_handle_post_save_skips_translation_when_auto_translate_disabled(): void
    {
        $options = Settings::get_options();
        $options['auto_translate'] = false;
        update_option(Settings::OPTION_NAME, $options);

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
}
}
