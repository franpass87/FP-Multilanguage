<?php
/**
 * Content Filter Interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Frontend\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for frontend content filters.
 *
 * @since 1.0.0
 */
interface ContentFilterInterface {
	/**
	 * Filter content for translation.
	 *
	 * @param string $content Original content.
	 * @param string $lang    Target language code.
	 * @return string Filtered content.
	 */
	public function filter( string $content, string $lang = 'en' ): string;
}














