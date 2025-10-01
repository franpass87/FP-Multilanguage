<?php
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'wp_unslash', $value );
        }

        return stripslashes( (string) $value );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $string ) {
        return strip_tags( $string );
    }
}

if ( ! function_exists( 'is_ssl' ) ) {
    function is_ssl() {
        if ( isset( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) ) {
            return true;
        }

        return isset( $_SERVER['SERVER_PORT'] ) && 443 === (int) $_SERVER['SERVER_PORT'];
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return filter_var( $url, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '', $scheme = null ) {
        $path = (string) $path;

        if ( '' !== $path && '/' !== $path[0] ) {
            $path = '/' . $path;
        }

        $default_scheme = 'https';

        if ( 'http' === $scheme || 'https' === $scheme ) {
            $default_scheme = (string) $scheme;
        } elseif ( 'relative' === $scheme ) {
            return $path;
        }

        if ( '' === $path ) {
            return sprintf( '%s://example.com', $default_scheme );
        }

        return sprintf( '%s://example.com%s', $default_scheme, $path );
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $string ) {
        return untrailingslashit( $string ) . '/';
    }
}

if ( ! function_exists( 'untrailingslashit' ) ) {
    function untrailingslashit( $string ) {
        return rtrim( (string) $string, "/\\" );
    }
}

if ( ! function_exists( 'user_trailingslashit' ) ) {
    function user_trailingslashit( $string ) {
        return trailingslashit( rtrim( (string) $string, '/' ) );
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) {
        return parse_url( $url, $component );
    }
}

require_once __DIR__ . '/../fp-multilanguage/includes/class-language.php';
