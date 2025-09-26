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
use FPMultilanguage\Tests\Helpers\SettingsFactory;
use PHPUnit\Framework\TestCase;

class SEOTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_filters, $wp_test_actions;
        $wp_test_options = [];
        $wp_test_filters = [];
        $wp_test_actions = [];

        Settings::bootstrap_defaults();
    }

    public function test_filter_sitemap_entry_adds_hreflang_links(): void
    {
        $logger = new Logger();
        $notices = new AdminNotices($logger);
        $settings = SettingsFactory::create($logger, $notices);
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

    public function test_resolve_translated_slug_maps_query_to_post(): void
    {
        $logger = new Logger();
        $notices = new AdminNotices($logger);
        $settings = SettingsFactory::create($logger, $notices);
        $translationService = new TranslationService($logger, $notices, $settings);

        $postTranslationManager = new class extends PostTranslationManager {
            public function __construct()
            {
            }
        };

        update_option(SEO::SLUG_INDEX_OPTION, [
            'chi-siamo' => [
                'post_id' => 321,
                'language' => 'it',
            ],
        ]);

        $seo = new SEO($settings, $translationService, $postTranslationManager, $logger);

        $result = $seo->resolve_translated_slug(['name' => 'chi-siamo']);

        $this->assertSame(321, $result['p']);
        $this->assertSame(321, $result['page_id']);
        $this->assertSame('it', $result['fp_lang']);
    }

    public function test_cleanup_slug_index_removes_post_entries(): void
    {
        $logger = new Logger();
        $notices = new AdminNotices($logger);
        $settings = SettingsFactory::create($logger, $notices);
        $translationService = new TranslationService($logger, $notices, $settings);

        $postTranslationManager = new class extends PostTranslationManager {
            public function __construct()
            {
            }
        };

        update_option(SEO::SLUG_INDEX_OPTION, [
            'chi-siamo' => [
                'post_id' => 654,
                'language' => 'it',
            ],
            'en/about' => [
                'post_id' => 123,
                'language' => 'en',
            ],
        ]);

        $seo = new SEO($settings, $translationService, $postTranslationManager, $logger);
        $seo->cleanup_slug_index(654);

        $index = get_option(SEO::SLUG_INDEX_OPTION);

        $this->assertIsArray($index);
        $this->assertArrayNotHasKey('chi-siamo', $index);
        $this->assertArrayHasKey('en/about', $index);
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
