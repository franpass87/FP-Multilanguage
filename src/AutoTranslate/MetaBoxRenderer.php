<?php
/**
 * Auto Translate Meta Box Renderer - Renders meta box in editor.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\AutoTranslate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders meta box in editor.
 *
 * @since 0.10.0
 */
class MetaBoxRenderer {
	/**
	 * Meta key for auto-translate.
	 */
	const META_AUTO_TRANSLATE = '_fpml_auto_translate_on_publish';

	/**
	 * Render meta box.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		// Check if it's a translation
		if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			echo '<p>' . esc_html__( 'Questo è un post tradotto.', 'fp-multilanguage' ) . '</p>';
			return;
		}

		$auto_translate = get_post_meta( $post->ID, self::META_AUTO_TRANSLATE, true );

		wp_nonce_field( '\FPML_auto_translate_meta', '\FPML_auto_translate_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="\FPML_auto_translate_on_publish" value="1" <?php checked( $auto_translate, '1' ); ?> />
				<?php esc_html_e( 'Traduci automaticamente alla pubblicazione', 'fp-multilanguage' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Quando pubblichi questo contenuto, verrà tradotto immediatamente in inglese e pubblicato automaticamente.', 'fp-multilanguage' ); ?>
		</p>
		<?php

		// Show translation status if exists
		$pair_id = get_post_meta( $post->ID, '_fpml_pair_id', true );
		if ( $pair_id ) {
			$translation = get_post( $pair_id );
			if ( $translation ) {
				echo '<hr />';
				echo '<p><strong>' . esc_html__( 'Traduzione:', 'fp-multilanguage' ) . '</strong></p>';
				echo '<p>';
				echo '<a href="' . esc_url( get_edit_post_link( $translation->ID ) ) . '" target="_blank">';
				
				// Check translation title status
				$title_status = get_post_meta( $translation->ID, '_fpml_status_post_title', true );
				if ( 'needs_update' === $title_status ) {
					echo esc_html__( '(Traduzione in corso...)', 'fp-multilanguage' );
				} elseif ( $translation->post_title ) {
					echo esc_html( $translation->post_title );
				} else {
					echo esc_html__( '(Senza titolo)', 'fp-multilanguage' );
				}
				
				echo '</a><br />';
				echo '<span class="dashicons dashicons-visibility"></span> ';
				echo '<a href="' . esc_url( get_permalink( $translation->ID ) ) . '" target="_blank">' . esc_html__( 'Visualizza', 'fp-multilanguage' ) . '</a>';
				echo '</p>';

				// Translation status
				$this->render_translation_status( $translation );
			}
		}
	}

	/**
	 * Render translation status.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $translation Translated post.
	 * @return void
	 */
	protected function render_translation_status( \WP_Post $translation ): void {
		$fields = array( 'post_title', 'post_content', 'post_excerpt' );
		$status = array();

		foreach ( $fields as $field ) {
			$meta_key   = '_fpml_status_' . $field;
			$field_status = get_post_meta( $translation->ID, $meta_key, true );

			$status[ $field ] = $field_status ? $field_status : 'unknown';
		}

		echo '<p><small>';
		foreach ( $status as $field => $state ) {
			$icon = 'completed' === $state ? '✓' : '⏳';
			$label = str_replace( 'post_', '', $field );
			echo esc_html( sprintf( '%s %s ', $icon, ucfirst( $label ) ) );
		}
		echo '</small></p>';
	}
}















