<?php
/**
 * Widget Hooks Handler.
 *
 * Handles WordPress widget-related hooks.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages widget-related WordPress hooks.
 *
 * @since 1.0.0
 */
class WidgetHooks extends BaseHookHandler {
	/**
	 * Register widget hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Only register if not in assisted mode
		if ( ! $this->shouldRegister() ) {
			return;
		}

		add_filter( 'widget_update_callback', array( $this, 'handle_widget_update' ), 10, 4 );
	}

	/**
	 * Handle widget_update_callback filter.
	 *
	 * @param array     $instance     New widget instance.
	 * @param array     $new_instance New widget instance.
	 * @param array     $old_instance Old widget instance.
	 * @param WP_Widget $widget       Widget object.
	 * @return array
	 */
	public function handle_widget_update( $instance, $new_instance, $old_instance, $widget ) {
		$result = $this->delegateWithFallback( 'content.widget_handler', 'handle_widget_update', $instance, $new_instance, $old_instance, $widget );
		return $result !== null ? $result : $instance;
	}
}

