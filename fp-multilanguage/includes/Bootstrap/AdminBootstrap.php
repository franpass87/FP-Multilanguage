<?php
namespace FPMultilanguage\Bootstrap;

use FPMultilanguage\Admin\Settings;

class AdminBootstrap {
	private Settings $settings;

	public function __construct( Settings $settings ) {
			$this->settings = $settings;
	}

	public function register(): void {
			$doingAjax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );

		if ( ! is_admin() && ! $doingAjax && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return;
		}

			$this->settings->register();
	}
}
