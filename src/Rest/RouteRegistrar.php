<?php
/**
 * REST Route Registrar - Registers all REST API routes.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Rest;

use FP\Multilanguage\Rest\Handlers\QueueHandler;
use FP\Multilanguage\Rest\Handlers\ProviderHandler;
use FP\Multilanguage\Rest\Handlers\ReindexHandler;
use FP\Multilanguage\Rest\Handlers\SystemHandler;
use FP\Multilanguage\Rest\Handlers\TranslationHandler;
use FP\Multilanguage\Rest\PermissionChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all REST API routes.
 *
 * @since 0.10.0
 */
class RouteRegistrar {
	/**
	 * Permission checker instance.
	 *
	 * @since 0.10.0
	 *
	 * @var PermissionChecker
	 */
	protected PermissionChecker $permission_checker;

	/**
	 * Queue handler instance.
	 *
	 * @since 0.10.0
	 *
	 * @var QueueHandler
	 */
	protected QueueHandler $queue_handler;

	/**
	 * Provider handler instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ProviderHandler
	 */
	protected ProviderHandler $provider_handler;

	/**
	 * Reindex handler instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ReindexHandler
	 */
	protected ReindexHandler $reindex_handler;

	/**
	 * System handler instance.
	 *
	 * @since 0.10.0
	 *
	 * @var SystemHandler
	 */
	protected SystemHandler $system_handler;

	/**
	 * Translation handler instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TranslationHandler
	 */
	protected TranslationHandler $translation_handler;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	public function __construct() {
		$this->permission_checker = new PermissionChecker();
		$this->queue_handler = new QueueHandler();
		$this->provider_handler = new ProviderHandler();
		$this->reindex_handler = new ReindexHandler();
		$this->system_handler = new SystemHandler();
		$this->translation_handler = new TranslationHandler();
	}

	/**
	 * Register all REST routes.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Queue routes
		register_rest_route(
			'fpml/v1',
			'/queue/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->queue_handler, 'handle_run_queue' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/queue/cleanup',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->queue_handler, 'handle_cleanup' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		// Provider routes
		register_rest_route(
			'fpml/v1',
			'/test-provider',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->provider_handler, 'handle_test_provider' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/preview-translation',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->provider_handler, 'handle_preview_translation' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
				'args'                => array(
					'text' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'provider' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'source' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'it',
						'sanitize_callback' => 'sanitize_key',
					),
					'target' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'en',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/check-billing',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->provider_handler, 'handle_check_billing' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
				'args'                => array(
					'provider' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/refresh-nonce',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->provider_handler, 'handle_refresh_nonce' ),
				'permission_callback' => array( $this->permission_checker, 'check_refresh_nonce_permissions' ),
			)
		);

		// Reindex routes
		register_rest_route(
			'fpml/v1',
			'/reindex',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->reindex_handler, 'handle_reindex' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/reindex-batch',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->reindex_handler, 'handle_reindex_batch' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
				'args'                => array(
					'step' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// System routes
		register_rest_route(
			'fpml/v1',
			'/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->system_handler, 'handle_health_check' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->system_handler, 'handle_get_stats' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->system_handler, 'handle_get_logs' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		// Translation routes
		register_rest_route(
			'fpml/v1',
			'/translations',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->translation_handler, 'handle_get_translations' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/translations/bulk',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->translation_handler, 'handle_bulk_translate_rest' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/translations/(?P<id>\d+)/regenerate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->translation_handler, 'handle_regenerate_translation' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/translations/(?P<id>\d+)/versions',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->translation_handler, 'handle_get_versions' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'fpml/v1',
			'/translations/(?P<id>\d+)/rollback',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->translation_handler, 'handle_rollback' ),
				'permission_callback' => array( $this->permission_checker, 'check_permissions' ),
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'version' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}
}
















