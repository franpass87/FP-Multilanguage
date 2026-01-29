<?php
/**
 * CLI Service Provider.
 *
 * Registers WP-CLI commands.
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
 * CLI service provider.
 *
 * @since 1.0.0
 */
class CLIServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Only register if WP-CLI is available
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		// CLI Queue Command
		$container->bind( 'cli.command.queue', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\CLI\QueueCommand' ) ) {
				return new \FP\Multilanguage\CLI\QueueCommand();
			}
			return null;
		}, true );

		// CLI Utility Command
		$container->bind( 'cli.command.utility', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\CLI\UtilityCommand' ) ) {
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				$queue = $c->has( 'queue' ) ? $c->get( 'queue' ) : null;
				return new \FP\Multilanguage\CLI\UtilityCommand( $logger, $queue );
			}
			return null;
		}, true );

		// Legacy CLI (for backward compatibility)
		$container->bind( 'cli.legacy', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\CLI\CLI' ) ) {
				return \FP\Multilanguage\CLI\CLI::instance();
			}
			return null;
		}, true );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Only boot if WP-CLI is available
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		// Register WP-CLI commands
		$this->registerCLICommands( $container );
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	protected function registerCLICommands( Container $container ): void {
		// Queue command
		$queue_command = $container->has( 'cli.command.queue' ) ? $container->get( 'cli.command.queue' ) : null;
		if ( $queue_command && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'fpml queue', get_class( $queue_command ) );
		}

		// Utility command
		$utility_command = $container->has( 'cli.command.utility' ) ? $container->get( 'cli.command.utility' ) : null;
		if ( $utility_command && class_exists( '\WP_CLI' ) ) {
			// Register utility subcommands
			if ( method_exists( $utility_command, 'test_translation' ) ) {
				\WP_CLI::add_command( 'fpml test-translation', array( $utility_command, 'test_translation' ) );
			}
			if ( method_exists( $utility_command, 'sync_status' ) ) {
				\WP_CLI::add_command( 'fpml sync-status', array( $utility_command, 'sync_status' ) );
			}
			if ( method_exists( $utility_command, 'export_translations' ) ) {
				\WP_CLI::add_command( 'fpml export-translations', array( $utility_command, 'export_translations' ) );
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
			'cli.command.queue',
			'cli.command.utility',
			'cli.legacy',
		);
	}
}


