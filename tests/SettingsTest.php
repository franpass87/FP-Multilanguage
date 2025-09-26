<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Admin\Settings\ManualStringsUI;
use FPMultilanguage\Admin\Settings\ProviderTester;
use FPMultilanguage\Admin\Settings\Repository as SettingsRepository;
use FPMultilanguage\Admin\Settings\RestController as SettingsRestController;
use FPMultilanguage\Services\Logger;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_cache, $wp_test_transients;
        $wp_test_options   = array();
        $wp_test_cache     = array();
        $wp_test_transients = array();

        global $wp_test_taxonomies;
        $wp_test_taxonomies = array(
            'category' => (object) array(
                'name' => 'category',
                'labels' => (object) array(
                    'singular_name' => 'Categoria',
                ),
            ),
            'post_tag' => (object) array(
                'name' => 'post_tag',
                'labels' => (object) array(
                    'singular_name' => 'Tag',
                ),
            ),
        );

        Settings::clear_cache();

        $logger        = new Logger();
        $notices       = new AdminNotices( $logger );
        $repository    = new SettingsRepository( $notices );
        $manualStrings = new ManualStringsUI( $repository, $logger );
        $providerTester = new ProviderTester( $logger, $repository );
        $restController = new SettingsRestController( $repository, $logger, $notices, $providerTester );
        $this->settings = new Settings( $logger, $notices, $repository, $manualStrings, $restController );
    }

    public function test_sanitize_ensures_fallback_added_to_targets(): void
    {
        $input = array(
            'source_language'   => 'en',
            'fallback_language' => 'fr',
            'target_languages'  => array( 'en', 'it', '' ),
        );

        $sanitized = $this->settings->sanitize( $input );

        $this->assertSame( 'fr', $sanitized['fallback_language'] );
        $this->assertContains( 'it', $sanitized['target_languages'] );
        $this->assertContains( 'fr', $sanitized['target_languages'] );
        $this->assertNotContains( 'en', $sanitized['target_languages'], 'La lingua sorgente non deve essere presente tra le destinazioni.' );
    }

    public function test_sanitize_uses_defaults_when_targets_empty(): void
    {
        $input = array(
            'source_language'   => 'en',
            'fallback_language' => 'en',
            'target_languages'  => array(),
        );

        $sanitized = $this->settings->sanitize( $input );

        $this->assertNotEmpty( $sanitized['target_languages'] );
        $this->assertContains( 'it', $sanitized['target_languages'], 'Quando mancano le lingue di destinazione devono essere ripristinati i valori di default.' );
    }

    public function test_sanitize_disables_provider_without_api_key(): void
    {
        $input = array(
            'providers' => array(
                'google' => array(
                    'enabled' => true,
                    'api_key' => '',
                ),
                'deepl' => array(
                    'enabled' => 1,
                    'api_key' => '   ',
                ),
            ),
        );

        $sanitized = $this->settings->sanitize( $input );

        $this->assertFalse( $sanitized['providers']['google']['enabled'], 'Il provider Google deve essere disabilitato senza chiave API.' );
        $this->assertFalse( $sanitized['providers']['deepl']['enabled'], 'Il provider DeepL deve essere disabilitato senza chiave API.' );
    }

    public function test_sanitize_normalizes_provider_glossary_options(): void
    {
        $input = array(
            'providers' => array(
                'google' => array(
                    'enabled'              => true,
                    'api_key'              => 'key',
                    'glossary_id'          => '  projects/example/locations/eu/glossaries/demo  ',
                    'glossary_ignore_case' => '1',
                    'timeout'              => '2',
                ),
                'deepl' => array(
                    'enabled'     => true,
                    'api_key'     => 'secret',
                    'endpoint'    => 'https://api.deepl.com/v2/translate',
                    'glossary_id' => " 1234-5678 \n",
                    'formality'   => 'MORE',
                ),
            ),
        );

        $sanitized = $this->settings->sanitize( $input );

        $google = $sanitized['providers']['google'];
        $this->assertSame( 'projects/example/locations/eu/glossaries/demo', $google['glossary_id'] );
        $this->assertTrue( $google['glossary_ignore_case'], 'L\'opzione ignoreCase deve essere attivata.' );
        $this->assertSame( 5, $google['timeout'], 'Il timeout deve rispettare il valore minimo di 5 secondi.' );

        $deepl = $sanitized['providers']['deepl'];
        $this->assertSame( '1234-5678', $deepl['glossary_id'] );
        $this->assertSame( 'more', $deepl['formality'] );
    }

    public function test_sanitize_normalizes_language_formats(): void
    {
        $input = array(
            'source_language'   => 'EN',
            'fallback_language' => 'PT_BR',
            'target_languages'  => 'PT_BR, ES_es',
        );

        $sanitized = $this->settings->sanitize( $input );

        $this->assertSame( 'en', $sanitized['source_language'] );
        $this->assertSame( 'pt-br', $sanitized['fallback_language'] );
        $this->assertContains( 'pt-br', $sanitized['target_languages'] );
        $this->assertContains( 'es-es', $sanitized['target_languages'] );
    }

    public function test_sanitize_preserves_registered_taxonomies(): void
    {
        global $wp_test_taxonomies;
        $wp_test_taxonomies['product_cat'] = (object) array(
            'name' => 'product_cat',
            'labels' => (object) array(
                'singular_name' => 'Categoria prodotto',
            ),
        );

        $input = array(
            'taxonomies' => array( 'category', 'product_cat', 'invalid' ),
        );

        $sanitized = $this->settings->sanitize( $input );

        $this->assertSame(
            array( 'category', 'product_cat' ),
            $sanitized['taxonomies'],
            'Le tassonomie registrate devono essere mantenute e gli slug sconosciuti rimossi.'
        );
    }

    public function test_sanitize_falls_back_to_default_taxonomies(): void
    {
        global $wp_test_taxonomies;
        $wp_test_taxonomies = array();

        $input = array(
            'taxonomies' => array( 'custom_tax' ),
        );

        $sanitized = $this->settings->sanitize( $input );

        $this->assertSame(
            array( 'category', 'post_tag' ),
            $sanitized['taxonomies'],
            'Quando nessuna tassonomia valida è selezionata devono essere ripristinati i valori di default.'
        );
    }

    public function test_get_options_uses_cache_until_cleared(): void
    {
        $stored = array(
            'source_language'   => 'en',
            'fallback_language' => 'en',
            'target_languages'  => array( 'it' ),
            'providers'         => array(),
            'seo'               => array(),
            'quote_tracking'    => array(),
        );

        update_option( Settings::OPTION_NAME, $stored );

        $first = Settings::get_options();
        $this->assertSame( 'en', $first['source_language'] );

        $stored['source_language'] = 'es';
        global $wp_test_options;
        $wp_test_options[ Settings::OPTION_NAME ] = $stored;

        $cached = Settings::get_options();
        $this->assertSame( 'en', $cached['source_language'], 'Il valore deve restare invariato finché la cache non viene svuotata.' );

        Settings::clear_cache();
        $updated = Settings::get_options();
        $this->assertSame( 'es', $updated['source_language'] );
    }

    public function test_update_manual_string_sanitizes_and_persists(): void
    {
        $rawKey      = ' My-Key ';
        $rawLanguage = ' IT ';
        $rawValue    = " <strong>Hello</strong><script>alert('x')</script> ";

        Settings::update_manual_string( $rawKey, $rawLanguage, $rawValue );

        $strings = get_option( Settings::MANUAL_STRINGS_OPTION, array() );
        $this->assertArrayHasKey( 'my-key', $strings );
        $this->assertArrayHasKey( 'it', $strings['my-key'] );
        $this->assertSame( '<strong>Hello</strong>', $strings['my-key']['it'] );

        $fallback = get_option( 'fp_multilanguage_strings', array() );
        $this->assertArrayHasKey( 'my-key', $fallback );
        $this->assertArrayHasKey( 'translations', $fallback['my-key'] );
        $this->assertSame( '<strong>Hello</strong>', $fallback['my-key']['translations']['it'] );
    }

    public function test_update_manual_string_removes_translation_when_empty(): void
    {
        $key = 'manual-key';

        Settings::update_manual_string( $key, 'it', 'Valore iniziale' );
        Settings::update_manual_string( $key, 'it', '' );

        $strings = get_option( Settings::MANUAL_STRINGS_OPTION, array() );
        $this->assertArrayNotHasKey( $key, $strings );

        $fallback = get_option( 'fp_multilanguage_strings', array() );
        $this->assertArrayNotHasKey( $key, $fallback );
    }

    public function test_enqueue_assets_localizes_onboarding_data(): void
    {
        global $wp_localized_scripts;

        $wp_localized_scripts = array();

        $this->settings->enqueue_assets( 'settings_page_fp-multilanguage-onboarding' );

        $this->assertArrayHasKey( 'fp-multilanguage-admin', $wp_localized_scripts );
        $this->assertArrayHasKey( 'fpMultilanguageSettings', $wp_localized_scripts['fp-multilanguage-admin'] );
        $this->assertArrayHasKey( 'fpMultilanguageOnboarding', $wp_localized_scripts['fp-multilanguage-admin'] );

        $onboarding = $wp_localized_scripts['fp-multilanguage-admin']['fpMultilanguageOnboarding'];

        $this->assertArrayHasKey( 'i18n', $onboarding );
        $this->assertArrayHasKey( 'validationProviders', $onboarding['i18n'] );
        $this->assertStringContainsString( '%s', $onboarding['i18n']['validationProviders'] );
    }

    public function test_render_onboarding_page_outputs_wizard_markup(): void
    {
        ob_start();
        $this->settings->render_onboarding_page();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString( 'fp-multilanguage-onboarding', $output );
        $this->assertStringContainsString( 'data-step="1"', $output );
        $this->assertStringContainsString( 'data-step="2"', $output );
        $this->assertStringContainsString( 'data-step="3"', $output );
        $this->assertStringContainsString( 'data-summary="providers"', $output );
        $this->assertStringContainsString( 'fp-multilanguage-provider-status', $output );
    }

    public function test_get_manual_strings_catalog_merges_metadata_and_translations(): void
    {
        update_option(
            'fp_multilanguage_strings',
            array(
                'menu-label' => array(
                    'context'    => 'menu|primary',
                    'original'   => 'Menu',
                    'updated_at' => time(),
                ),
            )
        );

        update_option(
            Settings::MANUAL_STRINGS_OPTION,
            array(
                'menu-label' => array( 'it' => 'Menù' ),
                'cta'        => array( 'it' => 'Acquista ora' ),
            )
        );

        $catalog = Settings::get_manual_strings_catalog();

        $this->assertArrayHasKey( 'menu-label', $catalog );
        $this->assertSame( 'Menu', $catalog['menu-label']['original'] );
        $this->assertArrayHasKey( 'it', $catalog['menu-label']['translations'] );
        $this->assertSame( 'Menù', $catalog['menu-label']['translations']['it'] );

        $this->assertArrayHasKey( 'cta', $catalog );
        $this->assertSame( '', $catalog['cta']['context'] );
        $this->assertSame( 'Acquista ora', $catalog['cta']['translations']['it'] );
    }

    public function test_rest_update_settings_requires_valid_nonce(): void
    {
        global $wp_test_nonce_expectations;

        $wp_test_nonce_expectations = array(
            'fp_multilanguage_settings' => array( 'valid-rest-nonce' ),
        );

        $request = new \WP_REST_Request();
        $request->set_json_params(
            array(
                'source_language'   => 'en',
                'fallback_language' => 'en',
                'target_languages'  => array( 'it' ),
                'providers'         => array(),
                'seo'               => array(),
                'quote_tracking'    => array(),
            )
        );

        $response = $this->settings->rest_update_settings( $request );
        $this->assertInstanceOf( \WP_Error::class, $response );
        $this->assertSame( 'invalid_nonce', $response->get_error_code() );

        $request->set_header( 'X-WP-Nonce', 'invalid' );
        $response = $this->settings->rest_update_settings( $request );
        $this->assertInstanceOf( \WP_Error::class, $response );
        $this->assertSame( 'invalid_nonce', $response->get_error_code() );

        $request->set_header( 'X-WP-Nonce', 'valid-rest-nonce' );
        $response = $this->settings->rest_update_settings( $request );
        $this->assertIsArray( $response );
        $this->assertSame( 'en', $response['source_language'] );

        $wp_test_nonce_expectations = array();
    }
}
