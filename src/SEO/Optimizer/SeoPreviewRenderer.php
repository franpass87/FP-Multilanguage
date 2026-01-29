<?php
/**
 * SEO Optimizer SEO Preview Renderer - Renders SEO preview meta box.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO\Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders SEO preview meta box.
 *
 * @since 0.10.0
 */
class SeoPreviewRenderer {
	/**
	 * Meta description generator instance.
	 *
	 * @var MetaDescriptionGenerator
	 */
	protected MetaDescriptionGenerator $meta_description;

	/**
	 * Focus keyword generator instance.
	 *
	 * @var FocusKeywordGenerator
	 */
	protected FocusKeywordGenerator $focus_keyword;

	/**
	 * Constructor.
	 *
	 * @param MetaDescriptionGenerator $meta_description Meta description generator instance.
	 * @param FocusKeywordGenerator    $focus_keyword    Focus keyword generator instance.
	 */
	public function __construct( MetaDescriptionGenerator $meta_description, FocusKeywordGenerator $focus_keyword ) {
		$this->meta_description = $meta_description;
		$this->focus_keyword = $focus_keyword;
	}

	/**
	 * Render SEO preview meta box.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		// Show only for translated posts
		if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			echo '<p>' . esc_html__( 'Questo è il post originale. La preview SEO è disponibile sul post tradotto.', 'fp-multilanguage' ) . '</p>';
			return;
		}

		$title       = $post->post_title;
		$description = $this->meta_description->get_existing( $post );
		$keyword     = $this->focus_keyword->get_existing( $post );
		$permalink   = get_permalink( $post->ID );

		?>
		<div class="fpml-seo-preview" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 4px;">
			<p style="margin: 0 0 10px;"><strong><?php esc_html_e( 'Anteprima Google:', 'fp-multilanguage' ); ?></strong></p>
			
			<div style="max-width: 600px;">
				<div style="color: #1a0dab; font-size: 18px; line-height: 1.3; margin-bottom: 5px;">
					<?php echo esc_html( $title ); ?>
				</div>
				<div style="color: #006621; font-size: 14px; margin-bottom: 5px;">
					<?php echo esc_html( $permalink ); ?>
				</div>
				<div style="color: #545454; font-size: 13px; line-height: 1.4;">
					<?php echo esc_html( $description ? $description : __( 'Nessuna description impostata.', 'fp-multilanguage' ) ); ?>
				</div>
			</div>

			<?php if ( $keyword ) : ?>
				<p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
					<strong><?php esc_html_e( 'Focus Keyword:', 'fp-multilanguage' ); ?></strong>
					<code><?php echo esc_html( $keyword ); ?></code>
				</p>
			<?php endif; ?>

			<p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
				<small style="color: #666;">
					<?php
					esc_html_e( 'Questo è un\'anteprima di come apparirà il contenuto nei risultati di ricerca Google. I meta SEO sono stati ottimizzati automaticamente.', 'fp-multilanguage' );
					?>
				</small>
			</p>
		</div>
		<?php
	}
}















