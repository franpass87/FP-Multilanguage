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
        if ( empty( $requested ) && isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $requested = strtolower( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ( self::TARGET === $requested ) {
            $lang = self::TARGET;
        } else {
            $path = $this->get_current_path();
            if ( 0 === strpos( $path, '/en/' ) || '/en' === $path ) {
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
            wp_register_script( 'fpml-switcher-dropdown', '', array(), FPML_PLUGIN_VERSION, true );
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

        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER

        return esc_url_raw( $scheme . '://' . $host . $uri );
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

        if ( 0 === strpos( $path, '/en/' ) ) {
            $path = substr( $path, 3 );
        } elseif ( '/en/' === $path ) {
            $path = '/';
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
            $url = remove_query_arg( 'lang', $url );

            if ( self::TARGET === $lang ) {
                $url = add_query_arg( 'lang', self::TARGET, $url );
            }

            return $url;
        }

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
            $target = home_url( '/' );
        } else {
            $target = home_url( trailingslashit( $path ) );
        }

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

