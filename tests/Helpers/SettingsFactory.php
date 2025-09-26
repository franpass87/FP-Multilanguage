<?php
namespace FPMultilanguage\Tests\Helpers;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Admin\Settings\ManualStringsUI;
use FPMultilanguage\Admin\Settings\ProviderTester;
use FPMultilanguage\Admin\Settings\Repository as SettingsRepository;
use FPMultilanguage\Admin\Settings\RestController as SettingsRestController;
use FPMultilanguage\Services\Logger;

final class SettingsFactory
{
    public static function create(?Logger $logger = null, ?AdminNotices $notices = null): Settings
    {
        $logger ??= new Logger();
        $notices ??= new AdminNotices($logger);

        $repository     = new SettingsRepository($notices);
        $manualStrings  = new ManualStringsUI($repository, $logger);
        $providerTester = new ProviderTester($logger, $repository);
        $restController = new SettingsRestController($repository, $logger, $notices, $providerTester);

        return new Settings($logger, $notices, $repository, $manualStrings, $restController);
    }
}
