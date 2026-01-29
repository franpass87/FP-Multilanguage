<?php
/**
 * Auto Translate Column Manager - Manages post list columns.
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
 * Manages post list columns.
 *
 * @since 0.10.0
 */
class ColumnManager {
	/**
	 * Meta key for auto-translate.
	 */
	const META_AUTO_TRANSLATE = '_fpml_auto_translate_on_publish';

	/**
	 * Add column to post list.
	 *
	 * @since 0.10.0
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_column( array $columns ): array {
		$columns['\FPML_auto_translate'] = __( 'Auto-Traduzione', 'fp-multilanguage' );
		return $columns;
	}

	/**
	 * Render column content.
	 *
	 * @since 0.10.0
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( '\FPML_auto_translate' !== $column ) {
			return;
		}

		$auto_translate = get_post_meta( $post_id, self::META_AUTO_TRANSLATE, true );

		if ( $auto_translate ) {
			echo '<span class="dashicons dashicons-yes" style="color: green;" title="' . esc_attr__( 'Attivo', 'fp-multilanguage' ) . '"></span>';
		} else {
			echo '<span class="dashicons dashicons-minus" style="color: #ccc;" title="' . esc_attr__( 'Disattivo', 'fp-multilanguage' ) . '"></span>';
		}
	}
}















