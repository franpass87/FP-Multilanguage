<?php
/**
 * Admin Page Interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Admin\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for admin pages.
 *
 * @since 1.0.0
 */
interface PageInterface {
	/**
	 * Get the page slug.
	 *
	 * @return string Page slug.
	 */
	public function getPageSlug(): string;

	/**
	 * Get the page title.
	 *
	 * @return string Page title.
	 */
	public function getPageTitle(): string;

	/**
	 * Render the page content.
	 *
	 * @return void
	 */
	public function render(): void;
}














