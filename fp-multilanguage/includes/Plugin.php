<?php
namespace FPMultilanguage;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CLI\Commands;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Dynamic\DynamicStrings;
use FPMultilanguage\Install\Migrator;
use FPMultilanguage\SEO\SEO;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use FPMultilanguage\Services\Providers\DeepLProvider;
use FPMultilanguage\Services\Providers\GoogleProvider;
use FPMultilanguage\Support\Container;
use FPMultilanguage\Widgets\LanguageSwitcher;
use RuntimeException;

class Plugin {

	private const VERSION_OPTION = 'fp_multilanguage_version';

	private static ?Plugin $instance = null;

	private Container $container;

	private bool $bootstrapped = false;

	private function __construct() {
		$this->container = new Container();
	}

	public static function instance(): Plugin {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		$this->register_services();

				add_action( 'plugins_loaded', array( $this, 'bootstrap' ), 5 );
				add_action( 'init', array( $this, 'load_textdomain' ), 1 );
				add_action( 'template_redirect', array( $this, 'remember_language_preference' ), 1 );
				add_action( 'widgets_init', array( $this, 'register_widgets' ) );
				add_shortcode( 'fp_language_switcher', array( $this, 'render_language_switcher' ) );
	}

	public function bootstrap(): void {
		if ( $this->bootstrapped ) {
			return;
		}

		$settings = $this->container->get( 'settings' );
		if ( ! $settings instanceof Settings ) {
			throw new RuntimeException( 'Unable to bootstrap plugin: invalid settings service.' );
		}

		$settings->register();

		$notices = $this->container->get( 'notices' );
		if ( $notices instanceof AdminNotices ) {
			$notices->register();
		}

		$translationService = $this->container->get( 'translation_service' );
		if ( $translationService instanceof TranslationService ) {
			$translationService->register();
		}

		$postTranslationManager = $this->container->get( 'post_translation_manager' );
		if ( $postTranslationManager instanceof PostTranslationManager ) {
			$postTranslationManager->register();
		}

		$dynamicStrings = $this->container->get( 'dynamic_strings' );
		if ( $dynamicStrings instanceof DynamicStrings ) {
			$dynamicStrings->register();
		}

		$seo = $this->container->get( 'seo' );
		if ( $seo instanceof SEO ) {
			$seo->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$commands = $this->container->get( 'cli_commands' );
			if ( $commands instanceof Commands ) {
				$commands->register();
			}
		}

		$this->bootstrapped = true;
	}

	public function load_textdomain(): void {
		$pluginFile = defined( 'FP_MULTILANGUAGE_FILE' )
			? FP_MULTILANGUAGE_FILE
			: FP_MULTILANGUAGE_PATH . 'fp-multilanguage.php';

		load_plugin_textdomain( 'fp-multilanguage', false, dirname( plugin_basename( $pluginFile ) ) . '/languages' );
	}

	public function register_widgets(): void {
		register_widget( LanguageSwitcher::class );
	}

	public function render_language_switcher( array $atts = array() ): string {
		$widget = $this->container->get( 'language_switcher' );
		if ( ! $widget instanceof LanguageSwitcher ) {
				return '';
		}

				return $widget->render_shortcode( $atts );
	}

	public function remember_language_preference(): void {
		if ( is_admin() ) {
			return;
		}

		$language = CurrentLanguage::resolve();
		if ( '' === $language ) {
			return;
		}

				$allowed_languages = array_merge(
					array( Settings::get_source_language() ),
					Settings::get_target_languages()
				);
				$allowed_languages = array_unique(
					array_map(
						static function ( string $language ): string {
										$language = strtolower( $language );
										$language = str_replace( array( ' ', '_' ), '-', $language );

										return trim( preg_replace( '/[^a-z0-9-]/', '', $language ) ?? '', '-' );
						},
						$allowed_languages
					)
				);

		if ( ! in_array( $language, $allowed_languages, true ) ) {
				return;
		}

		$remembered = '';
		if ( isset( $_COOKIE['fp_multilanguage_lang'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			$remembered = sanitize_key( (string) $_COOKIE['fp_multilanguage_lang'] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		}

		if ( $remembered === $language ) {
			return;
		}

		CurrentLanguage::remember( $language );
	}

	public function get_container(): Container {
			return $this->container;
	}

	public static function activate(): void {
		$instance = self::instance();
		$instance->register_services();

		/** @var Migrator $migrator */
		$migrator = $instance->container->get( 'migrator' );
		$migrator->maybe_migrate();

		Settings::bootstrap_defaults();

		update_option( self::VERSION_OPTION, FP_MULTILANGUAGE_VERSION );
	}

	public static function deactivate(): void {
		TranslationService::flush_cache();
	}

	public static function uninstall(): void {
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::MANUAL_STRINGS_OPTION );
		delete_option( 'fp_multilanguage_quota' );
		delete_option( self::VERSION_OPTION );
		delete_option( SEO::SLUG_INDEX_OPTION );

		/** @var Migrator $migrator */
		$migrator = self::instance()->container->get( 'migrator' );
		$migrator->drop_tables();
	}

	private function register_services(): void {
		$container = $this->container;

		if ( ! $container->has( 'logger' ) ) {
			$container->set(
				'logger',
				static function ( Container $container ): Logger {
					unset( $container );

					return new Logger();
				}
			);
		}

		$container->set(
			'notices',
			static function ( Container $c ): AdminNotices {
				return new AdminNotices( $c->get( 'logger' ) );
			}
		);

		$container->set(
			'settings',
			static function ( Container $c ): Settings {
				return new Settings( $c->get( 'logger' ), $c->get( 'notices' ) );
			}
		);

		$container->set(
			'translation_service',
			static function ( Container $c ): TranslationService {
				$providers = array(
					'google' => new GoogleProvider( $c->get( 'logger' ) ),
					'deepl'  => new DeepLProvider( $c->get( 'logger' ) ),
				);

				return new TranslationService( $c->get( 'logger' ), $c->get( 'notices' ), $c->get( 'settings' ), $providers );
			}
		);

		$container->set(
			'post_translation_manager',
			static function ( Container $c ): PostTranslationManager {
				return new PostTranslationManager(
					$c->get( 'translation_service' ),
					$c->get( 'settings' ),
					$c->get( 'notices' ),
					$c->get( 'logger' )
				);
			}
		);

		$container->set(
			'dynamic_strings',
			static function ( Container $c ): DynamicStrings {
				return new DynamicStrings(
					$c->get( 'translation_service' ),
					$c->get( 'settings' ),
					$c->get( 'notices' ),
					$c->get( 'logger' )
				);
			}
		);

		$container->set(
			'seo',
			static function ( Container $c ): SEO {
				return new SEO(
					$c->get( 'settings' ),
					$c->get( 'translation_service' ),
					$c->get( 'post_translation_manager' ),
					$c->get( 'logger' )
				);
			}
		);

		$container->set(
			'migrator',
			static function ( Container $c ): Migrator {
				$migrator = new Migrator();
				$migrator->set_logger( $c->get( 'logger' ) );

				return $migrator;
			}
		);

		$container->set(
			'language_switcher',
			static function ( Container $c ): LanguageSwitcher {
				return new LanguageSwitcher();
			}
		);

		$container->set(
			'cli_commands',
			static function ( Container $c ): Commands {
				return new Commands( $c->get( 'post_translation_manager' ), $c->get( 'translation_service' ), $c->get( 'logger' ) );
			}
		);
	}
}
