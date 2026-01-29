<?php
/**
 * AJAX Handler Interface.
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
 * Interface for AJAX handlers.
 *
 * @since 1.0.0
 */
interface AjaxHandlerInterface {
	/**
	 * Get the AJAX action name.
	 *
	 * @return string Action name.
	 */
	public function getAction(): string;

	/**
	 * Handle the AJAX request.
	 *
	 * @param array $data Request data.
	 * @return array Response data.
	 */
	public function handle( array $data ): array;
}














