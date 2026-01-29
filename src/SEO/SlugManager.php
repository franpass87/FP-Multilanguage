<?php
/**
 * SEO Slug Manager - Handles slug translation and legacy redirects.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages slug translation and legacy slug redirects.
 *
 * @since 0.10.0
 */
class SlugManager {
    /**
     * Settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Logger instance.
     *
     * @var \FPML_Logger
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param \FPML_Settings $settings Settings instance.
     * @param \FPML_Logger   $logger   Logger instance.
     */
    public function __construct( $settings, $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    /**
     * Handle slug translation saving logic.
     *
     * @since 0.10.0
     *
     * @param \WP_Post $post      English post object.
     * @param string   $new_value Raw translated phrase.
     * @return void
     */
    public function handle_slug_translation( $post, $new_value ) {
        if ( ! ( $post instanceof \WP_Post ) ) {
            return;
        }

        $translated = sanitize_text_field( (string) $new_value );

        if ( '' === $translated ) {
            return;
        }

        if ( false !== strpos( $translated, ' ' ) ) {
            $slug_candidate = sanitize_title( $translated );
        } else {
            $slug_candidate = sanitize_title( $translated );
        }

        if ( '' === $slug_candidate ) {
            return;
        }

        $slug_candidate = preg_replace( '/^en[-_]/i', '', $slug_candidate );

        $old_slug = (string) $post->post_name;
        $old_slug_clean = preg_replace( '/^en[-_]/i', '', $old_slug );

        if ( $old_slug_clean === $slug_candidate ) {
            update_post_meta( $post->ID, '_fpml_status_slug', 'automatic' );
            return;
        }

        $result = \fpml_safe_update_post(
            array(
                'ID'        => $post->ID,
                'post_name' => $slug_candidate,
            )
        );

        if ( is_wp_error( $result ) ) {
            $this->logger->log(
                'error',
                sprintf( 'Impossibile aggiornare lo slug inglese per il post #%d: %s', $post->ID, $result->get_error_message() ),
                array(
                    'post_id' => $post->ID,
                    'context' => 'slug',
                )
            );
            return;
        }

        update_post_meta( $post->ID, '_fpml_status_slug', 'automatic' );

        if ( $this->settings->get( 'slug_redirect', false ) && '' !== $old_slug ) {
            $this->register_slug_redirect( $post->ID, $old_slug, $slug_candidate );
        }
    }

    /**
     * Persist redirect mapping for legacy English slugs.
     *
     * @since 0.10.0
     *
     * @param int    $post_id  Post ID.
     * @param string $old_slug Previous slug.
     * @param string $new_slug New slug.
     * @return void
     */
    protected function register_slug_redirect( $post_id, $old_slug, $new_slug ) {
        $post_id  = absint( $post_id );
        $old_slug = sanitize_title( $old_slug );
        $new_slug = sanitize_title( $new_slug );

        if ( $post_id <= 0 || '' === $old_slug || '' === $new_slug || $old_slug === $new_slug ) {
            return;
        }

        $redirects = get_option( '\FPML_slug_redirects', array() );

        if ( ! is_array( $redirects ) ) {
            $redirects = array();
        }

        $redirects = array_values( array_filter( $redirects, function( $entry ) use ( $post_id, $old_slug ) {
            if ( ! is_array( $entry ) || ! isset( $entry['post_id'], $entry['old_slug'] ) ) {
                return false;
            }

            if ( (int) $entry['post_id'] === $post_id && sanitize_title( $entry['old_slug'] ) === $old_slug ) {
                return false;
            }

            return true;
        } ) );

        $redirects[] = array(
            'post_id'   => $post_id,
            'old_slug'  => $old_slug,
            'new_slug'  => $new_slug,
            'lang'      => 'en',
            'timestamp' => time(),
        );

        if ( count( $redirects ) > 50 ) {
            $redirects = array_slice( $redirects, -50 );
        }

        update_option( '\FPML_slug_redirects', $redirects, false );

        $this->logger->log(
            'info',
            sprintf( 'Registrato redirect 301 da %1$s a %2$s per il post #%3$d.', $old_slug, $new_slug, $post_id ),
            array(
                'post_id' => $post_id,
                'context' => 'slug_redirect',
            )
        );
    }

    /**
     * Redirect legacy English slugs when requested.
     *
     * @since 0.10.0
     *
     * @return void
     */
    public function handle_legacy_slug_redirects() {
        if ( is_admin() || ! is_404() || ! $this->settings->get( 'slug_redirect', false ) ) {
            return;
        }

        $slug = $this->get_request_slug();

        if ( '' === $slug ) {
            return;
        }

        $redirects = get_option( '\FPML_slug_redirects', array() );

        if ( empty( $redirects ) || ! is_array( $redirects ) ) {
            return;
        }

        foreach ( $redirects as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['old_slug'] ) || $entry['old_slug'] !== $slug ) {
                continue;
            }

            if ( ! empty( $entry['lang'] ) && 'en' !== $entry['lang'] ) {
                continue;
            }

            $post_id = isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;

            if ( $post_id <= 0 ) {
                continue;
            }

            $post = get_post( $post_id );

            if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
                continue;
            }

            $target = get_permalink( $post );

            if ( empty( $target ) ) {
                continue;
            }

            wp_safe_redirect( $target, 301 );
            exit;
        }
    }

    /**
     * Extract slug from current request.
     *
     * @since 0.10.0
     *
     * @return string
     */
    protected function get_request_slug() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

        if ( empty( $request_uri ) ) {
            return '';
        }

        $parsed = wp_parse_url( $request_uri );

        if ( empty( $parsed['path'] ) ) {
            return '';
        }

        $path = trim( $parsed['path'], '/' );
        $segments = explode( '/', $path );

        if ( empty( $segments ) ) {
            return '';
        }

        return sanitize_title( end( $segments ) );
    }
}
















