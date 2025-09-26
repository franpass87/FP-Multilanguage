<?php
namespace FPMultilanguage\Tests;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Admin\Settings\Repository as SettingsRepository;
use FPMultilanguage\Install\UpgradeManager;
use FPMultilanguage\Services\Logger;
use PHPUnit\Framework\TestCase;

class UpgradeManagerTest extends TestCase
{
    private UpgradeManager $upgradeManager;

    private SettingsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_test_options, $wp_test_cache, $wp_test_transients, $wp_cache_flush_calls;

        $wp_test_options    = array();
        $wp_test_cache      = array();
        $wp_test_transients = array();
        $wp_cache_flush_calls = 0;

        Logger::clear_stored_entries();
        Settings::clear_cache();

        $logger         = new Logger();
        $notices        = new AdminNotices( $logger );
        $this->repository = new SettingsRepository( $notices );
        $this->upgradeManager = new UpgradeManager( $this->repository, $logger );
    }

    public function test_run_flushes_caches_and_creates_manual_store(): void
    {
        global $wp_cache_flush_calls, $wp_test_cache;

        $this->repository->bootstrap_defaults();
        $this->repository->get_options();
        $this->repository->get_manual_strings_catalog();

        update_option( SettingsRepository::MANUAL_STRINGS_FALLBACK_OPTION, false );
        update_option( SettingsRepository::MANUAL_STRINGS_OPTION, array( 'greeting' => array( 'it' => 'Ciao' ) ) );
        wp_cache_set( 'custom', 'value', 'fp_multilanguage_settings' );

        $this->upgradeManager->run( '1.0.0' );

        $this->assertSame( 1, $wp_cache_flush_calls, 'Object cache must be flushed during upgrade.' );
        $this->assertSame( array(), $wp_test_cache, 'Cache store should be cleared after flush.' );

        $manualStrings = get_option( SettingsRepository::MANUAL_STRINGS_OPTION );
        $this->assertIsArray( $manualStrings );
        $this->assertArrayHasKey( 'greeting', $manualStrings, 'Existing manual strings must be preserved.' );

        $fallback = get_option( SettingsRepository::MANUAL_STRINGS_FALLBACK_OPTION );
        $this->assertIsArray( $fallback );
        $this->assertSame( array(), $fallback, 'Fallback store should be initialised as an empty array.' );

        $logEntries = Logger::get_stored_entries();
        $this->assertNotEmpty( $logEntries, 'Upgrade completion should be logged.' );
        $lastEntry = $logEntries[ count( $logEntries ) - 1 ];
        $this->assertSame( 'info', $lastEntry['level'] );
        $this->assertStringContainsString( 'Upgrade routine completed', $lastEntry['message'] );
    }
}
