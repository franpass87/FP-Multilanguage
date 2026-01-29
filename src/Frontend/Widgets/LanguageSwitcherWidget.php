<?php
/**
 * Language Switcher Widget
 *
 * @package FP_Multilanguage
 * @since 0.4.2
 * @since 0.10.0 Refactored to use modular components.
 */


namespace FP\Multilanguage\Frontend\Widgets;

use FP\Multilanguage\Language\Switcher\LanguageUrlResolver;
use FP\Multilanguage\Language\Switcher\FlagProvider;
use FP\Multilanguage\Language\Switcher\SwitcherShortcodeHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Widget per mostrare il selettore di lingua nel frontend.
 *
 * @since 0.4.2
 * @since 0.10.0 Refactored to use modular components.
 */
class LanguageSwitcherWidget extends \WP_Widget {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            '\FPML_language_switcher',
            __( 'Selettore Lingua FP', 'fp-multilanguage' ),
            array(
                'description' => __( 'Mostra un selettore per cambiare lingua tra quelle abilitate', 'fp-multilanguage' ),
            )
        );
    }

    /**
     * Render widget output.
     *
     * @param array $args     Widget arguments.
     * @param array $instance Widget instance.
     *
     * @return void
     */
    public function widget( $args, $instance ) {
        $title      = ! empty( $instance['title'] ) ? $instance['title'] : '';
        $style      = ! empty( $instance['style'] ) ? $instance['style'] : 'auto';
        $show_flags = ! empty( $instance['show_flags'] );

        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        // Use fpml_language_switcher shortcode for better control
        // Show both flags and names by default, or just names if flags are disabled
        echo do_shortcode( sprintf(
            '[fpml_language_switcher style="%s" show_flags="%s" show_names="yes"]',
            esc_attr( $style ),
            $show_flags ? 'yes' : 'no'
        ) );

        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Render widget settings form.
     *
     * @param array $instance Widget instance.
     *
     * @return void
     */
    public function form( $instance ) {
        $title      = ! empty( $instance['title'] ) ? $instance['title'] : '';
        $style      = ! empty( $instance['style'] ) ? $instance['style'] : 'auto';
        $show_flags = ! empty( $instance['show_flags'] );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Titolo:', 'fp-multilanguage' ); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( $title ); ?>"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>">
                <?php esc_html_e( 'Stile:', 'fp-multilanguage' ); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>"
            >
                <option value="auto" <?php selected( $style, 'auto' ); ?>>
                    <?php esc_html_e( 'Automatico (inline se 2 lingue, dropdown se più)', 'fp-multilanguage' ); ?>
                </option>
                <option value="flags" <?php selected( $style, 'flags' ); ?>>
                    <?php esc_html_e( 'Bandierine inline', 'fp-multilanguage' ); ?>
                </option>
                <option value="links" <?php selected( $style, 'links' ); ?>>
                    <?php esc_html_e( 'Link inline', 'fp-multilanguage' ); ?>
                </option>
                <option value="dropdown" <?php selected( $style, 'dropdown' ); ?>>
                    <?php esc_html_e( 'Dropdown (menu a tendina)', 'fp-multilanguage' ); ?>
                </option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_flags' ) ); ?>">
                <input
                    type="checkbox"
                    id="<?php echo esc_attr( $this->get_field_id( 'show_flags' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'show_flags' ) ); ?>"
                    value="1"
                    <?php checked( $show_flags, true ); ?>
                />
                <?php esc_html_e( 'Mostra bandierine 🇮🇹 🇬🇧', 'fp-multilanguage' ); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Update widget settings.
     *
     * @param array $new_instance New settings.
     * @param array $old_instance Old settings.
     *
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();

        $instance['title']      = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['style']      = in_array( $new_instance['style'], array( 'auto', 'flags', 'links', 'dropdown' ), true ) ? $new_instance['style'] : 'auto';
        $instance['show_flags'] = ! empty( $new_instance['show_flags'] );

        return $instance;
    }
}

/**
 * Register the widget.
 *
 * @since 0.4.2
 *
 * @return void
 */
function FPML_register_language_switcher_widget() {
    register_widget( 'FPML_Language_Switcher_Widget' );
}
add_action( 'widgets_init', __NAMESPACE__ . '\FPML_register_language_switcher_widget' );

/**
 * Language switcher shortcode.
 *
 * @since 0.5.0
 * @since 0.10.0 Refactored to use modular components.
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string HTML output.
 */
function fpml_language_switcher_shortcode( $atts ) {
	// Get language instance for URL generation
	$language_instance = class_exists( '\FPML_Language' ) ? ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() ) : null;
	if ( ! $language_instance ) {
		return '';
	}

	$language_manager = fpml_get_language_manager();
	$available_languages = $language_manager->get_all_languages();

	// Ensure arrays are valid
	if ( ! is_array( $available_languages ) ) {
		$available_languages = array();
	}

	// Initialize modules
	$url_resolver = new LanguageUrlResolver( $language_instance, $available_languages );
	$flag_provider = new FlagProvider();
	$handler = new SwitcherShortcodeHandler( $url_resolver, $flag_provider );

	return $handler->handle_shortcode( $atts );
}
add_shortcode( 'fpml_language_switcher', 'FP\Multilanguage\Frontend\Widgets\fpml_language_switcher_shortcode' );
