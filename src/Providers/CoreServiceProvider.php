<?php
/**
 * Core Service Provider.
 *
 * Registers core business logic services (Translation, Queue, Content handlers).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core service provider.
 *
 * @since 1.0.0
 */
class CoreServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Queue service - Try new Core\Queue first, fallback to old Queue
		// Create new instance (no singleton) for DI
		$container->bind( 'queue', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Queue\Queue' ) ) {
				// Try to create new instance if constructor is public
				if ( method_exists( '\FP\Multilanguage\Core\Queue\Queue', 'instance' ) ) {
					// Use singleton for now (will be refactored)
					return \FP\Multilanguage\Core\Queue\Queue::instance();
				}
			}
			if ( class_exists( '\FP\Multilanguage\Queue' ) ) {
				// Create new instance (no singleton) for DI
				return new \FP\Multilanguage\Queue();
			}
			return null;
		}, true );

		// Alias for QueueInterface
		$container->alias( \FP\Multilanguage\Core\Queue\QueueInterface::class, 'queue' );

		// Translation Manager - Create new instance with Logger dependency
		$container->bind( 'translation.manager', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Content\TranslationManager' ) ) {
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				return new \FP\Multilanguage\Content\TranslationManager( $logger );
			}
			return null;
		}, true );

		// Job Enqueuer - Create new instance with dependencies
		$container->bind( 'translation.job_enqueuer', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Translation\JobEnqueuer' ) ) {
				$queue = $c->has( 'queue' ) ? $c->get( 'queue' ) : null;
				$settings = $c->has( 'options' ) ? $c->get( 'options' ) : null;
				return new \FP\Multilanguage\Translation\JobEnqueuer( $queue, $settings );
			}
			return null;
		}, true );

		// Content Indexer - Create new instance with dependencies
		$container->bind( 'content.indexer', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Content\ContentIndexer' ) ) {
				$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
				$job_enqueuer = $c->has( 'translation.job_enqueuer' ) ? $c->get( 'translation.job_enqueuer' ) : null;
				return new \FP\Multilanguage\Content\ContentIndexer( $translation_manager, $job_enqueuer );
			}
			return null;
		}, true );

		// Cost Estimator - Create new instance with Queue dependency
		$container->bind( 'cost_estimator', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Diagnostics\CostEstimator' ) ) {
				$queue = $c->has( 'queue' ) ? $c->get( 'queue' ) : null;
				return new \FP\Multilanguage\Diagnostics\CostEstimator( $queue );
			}
			return null;
		}, true );

		// Glossary - Create new instance (no dependencies)
		$container->bind( 'glossary', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Glossary' ) ) {
				return new \FP\Multilanguage\Glossary();
			}
			return null;
		}, true );

		// Post Handlers (from Core\Plugin) - Create new instance with dependencies
		$container->bind( 'content.post_handler', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\PostHandlers' ) ) {
				$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
				$job_enqueuer = $c->has( 'translation.job_enqueuer' ) ? $c->get( 'translation.job_enqueuer' ) : null;
				return new \FP\Multilanguage\Core\PostHandlers( $translation_manager, $job_enqueuer );
			}
			return null;
		}, true );

		// Term Handlers (from Core\Plugin) - Create new instance with dependencies
		$container->bind( 'content.term_handler', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\TermHandlers' ) ) {
				$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
				$job_enqueuer = $c->has( 'translation.job_enqueuer' ) ? $c->get( 'translation.job_enqueuer' ) : null;
				return new \FP\Multilanguage\Core\TermHandlers( $translation_manager, $job_enqueuer );
			}
			return null;
		}, true );

		// Hook Manager
		$container->bind( 'hook.manager', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Hook\HookManager' ) ) {
				return \FP\Multilanguage\Core\Hook\fpml_get_hook_manager();
			}
			if ( class_exists( '\FP\Multilanguage\Core\HookManager' ) ) {
				return fpml_get_hook_manager();
			}
			return null;
		}, true );

		// Media Handler
		$container->bind( 'content.media_handler', function( Container $c ) {
			$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
			$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
			
			if ( $translation_manager && $logger && class_exists( '\FP\Multilanguage\Core\Content\Media\MediaHandler' ) ) {
				return new \FP\Multilanguage\Core\Content\Media\MediaHandler( $translation_manager, $logger );
			}
			return null;
		}, true );

		// Comment Handler
		$container->bind( 'content.comment_handler', function( Container $c ) {
			$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
			
			if ( $logger && class_exists( '\FP\Multilanguage\Core\Content\Comment\CommentHandler' ) ) {
				return new \FP\Multilanguage\Core\Content\Comment\CommentHandler( $logger );
			}
			return null;
		}, true );

		// Plugin Orchestrator
		$container->bind( 'plugin.orchestrator', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\PluginOrchestrator' ) ) {
				return \FP\Multilanguage\Core\PluginOrchestrator::instance();
			}
			return null;
		}, true );

		// Translation Orchestrator
		$container->bind( 'translation.orchestrator', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\TranslationOrchestrator' ) ) {
				// TranslationOrchestrator uses singleton pattern
				if ( method_exists( '\FP\Multilanguage\Core\TranslationOrchestrator', 'instance' ) ) {
					return \FP\Multilanguage\Core\TranslationOrchestrator::instance();
				}
			}
			return null;
		}, true );

		// Processor (legacy, will be refactored)
		$container->bind( 'processor', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Processor' ) ) {
				return fpml_get_processor();
			}
			return null;
		}, true );

		// Register hook handlers
		// Note: PostHooks, TermHooks, CommentHooks, and WidgetHooks use ContainerAwareTrait
		// and retrieve dependencies from the container when needed via Kernel\Plugin::getInstance().
		// They don't need constructor injection.
		$container->bind( 'hooks.post', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Hooks\PostHooks' ) ) {
				return new \FP\Multilanguage\Core\Hooks\PostHooks();
			}
			return null;
		}, true );

		$container->bind( 'hooks.term', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Hooks\TermHooks' ) ) {
				return new \FP\Multilanguage\Core\Hooks\TermHooks();
			}
			return null;
		}, true );

		$container->bind( 'hooks.comment', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Hooks\CommentHooks' ) ) {
				return new \FP\Multilanguage\Core\Hooks\CommentHooks();
			}
			return null;
		}, true );

		$container->bind( 'hooks.widget', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Hooks\WidgetHooks' ) ) {
				return new \FP\Multilanguage\Core\Hooks\WidgetHooks();
			}
			return null;
		}, true );

		// Attachment Hooks
		$container->bind( 'hooks.attachment', function( Container $c ) {
			$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
			$job_enqueuer = $c->has( 'translation.job_enqueuer' ) ? $c->get( 'translation.job_enqueuer' ) : null;
			if ( $translation_manager && $job_enqueuer && class_exists( '\FP\Multilanguage\Core\Hooks\AttachmentHooks' ) ) {
				return new \FP\Multilanguage\Core\Hooks\AttachmentHooks( $translation_manager, $job_enqueuer );
			}
			return null;
		}, true );

		// Assisted Mode Service
		$container->bind( 'service.assisted_mode', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\AssistedModeService' ) ) {
				return new \FP\Multilanguage\Core\Services\AssistedModeService();
			}
			return null;
		}, true );

		// Dependency Resolver Service
		$container->bind( 'service.dependency_resolver', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\DependencyResolver' ) ) {
				return new \FP\Multilanguage\Core\Services\DependencyResolver( $c );
			}
			return null;
		}, true );

		// Loop Protection Service
		$container->bind( 'service.loop_protection', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\LoopProtectionService' ) ) {
				return new \FP\Multilanguage\Core\Services\LoopProtectionService();
			}
			return null;
		}, true );

		// Setup Service
		$container->bind( 'service.setup', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\SetupService' ) ) {
				return new \FP\Multilanguage\Core\Services\SetupService();
			}
			return null;
		}, true );

		// Diagnostics Service
		$container->bind( 'service.diagnostics', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\DiagnosticsService' ) ) {
				return new \FP\Multilanguage\Core\Services\DiagnosticsService();
			}
			return null;
		}, true );

		// Reindex Service
		$container->bind( 'service.reindex', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\ReindexService' ) ) {
				return new \FP\Multilanguage\Core\Services\ReindexService();
			}
			return null;
		}, true );

		// Registration Service
		$container->bind( 'service.registration', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\RegistrationService' ) ) {
				return new \FP\Multilanguage\Core\Services\RegistrationService();
			}
			return null;
		}, true );

		// Translation Sync Service
		$container->bind( 'service.translation_sync', function( Container $c ) {
			$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
			$job_enqueuer        = $c->has( 'translation.job_enqueuer' ) ? $c->get( 'translation.job_enqueuer' ) : null;
			if ( class_exists( '\FP\Multilanguage\Core\Services\TranslationSyncService' ) ) {
				return new \FP\Multilanguage\Core\Services\TranslationSyncService( $translation_manager, $job_enqueuer );
			}
			return null;
		}, true );

		// Content Type Service
		$container->bind( 'service.content_type', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\Services\ContentTypeService' ) ) {
				return new \FP\Multilanguage\Core\Services\ContentTypeService();
			}
			return null;
		}, true );

		// Translation Versioning
		$container->bind( 'translation.versioning', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\TranslationVersioning' ) ) {
				return new \FP\Multilanguage\Core\TranslationVersioning();
			}
			return null;
		}, true );

		// Translation Cache
		$container->bind( 'translation.cache', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Core\TranslationCache' ) ) {
				return new \FP\Multilanguage\Core\TranslationCache();
			}
			return null;
		}, true );

		// Diagnostics (legacy singleton wrapper)
		$container->bind( 'diagnostics', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Diagnostics\Diagnostics' ) ) {
				return new \FP\Multilanguage\Diagnostics\Diagnostics();
			}
			return null;
		}, true );

		// Aliases for backward compatibility with helper functions
		$container->alias( 'post.handlers', 'content.post_handler' );
		$container->alias( 'term.handlers', 'content.term_handler' );
		$container->alias( 'content.handlers', 'content.post_handler' );

		// Register Domain services
		$this->registerDomainServices( $container );
	}

	/**
	 * Register domain services.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	protected function registerDomainServices( Container $container ): void {
		// Translation Repository
		$container->bind( 'domain.repository.translation', function( Container $c ) {
			return new \FP\Multilanguage\Domain\Repositories\TranslationRepository();
		}, true );

		// Translation Service (uses existing provider system)
		$container->bind( 'domain.service.translation', function( Container $c ) {
			// This will be implemented to use existing provider system
			// For now, return a placeholder that uses the existing translator
			return new class implements \FP\Multilanguage\Domain\Services\TranslationServiceInterface {
				public function translate( string $content, string $source_language, string $target_language, array $context = array() ): string {
					// Use existing translator if available
					if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
						$translator = \FP\Multilanguage\Core\Container::get( 'translator' );
						if ( $translator && method_exists( $translator, 'translate' ) ) {
							return $translator->translate( $content, $source_language, $target_language );
						}
					}
					return $content; // Fallback
				}

				public function isAvailable(): bool {
					return class_exists( '\FP\Multilanguage\Core\Container' );
				}
			};
		}, true );

		// Post Translation Service
		$container->bind( 'domain.service.post_translation', function( Container $c ) {
			$repository = $c->has( 'domain.repository.translation' ) ? $c->get( 'domain.repository.translation' ) : null;
			$translation_service = $c->has( 'domain.service.translation' ) ? $c->get( 'domain.service.translation' ) : null;
			$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;

			if ( ! $repository || ! $translation_service ) {
				return null;
			}

			return new \FP\Multilanguage\Domain\Services\PostTranslationService(
				$repository,
				$translation_service,
				$logger
			);
		}, true );

		// Term Translation Service
		$container->bind( 'domain.service.term_translation', function( Container $c ) {
			$repository = $c->has( 'domain.repository.translation' ) ? $c->get( 'domain.repository.translation' ) : null;
			$translation_service = $c->has( 'domain.service.translation' ) ? $c->get( 'domain.service.translation' ) : null;
			$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;

			if ( ! $repository || ! $translation_service ) {
				return null;
			}

			return new \FP\Multilanguage\Domain\Services\TermTranslationService(
				$repository,
				$translation_service,
				$logger
			);
		}, true );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register WordPress hooks for content lifecycle
		$this->registerContentHooks( $container );
		$this->registerCronHooks( $container );
		$this->registerActivationHooks( $container );
	}
	
	/**
	 * Register activation/deactivation hooks.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	protected function registerActivationHooks( Container $container ): void {
		// Handle activation hook
		add_action( 'fpml_activate', function() use ( $container ) {
			// Trigger old activation for backward compatibility
			if ( class_exists( '\FP\Multilanguage\Core\Plugin' ) && method_exists( '\FP\Multilanguage\Core\Plugin', 'activate' ) ) {
				try {
					\FP\Multilanguage\Core\Plugin::activate();
				} catch ( \Exception $e ) {
					if ( function_exists( 'error_log' ) ) {
						error_log( 'FPML Activation Error: ' . $e->getMessage() );
					}
				}
			}
		}, 10 );
		
		// Handle deactivation hook
		add_action( 'fpml_deactivate', function() use ( $container ) {
			// Trigger old deactivation for backward compatibility
			if ( class_exists( '\FP\Multilanguage\Core\Plugin' ) && method_exists( '\FP\Multilanguage\Core\Plugin', 'deactivate' ) ) {
				try {
					\FP\Multilanguage\Core\Plugin::deactivate();
				} catch ( \Exception $e ) {
					if ( function_exists( 'error_log' ) ) {
						error_log( 'FPML Deactivation Error: ' . $e->getMessage() );
					}
				}
			}
		}, 10 );
	}

	/**
	 * Register content lifecycle hooks.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	protected function registerContentHooks( Container $container ): void {
		$post_handler = $container->has( 'content.post_handler' ) ? $container->get( 'content.post_handler' ) : null;
		$term_handler = $container->has( 'content.term_handler' ) ? $container->get( 'content.term_handler' ) : null;

		if ( $post_handler ) {
			// These hooks will be registered by PostHandlers class
			// For now, keep existing hook registration in Core\Plugin
		}

		if ( $term_handler ) {
			// These hooks will be registered by TermHandlers class
		}
	}

	/**
	 * Register cron hooks.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	protected function registerCronHooks( Container $container ): void {
		$queue = $container->has( 'queue' ) ? $container->get( 'queue' ) : null;

		if ( $queue ) {
			// Register queue processing cron
			add_action( 'fpml_process_queue', array( $queue, 'process_batch' ) );

			// Schedule cron if not already scheduled
			if ( ! wp_next_scheduled( 'fpml_process_queue' ) ) {
				wp_schedule_event( time(), 'fpml_queue_frequency', 'fpml_process_queue' );
			}
		}
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'queue',
			'translation.manager',
			'translation.job_enqueuer',
			'content.indexer',
			'content.post_handler',
			'content.term_handler',
			'hook.manager',
			'translation.orchestrator',
			'processor',
			'service.assisted_mode',
			'service.dependency_resolver',
			'service.loop_protection',
			'service.setup',
			'service.diagnostics',
			'service.reindex',
			'service.registration',
			'service.translation_sync',
			'service.content_type',
			'domain.repository.translation',
			'domain.service.translation',
			'domain.service.post_translation',
			'domain.service.term_translation',
			'hooks.post',
			'hooks.term',
			'hooks.comment',
			'hooks.widget',
			'hooks.attachment',
		);
	}
}

