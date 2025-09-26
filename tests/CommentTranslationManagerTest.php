<?php
namespace {
    if (! class_exists('WP_Comment')) {
        class WP_Comment
        {
            public int $comment_ID = 0;
            public int $comment_post_ID = 0;
            public string $comment_content = '';

            public function __construct(array $data = [])
            {
                foreach ($data as $key => $value) {
                    $this->{$key} = $value;
                }
            }
        }
    }
}

namespace FPMultilanguage\Tests {

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Content\CommentTranslationManager;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\Providers\GoogleProvider;
use FPMultilanguage\Services\TranslationService;
use FPMultilanguage\Tests\Helpers\SettingsFactory;
use PHPUnit\Framework\TestCase;

class CommentTranslationManagerTest extends TestCase
{
    private TranslationService $service;

    private Settings $settings;

    private AdminNotices $notices;

    private Logger $logger;

    private CommentTranslationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_remote_post_failures, $wp_remote_post_invalid_json, $wp_remote_post_requests, $wp_test_filters, $wp_test_actions, $wp_test_textdomains, $fp_test_comments, $fp_test_comment_meta;

        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_remote_post_failures = [];
        $wp_remote_post_invalid_json = [];
        $wp_remote_post_requests = [];
        $wp_test_filters = [];
        $wp_test_actions = [];
        $wp_test_textdomains = [];
        $fp_test_comments = [];
        $fp_test_comment_meta = [];
        $_GET = [];

        CurrentLanguage::clear_cache();
        TranslationService::flush_cache();
        Settings::clear_cache();

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
                    'enabled' => false,
                    'api_key' => '',
                    'endpoint' => 'https://api.deepl.com/v2/translate',
                ],
            ],
            'seo' => [],
            'quote_tracking' => [],
        ]);
        Settings::clear_cache();
        $this->assertContains('google', Settings::get_enabled_providers(), 'Il provider Google deve essere abilitato per i test.');

        $this->logger = new Logger();
        $this->notices = new AdminNotices($this->logger);
        $this->settings = SettingsFactory::create($this->logger, $this->notices);
        $this->service = new TranslationService($this->logger, $this->notices, $this->settings, [
            new GoogleProvider($this->logger),
        ]);

        $this->manager = new CommentTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
    }

    public function test_translate_comment_generates_translations(): void
    {
        global $fp_test_comments;

        $commentId = 10;
        $comment = new \WP_Comment([
            'comment_ID' => $commentId,
            'comment_post_ID' => 42,
            'comment_content' => 'Hello comment',
        ]);

        $fp_test_comments[$commentId] = $comment;

        $translations = $this->manager->translate_comment($commentId);

        $this->assertArrayHasKey('it', $translations);
        $this->assertSame('google:Hello comment', $translations['it']['content']);

        $stored = get_comment_meta($commentId, CommentTranslationManager::META_KEY, true);
        $this->assertSame($translations, $stored);
    }

    public function test_filter_comment_text_returns_translated_value(): void
    {
        global $fp_test_comments;

        $commentId = 11;
        $comment = new \WP_Comment([
            'comment_ID' => $commentId,
            'comment_post_ID' => 50,
            'comment_content' => 'Second comment body',
        ]);

        $fp_test_comments[$commentId] = $comment;
        $this->manager->translate_comment($commentId);

        $_GET['fp_lang'] = 'it';
        CurrentLanguage::clear_cache();

        $filtered = $this->manager->filter_comment_text('Second comment body', $comment);

        $this->assertSame('google:Second comment body', $filtered);

        unset($_GET['fp_lang']);
        CurrentLanguage::clear_cache();
    }

    public function test_expose_translations_adds_rest_data(): void
    {
        global $fp_test_comments;

        $commentId = 12;
        $comment = new \WP_Comment([
            'comment_ID' => $commentId,
            'comment_post_ID' => 60,
            'comment_content' => 'REST comment',
        ]);

        $fp_test_comments[$commentId] = $comment;
        $this->manager->translate_comment($commentId);

        $response = (object) ['data' => []];
        $result = $this->manager->expose_translations($response, $comment, null);

        $this->assertArrayHasKey('fp_multilanguage', $result->data);
        $this->assertSame(Settings::get_source_language(), $result->data['fp_multilanguage']['language']);
        $this->assertSame(
            'google:REST comment',
            $result->data['fp_multilanguage']['translations']['it']['content']
        );
    }
}
}
