<?php
/**
 * Internal REST API for admin actions.
 *
 * @package FP_Multilanguage
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
}
