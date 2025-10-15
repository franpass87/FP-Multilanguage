<?php
/**
 * Internal REST API for admin actions.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Register REST endpoints used by the admin UI.
 *
 * @since 0.2.0
 */
class FPML_REST_Admin {
        /**
         * Singleton instance.
         *
         * @var FPML_REST_Admin|null
         */
        protected static $instance = null;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_REST_Admin
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        protected function __construct() {
                add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }

        /**
         * Register REST routes.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function register_routes() {
                register_rest_route(
                        'fpml/v1',
                        '/queue/run',
                        array(
                                'methods'             => \WP_REST_Server::CREATABLE,
                                'callback'            => array( $this, 'handle_run_queue' ),
                                'permission_callback' => array( $this, 'check_permissions' ),
                        )
                );

                register_rest_route(
                        'fpml/v1',
                        '/test-provider',
                        array(
                                'methods'             => \WP_REST_Server::CREATABLE,
                                'callback'            => array( $this, 'handle_test_provider' ),
                                'permission_callback' => array( $this, 'check_permissions' ),
                        )
                );

                register_rest_route(
                        'fpml/v1',
                        '/reindex',
                        array(
                                'methods'             => \WP_REST_Server::CREATABLE,
                                'callback'            => array( $this, 'handle_reindex' ),
                                'permission_callback' => array( $this, 'check_permissions' ),
                        )
                );

                register_rest_route(
                        'fpml/v1',
                        '/queue/cleanup',
                        array(
                                'methods'             => \WP_REST_Server::CREATABLE,
                                'callback'            => array( $this, 'handle_cleanup' ),
                                'permission_callback' => array( $this, 'check_permissions' ),
                        )
                );

	register_rest_route(
		'fpml/v1',
		'/health',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'handle_health_check' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		)
	);

		register_rest_route(
			'fpml/v1',
			'/preview-translation',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_preview_translation' ),
				'permission_callback' => array( $this, 'check_permissions' ),
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
				'callback'            => array( $this, 'handle_check_billing' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'provider' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

        /**
         * Ensure the current request is authorized.
         *
         * @since 0.2.0
         *
         * @param WP_REST_Request $request Request instance.
         *
         * @return bool|WP_Error
         */
        public function check_permissions( $request ) {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return new WP_Error(
                                'fpml_rest_forbidden',
                                __( 'Permessi insufficienti.', 'fp-multilanguage' ),
                                array( 'status' => rest_authorization_required_code() )
                        );
                }

                $nonce = $request->get_header( 'X-WP-Nonce' );

                if ( empty( $nonce ) ) {
                        $nonce = $request->get_param( '_wpnonce' );
                }

                if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                        return new WP_Error(
                                'fpml_rest_nonce_invalid',
                                __( 'Nonce non valido.', 'fp-multilanguage' ),
                                array( 'status' => rest_authorization_required_code() )
                        );
                }

                return true;
        }

        /**
         * Process a queue batch via REST.
         *
         * @since 0.2.0
         *
         * @param WP_REST_Request $request Request instance.
         *
         * @return WP_REST_Response|WP_Error
         */
        public function handle_run_queue( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                $plugin = FPML_Plugin::instance();

                if ( $plugin->is_assisted_mode() ) {
                        return new WP_Error(
                                'fpml_assisted_mode',
                                __( 'Modalità assistita attiva: la coda interna è disabilitata.', 'fp-multilanguage' ),
                                array( 'status' => 409 )
                        );
                }

                $processor = FPML_Processor::instance();
                $result    = $processor->run_queue();

                if ( is_wp_error( $result ) ) {
                        $result->add_data( array( 'status' => 409 ), $result->get_error_code() );

                        return $result;
                }

                return rest_ensure_response(
                        array(
                                'success' => true,
                                'summary' => $result,
                        )
                );
        }

        /**
         * Execute a provider test translation.
         *
         * @since 0.2.0
         *
         * @param WP_REST_Request $request Request instance.
         *
         * @return WP_REST_Response|WP_Error
         */
        public function handle_test_provider( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                $processor  = FPML_Processor::instance();
                $translator = $processor->get_translator_instance();

                if ( is_wp_error( $translator ) ) {
                        $translator->add_data( array( 'status' => 400 ), $translator->get_error_code() );

                        return $translator;
                }

                $sample  = __( 'Questa è una frase di prova per FP Multilanguage.', 'fp-multilanguage' );
                $start   = microtime( true );
                $output  = $translator->translate( $sample, 'it', 'en', 'general' );
                $elapsed = microtime( true ) - $start;

                if ( is_wp_error( $output ) ) {
                        $output->add_data( array( 'status' => 400 ), $output->get_error_code() );

                        return $output;
                }

                $cost = $translator->estimate_cost( $sample );

                return rest_ensure_response(
                        array(
                                'success'         => true,
                                'provider'        => FPML_Settings::instance()->get( 'provider', '' ),
                                'elapsed'         => round( $elapsed, 4 ),
                                'characters'      => mb_strlen( $sample, 'UTF-8' ),
                                'estimated_cost'  => $cost,
                                'sample'          => $sample,
                                'translation'     => wp_kses_post( $output ),
                        )
                );
        }

        /**
         * Trigger a full reindex via REST.
         *
         * @since 0.2.0
         *
         * @param WP_REST_Request $request Request instance.
         *
         * @return WP_REST_Response
         */
        public function handle_reindex( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                $plugin = FPML_Plugin::instance();

                if ( $plugin->is_assisted_mode() ) {
                        return new WP_Error(
                                'fpml_assisted_mode',
                                __( 'Modalità assistita attiva: il reindex automatico è disabilitato.', 'fp-multilanguage' ),
                                array( 'status' => 409 )
                        );
                }

                $summary = $plugin->reindex_content();

                if ( is_wp_error( $summary ) ) {
                        $summary->add_data( array( 'status' => 409 ), $summary->get_error_code() );

                        return $summary;
                }

                return rest_ensure_response(
                        array(
                                'success' => true,
                                'summary' => $summary,
                        )
                );
        }

        /**
         * Execute a manual cleanup using the configured retention settings.
         *
         * @since 0.3.1
         *
         * @param WP_REST_Request $request Request instance.
         *
         * @return WP_REST_Response|WP_Error
         */
        public function handle_cleanup( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                $plugin = FPML_Plugin::instance();

                if ( $plugin->is_assisted_mode() ) {
                        return new WP_Error(
                                'fpml_assisted_mode',
                                __( 'Modalità assistita attiva: la coda interna è disabilitata.', 'fp-multilanguage' ),
                                array( 'status' => 409 )
                        );
                }

                $settings = FPML_Settings::instance();
                $days     = $settings ? (int) $settings->get( 'queue_retention_days', 0 ) : 0;

                if ( $days <= 0 ) {
                        return new WP_Error(
                                'fpml_cleanup_disabled',
                                __( 'Configura prima la retention della coda dalle impostazioni.', 'fp-multilanguage' ),
                                array( 'status' => 400 )
                        );
                }

                $states = $plugin->get_queue_cleanup_states();

                if ( empty( $states ) ) {
                        return new WP_Error(
                                'fpml_cleanup_states_empty',
                                __( 'Nessuno stato valido configurato per la pulizia della coda.', 'fp-multilanguage' ),
                                array( 'status' => 400 )
                        );
                }

                $queue   = FPML_Queue::instance();
                $deleted = $queue->cleanup_old_jobs( $states, $days, 'updated_at' );

                if ( is_wp_error( $deleted ) ) {
                        $deleted->add_data( array( 'status' => 500 ), $deleted->get_error_code() );

                        return $deleted;
                }

                return rest_ensure_response(
                        array(
                                'success' => true,
                                'deleted' => (int) $deleted,
                                'states'  => $states,
                                'days'    => $days,
                        )
                );
        }

        /**
         * Health check endpoint for monitoring.
         *
         * @since 0.3.2
         *
         * @param WP_REST_Request $request Request instance.
         *
         * @return WP_REST_Response
         */
        public function handle_health_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                $queue = FPML_Queue::instance();
                $processor = FPML_Processor::instance();
                $plugin = FPML_Plugin::instance();

                global $wpdb;
                $table = $wpdb->prefix . 'fpml_queue';

                // Check database accessibility
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $db_check = $wpdb->query( $wpdb->prepare( 'SELECT 1 FROM %i LIMIT 1', $table ) );

                $health = array(
                        'status' => 'ok',
                        'version' => defined( 'FPML_PLUGIN_VERSION' ) ? FPML_PLUGIN_VERSION : 'unknown',
                        'checks' => array(
                                'database' => array(
                                        'accessible' => false !== $db_check,
                                ),
                                'queue' => array(
                                        'accessible' => true,
                                        'locked' => $processor->is_locked(),
                                        'pending_jobs' => $queue->count_by_state( 'pending' ),
                                        'error_jobs' => $queue->count_by_state( 'error' ),
                                ),
                                'provider' => array(
                                        'configured' => ! is_wp_error( $processor->get_translator_instance() ),
                                ),
                                'assisted_mode' => $plugin->is_assisted_mode(),
                        ),
                        'timestamp' => current_time( 'mysql', true ),
                );

                // Determine overall status
                $pending = isset( $health['checks']['queue']['pending_jobs'] ) ? (int) $health['checks']['queue']['pending_jobs'] : 0;
                $errors = isset( $health['checks']['queue']['error_jobs'] ) ? (int) $health['checks']['queue']['error_jobs'] : 0;

                if ( $pending > 10000 || $errors > 100 ) {
                        $health['status'] = 'warning';
                }

                if ( ! $health['checks']['database']['accessible'] || ! $health['checks']['provider']['configured'] ) {
                        $health['status'] = 'error';
                }

		return rest_ensure_response( $health );
	}

	/**
	 * Preview translation without saving.
	 *
	 * @since 0.4.1
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_preview_translation( $request ) {
		$text     = $request->get_param( 'text' );
		$provider = $request->get_param( 'provider' );
		$source   = $request->get_param( 'source' );
		$target   = $request->get_param( 'target' );

		if ( empty( $text ) ) {
			return new WP_Error(
				'fpml_empty_text',
				__( 'Testo da tradurre mancante.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$processor  = FPML_Processor::instance();
		$translator = $processor->get_translator_instance();

		// Override provider if specified
		if ( ! empty( $provider ) ) {
			$settings = FPML_Settings::instance();
			$current_provider = $settings->get( 'provider', '' );
			
			if ( $provider !== $current_provider ) {
				// Temporarily switch provider for preview
				$translator = $this->get_translator_by_slug( $provider );
			}
		}

		if ( is_wp_error( $translator ) ) {
			$translator->add_data( array( 'status' => 400 ), $translator->get_error_code() );
			return $translator;
		}

		// Check cache first
		$cache = FPML_Container::get( 'translation_cache' );
		if ( $cache ) {
			$cached = $cache->get( $text, $provider ?: 'current', $source, $target );
			if ( false !== $cached ) {
				return rest_ensure_response(
					array(
						'success'        => true,
						'original'       => $text,
						'translated'     => $cached,
						'provider'       => $provider ?: FPML_Settings::instance()->get( 'provider', '' ),
						'cached'         => true,
						'characters'     => mb_strlen( $text, 'UTF-8' ),
						'estimated_cost' => 0,
					)
				);
			}
		}

		// Translate
		$start       = microtime( true );
		$translated  = $translator->translate( $text, $source, $target, 'general' );
		$elapsed     = microtime( true ) - $start;

		if ( is_wp_error( $translated ) ) {
			$translated->add_data( array( 'status' => 400 ), $translated->get_error_code() );
			return $translated;
		}

		$cost = $translator->estimate_cost( $text );

		// Cache the result
		if ( $cache ) {
			$cache->set( $text, $provider ?: 'current', $translated, $source, $target );
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'original'       => $text,
				'translated'     => wp_kses_post( $translated ),
				'provider'       => $provider ?: FPML_Settings::instance()->get( 'provider', '' ),
				'cached'         => false,
				'elapsed'        => round( $elapsed, 4 ),
				'characters'     => mb_strlen( $text, 'UTF-8' ),
				'estimated_cost' => $cost,
			)
		);
	}

	/**
	 * Check billing status for OpenAI provider.
	 *
	 * @since 0.4.2
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_check_billing( $request ) {
		$provider = $request->get_param( 'provider' );
		
		if ( empty( $provider ) ) {
			$settings = FPML_Settings::instance();
			$provider = $settings->get( 'provider', '' );
		}

		if ( 'openai' !== $provider ) {
			return new WP_Error(
				'fpml_unsupported_provider',
				__( 'Il controllo billing è disponibile solo per OpenAI al momento.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$translator = $this->get_translator_by_slug( $provider );

		if ( is_wp_error( $translator ) ) {
			$translator->add_data( array( 'status' => 400 ), $translator->get_error_code() );
			return $translator;
		}

		if ( ! method_exists( $translator, 'verify_billing_status' ) ) {
			return new WP_Error(
				'fpml_method_not_supported',
				__( 'Il provider selezionato non supporta il controllo billing.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$result = $translator->verify_billing_status();

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ), $result->get_error_code() );
			return $result;
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'provider' => $provider,
				'billing'  => $result,
			)
		);
	}

	/**
	 * Get translator instance by provider slug.
	 *
	 * @since 0.4.1
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return FPML_Translator_Interface|WP_Error
	 */
	protected function get_translator_by_slug( $provider ) {
		$settings = FPML_Settings::instance();

		switch ( $provider ) {
			case 'openai':
				$api_key = $settings->get( 'openai_api_key', '' );
				if ( empty( $api_key ) ) {
					return new WP_Error( 'fpml_no_api_key', __( 'API key OpenAI mancante.', 'fp-multilanguage' ) );
				}
				return new FPML_Provider_OpenAI();

			case 'deepl':
				$api_key = $settings->get( 'deepl_api_key', '' );
				if ( empty( $api_key ) ) {
					return new WP_Error( 'fpml_no_api_key', __( 'API key DeepL mancante.', 'fp-multilanguage' ) );
				}
				return new FPML_Provider_DeepL();

			case 'google':
				$api_key = $settings->get( 'google_api_key', '' );
				if ( empty( $api_key ) ) {
					return new WP_Error( 'fpml_no_api_key', __( 'API key Google mancante.', 'fp-multilanguage' ) );
				}
				return new FPML_Provider_Google();

			case 'libretranslate':
				$api_url = $settings->get( 'libretranslate_api_url', '' );
				if ( empty( $api_url ) ) {
					return new WP_Error( 'fpml_no_api_url', __( 'URL API LibreTranslate mancante.', 'fp-multilanguage' ) );
				}
				return new FPML_Provider_LibreTranslate();

			default:
				return new WP_Error( 'fpml_invalid_provider', __( 'Provider non valido.', 'fp-multilanguage' ) );
		}
	}
}
