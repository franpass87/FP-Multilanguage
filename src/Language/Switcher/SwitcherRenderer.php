<?php
/**
 * Switcher Renderer - Renders language switcher in different styles.
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
 * Renders language switcher in different styles.
 *
 * @since 0.10.0
 */
class SwitcherRenderer {
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
	 * Current language code.
	 *
	 * @var string
	 */
	protected $current_lang;

	/**
	 * Enabled languages.
	 *
	 * @var array
	 */
	protected $enabled_languages;

	/**
	 * Available languages.
	 *
	 * @var array
	 */
	protected $available_languages;

	/**
	 * Constructor.
	 *
	 * @param LanguageUrlResolver $url_resolver        URL resolver instance.
	 * @param FlagProvider        $flag_provider       Flag provider instance.
	 * @param string              $current_lang        Current language code.
	 * @param array               $enabled_languages   Enabled languages.
	 * @param array               $available_languages Available languages.
	 */
	public function __construct( LanguageUrlResolver $url_resolver, FlagProvider $flag_provider, $current_lang, $enabled_languages, $available_languages ) {
		$this->url_resolver        = $url_resolver;
		$this->flag_provider       = $flag_provider;
		$this->current_lang        = $current_lang;
		$this->enabled_languages   = $enabled_languages;
		$this->available_languages = $available_languages;
	}

	/**
	 * Render dropdown style.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_dropdown( $atts ) {
		$it_url = $this->url_resolver->get_italian_url();
		ob_start();
		?>
		<select onchange="window.location.href=this.value;" class="fpml-lang-select">
			<option value="<?php echo esc_url( $it_url ); ?>" <?php selected( 'it' === $this->current_lang ); ?>>
				<?php if ( 'yes' === $atts['show_flags'] ) : ?>🇮🇹 <?php endif; ?>
				<?php if ( 'yes' === $atts['show_names'] ) : ?>Italiano<?php endif; ?>
			</option>
			<?php foreach ( $this->enabled_languages as $lang_code ) : 
				if ( ! isset( $this->available_languages[ $lang_code ] ) ) continue;
				$lang_info = $this->available_languages[ $lang_code ];
				if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) continue;
				
				$lang_url = $this->url_resolver->get_language_url( $lang_code );
				$lang_flag = '';
				if ( 'yes' === $atts['show_flags'] ) {
					$lang_flag = $this->flag_provider->get_flag_with_space( $lang_code, $lang_info );
				}
			?>
			<option value="<?php echo esc_url( $lang_url ); ?>" <?php selected( $lang_code === $this->current_lang ); ?>>
				<?php echo esc_html( $lang_flag ); ?>
				<?php if ( 'yes' === $atts['show_names'] && isset( $lang_info['name'] ) ) : ?><?php echo esc_html( $lang_info['name'] ); ?><?php endif; ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render links style.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_links( $atts ) {
		$it_url = $this->url_resolver->get_italian_url();
		ob_start();
		?>
		<div class="fpml-lang-links">
			<a href="<?php echo esc_url( $it_url ); ?>" class="fpml-lang-link <?php echo esc_attr( 'it' === $this->current_lang ? 'active' : '' ); ?>">
				<?php if ( 'yes' === $atts['show_flags'] ) : ?>🇮🇹 <?php endif; ?>
				<?php if ( 'yes' === $atts['show_names'] ) : ?>IT<?php endif; ?>
			</a>
			<?php foreach ( $this->enabled_languages as $lang_code ) : 
				if ( ! isset( $this->available_languages[ $lang_code ] ) ) continue;
				$lang_info = $this->available_languages[ $lang_code ];
				if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) continue;
				
				$lang_url = $this->url_resolver->get_language_url( $lang_code );
				$lang_flag = '';
				if ( 'yes' === $atts['show_flags'] ) {
					$lang_flag = $this->flag_provider->get_flag_with_space( $lang_code, $lang_info );
				}
			?>
			<a href="<?php echo esc_url( $lang_url ); ?>" class="fpml-lang-link <?php echo esc_attr( $lang_code === $this->current_lang ? 'active' : '' ); ?>">
				<?php echo esc_html( $lang_flag ); ?>
				<?php if ( 'yes' === $atts['show_names'] ) : ?><?php echo esc_html( strtoupper( $lang_code ) ); ?><?php endif; ?>
			</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render flags style.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_flags( $atts ) {
		$it_url = $this->url_resolver->get_italian_url();
		ob_start();
		?>
		<div class="fpml-lang-flags">
			<a href="<?php echo esc_url( $it_url ); ?>" class="fpml-flag <?php echo esc_attr( 'it' === $this->current_lang ? 'active' : '' ); ?>" title="Italiano">🇮🇹</a>
			<?php foreach ( $this->enabled_languages as $lang_code ) : 
				if ( ! isset( $this->available_languages[ $lang_code ] ) ) continue;
				$lang_info = $this->available_languages[ $lang_code ];
				if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) continue;
				
				$lang_url = $this->url_resolver->get_language_url( $lang_code );
				$lang_flag = $this->flag_provider->get_flag( $lang_code, $lang_info );
				$lang_name = isset( $lang_info['name'] ) ? $lang_info['name'] : ucfirst( $lang_code );
			?>
			<a href="<?php echo esc_url( $lang_url ); ?>" class="fpml-flag <?php echo esc_attr( $lang_code === $this->current_lang ? 'active' : '' ); ?>" title="<?php echo esc_attr( $lang_name ); ?>"><?php echo esc_html( $lang_flag ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}















