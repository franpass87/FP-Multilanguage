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
}

namespace FPMultilanguage\Tests {

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\SEO\SEO;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use PHPUnit\Framework\TestCase;

class SEOTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_filters, $wp_test_actions, $wp_test_post_meta;
        $wp_test_options = [];
        $wp_test_filters = [];
        $wp_test_actions = [];
        $wp_test_post_meta = [];

        Settings::bootstrap_defaults();
        update_option('fp_multilanguage_slug_index', ['by_path' => [], 'by_post' => []]);
    }

    protected function tearDown(): void
    {
        unset($_POST['fp_multilanguage_seo'], $_POST['fp_multilanguage_seo_nonce']);
        unset($_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    public function test_filter_sitemap_entry_adds_hreflang_links(): void
    {
        $logger = new Logger();
        $notices = new AdminNotices($logger);
        $settings = new Settings($logger, $notices);
        $translationService = new TranslationService($logger, $notices, $settings);

        $translations = [
            123 => [
                'it' => [
                    'content' => 'Contenuto tradotto',
                    'title' => 'Titolo IT',
                    'excerpt' => 'Estratto IT',
                ],
            ],
        ];

        $postTranslationManager = new class($translations) extends PostTranslationManager {
            /**
             * @var array<int, array<string, array<string, string>>>
             */
            private array $translations;

            /**
             * @param array<int, array<string, array<string, string>>> $translations
             */
            public function __construct(array $translations)
            {
                $this->translations = $translations;
            }

            public function get_post_translations(int $postId): array
            {
                return $this->translations[$postId] ?? [];
            }
        };

        $seo = new SEO($settings, $translationService, $postTranslationManager, $logger);

        $post = new \WP_Post(['ID' => 123]);

        $entry = [
            'loc' => 'https://example.com/post/123',
        ];

        $filteredEntry = $seo->filter_sitemap_entry($entry, $post, 'post');

        $expectedAlternates = [
            [
                'rel' => 'alternate',
                'hreflang' => 'en',
                'href' => 'https://example.com/post/123',
            ],
            [
                'rel' => 'alternate',
                'hreflang' => 'it',
                'href' => 'https://example.com/post/123?fp_lang=it',
            ],
        ];

        $this->assertArrayHasKey('alternates', $filteredEntry);
        $this->assertSame($expectedAlternates, $filteredEntry['alternates']);

        $sitemapXml = $this->renderSitemap([$filteredEntry]);
        $this->assertNotSame('', $sitemapXml);

        $document = simplexml_load_string($sitemapXml);
        $this->assertNotFalse($document);

        $document->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $document->registerXPathNamespace('xhtml', 'http://www.w3.org/1999/xhtml');
        $links = $document->xpath('/sm:urlset/sm:url/xhtml:link');

        $this->assertIsArray($links);
        $this->assertCount(2, $links);

        $resolvedLinks = array_map(
            static fn(\SimpleXMLElement $link): array => [
                'rel' => (string) $link['rel'],
                'hreflang' => (string) $link['hreflang'],
                'href' => (string) $link['href'],
            ],
            $links
        );

        $this->assertSame($expectedAlternates, $resolvedLinks);
    }

    public function test_resolve_slug_request_maps_to_original_post(): void
    {
        $logger = new Logger();
        $notices = new AdminNotices($logger);
        $settings = new Settings($logger, $notices);
        $translationService = new TranslationService($logger, $notices, $settings);
        $manager = $this->createMock(PostTranslationManager::class);
        $manager->method('get_post_translations')->willReturn([
            'it' => ['content' => 'test'],
        ]);

        $seo = new SEO($settings, $translationService, $manager, $logger);

        $postId = 55;
        $post = new \WP_Post(['ID' => $postId]);
        $_POST['fp_multilanguage_seo_nonce'] = wp_create_nonce('fp_multilanguage_seo_meta');
        $_POST['fp_multilanguage_seo'] = [
            'slug' => [
                'en' => 'en/product',
                'it' => 'it/prodotto',
            ],
        ];

        $seo->save_meta($postId, $post);

        $slugs = SEO::get_language_slugs($postId);
        $this->assertSame([
            'en' => 'en/product',
            'it' => 'it/prodotto',
        ], $slugs);

        $_SERVER['REQUEST_URI'] = '/it/prodotto/';
        $query = $seo->resolve_slug_request([]);

        $this->assertSame(55, $query['p']);
        $this->assertSame(55, $query['page_id']);
        $this->assertSame('it', $query['fp_lang']);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function renderSitemap(array $entries): string
    {
        $urlset = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
            . 'xmlns:xhtml="http://www.w3.org/1999/xhtml"></urlset>'
        );

        foreach ($entries as $entry) {
            $url = $urlset->addChild('url');
            foreach ($entry as $name => $value) {
                if ($name === 'loc') {
                    $url->addChild('loc', (string) $value);
                    continue;
                }

                if ($name === 'alternates' && is_array($value)) {
                    foreach ($value as $alternate) {
                        if (! is_array($alternate)) {
                            continue;
                        }

                        $href = $alternate['href'] ?? null;
                        $hreflang = $alternate['hreflang'] ?? null;
                        if ($href === null || $hreflang === null) {
                            continue;
                        }

                        $link = $url->addChild('xhtml:link', '', 'http://www.w3.org/1999/xhtml');
                        $link->addAttribute('rel', (string) ($alternate['rel'] ?? 'alternate'));
                        $link->addAttribute('hreflang', (string) $hreflang);
                        $link->addAttribute('href', (string) $href);
                    }
                }
            }
        }

        $xml = $urlset->asXML();

        return $xml === false ? '' : $xml;
    }
}
}
