<?php
/**
 * Backward compatibility aliases for PSR-4 refactoring.
 *
 * Maps old class names (\FPML_*) to new namespaced classes.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register new compatibility layer first
if ( class_exists( '\FP\Multilanguage\Compatibility\LegacyAliases' ) ) {
	\FP\Multilanguage\Compatibility\LegacyAliases::register();
}

// Register legacy adapters
use FP\Multilanguage\Compatibility\LegacyPluginAdapter;
use FP\Multilanguage\Compatibility\LegacyContainerAdapter;
use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Core\Plugin;
use FP\Multilanguage\Core\SecureSettings;
use FP\Multilanguage\Core\SettingsMigration;
use FP\Multilanguage\Core\SimpleSettings;
use FP\Multilanguage\Core\TranslationCache;
use FP\Multilanguage\Core\TranslationVersioning;
use FP\Multilanguage\Content\ContentIndexer;
use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Providers\TranslatorInterface;
use FP\Multilanguage\Providers\ProviderOpenAI;
use FP\Multilanguage\Providers\BaseProvider;
use FP\Multilanguage\Diagnostics\Diagnostics;
use FP\Multilanguage\Diagnostics\CostEstimator;
use FP\Multilanguage\Settings;
use FP\Multilanguage\Logger;
use FP\Multilanguage\Queue;
use FP\Multilanguage\Processor;
use FP\Multilanguage\Language;
use FP\Multilanguage\SEO;
use FP\Multilanguage\SEOOptimizer;
use FP\Multilanguage\Rewrites;
use FP\Multilanguage\SiteTranslations;
use FP\Multilanguage\Frontend\Routing\Rewrites as FrontendRewrites;
use FP\Multilanguage\Frontend\Content\SiteTranslations as FrontendSiteTranslations;
use FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget as FrontendLanguageSwitcherWidget;
use FP\Multilanguage\AutoTranslate;
use FP\Multilanguage\AutoDetection;
use FP\Multilanguage\AutoRelink;
use FP\Multilanguage\Glossary;
use FP\Multilanguage\MenuSync;
use FP\Multilanguage\FeaturedImageSync;
use FP\Multilanguage\MediaFront;
use FP\Multilanguage\ExportImport;
use FP\Multilanguage\StringsScanner;
use FP\Multilanguage\StringsOverride;
use FP\Multilanguage\ContentDiff;
use FP\Multilanguage\Webhooks;
use FP\Multilanguage\HealthCheck;
use FP\Multilanguage\DashboardWidget;
use FP\Multilanguage\RushMode;
use FP\Multilanguage\RateLimiter;
use FP\Multilanguage\ProviderFallback;
use FP\Multilanguage\ACFSupport;
use FP\Multilanguage\PluginDetector;
use FP\Multilanguage\ThemeCompatibility;
use FP\Multilanguage\Admin\Admin;
use FP\Multilanguage\Admin\Pages\PageRenderer;
use FP\Multilanguage\Admin\Ajax\AjaxHandlers;
use FP\Multilanguage\Rest\RestAdmin;
use FP\Multilanguage\Rest\Controllers\AdminController as RestAdminController;
use FP\Multilanguage\CLI\CLI;

// Core aliases - Use adapters for backward compatibility
// Try new Kernel first, fallback to old Core
if ( ! class_exists( '\FPML_Container' ) ) {
	if ( class_exists( '\FP\Multilanguage\Kernel\Container' ) ) {
		// Use adapter that delegates to new Kernel
		class_alias( LegacyContainerAdapter::class, '\FPML_Container' );
	} else {
		class_alias( Container::class, '\FPML_Container' );
	}
}

if ( ! class_exists( '\FPML_Plugin' ) ) {
	if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
		// Use adapter that delegates to new Kernel
		class_alias( LegacyPluginAdapter::class, '\FPML_Plugin' );
	} else {
		class_alias( Plugin::class, '\FPML_Plugin' );
	}
}
if ( ! class_exists( '\FPML_Secure_Settings' ) ) {
	class_alias( SecureSettings::class, '\FPML_Secure_Settings' );
}
if ( ! class_exists( '\FPML_Settings_Migration' ) ) {
	class_alias( SettingsMigration::class, '\FPML_Settings_Migration' );
}
if ( ! class_exists( '\FPML_Simple_Settings' ) ) {
	class_alias( SimpleSettings::class, '\FPML_Simple_Settings' );
}
if ( ! class_exists( '\FPML_Translation_Cache' ) ) {
	class_alias( TranslationCache::class, '\FPML_Translation_Cache' );
}
if ( ! class_exists( '\FPML_Translation_Versioning' ) ) {
	class_alias( TranslationVersioning::class, '\FPML_Translation_Versioning' );
}

// Content aliases
if ( ! class_exists( '\FPML_Content_Indexer' ) ) {
	class_alias( ContentIndexer::class, '\FPML_Content_Indexer' );
}
if ( ! class_exists( '\FPML_Translation_Manager' ) ) {
	class_alias( TranslationManager::class, '\FPML_Translation_Manager' );
}

// Translation aliases
if ( ! class_exists( '\FPML_Job_Enqueuer' ) ) {
	class_alias( JobEnqueuer::class, '\FPML_Job_Enqueuer' );
}

// Provider aliases
if ( ! class_exists( 'Translator_Interface' ) ) {
	class_alias( TranslatorInterface::class, 'Translator_Interface' );
}
if ( ! class_exists( '\FPML_Provider_OpenAI' ) ) {
	class_alias( ProviderOpenAI::class, '\FPML_Provider_OpenAI' );
}
if ( ! class_exists( '\FPML_Base_Provider' ) ) {
	class_alias( BaseProvider::class, '\FPML_Base_Provider' );
}

// Diagnostic aliases
if ( ! class_exists( '\FPML_Diagnostics' ) ) {
	class_alias( Diagnostics::class, '\FPML_Diagnostics' );
}
if ( ! class_exists( '\FPML_Cost_Estimator' ) ) {
	class_alias( CostEstimator::class, '\FPML_Cost_Estimator' );
}

// Main classes aliases - Use adapters when available for Foundation services
if ( ! class_exists( '\FPML_Settings' ) ) {
	if ( class_exists( '\FP\Multilanguage\Foundation\Options\SettingsAdapter' ) ) {
		class_alias( \FP\Multilanguage\Foundation\Options\SettingsAdapter::class, '\FPML_Settings' );
	} else {
		class_alias( Settings::class, '\FPML_Settings' );
	}
}

if ( ! class_exists( '\FPML_Logger' ) ) {
	if ( class_exists( '\FP\Multilanguage\Foundation\Logger\LoggerAdapter' ) ) {
		class_alias( \FP\Multilanguage\Foundation\Logger\LoggerAdapter::class, '\FPML_Logger' );
	} else {
		class_alias( Logger::class, '\FPML_Logger' );
	}
}
if ( ! class_exists( '\FPML_Queue' ) ) {
	class_alias( Queue::class, '\FPML_Queue' );
}
if ( ! class_exists( '\FPML_Processor' ) && class_exists( Processor::class ) ) {
	class_alias( Processor::class, '\FPML_Processor' );
}
if ( ! class_exists( '\FPML_Language' ) ) {
	class_alias( Language::class, '\FPML_Language' );
}
if ( ! class_exists( '\FPML_SEO' ) ) {
	class_alias( SEO::class, '\FPML_SEO' );
}
if ( ! class_exists( '\FPML_SEO_Optimizer' ) ) {
	class_alias( SEOOptimizer::class, '\FPML_SEO_Optimizer' );
}
// Rewrites - support both old and new locations
if ( ! class_exists( '\FPML_Rewrites' ) ) {
	if ( class_exists( '\FP\Multilanguage\Frontend\Routing\Rewrites' ) ) {
		class_alias( FrontendRewrites::class, '\FPML_Rewrites' );
	} else {
		class_alias( Rewrites::class, '\FPML_Rewrites' );
	}
}

// Routing classes - backward compatibility aliases
if ( ! class_exists( '\FP\Multilanguage\Routing\RewriteRules' ) ) {
	class_alias( \FP\Multilanguage\Frontend\Routing\RewriteRules::class, '\FP\Multilanguage\Routing\RewriteRules' );
}
if ( ! class_exists( '\FP\Multilanguage\Routing\QueryFilter' ) ) {
	class_alias( \FP\Multilanguage\Frontend\Routing\QueryFilter::class, '\FP\Multilanguage\Routing\QueryFilter' );
}
if ( ! class_exists( '\FP\Multilanguage\Routing\PostResolver' ) ) {
	class_alias( \FP\Multilanguage\Frontend\Routing\PostResolver::class, '\FP\Multilanguage\Routing\PostResolver' );
}
if ( ! class_exists( '\FP\Multilanguage\Routing\RequestHandler' ) ) {
	class_alias( \FP\Multilanguage\Frontend\Routing\RequestHandler::class, '\FP\Multilanguage\Routing\RequestHandler' );
}
if ( ! class_exists( '\FP\Multilanguage\Routing\AdjacentPostFilter' ) ) {
	class_alias( \FP\Multilanguage\Frontend\Routing\AdjacentPostFilter::class, '\FP\Multilanguage\Routing\AdjacentPostFilter' );
}
// SiteTranslations - support both old and new locations
if ( ! class_exists( '\FPML_Site_Translations' ) ) {
	if ( class_exists( '\FP\Multilanguage\Frontend\Content\SiteTranslations' ) ) {
		class_alias( FrontendSiteTranslations::class, '\FPML_Site_Translations' );
	} else {
		class_alias( SiteTranslations::class, '\FPML_Site_Translations' );
	}
}
if ( ! class_exists( '\FPML_Auto_Translate' ) ) {
	class_alias( AutoTranslate::class, '\FPML_Auto_Translate' );
}
if ( ! class_exists( '\FPML_Auto_Detection' ) ) {
	class_alias( AutoDetection::class, '\FPML_Auto_Detection' );
}
if ( ! class_exists( '\FPML_Auto_Relink' ) ) {
	class_alias( AutoRelink::class, '\FPML_Auto_Relink' );
}
if ( ! class_exists( '\FPML_Glossary' ) ) {
	class_alias( Glossary::class, '\FPML_Glossary' );
}
if ( ! class_exists( '\FPML_Menu_Sync' ) ) {
	class_alias( MenuSync::class, '\FPML_Menu_Sync' );
}
if ( ! class_exists( '\FPML_Featured_Image_Sync' ) ) {
	class_alias( FeaturedImageSync::class, '\FPML_Featured_Image_Sync' );
}
if ( ! class_exists( '\FPML_Media_Front' ) ) {
	class_alias( MediaFront::class, '\FPML_Media_Front' );
}
if ( ! class_exists( '\FPML_Export_Import' ) ) {
	class_alias( ExportImport::class, '\FPML_Export_Import' );
}
if ( ! class_exists( '\FPML_Strings_Scanner' ) ) {
	class_alias( StringsScanner::class, '\FPML_Strings_Scanner' );
}
if ( ! class_exists( '\FPML_Strings_Override' ) ) {
	class_alias( StringsOverride::class, '\FPML_Strings_Override' );
}
if ( ! class_exists( '\FPML_Content_Diff' ) ) {
	class_alias( ContentDiff::class, '\FPML_Content_Diff' );
}
if ( ! class_exists( '\FPML_Webhooks' ) ) {
	class_alias( Webhooks::class, '\FPML_Webhooks' );
}
if ( ! class_exists( '\FPML_Health_Check' ) ) {
	class_alias( HealthCheck::class, '\FPML_Health_Check' );
}
if ( ! class_exists( '\FPML_Dashboard_Widget' ) ) {
	class_alias( DashboardWidget::class, '\FPML_Dashboard_Widget' );
}
// LanguageSwitcherWidget - use Frontend\Widgets location (old location removed)
if ( ! class_exists( '\FPML_Language_Switcher_Widget' ) ) {
	if ( class_exists( '\FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget' ) ) {
		class_alias( FrontendLanguageSwitcherWidget::class, '\FPML_Language_Switcher_Widget' );
	}
}
if ( ! class_exists( '\FPML_Rush_Mode' ) ) {
	class_alias( RushMode::class, '\FPML_Rush_Mode' );
}
if ( ! class_exists( '\FPML_Rate_Limiter' ) ) {
	class_alias( RateLimiter::class, '\FPML_Rate_Limiter' );
}
if ( ! class_exists( '\FPML_Provider_Fallback' ) ) {
	class_alias( ProviderFallback::class, '\FPML_Provider_Fallback' );
}
if ( ! class_exists( '\FPML_ACF_Support' ) ) {
	class_alias( ACFSupport::class, '\FPML_ACF_Support' );
}
if ( ! class_exists( '\FPML_Plugin_Detector' ) ) {
	class_alias( PluginDetector::class, '\FPML_Plugin_Detector' );
}
if ( ! class_exists( '\FPML_Theme_Compatibility' ) ) {
	class_alias( ThemeCompatibility::class, '\FPML_Theme_Compatibility' );
}

// Component aliases
if ( ! class_exists( '\FPML_Admin' ) ) {
	class_alias( Admin::class, '\FPML_Admin' );
}
// Admin sub-components - support both old and new locations
if ( ! class_exists( '\FPML_Admin_Page_Renderer' ) ) {
	if ( class_exists( '\FP\Multilanguage\Admin\Pages\PageRenderer' ) ) {
		class_alias( PageRenderer::class, '\FPML_Admin_Page_Renderer' );
		// Also alias old location if it exists
		if ( class_exists( '\FP\Multilanguage\Admin\PageRenderer' ) && ! class_exists( '\FPML_Admin_Page_Renderer_Legacy' ) ) {
			class_alias( '\FP\Multilanguage\Admin\PageRenderer', '\FPML_Admin_Page_Renderer_Legacy' );
		}
	}
}
if ( ! class_exists( '\FPML_Admin_Ajax_Handlers' ) ) {
	if ( class_exists( '\FP\Multilanguage\Admin\Ajax\AjaxHandlers' ) ) {
		class_alias( AjaxHandlers::class, '\FPML_Admin_Ajax_Handlers' );
		// Also alias old location if it exists
		if ( class_exists( '\FP\Multilanguage\Admin\AjaxHandlers' ) && ! class_exists( '\FPML_Admin_Ajax_Handlers_Legacy' ) ) {
			class_alias( '\FP\Multilanguage\Admin\AjaxHandlers', '\FPML_Admin_Ajax_Handlers_Legacy' );
		}
	}
}
// RestAdmin - support both old and new locations
if ( ! class_exists( '\FPML_REST_Admin' ) ) {
	if ( class_exists( '\FP\Multilanguage\Rest\Controllers\AdminController' ) ) {
		class_alias( RestAdminController::class, '\FPML_REST_Admin' );
	} else {
		class_alias( RestAdmin::class, '\FPML_REST_Admin' );
	}
}
if ( ! class_exists( '\FPML_CLI' ) ) {
	class_alias( CLI::class, '\FPML_CLI' );
}

// Note: Nuove classi v0.5.0+ usano solo namespace moderno (no alias backward)


