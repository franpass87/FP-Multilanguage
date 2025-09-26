<?php
namespace FPMultilanguage;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Admin\Settings\ManualStringsUI;
use FPMultilanguage\Admin\Settings\ProviderTester;
use FPMultilanguage\Admin\Settings\Repository as SettingsRepository;
use FPMultilanguage\Admin\Settings\RestController as SettingsRestController;
use FPMultilanguage\Blocks\LanguageSwitcherBlock;
use FPMultilanguage\CLI\Commands;
use FPMultilanguage\Content\CommentTranslationManager;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Content\TermTranslationManager;
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

                $this->maybe_upgrade();

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

                $commentTranslationManager = $this->container->get( 'comment_translation_manager' );
                if ( $commentTranslationManager instanceof CommentTranslationManager ) {
                        $commentTranslationManager->register();
                }

                $termTranslationManager = $this->container->get( 'term_translation_manager' );
                if ( $termTranslationManager instanceof TermTranslationManager ) {
                        $termTranslationManager->register();
                }

                $dynamicStrings = $this->container->get( 'dynamic_strings' );
                if ( $dynamicStrings instanceof DynamicStrings ) {
                        $dynamicStrings->register();
                }

                $languageSwitcherBlock = $this->container->get( 'language_switcher_block' );
                if ( $languageSwitcherBlock instanceof LanguageSwitcherBlock ) {
                        $languageSwitcherBlock->register();
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

                $instance->maybe_upgrade( true );
        }

	public static function deactivate(): void {
		TranslationService::flush_cache();
	}

	public static function uninstall(): void {
		$instance  = self::instance();
		$container = $instance->get_container();

		if ( ! $container->has( 'migrator' ) ) {
			$instance->register_services();
			$container = $instance->get_container();
		}

		delete_option( Settings::OPTION_NAME );
                delete_option( Settings::MANUAL_STRINGS_OPTION );
                delete_option( 'fp_multilanguage_quota' );
                delete_option( self::VERSION_OPTION );
                delete_option( SEO::SLUG_INDEX_OPTION );
                delete_option( Logger::LOG_STORE_OPTION );

                $migrator = $container->get( 'migrator' );
		if ( $migrator instanceof Migrator ) {
			$migrator->drop_tables();
		}
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
			'settings_repository',
			static function ( Container $c ): SettingsRepository {
				return new SettingsRepository( $c->get( 'notices' ) );
			}
		);

		$container->set(
			'manual_strings_ui',
			static function ( Container $c ): ManualStringsUI {
				return new ManualStringsUI( $c->get( 'settings_repository' ), $c->get( 'logger' ) );
			}
		);

		$container->set(
			'provider_tester',
			static function ( Container $c ): ProviderTester {
				return new ProviderTester( $c->get( 'logger' ), $c->get( 'settings_repository' ) );
			}
		);

		$container->set(
			'settings_rest_controller',
			static function ( Container $c ): SettingsRestController {
				return new SettingsRestController(
					$c->get( 'settings_repository' ),
					$c->get( 'logger' ),
					$c->get( 'notices' ),
					$c->get( 'provider_tester' )
				);
			}
		);

		$container->set(
			'settings',
			static function ( Container $c ): Settings {
				return new Settings(
					$c->get( 'logger' ),
					$c->get( 'notices' ),
					$c->get( 'settings_repository' ),
					$c->get( 'manual_strings_ui' ),
					$c->get( 'settings_rest_controller' )
				);
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
                        'comment_translation_manager',
                        static function ( Container $c ): CommentTranslationManager {
                                return new CommentTranslationManager(
                                        $c->get( 'translation_service' ),
                                        $c->get( 'settings' ),
                                        $c->get( 'notices' ),
                                        $c->get( 'logger' )
                                );
                        }
                );

                $container->set(
                        'term_translation_manager',
                        static function ( Container $c ): TermTranslationManager {
                                return new TermTranslationManager(
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
                                unset( $c );

                                return new LanguageSwitcher();
                        }
                );

                $container->set(
                        'language_switcher_block',
                        static function ( Container $c ): LanguageSwitcherBlock {
                                return new LanguageSwitcherBlock( $c->get( 'language_switcher' ) );
                        }
                );

                $container->set(
                        'cli_commands',
                        static function ( Container $c ): Commands {
                                return new Commands( $c->get( 'post_translation_manager' ), $c->get( 'translation_service' ), $c->get( 'logger' ) );
                        }
                );
        }

        private function maybe_upgrade( bool $force = false ): void {
                if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
                        return;
                }

                $storedVersion = (string) get_option( self::VERSION_OPTION, '' );

                if ( ! $force && '' !== $storedVersion && version_compare( $storedVersion, FP_MULTILANGUAGE_VERSION, '>=' ) ) {
                        return;
                }

                /** @var Migrator $migrator */
                $migrator = $this->container->get( 'migrator' );
                $migrator->maybe_migrate();

                Settings::bootstrap_defaults();
                TranslationService::flush_cache();

                update_option( self::VERSION_OPTION, FP_MULTILANGUAGE_VERSION );

                $logger = $this->container->get( 'logger' );
                if ( $logger instanceof Logger ) {
                        $logger->info(
                                'Plugin upgraded.',
                                array(
                                        'previous_version' => $storedVersion !== '' ? $storedVersion : null,
                                        'current_version'  => FP_MULTILANGUAGE_VERSION,
                                )
                        );
                }
        }
}
