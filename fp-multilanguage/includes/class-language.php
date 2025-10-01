<?php
/**
 * Language resolver and helpers.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manage current language state, redirects and switcher helpers.
 *
 * @since 0.2.0
 */
class FPML_Language {
    /**
     * Cookie key for language preference.
     */
    const COOKIE_NAME = 'fpml_lang_pref';

    /**
     * Cookie lifetime (30 days).
     */
    const COOKIE_TTL = 2592000;

    /**
     * Target language slug.
     */
    const TARGET = 'en';

    /**
     * Source language slug.
     */
    const SOURCE = 'it';

    /**
     * Singleton instance.
     *
     * @var FPML_Language|null
     */
    protected static $instance = null;

    /**
     * Current language code (it|en).
     *
     * @var string
     */
    protected $current = self::SOURCE;

    /**
     * Cached settings instance.
     *
     * @var FPML_Settings
     */
    protected $settings;

    /**
     * Cached term pairs map.
     *
     * @since 0.3.0
     *
     * @var array|null
     */
    protected $term_pairs = null;

    /**
     * Retrieve singleton.
     *
     * @since 0.2.0
     *
     * @return FPML_Language
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
        $this->settings = FPML_Settings::instance();

        add_action( 'parse_query', array( $this, 'determine_language' ) );
        add_action( 'template_redirect', array( $this, 'maybe_redirect_browser_language' ), 0 );
        add_action( 'template_redirect', array( $this, 'persist_language_cookie' ), 1 );
        add_shortcode( 'fp_lang_switcher', array( $this, 'render_switcher' ) );
        add_filter( 'locale', array( $this, 'filter_locale' ) );
    }

    /**
     * Determine current language from query vars, request and cookies.
     *
     * @since 0.2.0
     *
     * @param WP_Query $query Current query.
     *
     * @return void
     */
    public function determine_language( $query ) {
        if ( ! $query->is_main_query() || is_admin() ) {
            return;
        }

        $lang = self::SOURCE;

        $requested = $query->get( 'fpml_lang' );

        if ( is_string( $requested ) ) {
            $requested = sanitize_key( $requested );
        } else {
            $requested = '';
        }
        if ( empty( $requested ) && isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $requested = strtolower( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ( self::TARGET === $requested ) {
            $lang = self::TARGET;
        } else {
            $path      = $this->get_current_path();
            $lowered   = strtolower( $path );
            $is_target = ( 0 === strpos( $lowered, '/en/' ) );

            if ( ! $is_target ) {
                $is_target = ( '/en' === rtrim( $lowered, '/' ) );
            }

            if ( $is_target ) {
                $lang = self::TARGET;
            }
        }

        $this->current = $lang;
        $query->set( 'fpml_lang', $lang );
    }

    /**
     * Persist language choice in cookie for subsequent visits.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function persist_language_cookie() {
        if ( is_admin() || headers_sent() ) {
            return;
        }

        if ( ! $this->has_cookie_consent() ) {
            return;
        }

        $cookie_value = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE

        if ( $cookie_value === $this->current ) {
            return;
        }

        setcookie( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
            self::COOKIE_NAME,
            $this->current,
            time() + self::COOKIE_TTL,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        $_COOKIE[ self::COOKIE_NAME ] = $this->current; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
    }

    /**
     * Redirect first-time visitors based on browser language preference.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function maybe_redirect_browser_language() {
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        if ( self::TARGET === $this->current ) {
            return;
        }

        if ( ! $this->settings->get( 'browser_redirect', false ) ) {
            return;
        }

        if ( ! $this->has_cookie_consent() ) {
            return;
        }

        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
            return;
        }

        $accept_language = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER

        if ( empty( $accept_language ) ) {
            return;
        }

        if ( false === stripos( $accept_language, 'en' ) ) {
            return;
        }

        $target_url = $this->get_url_for_language( self::TARGET );

        if ( empty( $target_url ) || headers_sent() ) {
            return;
        }

        wp_safe_redirect( $target_url, 302 );
        exit;
    }

    /**
     * Force English locale on frontend when needed.
     *
     * @since 0.3.0
     *
     * @param string $locale Current locale.
     *
     * @return string
     */
    public function filter_locale( $locale ) {
        if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return $locale;
        }

        if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return $locale;
        }

        $language = $this->current;

        if ( self::TARGET !== $language ) {
            $requested = '';

            if ( isset( $_GET['fpml_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested_raw = wp_unslash( $_GET['fpml_lang'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested     = strtolower( sanitize_text_field( $requested_raw ) );
            } elseif ( isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested_raw = wp_unslash( $_GET['lang'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested     = strtolower( sanitize_text_field( $requested_raw ) );
            }

            if ( self::TARGET !== $requested ) {
                $path    = $this->get_current_path();
                $lowered = strtolower( $path );
                $is_target = ( 0 === strpos( $lowered, '/en/' ) );

                if ( ! $is_target ) {
                    $is_target = ( '/en' === rtrim( $lowered, '/' ) );
                }

                if ( $is_target ) {
                    $requested = self::TARGET;
                }
            }

            if ( self::TARGET === $requested ) {
                $language = self::TARGET;
            }
        }

        if ( self::TARGET !== $language ) {
            return $locale;
        }

        return 'en_US';
    }

    /**
     * Retrieve current language code.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function get_current_language() {
        return $this->current;
    }

    /**
     * Build URL for a given language keeping the current request when possible.
     *
     * @since 0.2.0
     *
     * @param string $lang Language code.
     *
     * @return string
     */
    public function get_url_for_language( $lang ) {
        $lang = self::TARGET === strtolower( $lang ) ? self::TARGET : self::SOURCE;

        $url = $this->get_contextual_url( $lang );

        if ( empty( $url ) ) {
            $url = $this->get_language_home( $lang );
        }

        $url = $this->apply_language_to_url( $url, $lang );

        return esc_url_raw( $url );
    }

    /**
     * Render the [fp_lang_switcher] shortcode.
     *
     * @since 0.2.0
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string
     */
    public function render_switcher( $atts ) {
        $atts = shortcode_atts(
            array(
                'style'      => 'inline',
                'show_flags' => '0',
            ),
            $atts,
            'fp_lang_switcher'
        );

        $style      = in_array( $atts['style'], array( 'inline', 'dropdown' ), true ) ? $atts['style'] : 'inline';
        $show_flags = in_array( (string) $atts['show_flags'], array( '1', 'true', 'yes' ), true );
        $current    = $this->get_current_language();

        $languages = array(
            self::SOURCE => array(
                'label' => __( 'Italiano', 'fp-multilanguage' ),
                'url'   => $this->get_url_for_language( self::SOURCE ),
            ),
            self::TARGET => array(
                'label' => __( 'English', 'fp-multilanguage' ),
                'url'   => $this->get_url_for_language( self::TARGET ),
            ),
        );

        if ( 'dropdown' === $style ) {
            return $this->render_dropdown_switcher( $languages, $current, $show_flags );
        }

        return $this->render_inline_switcher( $languages, $current, $show_flags );
    }

    /**
     * Render inline language switcher.
     *
     * @since 0.2.0
     *
     * @param array  $languages Language map.
     * @param string $current   Current language code.
     * @param bool   $show_flags Whether to display flags.
     *
     * @return string
     */
    protected function render_inline_switcher( $languages, $current, $show_flags ) {
        $items = array();

        foreach ( $languages as $code => $language ) {
            $classes = array( 'fpml-switcher__item' );

            if ( $current === $code ) {
                $classes[] = 'fpml-switcher__item--current';
            }

            $label = esc_html( $language['label'] );

            if ( $show_flags ) {
                $label = $this->maybe_prefix_flag( $code ) . $label;
            }

            $items[] = sprintf(
                '<a class="%1$s" href="%2$s" rel="nofollow">%3$s</a>',
                esc_attr( implode( ' ', $classes ) ),
                esc_url( $language['url'] ),
                $label
            );
        }

        return sprintf(
            '<div class="fpml-switcher fpml-switcher--inline">%s</div>',
            implode( '<span class="fpml-switcher__separator"> / </span>', $items )
        );
    }

    /**
     * Render dropdown switcher.
     *
     * @since 0.2.0
     *
     * @param array  $languages Languages map.
     * @param string $current   Current language code.
     * @param bool   $show_flags Whether to display flags.
     *
     * @return string
     */
    protected function render_dropdown_switcher( $languages, $current, $show_flags ) {
        $options = array();

        foreach ( $languages as $code => $language ) {
            $label = esc_html( $language['label'] );

            if ( $show_flags ) {
                $label = wp_kses_post( $this->maybe_prefix_flag( $code ) . $label );
            }

            $options[] = sprintf(
                '<option value="%1$s" %3$s data-url="%2$s">%4$s</option>',
                esc_attr( $code ),
                esc_url( $language['url'] ),
                selected( $current, $code, false ),
                $label
            );
        }

        if ( ! wp_script_is( 'fpml-switcher-dropdown', 'registered' ) ) {
            wp_register_script( 'fpml-switcher-dropdown', false, array(), FPML_PLUGIN_VERSION, true );
        }

        wp_enqueue_script( 'fpml-switcher-dropdown' );

        $script = "document.addEventListener('change',function(event){var el=event.target;if(!el.classList.contains('fpml-switcher__select')){return;}var url=el.options[el.selectedIndex].getAttribute('data-url');if(url){window.location.href=url;}});";
        wp_add_inline_script( 'fpml-switcher-dropdown', $script );

        return sprintf(
            '<div class="fpml-switcher fpml-switcher--dropdown"><select class="fpml-switcher__select">%s</select></div>',
            implode( '', $options )
        );
    }

    /**
     * Maybe prefix label with flag span.
     *
     * @since 0.2.0
     *
     * @param string $code Language code.
     *
     * @return string
     */
    protected function maybe_prefix_flag( $code ) {
        $flags = array(
            self::SOURCE => '🇮🇹',
            self::TARGET => '🇺🇸',
        );

        if ( ! isset( $flags[ $code ] ) ) {
            return '';
        }

        return sprintf( '<span class="fpml-switcher__flag" aria-hidden="true">%s</span> ', esc_html( $flags[ $code ] ) );
    }

    /**
     * Obtain the current full URL.
     *
     * @since 0.2.0
     *
     * @return string
     */
    protected function get_current_url() {
        $scheme = is_ssl() ? 'https' : 'http';

        $host = isset( $_SERVER['HTTP_HOST'] ) ? $this->sanitize_host_header( $_SERVER['HTTP_HOST'] ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? $this->sanitize_request_uri( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER

        if ( '' === $uri ) {
            $uri = '/';
        }

        if ( '' === $host ) {
            $home_scheme = is_ssl() ? 'https' : null;

            return esc_url_raw( home_url( $uri, $home_scheme ) );
        }

        return esc_url_raw( $scheme . '://' . $host . $uri );
    }

    /**
     * Sanitize the host header preserving valid port or IPv6 notations.
     *
     * @since 0.3.1
     *
     * @param string $host Raw host header.
     *
     * @return string
     */
    protected function sanitize_host_header( $host ) {
        if ( ! is_string( $host ) ) {
            return '';
        }

        $host = wp_unslash( $host );
        $host = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $host ) : strip_tags( $host );
        $host = preg_replace( '/[\r\n\t ]+/', '', $host );

        if ( is_string( $host ) ) {
            $segments = preg_split( '/[\x00-\x1F\x7F]+/', $host, 2 );

            if ( is_array( $segments ) && isset( $segments[0] ) ) {
                $host = $segments[0];
            }
        }

        if ( null === $host ) {
            return '';
        }

        $normalized_host = $host;

        $scheme_position = stripos( $normalized_host, '://' );

        if ( false !== $scheme_position ) {
            $normalized_host = substr( $normalized_host, $scheme_position + 3 );
        } elseif ( str_starts_with( $normalized_host, '//' ) ) {
            $normalized_host = substr( $normalized_host, 2 );
        }

        $at_position = strrpos( $normalized_host, '@' );

        if ( false !== $at_position ) {
            $normalized_host = substr( $normalized_host, $at_position + 1 );
        }

        $maybe_ipv6_literal = str_starts_with( $normalized_host, '[' );

        if ( ! $maybe_ipv6_literal && str_contains( $normalized_host, '%' ) ) {
            $decoded        = $normalized_host;
            $max_iterations = 5;
            $iteration      = 0;

            do {
                $previous = $decoded;
                $decoded  = rawurldecode( $decoded );

                if ( ! is_string( $decoded ) ) {
                    break;
                }

                $decoded = preg_replace( '/[\r\n\t ]+/', '', $decoded );

                if ( ! is_string( $decoded ) ) {
                    break;
                }

                $segments = preg_split( '/[\x00-\x1F\x7F]+/', $decoded, 2 );

                if ( is_array( $segments ) && isset( $segments[0] ) ) {
                    $decoded = $segments[0];
                }

                $iteration++;
            } while ( $iteration < $max_iterations && $decoded !== $previous && str_contains( $decoded, '%' ) );

            if ( is_string( $decoded ) ) {
                $normalized_host = $decoded;

                $scheme_position = stripos( $normalized_host, '://' );

                if ( false !== $scheme_position ) {
                    $normalized_host = substr( $normalized_host, $scheme_position + 3 );
                } elseif ( str_starts_with( $normalized_host, '//' ) ) {
                    $normalized_host = substr( $normalized_host, 2 );
                }

                $at_position = strrpos( $normalized_host, '@' );

                if ( false !== $at_position ) {
                    $normalized_host = substr( $normalized_host, $at_position + 1 );
                }
            }
        }

        $authority_segments = preg_split( '/[\/?#]/', $normalized_host, 2 );

        if ( is_array( $authority_segments ) && isset( $authority_segments[0] ) && '' !== $authority_segments[0] ) {
            $host = $authority_segments[0];
        }

        $host = preg_replace( '/[^A-Za-z0-9\.\-:\[\]_%]/', '', $host );

        if ( ! is_string( $host ) ) {
            return '';
        }

        $is_ipv6 = str_starts_with( $host, '[' );

        if ( ! $is_ipv6 && str_contains( $host, '%' ) ) {
            $host = preg_replace( '/%[0-9A-Fa-f]{2}/', '', $host );

            if ( ! is_string( $host ) ) {
                return '';
            }

            $host = str_replace( '%', '', $host );
        }

        if ( '' === $host ) {
            return '';
        }

        if ( $is_ipv6 ) {
            $closing = strpos( $host, ']' );

            if ( false === $closing ) {
                return '';
            }

            $address   = substr( $host, 0, $closing + 1 );
            $remainder = substr( $host, $closing + 1 );

            if ( '' === $remainder ) {
                return $address;
            }

            if ( ':' !== $remainder[0] ) {
                return $address;
            }

            $port = substr( $remainder, 1 );

            if ( '' === $port ) {
                return $address;
            }

            if ( ctype_digit( $port ) ) {
                if ( strlen( $port ) > 5 ) {
                    return $address;
                }

                if ( (int) $port > 65535 ) {
                    return $address;
                }

                return $address . ':' . $port;
            }

            return $address;
        }

        $segments = explode( ':', $host, 2 );
        $name     = $segments[0];

        if ( '' === $name ) {
            return '';
        }

        if ( ! isset( $segments[1] ) ) {
            return $name;
        }

        $port = $segments[1];

        if ( '' === $port ) {
            return $name;
        }

        if ( ctype_digit( $port ) ) {
            if ( strlen( $port ) > 5 ) {
                return $name;
            }

            if ( (int) $port > 65535 ) {
                return $name;
            }

            return $name . ':' . $port;
        }

        return $name;
    }

    /**
     * Sanitize the current request URI keeping query string and fragments.
     *
     * @since 0.3.1
     *
     * @param string $uri Raw request URI.
     *
     * @return string
     */
    protected function sanitize_request_uri( $uri ) {
        if ( ! is_string( $uri ) ) {
            return '/';
        }

        $uri = wp_unslash( $uri );
        $uri = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $uri ) : strip_tags( $uri );
        $uri = preg_replace( '/[\x00-\x1F\x7F]+/', '', $uri );

        if ( null === $uri ) {
            return '/';
        }

        $uri = trim( $uri );

        if ( '' === $uri ) {
            return '/';
        }

        if ( preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $uri ) ) {
            $parts = wp_parse_url( $uri );

            if ( false === $parts ) {
                return '/';
            }

            $uri = '';

            if ( isset( $parts['path'] ) ) {
                $uri = (string) $parts['path'];
            }

            if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
                $uri .= '?' . $parts['query'];
            }

            if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
                $uri .= '#' . $parts['fragment'];
            }

            $uri = trim( $uri );
        }

        if ( '' === $uri ) {
            return '/';
        }

        return '/' . ltrim( $uri, '/' );
    }

    /**
     * Get current request path.
     *
     * @since 0.2.0
     *
     * @return string
     */
    protected function get_current_path() {
        $current = wp_parse_url( $this->get_current_url(), PHP_URL_PATH );

        if ( ! is_string( $current ) ) {
            return '/';
        }

        if ( '' === $current ) {
            return '/';
        }

        if ( '/' !== substr( $current, 0, 1 ) ) {
            $current = '/' . $current;
        }

        $current = $this->strip_installation_path( $current );

        if ( '' === $current || '/' === $current ) {
            return '/';
        }

        return untrailingslashit( $current ) . '/';
    }

    /**
     * Strip language segment from a path.
     *
     * @since 0.2.0
     *
     * @param string $path Current path.
     *
     * @return string
     */
    protected function strip_language_segment( $path ) {
        $path = '/' === $path ? '/' : untrailingslashit( $path ) . '/';

        $lowered = strtolower( $path );

        if ( 0 === strpos( $lowered, '/en/' ) ) {
            $path = substr( $path, 3 );
        } elseif ( '/en' === rtrim( $lowered, '/' ) ) {
            $path = '/';
        }

        return $path;
    }

    /**
     * Remove the WordPress installation path from a request path.
     *
     * Ensures detection works on subdirectory installs.
     *
     * @since 0.3.1
     *
     * @param string $path Absolute path including the leading slash.
     *
     * @return string
     */
    protected function strip_installation_path( $path ) {
        if ( ! is_string( $path ) || '' === $path ) {
            return '';
        }

        if ( '/' !== substr( $path, 0, 1 ) ) {
            $path = '/' . $path;
        }

        $base_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

        if ( ! is_string( $base_path ) ) {
            $base_path = '';
        }

        $base_path = '/' . trim( $base_path, '/' );

        if ( '/' === $base_path ) {
            return $path;
        }

        $base_trailing       = trailingslashit( $base_path );
        $base_trailing_lower = strtolower( $base_trailing );
        $path_lower          = strtolower( $path );

        if ( 0 === strpos( $path_lower, $base_trailing_lower ) ) {
            $remainder = substr( $path, strlen( $base_trailing ) );
            $remainder = '/' . ltrim( $remainder, '/' );

            if ( '/' === substr( $path, -1 ) ) {
                return untrailingslashit( $remainder ) . '/';
            }

            return '/' === $remainder ? '/' : $remainder;
        }

        if ( 0 === strcasecmp( rtrim( $path, '/' ), rtrim( $base_path, '/' ) ) ) {
            return '/';
        }

        return $path;
    }

    /**
     * Derive the contextual URL for the requested language.
     *
     * @since 0.2.0
     *
     * @param string $lang Target language code.
     *
     * @return string
     */
    protected function get_contextual_url( $lang ) {
        if ( is_singular() ) {
            $object = get_queried_object();

            if ( $object instanceof WP_Post ) {
                $url = $this->get_post_translation_url( $object, $lang );

                if ( ! empty( $url ) ) {
                    return $url;
                }
            }
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();

            if ( $term instanceof WP_Term ) {
                $url = $this->get_term_translation_url( $term, $lang );

                if ( ! empty( $url ) ) {
                    return $url;
                }
            }
        }

        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );

            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            if ( is_string( $post_type ) && '' !== $post_type ) {
                $link = get_post_type_archive_link( $post_type );

                if ( $link ) {
                    return $link;
                }
            }

            return $this->get_current_url();
        }

        if ( is_search() || is_author() || is_date() ) {
            return $this->get_current_url();
        }

        return '';
    }

    /**
     * Retrieve the translated permalink for a post.
     *
     * @since 0.2.0
     *
     * @param WP_Post $post Post object.
     * @param string  $lang Language code.
     *
     * @return string
     */
    protected function get_post_translation_url( WP_Post $post, $lang ) {
        if ( self::TARGET === $lang ) {
            if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
                return get_permalink( $post );
            }

            $target_id = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );

            if ( $target_id > 0 ) {
                $target = get_post( $target_id );

                if ( $target instanceof WP_Post && in_array( $target->post_status, array( 'publish', 'inherit' ), true ) ) {
                    return get_permalink( $target );
                }
            }

            return '';
        }

        if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            $source_id = (int) get_post_meta( $post->ID, '_fpml_pair_source_id', true );

            if ( $source_id > 0 ) {
                $source = get_post( $source_id );

                if ( $source instanceof WP_Post && in_array( $source->post_status, array( 'publish', 'inherit' ), true ) ) {
                    return get_permalink( $source );
                }
            }

            return '';
        }

        return get_permalink( $post );
    }

    /**
     * Retrieve the translated permalink for a taxonomy term.
     *
     * @since 0.2.0
     *
     * @param WP_Term $term Term object.
     * @param string  $lang Language code.
     *
     * @return string
     */
    protected function get_term_translation_url( WP_Term $term, $lang ) {
        if ( self::TARGET === $lang ) {
            if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
                $link = get_term_link( $term );

                return is_wp_error( $link ) ? '' : $link;
            }

            $target_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );

            if ( $target_id > 0 ) {
                $target = get_term( $target_id, $term->taxonomy );

                if ( $target instanceof WP_Term ) {
                    $link = get_term_link( $target );

                    return is_wp_error( $link ) ? '' : $link;
                }
            }

            return '';
        }

        if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
            $source_id = (int) get_term_meta( $term->term_id, '_fpml_pair_source_id', true );

            if ( $source_id > 0 ) {
                $source = get_term( $source_id, $term->taxonomy );

                if ( $source instanceof WP_Term ) {
                    $link = get_term_link( $source );

                    return is_wp_error( $link ) ? '' : $link;
                }
            }

            return '';
        }

        $link = get_term_link( $term );

        return is_wp_error( $link ) ? '' : $link;
    }

    /**
     * Retrieve cached term pairs from storage.
     *
     * @since 0.3.0
     *
     * @return array
     */
    protected function get_term_pairs() {
        if ( null !== $this->term_pairs ) {
            return $this->term_pairs;
        }

        $stored = get_option( 'fpml_term_pairs', array() );
        $pairs  = array(
            'source_to_target' => array(),
            'target_to_source' => array(),
        );

        if ( is_array( $stored ) ) {
            if ( isset( $stored['source_to_target'] ) && is_array( $stored['source_to_target'] ) ) {
                foreach ( $stored['source_to_target'] as $source_id => $target_id ) {
                    $source = absint( $source_id );
                    $target = absint( $target_id );

                    if ( $source && $target ) {
                        $pairs['source_to_target'][ $source ] = $target;
                        $pairs['target_to_source'][ $target ] = $source;
                    }
                }
            }

            if ( isset( $stored['target_to_source'] ) && is_array( $stored['target_to_source'] ) ) {
                foreach ( $stored['target_to_source'] as $target_id => $source_id ) {
                    $target = absint( $target_id );
                    $source = absint( $source_id );

                    if ( $target && $source ) {
                        $pairs['target_to_source'][ $target ] = $source;

                        if ( ! isset( $pairs['source_to_target'][ $source ] ) ) {
                            $pairs['source_to_target'][ $source ] = $target;
                        }
                    }
                }
            }
        }

        $this->term_pairs = $pairs;

        return $this->term_pairs;
    }

    /**
     * Retrieve the translated term ID for a given source term.
     *
     * @since 0.3.0
     *
     * @param int $source_id Source term ID.
     *
     * @return int
     */
    public function get_term_translation_id( $source_id ) {
        $source_id = absint( $source_id );

        if ( ! $source_id ) {
            return 0;
        }

        $pairs = $this->get_term_pairs();

        return isset( $pairs['source_to_target'][ $source_id ] ) ? (int) $pairs['source_to_target'][ $source_id ] : 0;
    }

    /**
     * Retrieve the source term ID for a translated term.
     *
     * @since 0.3.0
     *
     * @param int $target_id Target term ID.
     *
     * @return int
     */
    public function get_term_source_id( $target_id ) {
        $target_id = absint( $target_id );

        if ( ! $target_id ) {
            return 0;
        }

        $pairs = $this->get_term_pairs();

        return isset( $pairs['target_to_source'][ $target_id ] ) ? (int) $pairs['target_to_source'][ $target_id ] : 0;
    }

    /**
     * Persist the mapping between source and translated terms.
     *
     * @since 0.3.0
     *
     * @param int $source_id Source term ID.
     * @param int $target_id Target term ID.
     *
     * @return bool
     */
    public function set_term_pair( $source_id, $target_id ) {
        $source_id = absint( $source_id );
        $target_id = absint( $target_id );

        if ( ! $source_id || ! $target_id ) {
            return false;
        }

        $pairs       = $this->get_term_pairs();
        $source_map  = $pairs['source_to_target'];
        $target_map  = $pairs['target_to_source'];
        $has_changed = false;

        if ( isset( $source_map[ $source_id ] ) ) {
            if ( (int) $source_map[ $source_id ] !== $target_id ) {
                $previous_target = (int) $source_map[ $source_id ];
                unset( $target_map[ $previous_target ] );
                $source_map[ $source_id ] = $target_id;
                $has_changed              = true;
            }
        } else {
            $source_map[ $source_id ] = $target_id;
            $has_changed              = true;
        }

        if ( isset( $target_map[ $target_id ] ) ) {
            if ( (int) $target_map[ $target_id ] !== $source_id ) {
                $previous_source = (int) $target_map[ $target_id ];
                unset( $source_map[ $previous_source ] );
                $target_map[ $target_id ] = $source_id;
                $has_changed              = true;
            }
        } else {
            $target_map[ $target_id ] = $source_id;
            $has_changed              = true;
        }

        if ( ! $has_changed ) {
            return true;
        }

        $this->term_pairs = array(
            'source_to_target' => $source_map,
            'target_to_source' => $target_map,
        );

        update_option( 'fpml_term_pairs', $this->term_pairs, false );

        return true;
    }
    /**
     * Return the language-specific home URL baseline.
     *
     * @since 0.2.0
     *
     * @param string $lang Language code.
     *
     * @return string
     */
    protected function get_language_home( $lang ) {
        unset( $lang );

        return home_url( '/' );
    }

    /**
     * Apply routing rules to a base URL according to language.
     *
     * @since 0.2.0
     *
     * @param string $url  Base URL.
     * @param string $lang Language code.
     *
     * @return string
     */
    protected function apply_language_to_url( $url, $lang ) {
        $routing = $this->settings->get( 'routing_mode', 'segment' );

        if ( 'segment' !== $routing ) {
            $url = remove_query_arg( array( 'lang', 'fpml_lang' ), $url );

            if ( self::TARGET === $lang ) {
                $url = add_query_arg( 'lang', self::TARGET, $url );
            }

            return $url;
        }

        $url    = remove_query_arg( array( 'lang', 'fpml_lang' ), $url );
        $parsed = wp_parse_url( $url );

        if ( false === $parsed ) {
            return $url;
        }

        $path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
        $path = $this->normalize_path( $path );

        if ( self::TARGET === $lang ) {
            $path = 'en' . ( '' !== $path ? '/' . $path : '' );
        }

        $path = trim( $path, '/' );

        if ( '' === $path ) {
            $formatted_path = user_trailingslashit( '' );
        } else {
            $formatted_path = user_trailingslashit( $path );
        }

        if ( '' !== $formatted_path && '/' !== substr( $formatted_path, 0, 1 ) ) {
            $formatted_path = '/' . ltrim( $formatted_path, '/' );
        }

        $target = home_url( $formatted_path );

        if ( ! empty( $parsed['query'] ) ) {
            $target = rtrim( $target, '?' );
            $target .= '?' . $parsed['query'];
        }

        if ( ! empty( $parsed['fragment'] ) ) {
            $fragment = sanitize_text_field( $parsed['fragment'] );

            if ( '' !== $fragment ) {
                $target .= '#' . rawurlencode( $fragment );
            }
        }

        return $target;
    }

    /**
     * Normalize an URL path stripping the language segment.
     *
     * @since 0.2.0
     *
     * @param string $path Path to normalize.
     *
     * @return string
     */
    protected function normalize_path( $path ) {
        if ( ! is_string( $path ) || '' === $path ) {
            return '';
        }

        if ( '/' !== substr( $path, 0, 1 ) ) {
            $path = '/' . $path;
        }

        $path = $this->strip_installation_path( $path );
        $path = $this->strip_language_segment( $path );

        return trim( $path, '/' );
    }

    /**
     * Determine whether user consent is available for redirect/cookies.
     *
     * @since 0.2.0
     *
     * @return bool
     */
    protected function has_cookie_consent() {
        if ( ! $this->settings->get( 'browser_redirect_requires_consent', false ) ) {
            return true;
        }

        $cookie_name = $this->settings->get( 'browser_redirect_consent_cookie', '' );

        if ( '' === $cookie_name ) {
            return false;
        }

        if ( ! isset( $_COOKIE[ $cookie_name ] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
            return false;
        }

        $raw_value = wp_unslash( $_COOKIE[ $cookie_name ] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
        $value     = strtolower( trim( wp_check_invalid_utf8( $raw_value ) ) );

        if ( '' === $value ) {
            return false;
        }

        $negative = array( '0', 'false', 'deny', 'denied', 'reject', 'no' );

        if ( in_array( $value, $negative, true ) ) {
            return false;
        }

        /**
         * Filter the detection of cookie consent value.
         *
         * @since 0.2.0
         *
         * @param bool   $has_consent Whether consent is granted.
         * @param string $cookie_name Cookie name.
         * @param string $raw_value   Raw cookie value.
         */
        return (bool) apply_filters( 'fpml_has_cookie_consent', true, $cookie_name, $raw_value );
    }
}

