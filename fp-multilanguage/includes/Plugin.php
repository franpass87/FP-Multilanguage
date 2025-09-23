<?php
namespace FPMultilanguage;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Dynamic\DynamicStrings;
use FPMultilanguage\SEO\SEO;
use FPMultilanguage\Services\TranslationService;

/**
 * Core plugin orchestrator.
 */
class Plugin
{
    private static ?Plugin $instance = null;

    private Settings $settings;

    private TranslationService $translationService;

    private PostTranslationManager $postTranslationManager;

    private DynamicStrings $dynamicStrings;

    private SEO $seo;

    private function __construct()
    {
        $this->settings = new Settings();
        $this->translationService = new TranslationService();
        $this->postTranslationManager = new PostTranslationManager($this->translationService, $this->settings);
        $this->dynamicStrings = new DynamicStrings($this->translationService, $this->settings);
        $this->seo = new SEO($this->settings, $this->translationService, $this->postTranslationManager);

        $this->register_hooks();
    }

    public static function instance(): Plugin
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        Settings::bootstrap_defaults();
        TranslationService::flush_cache();
    }

    public static function deactivate(): void
    {
        TranslationService::flush_cache();
    }

    private function register_hooks(): void
    {
        if (function_exists('add_action')) {
            add_action('plugins_loaded', [$this, 'bootstrap']);
        } else {
            $this->bootstrap();
        }
    }

    public function bootstrap(): void
    {
        $this->settings->register();
        $this->postTranslationManager->register();
        $this->dynamicStrings->register();
        $this->seo->register();
    }
}
