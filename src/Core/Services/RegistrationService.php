<?php
/**
 * Registration Service.
 *
 * Centralizes registration of WordPress components (widgets, shortcodes, REST API).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for registering WordPress components.
 *
 * @since 1.0.0
 */
class RegistrationService {

	/**
	 * Register widgets.
	 *
	 * @return void
	 */
	public function registerWidgets(): void {
		if ( class_exists( '\FPML_Language_Switcher_Widget' ) ) {
			register_widget( '\FPML_Language_Switcher_Widget' );
		} elseif ( class_exists( '\FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget' ) ) {
			register_widget( '\FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget' );
		}
	}

	/**
	 * Register shortcodes.
	 *
	 * @param callable|null $shortcode_callback Optional callback for shortcode rendering.
	 * @return void
	 */
	public function registerShortcodes( ?callable $shortcode_callback = null ): void {
		if ( $shortcode_callback ) {
			add_shortcode( 'fpml_language_switcher', $shortcode_callback );
		} else {
			// Default implementation
			add_shortcode( 'fpml_language_switcher', array( $this, 'renderLanguageSwitcherShortcode' ) );
		}
	}

	/**
	 * Render language switcher shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function renderLanguageSwitcherShortcode( $atts ): string {
		if ( class_exists( '\FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget' ) ) {
			$widget = new \FP\Multilanguage\Frontend\Widgets\LanguageSwitcherWidget();
			return $widget->render_shortcode( $atts );
		}
		return '';
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function registerRestRoutes(): void {
		if ( class_exists( '\FP\Multilanguage\Rest\Controllers\AdminController' ) ) {
			$admin_controller = new \FP\Multilanguage\Rest\Controllers\AdminController();
			$admin_controller->register_routes();
		}
	}

	/**
	 * Register all components.
	 *
	 * @param callable|null $shortcode_callback Optional callback for shortcode rendering.
	 * @return void
	 */
	public function registerAll( ?callable $shortcode_callback = null ): void {
		$this->registerWidgets();
		$this->registerShortcodes( $shortcode_callback );
		$this->registerRestRoutes();
	}
}








