<?php
namespace FPMultilanguage\Widgets;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use WP_Widget;

class LanguageSwitcher extends WP_Widget {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		parent::__construct(
			'fp_language_switcher',
			__( 'FP Multilanguage Switcher', 'fp-multilanguage' ),
			array( 'description' => __( 'Consente agli utenti di cambiare lingua.', 'fp-multilanguage' ) )
		);
	}

	public function widget( $args, $instance ): void {
		echo $args['before_widget'] ?? '';

		$title = isset( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : __( 'Lingue', 'fp-multilanguage' );
		if ( $title !== '' ) {
			echo $args['before_title'] ?? '';
			echo esc_html( $title );
			echo $args['after_title'] ?? '';
		}

		echo $this->render_switcher();

		echo $args['after_widget'] ?? '';
	}

	public function form( $instance ): void {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Titolo:', 'fp-multilanguage' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	public function update( $newInstance, $oldInstance ) {
		$instance          = $oldInstance;
		$instance['title'] = sanitize_text_field( $newInstance['title'] ?? '' );

		return $instance;
	}

	public function render_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'layout' => 'list',
			),
			$atts,
			'fp_language_switcher'
		);

		return $this->render_switcher( $atts['layout'] );
	}

	private function render_switcher( string $layout = 'list' ): string {
		$languages = $this->get_languages();
		$current   = CurrentLanguage::resolve();

		$items = array();
		foreach ( $languages as $language => $url ) {
			$class   = $language === $current ? ' class="current-language"' : '';
			$items[] = sprintf( '<li%s><a href="%s">%s</a></li>', $class, esc_url( $url ), esc_html( strtoupper( $language ) ) );
		}

		if ( $layout === 'inline' ) {
			return '<ul class="fp-language-switcher inline">' . implode( ' ', $items ) . '</ul>';
		}

		return '<ul class="fp-language-switcher">' . implode( '', $items ) . '</ul>';
	}

	private function get_languages(): array {
		$post         = get_queried_object();
		$translations = array();
		if ( $post instanceof \WP_Post ) {
			$manager      = fp_multilanguage()->get_container()->get( 'post_translation_manager' );
			$translations = $manager->get_post_translations( $post->ID );
		}

		$languages            = array();
		$source               = Settings::get_source_language();
		$languages[ $source ] = add_query_arg( 'fp_lang', $source, home_url( add_query_arg( array() ) ) );

		foreach ( Settings::get_target_languages() as $language ) {
			if ( $language === $source ) {
				continue;
			}

			if ( ! empty( $translations ) && empty( $translations[ $language ] ) ) {
				continue;
			}

			$languages[ $language ] = add_query_arg( 'fp_lang', $language, home_url( add_query_arg( array() ) ) );
		}

		return $languages;
	}
}
