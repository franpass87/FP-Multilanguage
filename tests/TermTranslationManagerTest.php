<?php
namespace {
    if (! class_exists('WP_Term')) {
        class WP_Term
        {
            public $term_id;
            public $name = '';
            public $description = '';
            public $taxonomy = '';

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
use FPMultilanguage\Content\TermTranslationManager;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\Providers\GoogleProvider;
use FPMultilanguage\Services\TranslationService;
use FPMultilanguage\Tests\Helpers\SettingsFactory;
use PHPUnit\Framework\TestCase;

class TermTranslationManagerTest extends TestCase
{
    private TranslationService $service;

    private Settings $settings;

    private AdminNotices $notices;

    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_remote_post_calls, $wp_test_filters, $wp_test_actions, $fp_test_term_meta, $fp_test_terms, $wp_test_taxonomies;

        $wp_test_options = [];
        $wp_test_cache = [];
        $wp_test_transients = [];
        $wp_remote_post_calls = [];
        $wp_test_filters = [];
        $wp_test_actions = [];
        $fp_test_term_meta = [];
        $fp_test_terms = [];
        $wp_test_taxonomies = [
            'category' => (object) [
                'name' => 'category',
                'labels' => (object) [
                    'singular_name' => 'Categoria',
                ],
            ],
            'post_tag' => (object) [
                'name' => 'post_tag',
                'labels' => (object) [
                    'singular_name' => 'Tag',
                ],
            ],
            'product_cat' => (object) [
                'name' => 'product_cat',
                'labels' => (object) [
                    'singular_name' => 'Categoria prodotto',
                ],
            ],
        ];

        Settings::clear_cache();
        Settings::bootstrap_defaults();
        \FPMultilanguage\CurrentLanguage::clear_cache();

        $options = Settings::get_options();
        $options['providers']['google']['enabled'] = true;
        $options['providers']['google']['api_key'] = 'unit-test-key';
        $options['taxonomies'] = ['category', 'product_cat'];
        update_option(Settings::OPTION_NAME, $options);
        Settings::clear_cache();
        $this->assertContains('google', Settings::get_enabled_providers(), 'Il provider Google deve essere abilitato per i test.');

        $this->logger = new Logger();
        $this->notices = new AdminNotices($this->logger);
        $this->settings = SettingsFactory::create($this->logger, $this->notices);
        $this->service = new TranslationService($this->logger, $this->notices, $this->settings, [new GoogleProvider($this->logger)]);
    }

    public function test_translate_term_generates_translations(): void
    {
        $termId = 12;
        $term = new \WP_Term([
            'term_id' => $termId,
            'name' => 'Categoria originale',
            'description' => 'Descrizione <strong>originale</strong>',
            'taxonomy' => 'category',
        ]);

        global $fp_test_terms;
        $fp_test_terms[$termId] = $term;

        $manager = new TermTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $translations = $manager->translate_term($termId, 'category');

        $this->assertArrayHasKey('it', $translations);
        $this->assertSame('google:Categoria originale', $translations['it']['name']);
        $this->assertSame('google:Descrizione <strong>originale</strong>', $translations['it']['description']);
        $this->assertArrayHasKey('updated_at', $translations['it']);
    }

    public function test_filter_term_returns_translated_values(): void
    {
        $termId = 22;
        $term = new \WP_Term([
            'term_id' => $termId,
            'name' => 'Originale',
            'description' => 'Descrizione originale',
            'taxonomy' => 'category',
        ]);

        global $fp_test_terms, $fp_test_term_meta;
        $fp_test_terms[$termId] = $term;
        $fp_test_term_meta[$termId][TermTranslationManager::META_KEY] = [
            'it' => [
                'name' => 'Categoria tradotta',
                'description' => 'Descrizione tradotta',
            ],
        ];

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $manager = new TermTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $translated = $manager->filter_term($term, 'category');

        $this->assertInstanceOf(\WP_Term::class, $translated);
        $this->assertSame('Categoria tradotta', $translated->name);
        $this->assertSame('Descrizione tradotta', $translated->description);
    }

    public function test_expose_translations_appends_payload(): void
    {
        $termId = 35;
        $term = new \WP_Term([
            'term_id' => $termId,
            'name' => 'Categoria',
            'description' => 'Descrizione',
            'taxonomy' => 'category',
        ]);

        global $fp_test_terms, $fp_test_term_meta;
        $fp_test_terms[$termId] = $term;
        $fp_test_term_meta[$termId][TermTranslationManager::META_KEY] = [
            'it' => [
                'name' => 'Categoria tradotta',
                'description' => 'Descrizione tradotta',
            ],
        ];

        add_filter('fp_multilanguage_current_language', static function () {
            return 'it';
        });

        $manager = new TermTranslationManager($this->service, $this->settings, $this->notices, $this->logger);
        $response = new \WP_REST_Response([]);
        $result = $manager->expose_translations($response, $term, new \WP_REST_Request());

        $data = $result->get_data();
        $this->assertIsArray($data['fp_multilanguage']);
        $this->assertSame('it', $data['fp_multilanguage']['language']);
        $this->assertArrayHasKey('it', $data['fp_multilanguage']['translations']);
        $this->assertSame('Categoria tradotta', $data['fp_multilanguage']['translations']['it']['name']);
    }
}
}

