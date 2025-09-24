<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
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

        Settings::clear_cache();

        $logger         = new Logger();
        $notices        = new AdminNotices( $logger );
        $this->settings = new Settings( $logger, $notices );
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
}
