<?php
/**
 * Plugin Orchestrator - Coordinates plugin initialization and lifecycle.
 *
 * This class replaces the old Plugin.php god class by orchestrating
 * specialized handlers and services.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Foundation\Options\OptionsInterface;
use FP\Multilanguage\Core\Queue\QueueInterface;
use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Core\PostHandlers;
use FP\Multilanguage\Core\TermHandlers;
use FP\Multilanguage\Core\Content\Media\MediaHandler;
use FP\Multilanguage\Core\Content\Comment\CommentHandler;
use FP\Multilanguage\Core\Hook\HookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin orchestrator - Coordinates all plugin services.
 *
 * @since 1.0.0
 */
class PluginOrchestrator {
	/**
	 * Option tracking completed migrations.
	 */
	const OPTION_AUTOLOAD_MIGRATED = '\FPML_options_autoload_migrated';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Settings instance.
	 *
	 * @var OptionsInterface
	 */
	protected $settings;

	/**
	 * Queue instance.
	 *
	 * @var QueueInterface
	 */
	protected $queue;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
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
	 * Post handlers instance.
	 *
	 * @var PostHandlers
	 */
	protected $post_handlers;

	/**
	 * Term handlers instance.
	 *
	 * @var TermHandlers
	 */
	protected $term_handlers;

	/**
	 * Media handler instance.
	 *
	 * @var MediaHandler
	 */
	protected $media_handler;

	/**
	 * Comment handler instance.
	 *
	 * @var CommentHandler
	 */
	protected $comment_handler;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManager
	 */
	protected $hook_manager;

	/**
	 * Whether the plugin is running in assisted mode.
	 *
	 * @var bool
	 */
	protected $assisted_mode = false;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Get container
		$this->container = Container::getInstance();

		// Load services from container
		$this->loadServices();

		// Detect assisted mode
		$this->detectAssistedMode();

		// Initialize handlers
		$this->initializeHandlers();

		// Register hooks
		$this->registerHooks();

		// Run setup if needed
		add_action( 'init', array( $this, 'maybeRunSetup' ), 5 );
	}

	/**
	 * Load services from container.
	 *
	 * @return void
	 */
	protected function loadServices(): void {
		$this->settings = $this->container->has( 'options' ) ? $this->container->get( 'options' ) : null;
		$this->queue = $this->container->has( 'queue' ) ? $this->container->get( 'queue' ) : null;
		$this->logger = $this->container->has( 'logger' ) ? $this->container->get( 'logger' ) : null;
		$this->translation_manager = $this->container->has( 'translation.manager' ) ? $this->container->get( 'translation.manager' ) : null;
		$this->job_enqueuer = $this->container->has( 'translation.job_enqueuer' ) ? $this->container->get( 'translation.job_enqueuer' ) : null;
		$this->hook_manager = $this->container->has( 'hook.manager' ) ? $this->container->get( 'hook.manager' ) : null;
	}

	/**
	 * Detect assisted mode (WPML/Polylang active).
	 *
	 * @return void
	 */
	protected function detectAssistedMode(): void {
		// Check for WPML
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' ) ) {
			$this->assisted_mode = true;
			return;
		}

		// Check for Polylang
		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
			$this->assisted_mode = true;
			return;
		}

		$this->assisted_mode = false;
	}

	/**
	 * Initialize handlers.
	 *
	 * @return void
	 */
	protected function initializeHandlers(): void {
		// Post handlers
		if ( $this->translation_manager && $this->job_enqueuer && $this->logger ) {
			$this->post_handlers = function_exists( 'fpml_get_post_handlers' ) ? fpml_get_post_handlers() : PostHandlers::instance();
			if ( method_exists( $this->post_handlers, 'setPlugin' ) ) {
				$this->post_handlers->setPlugin( $this );
			}
		}

		// Term handlers
		if ( $this->translation_manager && $this->job_enqueuer ) {
			$this->term_handlers = function_exists( 'fpml_get_term_handlers' ) ? fpml_get_term_handlers() : TermHandlers::instance();
			if ( method_exists( $this->term_handlers, 'setPlugin' ) ) {
				$this->term_handlers->setPlugin( $this );
			}
		}

		// Media handler
		if ( $this->translation_manager && $this->logger ) {
			$this->media_handler = new MediaHandler( $this->translation_manager, $this->logger );
		}

		// Comment handler
		if ( $this->logger ) {
			$this->comment_handler = new CommentHandler( $this->logger );
		}
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function registerHooks(): void {
		if ( ! $this->hook_manager ) {
			return;
		}

		// Register hooks via hook manager
		// This will be implemented based on existing hook registration
	}

	/**
	 * Run setup tasks if needed.
	 *
	 * @return void
	 */
	public function maybeRunSetup(): void {
		// Check if setup is needed
		if ( ! get_option( '\FPML_needs_setup' ) ) {
			return;
		}

		// Check if already completed
		if ( get_option( '\FPML_setup_completed' ) ) {
			delete_option( '\FPML_needs_setup' );
			return;
		}

		// Run setup tasks
		try {
			// Trigger settings restoration
			do_action( '\FPML_after_initialization' );

			// Register rewrites if not in assisted mode
			if ( ! $this->assisted_mode && class_exists( '\FPML_Rewrites' ) ) {
				( function_exists( 'fpml_get_rewrites' ) ? fpml_get_rewrites() : \FPML_Rewrites::instance() )->register_rewrites();
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
			update_option( '\FPML_setup_completed', true, false );
			delete_option( '\FPML_needs_setup' );
		} catch ( \Throwable $e ) {
			if ( $this->logger ) {
				$this->logger->error( 'Setup failed: ' . $e->getMessage(), array( 'exception' => $e ) );
			}
		}
	}

	/**
	 * Check if in assisted mode.
	 *
	 * @return bool
	 */
	public function isAssistedMode(): bool {
		return $this->assisted_mode;
	}

	/**
	 * Get assisted reason.
	 *
	 * @return string
	 */
	public function getAssistedReason(): string {
		if ( ! $this->assisted_mode ) {
			return '';
		}

		if ( defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' ) ) {
			return 'wpml';
		}

		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
			return 'polylang';
		}

		return '';
	}

	/**
	 * Get settings instance.
	 *
	 * @return OptionsInterface|null
	 */
	public function getSettings(): ?OptionsInterface {
		return $this->settings;
	}

	/**
	 * Get queue instance.
	 *
	 * @return QueueInterface|null
	 */
	public function getQueue(): ?QueueInterface {
		return $this->queue;
	}

	/**
	 * Get logger instance.
	 *
	 * @return LoggerInterface|null
	 */
	public function getLogger(): ?LoggerInterface {
		return $this->logger;
	}
}









