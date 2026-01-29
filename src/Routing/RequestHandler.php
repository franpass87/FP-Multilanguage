<?php
/**
 * Request handler - Handles request overrides for language routing.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Frontend\Routing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles request overrides for language routing.
 *
 * @since 0.10.0
 */
class RequestHandler {
    /**
     * Post resolver instance.
     *
     * @var PostResolver
     */
    protected $post_resolver;

    /**
     * Constructor.
     *
     * @param PostResolver $post_resolver Post resolver instance.
     */
    public function __construct( PostResolver $post_resolver ) {
        $this->post_resolver = $post_resolver;
    }

    /**
     * Normalize request variables for language routing.
     *
     * @since 0.2.0
     *
     * @param array<string, mixed> $request Request vars.
     *
     * @return array<string, mixed>
     */
    public function handle_request_overrides( array $request ): array {
        if ( is_admin() || defined( 'REST_REQUEST' ) ) {
            return $request;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();

        if ( isset( $request['lang'] ) ) {
            $lang = sanitize_text_field( $request['lang'] );
            $lang = strtolower( $lang );

            if ( in_array( $lang, $enabled_languages, true ) ) {
                $request['\FPML_lang'] = $lang;
            }

            unset( $request['lang'] );
        }

        if ( isset( $request['fpml_lang'] ) && ! isset( $request['\FPML_lang'] ) ) {
            $request['\FPML_lang'] = $request['fpml_lang'];
        }

        if ( isset( $request['fpml_path'] ) && ! isset( $request['\FPML_path'] ) ) {
            $request['\FPML_path'] = $request['fpml_path'];
        }

        if ( isset( $request['\FPML_lang'] ) ) {
            $request['\FPML_lang'] = sanitize_key( $request['\FPML_lang'] );
        }

        if ( empty( $request['\FPML_lang'] ) || ! in_array( $request['\FPML_lang'], $enabled_languages, true ) ) {
            return $request;
        }

        $current_lang = $request['\FPML_lang'];

        if ( isset( $request['\FPML_path'] ) ) {
            $mapped = $this->post_resolver->map_path_to_query( $request['\FPML_path'], $current_lang );

            unset( $request['\FPML_path'] );
            unset( $request['fpml_path'] );

            if ( ! empty( $mapped ) ) {
                if ( isset( $mapped['p'] ) && $mapped['p'] > 0 ) {
                    $request['p'] = (int) $mapped['p'];
                    unset( $request['name'] );
                    unset( $request['post_type'] );
                } else {
                    $request = array_merge( $request, $mapped );
                }
            }
        }

        unset( $request['fpml_lang'] );

        return $request;
    }

    /**
     * Handle English queries.
     *
     * @since 0.10.0
     *
     * @param \WP_Query $query Query object.
     * @return void
     */
    public function handle_english_queries( $query ) {
        if ( is_admin() ) {
            return;
        }

        $current_lang = $this->post_resolver->get_current_language_from_path();
        
        if ( ! $current_lang ) {
            return;
        }

        $lang = get_query_var( '\FPML_lang' );
        if ( empty( $lang ) ) {
            $lang = get_query_var( 'fpml_lang' );
        }

        if ( empty( $lang ) ) {
            $lang = $current_lang;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        
        if ( ! in_array( $lang, $enabled_languages, true ) ) {
            return;
        }

        $query->set( '\FPML_lang', $lang );
        $query->set( 'fpml_lang', $lang );
    }
}
















