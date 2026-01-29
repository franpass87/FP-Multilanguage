<?php
/**
 * Site Part Translators - Breadcrumb Translator - Handles breadcrumb translation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin\SitePartTranslators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles breadcrumb translation.
 *
 * @since 0.10.0
 */
class BreadcrumbTranslator {
	/**
	 * Text translator instance.
	 *
	 * @var TextTranslator
	 */
	protected TextTranslator $text_translator;

	/**
	 * Constructor.
	 *
	 * @param TextTranslator $text_translator Text translator instance.
	 */
	public function __construct( TextTranslator $text_translator ) {
		$this->text_translator = $text_translator;
	}

	/**
	 * Traduce i breadcrumb.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		$common_labels = array(
			'Home' => __( 'Home', 'fp-multilanguage' ),
			'Blog' => __( 'Blog', 'fp-multilanguage' ),
			'Category' => __( 'Category', 'fp-multilanguage' ),
			'Tag' => __( 'Tag', 'fp-multilanguage' ),
			'Author' => __( 'Author', 'fp-multilanguage' ),
			'Search' => __( 'Search', 'fp-multilanguage' ),
			'Page' => __( 'Page', 'fp-multilanguage' ),
			'Archive' => __( 'Archive', 'fp-multilanguage' ),
		);

		foreach ( $common_labels as $label ) {
			$translated = $this->text_translator->translate_text( $label );
			if ( $translated ) {
				update_option( '_fpml_en_breadcrumb_' . md5( $label ), $translated );
				$translated_count++;
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated breadcrumb labels */
				__( '%d etichette breadcrumb tradotte.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















