<?php
/**
 * Switcher Shortcode Handler - Handles language switcher shortcode.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Switcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles language switcher shortcode logic.
 *
 * @since 0.10.0
 */
class SwitcherShortcodeHandler {
	/**
	 * Language URL resolver instance.
	 *
	 * @var LanguageUrlResolver
	 */
	protected $url_resolver;

	/**
	 * Flag provider instance.
	 *
	 * @var FlagProvider
	 */
	protected $flag_provider;

	/**
	 * Switcher renderer instance.
	 *
	 * @var SwitcherRenderer
	 */
	protected $renderer;

	/**
	 * Constructor.
	 *
	 * @param LanguageUrlResolver $url_resolver  URL resolver instance.
	 * @param FlagProvider        $flag_provider Flag provider instance.
	 */
	public function __construct( LanguageUrlResolver $url_resolver, FlagProvider $flag_provider ) {
		$this->url_resolver  = $url_resolver;
		$this->flag_provider = $flag_provider;
	}

	/**
	 * Handle shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function handle_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'style'      => 'auto', // auto, dropdown, flags, links
				'show_flags' => 'yes',
				'show_names' => 'yes',
			),
			$atts,
			'fpml_language_switcher'
		);

		// Get language instance for URL generation
		$language_instance = class_exists( '\FPML_Language' ) ? ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() ) : null;
		if ( ! $language_instance ) {
			return '';
		}

		$current_lang = $language_instance->get_current_language();
		$language_manager = fpml_get_language_manager();
		$enabled_languages = $language_manager->get_enabled_languages();
		$available_languages = $language_manager->get_all_languages();

		// Ensure arrays are valid
		if ( ! is_array( $enabled_languages ) ) {
			$enabled_languages = array();
		}
		if ( ! is_array( $available_languages ) ) {
			$available_languages = array();
		}

		// Count valid languages: IT (always) + enabled languages that are valid
		$valid_languages = 1; // IT is always present
		foreach ( $enabled_languages as $lang_code ) {
			if ( isset( $available_languages[ $lang_code ] ) ) {
				$lang_info = $available_languages[ $lang_code ];
				if ( is_array( $lang_info ) && ! empty( $lang_info['slug'] ) ) {
					$valid_languages++;
				}
			}
		}

		// If only IT is available, don't show switcher
		if ( $valid_languages <= 1 ) {
			return '';
		}

		$total_languages = $valid_languages;

		// Auto-detect style based on number of languages
		if ( 'auto' === $atts['style'] ) {
			$atts['style'] = ( $total_languages <= 2 ) ? 'flags' : 'dropdown';
		}

		// Validate style after auto-detection
		$valid_styles = array( 'dropdown', 'links', 'flags' );
		if ( ! in_array( $atts['style'], $valid_styles, true ) ) {
			$atts['style'] = 'flags';
		}

		// Initialize renderer
		$this->renderer = new SwitcherRenderer( $this->url_resolver, $this->flag_provider, $current_lang, $enabled_languages, $available_languages );

		ob_start();
		?>
		<div class="fpml-language-switcher fpml-style-<?php echo esc_attr( $atts['style'] ); ?>">
			<?php
			switch ( $atts['style'] ) {
				case 'dropdown':
					echo $this->renderer->render_dropdown( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;
				case 'links':
					echo $this->renderer->render_links( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;
				case 'flags':
				default:
					echo $this->renderer->render_flags( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}















