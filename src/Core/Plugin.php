<?php
/**
 * Core plugin bootstrap - Refactored version.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */


namespace FP\Multilanguage\Core;

use FP\Multilanguage\Settings;
use FP\Multilanguage\Queue;
use FP\Multilanguage\Logger;
use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Core\PostHandlers;
use FP\Multilanguage\Core\TermHandlers;
use FP\Multilanguage\Core\ContentHandlers;
use FP\Multilanguage\Core\HookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class - Simplified with delegation to specialized services.
 *
 * @since 0.4.0
 * @deprecated 1.0.0 Use Kernel\Plugin instead. This class is kept for backward compatibility only.
 * 
 * This class will be removed in version 1.1.0. Please migrate to Kernel\Plugin.
 */
class PluginCore {
	/**
	 * Option tracking completed migrations.
	 */
	const OPTION_AUTOLOAD_MIGRATED = '\FPML_options_autoload_migrated';

	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Plugin_Core|null
	 */
	protected static $instance = null;

	/**
	 * Cached settings instance.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Cached queue handler.
	 *
	 * @var Queue
	 */
	protected $queue;

	/**
	 * Cached logger.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Translation manager instance.
	 *
	 * @var TranslationManager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer instance.
	 *
	 * @var JobEnqueuer
	 */
	protected $job_enqueuer;

	/**
	 * Hook manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var HookManager
	 */
	protected $hook_manager;

	/**
	 * Whether the plugin is running in assisted mode (WPML/Polylang active).
	 *
	 * @var bool
	 */
	protected $assisted_mode = false;

	/**
	 * Identifier of the multilingual plugin triggering assisted mode.
	 *
	 * @var string
	 */
	protected $assisted_reason = '';

	/**
	 * Assisted mode service instance.
	 *
	 * @var \FP\Multilanguage\Core\Services\AssistedModeService|null
	 */
	protected $assisted_mode_service = null;

	/**
	 * Dependency resolver instance.
	 *
	 * @var \FP\Multilanguage\Core\Services\DependencyResolver|null
	 */
	protected $dependency_resolver = null;

	/**
	 * Plugin constructor - TEST 5C: + define_hooks [CRITICO].
	 * 
	 * @deprecated 1.0.0 Use Kernel\Plugin instead
	 */
	protected function __construct() {
		// Show deprecation notice in admin
		if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
			add_action( 'admin_notices', function() {
				static $notice_shown = false;
				if ( ! $notice_shown ) {
					$notice_shown = true;
					echo '<div class="notice notice-warning"><p>';
					echo '<strong>FP Multilanguage:</strong> ';
					echo esc_html__( 'Core\Plugin is deprecated. The plugin now uses Kernel\Plugin. This notice will be removed in version 1.1.0.', 'fp-multilanguage' );
					echo '</p></div>';
				}
			}, 999 );
		}
		
		// Initialize services
		$this->initialize_services();
		
		$this->detect_assisted_mode();
		
		// Try new Kernel container first, fallback to old Core container, then singleton
		$container = $this->getContainer();
		
		// Use DependencyResolver if available, otherwise fallback to old pattern
		if ( $this->dependency_resolver ) {
			$this->settings = $this->dependency_resolver->resolve( 'options', Settings::class );
			$this->queue = $this->dependency_resolver->resolve( 'queue', Queue::class );
			$this->logger = $this->dependency_resolver->resolve( 'logger', Logger::class );
			$this->translation_manager = $this->dependency_resolver->resolve( 'translation.manager', TranslationManager::class );
			$this->job_enqueuer = $this->dependency_resolver->resolve( 'translation.job_enqueuer', JobEnqueuer::class );
		} else {
			// Fallback to old pattern
			$this->settings = $container ? ( $container->has( 'options' ) ? $container->get( 'options' ) : null ) : null;
			$this->settings = $this->settings ?: Container::get( 'settings' ) ?: Settings::instance();
			
			$this->queue = $container ? ( $container->has( 'queue' ) ? $container->get( 'queue' ) : null ) : null;
			$this->queue = $this->queue ?: Container::get( 'queue' ) ?: fpml_get_queue();
			
			$this->logger = $container ? ( $container->has( 'logger' ) ? $container->get( 'logger' ) : null ) : null;
			$this->logger = $this->logger ?: Container::get( 'logger' ) ?: fpml_get_logger();
			
			$this->translation_manager = $container ? ( $container->has( 'translation.manager' ) ? $container->get( 'translation.manager' ) : null ) : null;
			$this->translation_manager = $this->translation_manager ?: Container::get( 'translation_manager' ) ?: ( class_exists( TranslationManager::class ) ? fpml_get_translation_manager() : null );
			
			$this->job_enqueuer = $container ? ( $container->has( 'translation.job_enqueuer' ) ? $container->get( 'translation.job_enqueuer' ) : null ) : null;
			$this->job_enqueuer = $this->job_enqueuer ?: Container::get( 'job_enqueuer' ) ?: ( class_exists( JobEnqueuer::class ) ? fpml_get_job_enqueuer() : null );
		}
		
		// Initialize hook manager
		$this->hook_manager = new HookManager( $this, $this->assisted_mode );
		
		// Initialize settings migration
		$settings_migration = Container::get( 'settings_migration' );
		if ( $settings_migration ) {
			// Migration service is now initialized and will handle backup/restore
		}
		
		// Initialize database migration
		$database_migration = Container::get( 'database_migration' );
		if ( $database_migration ) {
			// Database migration service is now initialized and will check/run migrations on admin_init
		}
		
		if ( $this->queue && method_exists( $this->queue, 'maybe_upgrade' ) ) {
			$this->queue->maybe_upgrade();
		}
		
		$this->maybe_disable_autoloaded_options();
		
		// TEST 5C: Aggiungi define_hooks - QUESTA È SOSPETTA!
		$this->define_hooks();
		
		// Run setup if needed (includes rewrite rules registration)
		add_action( 'init', array( $this, 'maybe_run_setup' ), 5 );
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.2.0
	 *
	 * @return \FPML_Plugin_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize services.
	 *
	 * @return void
	 */
	protected function initialize_services() {
		$container = $this->getContainer();
		
		// Initialize AssistedModeService
		if ( $container && $container->has( 'service.assisted_mode' ) ) {
			$this->assisted_mode_service = $container->get( 'service.assisted_mode' );
		} elseif ( class_exists( '\FP\Multilanguage\Core\Services\AssistedModeService' ) ) {
			$this->assisted_mode_service = new \FP\Multilanguage\Core\Services\AssistedModeService();
		}
		
		// Initialize DependencyResolver
		if ( $container && $container->has( 'service.dependency_resolver' ) ) {
			$this->dependency_resolver = $container->get( 'service.dependency_resolver' );
		} elseif ( class_exists( '\FP\Multilanguage\Core\Services\DependencyResolver' ) ) {
			$this->dependency_resolver = new \FP\Multilanguage\Core\Services\DependencyResolver( $container );
		}
		
		// Initialize LoopProtectionService
		if ( $container && $container->has( 'service.loop_protection' ) ) {
			$this->loop_protection_service = $container->get( 'service.loop_protection' );
		} elseif ( class_exists( '\FP\Multilanguage\Core\Services\LoopProtectionService' ) ) {
			$this->loop_protection_service = new \FP\Multilanguage\Core\Services\LoopProtectionService();
		}
	}

	/**
	 * Get container instance (new Kernel container if available, null otherwise).
	 *
	 * @return \FP\Multilanguage\Kernel\Container|null
	 */
	protected function getContainer() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				return $kernel->getContainer();
			}
		}
		return null;
	}

	/**
	 * Run setup tasks if needed (safe - called after everything is loaded).
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function maybe_run_setup() {
		// Use SetupService if available
		$setup_service = $this->getSetupService();
		if ( $setup_service ) {
			$setup_service->runIfNeeded();
			return;
		}

		// Fallback to old logic
		// Check if setup is needed
		if ( ! get_option( '\FPML_needs_setup' ) ) {
			return;
		}

		// Check if already completed
		if ( get_option( '\FPML_setup_completed' ) ) {
			delete_option( '\FPML_needs_setup' );
			return;
		}

		// Now it's safe to run setup tasks
		try {
			// Trigger settings restoration after initialization
			do_action( '\FPML_after_initialization' );
			
			// Use AssistedModeService if available
			$reason = '';
			if ( $this->assisted_mode_service ) {
				$reason = $this->assisted_mode_service->detect();
			} else {
				$reason = self::detect_external_multilingual();
			}

			// Register rewrites if not in assisted mode
			if ( ! $reason && class_exists( '\FPML_Rewrites' ) ) {
				$rewrites = ( function_exists( 'fpml_get_rewrites' ) ? fpml_get_rewrites() : \FPML_Rewrites::instance() );
				if ( $rewrites && method_exists( $rewrites, 'register_rewrites' ) ) {
					$rewrites->register_rewrites();
				}
			}

			// Install queue tables
			if ( $this->queue && method_exists( $this->queue, 'install' ) ) {
				$this->queue->install();
			}

			// Flush rewrite rules
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules();
			}

			// Mark as completed
			update_option( '\FPML_setup_completed', '1', false );
			delete_option( '\FPML_needs_setup' );
		} catch ( \Exception $e ) {
			// Log error but don't break the site
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'Plugin setup error', array( 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ) );
			}
		}
	}

	/**
	 * Get SetupService instance.
	 *
	 * @return \FP\Multilanguage\Core\Services\SetupService|null
	 */
	protected function getSetupService() {
		$container = $this->getContainer();
		if ( $container && $container->has( 'service.setup' ) ) {
			return $container->get( 'service.setup' );
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\SetupService' ) ) {
			return new \FP\Multilanguage\Core\Services\SetupService();
		}
		
		return null;
	}

	/**
	 * Plugin activation callback.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function activate() {
		// Use SetupService if available
		$setup_service = self::getSetupServiceStatic();
		if ( $setup_service ) {
			$setup_service->handleActivation();
			return;
		}

		// Fallback to old logic
		// Trigger settings backup before activation
		do_action( '\FPML_before_activation' );
		
		// SAFE ACTIVATION: Just set a flag, do nothing else
		// Actual setup will happen on first use
		update_option( '\FPML_needs_setup', '1', false );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Use SetupService if available
		$setup_service = self::getSetupServiceStatic();
		if ( $setup_service ) {
			$setup_service->handleDeactivation();
			return;
		}

		// Fallback to old logic
		// Clear all scheduled events
		$events = array(
			'\FPML_run_queue',
			'\FPML_retry_failed',
			'\FPML_resync_outdated',
			'\FPML_cleanup_queue',
			'\FPML_daily_content_scan',
			'\FPML_health_check',
		);
		
		foreach ( $events as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			while ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
		
		// Clear single events with args (WordPress 5.1+)
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( '\FPML_reindex_post_type' );
			wp_unschedule_hook( '\FPML_reindex_taxonomy' );
		}
		
		flush_rewrite_rules();
	}

	/**
	 * Get SetupService instance (static method for activation/deactivation).
	 *
	 * @return \FP\Multilanguage\Core\Services\SetupService|null
	 */
	protected static function getSetupServiceStatic() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'service.setup' ) ) {
					return $container->get( 'service.setup' );
				}
			}
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\SetupService' ) ) {
			return new \FP\Multilanguage\Core\Services\SetupService();
		}
		
		return null;
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'fp-multilanguage', false, dirname( plugin_basename( \FPML_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Detect active multilingual plugins that require assisted mode.
	 *
	 * @since 0.2.0
	 *
	 * @return string Empty string when no external plugin is detected, otherwise the identifier.
	 */
	protected static function detect_external_multilingual() {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) ) {
			return 'wpml';
		}

		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
			return 'polylang';
		}

		return '';
	}

	/**
	 * Detect whether the plugin should operate in assisted mode.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function detect_assisted_mode() {
		// Use AssistedModeService if available
		if ( $this->assisted_mode_service ) {
			$reason = $this->assisted_mode_service->detect();
			if ( $reason ) {
				$this->assisted_mode   = true;
				$this->assisted_reason = $reason;
			}
			return;
		}
		
		// Fallback to old logic
		$reason = self::detect_external_multilingual();

		if ( $reason ) {
			$this->assisted_mode   = true;
			$this->assisted_reason = $reason;
		}
	}

	/**
	 * Check if assisted mode is active.
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public function is_assisted_mode() {
		// Use AssistedModeService if available
		if ( $this->assisted_mode_service ) {
			return $this->assisted_mode_service->isActive();
		}
		
		// Fallback to old property
		return (bool) $this->assisted_mode;
	}

	/**
	 * Retrieve the assisted mode reason identifier.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_assisted_reason() {
		// Use AssistedModeService if available
		if ( $this->assisted_mode_service ) {
			return $this->assisted_mode_service->getReason();
		}
		
		// Fallback to old property
		return $this->assisted_reason;
	}

	/**
	 * Get a human readable label for the assisted mode reason.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_assisted_reason_label() {
		// Use AssistedModeService if available
		if ( $this->assisted_mode_service ) {
			return $this->assisted_mode_service->getReasonLabel();
		}
		
		// Fallback to old switch
		switch ( $this->assisted_reason ) {
			case 'wpml':
				return 'WPML';
			case 'polylang':
				return 'Polylang';
			default:
				return '';
		}
	}

	/**
	 * Define hooks and bootstrap classes - VERSIONE SICURA.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function define_hooks() {
		$this->hook_manager->define_hooks();
	}

	/**
	 * Ensure heavy options are stored without autoload.
	 *
	 * @since 0.3.2
	 *
	 * @return void
	 */
	protected function maybe_disable_autoloaded_options() {
		$migrated = get_option( self::OPTION_AUTOLOAD_MIGRATED );

		if ( $migrated ) {
			return;
		}

		$options = array();

		if ( class_exists( '\FPML_Strings_Scanner' ) ) {
			$options[] = \FPML_Strings_Scanner::OPTION_KEY;
		}

		if ( class_exists( '\FPML_Strings_Override' ) ) {
			$options[] = \FPML_Strings_Override::OPTION_KEY;
		}

		if ( class_exists( '\FPML_Glossary' ) ) {
			$options[] = \FPML_Glossary::OPTION_KEY;
		}

		foreach ( array_filter( array_unique( $options ) ) as $option ) {
			$value = get_option( $option, null );

			if ( null === $value ) {
				continue;
			}

			update_option( $option, $value, false );
		}

		update_option( self::OPTION_AUTOLOAD_MIGRATED, 1, false );
	}

	/**
	 * Handle post save events - DELEGATES to Translation Manager and Job Enqueuer.
	 *
	 * @since 0.2.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function handle_save_post( $post_id, $post, $update ) {
		// Use LoopProtectionService if available
		if ( $this->loop_protection_service ) {
			// Check if should skip
			if ( $this->loop_protection_service->shouldSkip( (int) $post_id, 'save_post' ) ) {
				Logger::debug( 'handle_save_post skipped - loop protection', array( 'post_id' => $post_id ) );
				return;
			}
			
			// Check rate limit
			if ( $this->loop_protection_service->checkRateLimit( (int) $post_id, 3.0, 2, 10.0 ) ) {
				Logger::debug( 'handle_save_post rate limited', array( 'post_id' => $post_id ) );
				$this->loop_protection_service->blockPost( (int) $post_id, 30 );
				$GLOBALS['fpml_infinite_loop_detected'] = true;
				return;
			}
			
			// Mark as processing
			$this->loop_protection_service->markProcessing( (int) $post_id );
		} else {
			// Fallback to old logic
			// CONTROLLO IMMEDIATO: Se flag globale è attivo, BLOCCA SUBITO
			if ( isset( $GLOBALS['fpml_infinite_loop_detected'] ) && $GLOBALS['fpml_infinite_loop_detected'] ) {
				return;
			}
			
			// CONTROLLO: Se stiamo aggiornando una traduzione, salta completamente
			if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
				return;
			}
			
			// PROTEZIONE ULTRA-AGGRESSIVA contro loop infiniti da altri plugin
			static $processing = array();
			static $call_count = array();
			static $last_call_time = array();
			static $disabled_hooks = array();
			
			$current_time = microtime( true );
			
			// Se questo post ha hook bloccati, salta completamente
			$blocked = get_transient( 'fpml_blocked_hooks_' . $post_id );
			if ( $blocked || ( isset( $GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] ) && $GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] ) ) {
				Logger::debug( 'handle_save_post skipped - hooks blocked for this post', array( 'post_id' => $post_id ) );
				return;
			}
			
			// Rate limiting: max 1 chiamata ogni 3 secondi per post (aumentato per sicurezza)
			if ( isset( $last_call_time[ $post_id ] ) ) {
				$time_since_last = $current_time - $last_call_time[ $post_id ];
				if ( $time_since_last < 3.0 ) {
					Logger::debug( 'handle_save_post rate limited', array( 
						'post_id' => $post_id,
						'time_since_last' => $time_since_last 
					) );
					return;
				}
			}
			
			// Contatore chiamate: max 2 chiamate in 10 secondi (ridotto ulteriormente)
			if ( ! isset( $call_count[ $post_id ] ) ) {
				$call_count[ $post_id ] = array();
			}
			
			// Rimuovi chiamate più vecchie di 10 secondi
			$call_count[ $post_id ] = array_filter( $call_count[ $post_id ], function( $time ) use ( $current_time ) {
				return ( $current_time - $time ) < 10.0;
			} );
			
			// Aggiungi chiamata corrente
			$call_count[ $post_id ][] = $current_time;
			
			// Se troppe chiamate, disabilita gli hook problematici globalmente
			if ( count( $call_count[ $post_id ] ) > 2 ) {
				// ATTIVA FLAG GLOBALE IMMEDIATAMENTE per bloccare tutte le chiamate successive
				$GLOBALS['fpml_infinite_loop_detected'] = true;
				
				Logger::error( 'handle_save_post blocked - too many calls, disabling problematic hooks globally', array( 
					'post_id' => $post_id,
					'count' => count( $call_count[ $post_id ] )
				) );
				
				// Disabilita GLOBALMENTE gli hook problematici per 30 secondi
				global $wp_filter;
				$problematic_hooks = array( 'publish_post', 'transition_post_status', 'publish_page', 'on_publish', 'save_post' );
				
				// Salva gli hook disabilitati in un transient per persistenza
				$saved_hooks = get_transient( 'fpml_disabled_hooks_data' );
				if ( ! is_array( $saved_hooks ) ) {
					$saved_hooks = array();
				}
				
				foreach ( $problematic_hooks as $hook_name ) {
					if ( isset( $wp_filter[ $hook_name ] ) && ! isset( $saved_hooks[ $hook_name ] ) ) {
						// NON serializzare WP_Hook (contiene Closure non serializzabili)
						// Salva solo un flag che indica che l'hook è stato disabilitato
						$saved_hooks[ $hook_name ] = true; // Flag invece di dati serializzati
						unset( $wp_filter[ $hook_name ] );
						Logger::warning( 'Disabled hook globally to prevent infinite loop', array( 
							'hook' => $hook_name,
							'post_id' => $post_id 
						) );
					}
				}
				
				// Salva gli hook disabilitati in transient (30 secondi)
				// NON salvare i dati degli hook (non serializzabili), solo il flag
				set_transient( 'fpml_disabled_hooks_flag', array_keys( $saved_hooks ), 30 );
				
				// Ripristina dopo 30 secondi
				set_transient( 'fpml_blocked_hooks_' . $post_id, true, 30 );
				set_transient( 'fpml_restore_hooks_time', $current_time + 30, 30 );
				$GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] = true;
				
				// BLOCCA IMMEDIATAMENTE - non continuare l'esecuzione
				return;
			}
			
			// Ripristina hook se il tempo è scaduto (controllo all'inizio per evitare loop)
			$restore_time = get_transient( 'fpml_restore_hooks_time' );
			if ( $restore_time && $current_time >= $restore_time ) {
				global $wp_filter;
				$saved_hooks = get_transient( 'fpml_disabled_hooks_data' );
				if ( is_array( $saved_hooks ) && ! empty( $saved_hooks ) ) {
					foreach ( $saved_hooks as $hook_name => $hook_data ) {
						// Verifica che i dati serializzati siano validi prima di unserialize
						if ( ! is_string( $hook_data ) || empty( $hook_data ) ) {
							continue;
						}
						
						$hook_value = maybe_unserialize( $hook_data );
						if ( $hook_value !== false && $hook_value !== null && is_object( $hook_value ) ) {
							$wp_filter[ $hook_name ] = $hook_value;
							Logger::debug( 'Restored hook', array( 'hook' => $hook_name ) );
						}
					}
					delete_transient( 'fpml_disabled_hooks_data' );
					delete_transient( 'fpml_restore_hooks_time' );
				}
			}
			
			// Protezione contro loop infiniti
			if ( isset( $processing[ $post_id ] ) ) {
				Logger::debug( 'handle_save_post skipped - already processing', array( 'post_id' => $post_id ) );
				return;
			}
			$processing[ $post_id ] = true;
			$last_call_time[ $post_id ] = $current_time;
		}

		// Se stiamo creando una traduzione, salta completamente
		if ( isset( $GLOBALS['fpml_creating_translation'] ) && $GLOBALS['fpml_creating_translation'] ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( $this->is_assisted_mode() ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( ! $this->translation_manager ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( $this->translation_manager->is_creating_translation() ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( ! $post instanceof WP_Post ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			unset( $processing[ $post_id ] );
			return;
		}

		if ( get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
			unset( $processing[ $post_id ] );
			return;
		}

		$post_types = $this->get_translatable_post_types();

		if ( empty( $post_types ) || ! in_array( $post->post_type, $post_types, true ) ) {
			unset( $processing[ $post_id ] );
			return;
		}

		// REFACTORED: Check if translation exists, but don't create it automatically
		// Translation will be created only when explicitly requested (e.g., "Traduci ORA" button)
		// Get translation for first enabled language
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
		
		$target_id = $this->translation_manager->get_translation_id( $post->ID, $target_lang );
		
		// Backward compatibility: check legacy _fpml_pair_id
		if ( ! $target_id && 'en' === $target_lang ) {
			$target_id = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );
		}
		
		if ( ! $target_id ) {
			// Translation doesn't exist yet - mark as needed but don't create now
			// This prevents infinite loops during save_post
			update_post_meta( $post->ID, '_fpml_translation_needed_' . $target_lang, '1' );
			unset( $processing[ $post_id ] );
			return;
		}

		$target_post = get_post( $target_id );
		
		if ( ! $target_post || ! $this->job_enqueuer ) {
			unset( $processing[ $post_id ] );
			return;
		}

		// Accoda job per tradurre contenuto e meta fields
		$this->job_enqueuer->enqueue_post_jobs( $post, $target_post, $update );

		// Sincronizza taxonomies quando si modifica un post
		$this->sync_post_taxonomies( $post, $target_post );

		// Mark as done processing
		if ( $this->loop_protection_service ) {
			$this->loop_protection_service->markDone( (int) $post_id );
		} else {
			unset( $processing[ $post_id ] );
			
			// Cleanup: rimuovi dati vecchi dopo 30 secondi
			if ( isset( $call_count[ $post_id ] ) && ( $current_time - max( $call_count[ $post_id ] ) ) > 30.0 ) {
				unset( $call_count[ $post_id ] );
				unset( $last_call_time[ $post_id ] );
			}
		}
	}

	/**
	 * Intercetta publish_post per rilevare loop infiniti da altri plugin.
	 *
	 * @since 0.9.2
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_publish_post( $post_id ) {
		// CONTROLLO IMMEDIATO: Se flag globale è attivo, BLOCCA SUBITO
		if ( isset( $GLOBALS['fpml_infinite_loop_detected'] ) && $GLOBALS['fpml_infinite_loop_detected'] ) {
			return;
		}
		
		// CONTROLLO: Se stiamo aggiornando una traduzione, salta completamente
		if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
			return;
		}
		
		// Se questo post ha hook bloccati, salta completamente
		$blocked = get_transient( 'fpml_blocked_hooks_' . $post_id );
		if ( $blocked || ( isset( $GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] ) && $GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] ) ) {
			return;
		}
		
		static $publish_count = array();
		static $last_publish_time = array();
		
		$current_time = microtime( true );
		
		// Conta chiamate a publish_post per questo post
		if ( ! isset( $publish_count[ $post_id ] ) ) {
			$publish_count[ $post_id ] = array();
		}
		
		// Rimuovi chiamate più vecchie di 5 secondi
		$publish_count[ $post_id ] = array_filter( $publish_count[ $post_id ], function( $time ) use ( $current_time ) {
			return ( $current_time - $time ) < 5.0;
		} );
		
		// Aggiungi chiamata corrente
		$publish_count[ $post_id ][] = $current_time;
		
		// Se più di 2 chiamate in 5 secondi, BLOCCA IMMEDIATAMENTE (ridotto da 3 a 2 per maggiore sicurezza)
		if ( count( $publish_count[ $post_id ] ) > 2 ) {
			// ATTIVA FLAG GLOBALE IMMEDIATAMENTE per bloccare tutte le chiamate successive
			$GLOBALS['fpml_infinite_loop_detected'] = true;
			
			Logger::error( 'Infinite loop detected in publish_post! Blocking immediately', array( 
				'post_id' => $post_id,
				'count' => count( $publish_count[ $post_id ] )
			) );
			
			global $wp_filter;
			$problematic_hooks = array( 'publish_post', 'transition_post_status', 'publish_page', 'on_publish', 'save_post' );
			
			// Salva gli hook disabilitati (solo se non già salvati)
			$saved_hooks = get_transient( 'fpml_disabled_hooks_data' );
			if ( ! is_array( $saved_hooks ) ) {
				$saved_hooks = array();
			}
			
			foreach ( $problematic_hooks as $hook_name ) {
				if ( isset( $wp_filter[ $hook_name ] ) && ! isset( $saved_hooks[ $hook_name ] ) ) {
					// NON serializzare WP_Hook (contiene Closure non serializzabili)
					// Salva solo un flag che indica che l'hook è stato disabilitato
					$saved_hooks[ $hook_name ] = true; // Flag invece di dati serializzati
					unset( $wp_filter[ $hook_name ] );
					Logger::warning( 'Emergency: Disabled hook to stop infinite loop', array( 'hook' => $hook_name ) );
				}
			}
			
			// Salva per ripristino dopo 30 secondi (aumentato da 15)
			// NON salvare i dati degli hook (non serializzabili), solo il flag
			if ( ! empty( $saved_hooks ) ) {
				set_transient( 'fpml_disabled_hooks_flag', array_keys( $saved_hooks ), 30 );
				set_transient( 'fpml_restore_hooks_time', $current_time + 30, 30 );
			}
			
			// Blocca anche questo post
			set_transient( 'fpml_blocked_hooks_' . $post_id, true, 30 );
			$GLOBALS[ 'fpml_blocked_hooks_' . $post_id ] = true;
			
			// BLOCCA IMMEDIATAMENTE - non continuare l'esecuzione
			return;
		}
	}

	/**
	 * Intercetta on_publish (hook personalizzato) per bloccare loop infiniti.
	 *
	 * @since 0.9.2
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_on_publish( $post_id ) {
		// Flag globale per bloccare completamente on_publish - controllo IMMEDIATO
		if ( isset( $GLOBALS['fpml_block_on_publish'] ) && $GLOBALS['fpml_block_on_publish'] ) {
			// Rimuovi COMPLETAMENTE l'hook da $wp_filter per impedire qualsiasi esecuzione
			global $wp_filter;
			if ( isset( $wp_filter['on_publish'] ) ) {
				unset( $wp_filter['on_publish'] );
				Logger::debug( 'on_publish blocked globally - hook removed', array( 'post_id' => $post_id ) );
			}
			return;
		}
		
		// Se questo post ha hook bloccati, BLOCCA COMPLETAMENTE l'esecuzione
		$blocked = get_transient( 'fpml_blocked_hooks_' . $post_id );
		if ( $blocked ) {
			Logger::debug( 'on_publish blocked for post', array( 'post_id' => $post_id ) );
			$GLOBALS['fpml_block_on_publish'] = true; // Blocca globalmente
			// Rimuovi completamente l'hook
			global $wp_filter;
			if ( isset( $wp_filter['on_publish'] ) ) {
				unset( $wp_filter['on_publish'] );
			}
			return;
		}
		
		// Conta chiamate a on_publish
		static $on_publish_count = array();
		$current_time = microtime( true );
		
		if ( ! isset( $on_publish_count[ $post_id ] ) ) {
			$on_publish_count[ $post_id ] = array();
		}
		
		// Rimuovi chiamate più vecchie di 0.5 secondi (soglia ULTRA-aggressiva)
		$on_publish_count[ $post_id ] = array_filter( $on_publish_count[ $post_id ], function( $time ) use ( $current_time ) {
			return ( $current_time - $time ) < 0.5;
		} );
		
		$on_publish_count[ $post_id ][] = $current_time;
		
		// Se anche solo 1 chiamata ripetuta in 0.5 secondi, BLOCCA IMMEDIATAMENTE
		if ( count( $on_publish_count[ $post_id ] ) > 1 ) {
			Logger::error( 'Infinite loop detected in on_publish! Disabling FP-SEO-AutoIndex', array( 
				'post_id' => $post_id,
				'count' => count( $on_publish_count[ $post_id ] ),
				'times' => $on_publish_count[ $post_id ]
			) );
			
			// NUOVA STRATEGIA: Disabilita FP-SEO-AutoIndex rimuovendo TUTTI i suoi hook
			// Questo è l'unico modo per impedire che continui a chiamare do_action('on_publish')
			global $wp_filter;
			
			// Rimuovi TUTTI gli hook di FP-SEO-AutoIndex
			if ( isset( $wp_filter['on_publish'] ) && is_object( $wp_filter['on_publish'] ) ) {
				$callbacks = $wp_filter['on_publish']->callbacks;
				foreach ( $callbacks as $priority => $hooks ) {
					foreach ( $hooks as $hook_id => $hook_data ) {
						// Controlla se l'hook appartiene a FP-SEO-AutoIndex
						if ( is_array( $hook_data['function'] ) && is_object( $hook_data['function'][0] ) ) {
							$class_name = get_class( $hook_data['function'][0] );
							if ( strpos( $class_name, 'FP_SEO_AutoIndex' ) !== false || strpos( $class_name, 'FPSEO' ) !== false ) {
								unset( $wp_filter['on_publish']->callbacks[ $priority ][ $hook_id ] );
								Logger::warning( 'Removed FP-SEO-AutoIndex hook to stop infinite loop', array(
									'class' => $class_name,
									'priority' => $priority
								) );
							}
						}
					}
				}
			}
			
			// Blocca GLOBALMENTE on_publish IMMEDIATAMENTE
			$GLOBALS['fpml_block_on_publish'] = true;
			
			// Blocca questo post
			set_transient( 'fpml_blocked_hooks_' . $post_id, true, 10 );
			
			// Ripristina dopo 10 secondi
			wp_schedule_single_event( time() + 10, 'fpml_restore_on_publish' );
			
			// IMPORTANTE: return qui per impedire che altri hook vengano eseguiti
			return;
		}
	}

	/**
	 * Intercetta tutti gli hook per bloccare on_publish PRIMA che venga eseguito.
	 * 
	 * NUOVA STRATEGIA: Rimuove COMPLETAMENTE on_publish da $wp_filter PRIMA che do_action lo controlli.
	 * Questo impedisce che do_action trovi l'hook e faccia return immediatamente (riga 498 di plugin.php).
	 *
	 * @since 0.9.3
	 *
	 * @param string $hook_name Hook name.
	 * @return void
	 */
	public function handle_all_hooks( $hook_name, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null, $arg5 = null, $arg6 = null, $arg7 = null, $arg8 = null, $arg9 = null ) {
		// Intercetta solo on_publish
		if ( 'on_publish' !== $hook_name ) {
			return;
		}
		
		// Il primo argomento dopo il nome dell'hook è il post_id
		$post_id = is_numeric( $arg1 ) ? (int) $arg1 : 0;
		
		// NUOVA STRATEGIA: Controlla PRIMA se on_publish è bloccato globalmente
		// Se sì, rimuovi IMMEDIATAMENTE l'hook PRIMA che do_action lo controlli
		if ( isset( $GLOBALS['fpml_block_on_publish'] ) && $GLOBALS['fpml_block_on_publish'] ) {
			global $wp_filter, $wp_actions;
			if ( isset( $wp_filter['on_publish'] ) ) {
				unset( $wp_filter['on_publish'] );
			}
			// Rimuovi anche da $wp_actions per impedire che do_action incrementi il contatore
			if ( isset( $wp_actions['on_publish'] ) ) {
				unset( $wp_actions['on_publish'] );
			}
			return;
		}
		
		// Controlla anche i transient per post specifici
		if ( $post_id > 0 ) {
			$blocked = get_transient( 'fpml_blocked_hooks_' . $post_id );
			if ( $blocked ) {
				$GLOBALS['fpml_block_on_publish'] = true;
				global $wp_filter, $wp_actions;
				if ( isset( $wp_filter['on_publish'] ) ) {
					unset( $wp_filter['on_publish'] );
				}
				// Rimuovi anche da $wp_actions per sicurezza
				if ( isset( $wp_actions['on_publish'] ) ) {
					unset( $wp_actions['on_publish'] );
				}
				return;
			}
		}
		
		// NUOVA STRATEGIA: Rilevamento loop PREVENTIVO con contatore globale
		// Conta chiamate a on_publish per questo post (anche da chiamate multiple di do_action)
		static $on_publish_pre_count = array();
		$current_time = microtime( true );
		
		if ( $post_id > 0 ) {
			if ( ! isset( $on_publish_pre_count[ $post_id ] ) ) {
				$on_publish_pre_count[ $post_id ] = array();
			}
			
			// Rimuovi chiamate più vecchie di 0.2 secondi (soglia ULTRA-aggressiva)
			$on_publish_pre_count[ $post_id ] = array_filter( $on_publish_pre_count[ $post_id ], function( $time ) use ( $current_time ) {
				return ( $current_time - $time ) < 0.2;
			} );
			
			$on_publish_pre_count[ $post_id ][] = $current_time;
			
			// Se anche solo 1 chiamata ripetuta in 0.2 secondi, BLOCCA IMMEDIATAMENTE
			// Questo significa che do_action('on_publish') è stato chiamato più volte in modo rapido
			if ( count( $on_publish_pre_count[ $post_id ] ) > 1 ) {
				Logger::error( 'Infinite loop detected in on_publish via all hook! Blocking immediately', array( 
					'post_id' => $post_id,
					'count' => count( $on_publish_pre_count[ $post_id ] ),
					'times' => $on_publish_pre_count[ $post_id ]
				) );
				
				// Blocca GLOBALMENTE on_publish IMMEDIATAMENTE
				$GLOBALS['fpml_block_on_publish'] = true;
				set_transient( 'fpml_blocked_hooks_' . $post_id, true, 15 );
				
				// Rimuovi COMPLETAMENTE l'hook PRIMA che do_action lo controlli
				global $wp_filter, $wp_actions;
				if ( isset( $wp_filter['on_publish'] ) ) {
					unset( $wp_filter['on_publish'] );
					Logger::warning( 'Completely removed on_publish hook via all hook to stop infinite loop' );
				}
				// Rimuovi anche da $wp_actions per sicurezza
				if ( isset( $wp_actions['on_publish'] ) ) {
					unset( $wp_actions['on_publish'] );
				}
				
				// Ripristina dopo 15 secondi
				wp_schedule_single_event( time() + 15, 'fpml_restore_on_publish' );
				
				// IMPORTANTE: return qui per impedire qualsiasi ulteriore esecuzione
				return;
			}
		}
	}


	/**
	 * Ripristina gli hook on_publish dopo il blocco.
	 *
	 * @since 0.9.2
	 *
	 * @return void
	 */
	public function restore_on_publish_hooks() {
		// Rimuovi il flag globale per permettere l'esecuzione di on_publish nella prossima richiesta
		unset( $GLOBALS['fpml_block_on_publish'] );
		Logger::debug( 'on_publish hooks restored - global flag removed' );
	}

/**
 * Enqueue translation jobs after manual translation creation.
 *
 * @since 0.9.1
 *
 * @param int $target_id Translated post ID.
 * @param int $source_id Original post ID.
 *
 * @return void
 */
public function enqueue_jobs_after_translation( $target_id, $source_id ) {
	// Use TranslationSyncService if available
	$translation_sync_service = $this->getTranslationSyncService();
	if ( $translation_sync_service ) {
		$translation_sync_service->enqueueJobsAfterTranslation( (int) $target_id, (int) $source_id );
		return;
	}

	// Fallback to old logic
	// Log hook call
	Logger::debug( 'enqueue_jobs_after_translation called', array( 'target_id' => $target_id, 'source_id' => $source_id ) );
	
	if ( ! $this->job_enqueuer ) {
		Logger::warning( 'job_enqueuer not available in enqueue_jobs_after_translation' );
		return;
	}

	$source_post = get_post( $source_id );
	$target_post = get_post( $target_id );

	if ( ! $source_post || ! $target_post ) {
		Logger::warning( 'source_post or target_post not found', array( 'source_id' => $source_id, 'target_id' => $target_id ) );
		return;
	}

	Logger::debug( 'Enqueueing jobs for translation', array( 'source_id' => $source_id, 'target_id' => $target_id ) );
	
	// Enqueue jobs for the newly created translation
	$this->job_enqueuer->enqueue_post_jobs( $source_post, $target_post, false );
	
	// Sincronizza taxonomies (categorie, tag, ecc.)
	$this->sync_post_taxonomies( $source_post, $target_post );
	
	Logger::debug( 'Jobs enqueued successfully', array( 'source_id' => $source_id, 'target_id' => $target_id ) );
}

/**
 * Sincronizza le taxonomies di un post quando viene tradotto.
 *
 * @since 0.9.1
 *
 * @param WP_Post $source_post Post sorgente italiano.
 * @param WP_Post $target_post Post destinazione inglese.
 *
 * @return void
 */
	/**
	 * Sync post taxonomies - DELEGATES to PostHandlers.
	 *
	 * @param \WP_Post $source_post Source post.
	 * @param \WP_Post $target_post Target post.
	 *
	 * @return void
	 */
	protected function sync_post_taxonomies( $source_post, $target_post ) {
		// Use TranslationSyncService if available
		$translation_sync_service = $this->getTranslationSyncService();
		if ( $translation_sync_service ) {
			$translation_sync_service->syncPostTaxonomies( $source_post, $target_post );
			return;
		}

		// Fallback to PostHandlers
		$post_handlers = function_exists( 'fpml_get_post_handlers' ) ? fpml_get_post_handlers() : PostHandlers::instance();
		$post_handlers->set_plugin( $this );
		$post_handlers->sync_post_taxonomies( $source_post, $target_post );
	}

	/**
	 * Get TranslationSyncService instance.
	 *
	 * @return \FP\Multilanguage\Core\Services\TranslationSyncService|null
	 */
	protected function getTranslationSyncService() {
		$container = $this->getContainer();
		if ( $container && $container->has( 'service.translation_sync' ) ) {
			return $container->get( 'service.translation_sync' );
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\TranslationSyncService' ) ) {
			$service = new \FP\Multilanguage\Core\Services\TranslationSyncService();
			if ( $this->translation_manager ) {
				$service->setTranslationManager( $this->translation_manager );
			}
			if ( $this->job_enqueuer ) {
				$service->setJobEnqueuer( $this->job_enqueuer );
			}
			return $service;
		}
		
		return null;
	}


	/**
	 * Handle created terms - DELEGATES to TermHandlers.
	 *
	 * @since 0.2.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_created_term( $term_id, $tt_id, $taxonomy ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$term_handlers = function_exists( 'fpml_get_term_handlers' ) ? fpml_get_term_handlers() : TermHandlers::instance();
		$term_handlers->set_plugin( $this );
		$term_handlers->handle_created_term( (int) $term_id, (int) $tt_id, (string) $taxonomy );
	}

	/**
	 * Handle edited terms - DELEGATES to TermHandlers.
	 *
	 * @since 0.2.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_edited_term( $term_id, $tt_id, $taxonomy ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$term_handlers = function_exists( 'fpml_get_term_handlers' ) ? fpml_get_term_handlers() : TermHandlers::instance();
		$term_handlers->set_plugin( $this );
		$term_handlers->handle_edited_term( (int) $term_id, (int) $tt_id, (string) $taxonomy );
	}


	/**
	 * Retrieve allowed post types for translation.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	/**
	 * Get translatable post types - DELEGATES to PostHandlers.
	 *
	 * @return array<string> Post type slugs.
	 */
	protected function get_translatable_post_types(): array {
		// Use ContentTypeService if available
		$content_type_service = $this->getContentTypeService();
		if ( $content_type_service ) {
			return $content_type_service->getTranslatablePostTypes();
		}

		// Fallback to PostHandlers
		$post_handlers = function_exists( 'fpml_get_post_handlers' ) ? fpml_get_post_handlers() : PostHandlers::instance();
		return $post_handlers->get_translatable_post_types();
	}

	/**
	 * Get ContentTypeService instance.
	 *
	 * @return \FP\Multilanguage\Core\Services\ContentTypeService|null
	 */
	protected function getContentTypeService() {
		$container = $this->getContainer();
		if ( $container && $container->has( 'service.content_type' ) ) {
			return $container->get( 'service.content_type' );
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\ContentTypeService' ) ) {
			return new \FP\Multilanguage\Core\Services\ContentTypeService();
		}
		
		return null;
	}

	/**
	 * Plugin facade instance.
	 *
	 * @var \FP\Multilanguage\Core\Services\PluginFacade|null
	 */
	protected $facade = null;

	/**
	 * Get plugin facade instance.
	 *
	 * @return \FP\Multilanguage\Core\Services\PluginFacade
	 */
	protected function get_facade() {
		if ( null === $this->facade ) {
			$this->facade = new \FP\Multilanguage\Core\Services\PluginFacade();
			// Set container if available
			if ( method_exists( $this, 'getContainer' ) ) {
				$this->facade->setContainer( $this->getContainer() );
			}
		}
		return $this->facade;
	}

	/**
	 * Reindex existing content - DELEGATES to PluginFacade.
	 *
	 * @since 0.2.0
	 *
	 * @return array|WP_Error Summary data.
	 */
	public function reindex_content() {
		return $this->get_facade()->reindex_content();
	}

	/**
	 * Reindex specific post type - DELEGATES to PluginFacade.
	 *
	 * @since 0.4.0
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_post_type( $post_type ) {
		return $this->get_facade()->reindex_post_type( $post_type );
	}

	/**
	 * Reindex specific taxonomy - DELEGATES to PluginFacade.
	 *
	 * @since 0.4.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_taxonomy( $taxonomy ) {
		return $this->get_facade()->reindex_taxonomy( $taxonomy );
	}

	/**
	 * Build diagnostics snapshot - DELEGATES to PluginFacade.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string,mixed>
	 */
	public function get_diagnostics_snapshot() {
		return $this->get_facade()->get_diagnostics_snapshot();
	}

	/**
	 * Estimate queue cost - DELEGATES to PluginFacade.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string>|null $states   Queue states to inspect.
	 * @param int                $max_jobs Maximum number of jobs to analyse.
	 *
	 * @return array<string,float|int>|WP_Error
	 */
	public function estimate_queue_cost( $states = null, $max_jobs = 500 ) {
		return $this->get_facade()->estimate_queue_cost( $states, $max_jobs );
	}

	/**
	 * Get queue job text - DELEGATES to PluginFacade.
	 *
	 * @since 0.2.0
	 *
	 * @param object $job Queue job entry.
	 *
	 * @return string
	 */
	public function get_queue_job_text( $job ) {
		return $this->get_facade()->get_queue_job_text( $job );
	}

	/**
	 * Get queue cleanup states - DELEGATES to PluginFacade.
	 *
	 * @since 0.3.1
	 *
	 * @return array
	 */
	public function get_queue_cleanup_states() {
		return $this->get_facade()->get_queue_cleanup_states();
	}

	/**
	 * Get queue age summary - DELEGATES to PluginFacade.
	 *
	 * @since 0.3.1
	 *
	 * @return array
	 */
	public function get_queue_age_summary() {
		return $this->get_facade()->get_queue_age_summary();
	}

	/**
	 * Handle attachment add - DELEGATES to AttachmentHooks.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function handle_add_attachment( $attachment_id ) {
		// Delegate to AttachmentHooks via HookManager
		// This method is kept for backward compatibility
		$container = $this->getContainer();
		if ( $container && $container->has( 'hooks.attachment' ) ) {
			$attachment_hooks = $container->get( 'hooks.attachment' );
			if ( $attachment_hooks && method_exists( $attachment_hooks, 'handle_add_attachment' ) ) {
				$attachment_hooks->handle_add_attachment( (int) $attachment_id );
				return;
			}
		}
		// Fallback to ContentHandlers
		if ( class_exists( '\FP\Multilanguage\Core\ContentHandlers' ) ) {
			$content_handlers = function_exists( 'fpml_get_content_handlers' ) ? fpml_get_content_handlers() : \FP\Multilanguage\Core\ContentHandlers::instance();
			$content_handlers->set_plugin( $this );
			$content_handlers->handle_add_attachment( (int) $attachment_id );
		}
	}

	/**
	 * Handle attachment edit - DELEGATES to AttachmentHooks.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function handle_edit_attachment( $attachment_id ) {
		// Delegate to AttachmentHooks via HookManager
		// This method is kept for backward compatibility
		$container = $this->getContainer();
		if ( $container && $container->has( 'hooks.attachment' ) ) {
			$attachment_hooks = $container->get( 'hooks.attachment' );
			if ( $attachment_hooks && method_exists( $attachment_hooks, 'handle_edit_attachment' ) ) {
				$attachment_hooks->handle_edit_attachment( (int) $attachment_id );
				return;
			}
		}
		// Fallback to ContentHandlers
		if ( class_exists( '\FP\Multilanguage\Core\ContentHandlers' ) ) {
			$content_handlers = function_exists( 'fpml_get_content_handlers' ) ? fpml_get_content_handlers() : \FP\Multilanguage\Core\ContentHandlers::instance();
			$content_handlers->set_plugin( $this );
			$content_handlers->handle_edit_attachment( (int) $attachment_id );
		}
	}

	/**
	 * Handle comment post - DELEGATES to CommentHooks.
	 *
	 * @param int        $comment_id  Comment ID.
	 * @param int|string $approved    1 if approved, 0 if not, 'spam' if spam.
	 * @param array      $commentdata Comment data.
	 *
	 * @return void
	 */
	public function handle_comment_post( $comment_id, $approved, $commentdata ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Delegate to CommentHooks via HookManager
		// This method is kept for backward compatibility
		$container = $this->getContainer();
		if ( $container && $container->has( 'hooks.comment' ) ) {
			$comment_hooks = $container->get( 'hooks.comment' );
			if ( $comment_hooks && method_exists( $comment_hooks, 'handle_comment_post' ) ) {
				$comment_hooks->handle_comment_post( (int) $comment_id, $approved, (array) $commentdata );
				return;
			}
		}
		// Fallback to ContentHandlers
		if ( class_exists( '\FP\Multilanguage\Core\ContentHandlers' ) ) {
			$content_handlers = function_exists( 'fpml_get_content_handlers' ) ? fpml_get_content_handlers() : \FP\Multilanguage\Core\ContentHandlers::instance();
			$content_handlers->set_plugin( $this );
			$content_handlers->handle_comment_post( (int) $comment_id, $approved, (array) $commentdata );
		}
	}

	/**
	 * Handle comment edit - DELEGATES to CommentHooks.
	 *
	 * @param int $comment_id Comment ID.
	 *
	 * @return void
	 */
	public function handle_edit_comment( $comment_id ) {
		// Delegate to CommentHooks via HookManager
		// This method is kept for backward compatibility
		$container = $this->getContainer();
		if ( $container && $container->has( 'hooks.comment' ) ) {
			$comment_hooks = $container->get( 'hooks.comment' );
			if ( $comment_hooks && method_exists( $comment_hooks, 'handle_edit_comment' ) ) {
				$comment_hooks->handle_edit_comment( (int) $comment_id );
				return;
			}
		}
		// Fallback to ContentHandlers
		if ( class_exists( '\FP\Multilanguage\Core\ContentHandlers' ) ) {
			$content_handlers = function_exists( 'fpml_get_content_handlers' ) ? fpml_get_content_handlers() : \FP\Multilanguage\Core\ContentHandlers::instance();
			$content_handlers->set_plugin( $this );
			$content_handlers->handle_edit_comment( (int) $comment_id );
		}
	}

	/**
	 * Handle widget update - DELEGATES to WidgetHooks.
	 *
	 * @param array      $instance     Current widget instance's settings.
	 * @param array      $new_instance New widget settings.
	 * @param array      $old_instance Old widget settings.
	 * @param \WP_Widget $widget       Current widget instance.
	 *
	 * @return array
	 */
	public function handle_widget_update( $instance, $new_instance, $old_instance, $widget ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Delegate to WidgetHooks via HookManager
		// This method is kept for backward compatibility
		$container = $this->getContainer();
		if ( $container && $container->has( 'hooks.widget' ) ) {
			$widget_hooks = $container->get( 'hooks.widget' );
			if ( $widget_hooks && method_exists( $widget_hooks, 'handle_widget_update' ) ) {
				return $widget_hooks->handle_widget_update( (array) $instance, (array) $new_instance, (array) $old_instance, $widget );
			}
		}
		// Fallback to ContentHandlers
		if ( class_exists( '\FP\Multilanguage\Core\ContentHandlers' ) ) {
			$content_handlers = function_exists( 'fpml_get_content_handlers' ) ? fpml_get_content_handlers() : \FP\Multilanguage\Core\ContentHandlers::instance();
			$content_handlers->set_plugin( $this );
			return $content_handlers->handle_widget_update( (array) $instance, (array) $new_instance, (array) $old_instance, $widget );
		}
		return $instance;
	}

	/**
	 * Handle post deletion - DELEGATES to PostHandlers.
	 *
	 * @param int $post_id Post ID being deleted.
	 *
	 * @return void
	 */
	public function handle_delete_post( $post_id ) {
		$post_handlers = function_exists( 'fpml_get_post_handlers' ) ? fpml_get_post_handlers() : PostHandlers::instance();
		$post_handlers->set_plugin( $this );
		$post_handlers->handle_delete_post( (int) $post_id );
	}

	/**
	 * Handle term deletion - DELEGATES to TermHandlers.
	 *
	 * @param int    $term_id  Term ID being deleted.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function handle_delete_term( $term_id, $tt_id, $taxonomy ) {
		$term_handlers = function_exists( 'fpml_get_term_handlers' ) ? fpml_get_term_handlers() : TermHandlers::instance();
		$term_handlers->set_plugin( $this );
		$term_handlers->handle_delete_term( (int) $term_id, (int) $tt_id, (string) $taxonomy );
	}
}

/**
 * Alias for PSR-4 naming convention.
 *
 * @since 0.5.0
 * @deprecated 1.0.0 Use Kernel\Plugin instead. This class is kept for backward compatibility only.
 * 
 * This class will be removed in version 1.1.0. Please migrate to Kernel\Plugin.
 */
class Plugin extends PluginCore {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.5.0
	 * @deprecated 1.0.0 Use Kernel\Plugin::getInstance() instead
	 *
	 * @return Plugin
	 */
	public static function instance() {
		_doing_it_wrong( 
			'FP\Multilanguage\Core\Plugin::instance()', 
			'Core\Plugin is deprecated. Use Kernel\Plugin::getInstance() instead.', 
			'1.0.0' 
		);
		
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
