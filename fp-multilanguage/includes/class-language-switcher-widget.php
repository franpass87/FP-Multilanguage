<?php
/**
 * Language Switcher Widget
 *
 * @package FP_Multilanguage
 * @since 0.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Widget per mostrare il selettore di lingua nel frontend.
 *
 * @since 0.4.2
 */
class FPML_Language_Switcher_Widget extends WP_Widget {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'fpml_language_switcher',
            __( 'Selettore Lingua FP', 'fp-multilanguage' ),
            array(
                'description' => __( 'Mostra un selettore per cambiare lingua (Italiano/English)', 'fp-multilanguage' ),
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
        $style      = ! empty( $instance['style'] ) ? $instance['style'] : 'inline';
        $show_flags = ! empty( $instance['show_flags'] );

        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        echo do_shortcode( sprintf(
            '[fp_lang_switcher style="%s" show_flags="%s"]',
            esc_attr( $style ),
            $show_flags ? '1' : '0'
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
        $style      = ! empty( $instance['style'] ) ? $instance['style'] : 'inline';
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
                <option value="inline" <?php selected( $style, 'inline' ); ?>>
                    <?php esc_html_e( 'Inline (link affiancati)', 'fp-multilanguage' ); ?>
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
        $instance['style']      = in_array( $new_instance['style'], array( 'inline', 'dropdown' ), true ) ? $new_instance['style'] : 'inline';
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
function fpml_register_language_switcher_widget() {
    register_widget( 'FPML_Language_Switcher_Widget' );
}
add_action( 'widgets_init', 'fpml_register_language_switcher_widget' );
