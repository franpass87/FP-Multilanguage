<?php
namespace FPMultilanguage\Admin\Settings;

use FPMultilanguage\Admin\AdminNotices;
use FPMultilanguage\Services\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RestController {
    public const REST_NAMESPACE = 'fp-multilanguage/v1';
    public const NONCE_ACTION = 'fp_multilanguage_settings';

    private Repository $repository;

    private Logger $logger;

    private AdminNotices $notices;

    private ProviderTester $providerTester;

    public function __construct( Repository $repository, Logger $logger, AdminNotices $notices, ProviderTester $providerTester ) {
        $this->repository     = $repository;
        $this->logger         = $logger;
        $this->notices        = $notices;
        $this->providerTester = $providerTester;
    }

    public function register_hooks(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            '/settings',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'rest_get_settings' ),
                    'permission_callback' => array( $this, 'rest_permissions' ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'rest_update_settings' ),
                    'permission_callback' => array( $this, 'rest_permissions' ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/providers/test',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'rest_test_provider' ),
                    'permission_callback' => array( $this, 'rest_permissions' ),
                ),
            )
        );
    }

    public function rest_permissions(): bool {
        return current_user_can( 'manage_options' );
    }

    public function rest_get_settings( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );

        return rest_ensure_response( $this->repository->get_options() );
    }

    /**
     * @return WP_Error|WP_REST_Response
     */
    public function rest_update_settings( WP_REST_Request $request ) {
        if ( ! $this->verify_rest_nonce( $request ) ) {
            $message = __( 'Nonce di sicurezza non valido.', 'fp-multilanguage' );

            $this->logger->warning( $message );
            $this->notices->add_error( $message );

            return new WP_Error( 'invalid_nonce', $message, array( 'status' => 403 ) );
        }

        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $message = __( 'Payload non valido', 'fp-multilanguage' );
            $this->logger->error( $message );
            $this->notices->add_error( $message );

            return new WP_Error( 'invalid_payload', $message, array( 'status' => 400 ) );
        }

        $options = $this->repository->sanitize_options( $params );
        $this->repository->update_options( $options );

        $options = $this->repository->get_options();
        $this->logger->info( 'Settings updated via REST API.' );
        $this->notices->add_notice( __( 'Impostazioni aggiornate correttamente.', 'fp-multilanguage' ) );

        return rest_ensure_response( $options );
    }

    /**
     * @return WP_Error|WP_REST_Response
     */
    public function rest_test_provider( WP_REST_Request $request ) {
        if ( ! $this->verify_rest_nonce( $request ) ) {
            $message = __( 'Nonce di sicurezza non valido.', 'fp-multilanguage' );

            $this->logger->warning( 'Provider test blocked by invalid nonce.' );

            return new WP_Error( 'invalid_nonce', $message, array( 'status' => 403 ) );
        }

        $params   = $request->get_json_params();
        $provider = is_array( $params ) ? sanitize_key( (string) ( $params['provider'] ?? '' ) ) : '';
        $options  = is_array( $params['options'] ?? null ) ? (array) $params['options'] : array();

        if ( '' === $provider ) {
            $message = __( 'Provider non valido.', 'fp-multilanguage' );

            return new WP_Error( 'invalid_provider', $message, array( 'status' => 400 ) );
        }

        $sanitized = $this->providerTester->sanitize_options( $provider, $options );
        if ( is_wp_error( $sanitized ) ) {
            return $sanitized;
        }

        $result = $this->providerTester->test_credentials( $provider, $sanitized );

        return rest_ensure_response( $result );
    }

    public function get_rest_namespace(): string {
        return self::REST_NAMESPACE;
    }

    public function get_nonce_action(): string {
        return self::NONCE_ACTION;
    }

    private function verify_rest_nonce( WP_REST_Request $request ): bool {
        $nonce = '';

        if ( method_exists( $request, 'get_header' ) ) {
            $headerNonce = $request->get_header( 'X-WP-Nonce' );
            if ( is_string( $headerNonce ) ) {
                $nonce = $headerNonce;
            }
        }

        if ( '' === $nonce && method_exists( $request, 'get_param' ) ) {
            $paramNonce = $request->get_param( '_wpnonce' );
            if ( is_string( $paramNonce ) ) {
                $nonce = $paramNonce;
            }
        }

        if ( '' === $nonce ) {
            return false;
        }

        if ( function_exists( 'wp_unslash' ) ) {
            $nonce = wp_unslash( $nonce );
        }

        if ( function_exists( 'sanitize_text_field' ) ) {
            $nonce = sanitize_text_field( $nonce );
        }

        if ( '' === $nonce ) {
            return false;
        }

        if ( function_exists( 'wp_verify_nonce' ) ) {
            return false !== wp_verify_nonce( $nonce, self::NONCE_ACTION );
        }

        return true;
    }
}
