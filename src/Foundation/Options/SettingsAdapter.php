<?php
/**
 * Settings Adapter - Wraps Foundation\Options\Options to maintain backward compatibility.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter that wraps Foundation\Options\Options to provide Settings-compatible interface.
 *
 * This class maintains backward compatibility with the old Settings class
 * while using the new Foundation\Options\Options service internally.
 *
 * @since 1.0.0
 */
class SettingsAdapter {
	/**
	 * Option key.
	 */
	const OPTION_KEY = '\FPML_settings';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Wrapped Options service.
	 *
	 * @var OptionsInterface
	 */
	protected $options;

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	protected $defaults = array();

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Load defaults
		$this->defaults = $this->get_defaults();

		// Try to get Options from container, fallback to direct instantiation
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			try {
				$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
				if ( $kernel ) {
					$container = $kernel->getContainer();
					if ( $container && $container->has( 'options' ) ) {
						$this->options = $container->get( 'options' );
						// If we got SettingsAdapter back, get the wrapped options
						if ( $this->options instanceof self ) {
							$this->options = $this->options->getWrapped();
						}
					}
				}
			} catch ( \Throwable $e ) {
				// Fallback to direct instantiation
			}
		}

		// If no options service, create one
		if ( ! $this->options ) {
			$this->options = new Options( self::OPTION_KEY, $this->defaults );
		}

		// Register WordPress hooks
		$this->registerHooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'maybe_flush_rewrites' ), 10, 3 );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'invalidate_cache' ), 5, 3 );
		add_filter( '\FPML_translatable_taxonomies', array( $this, 'maybe_include_woocommerce_taxonomies' ) );
	}

	/**
	 * Get default values.
	 *
	 * @return array
	 */
	public function get_defaults(): array {
		return array(
			'provider'                => '',
			'openai_api_key'          => '',
			'openai_model'            => 'gpt-5-nano',
			'google_api_key'          => '',
			'google_project_id'       => '',
			'batch_size'              => 5,
			'max_chars'               => 4500,
			'max_chars_per_batch'     => 20000,
			'cron_frequency'          => '15min',
			'routing_mode'            => 'segment',
			'browser_redirect'        => false,
			'browser_redirect_requires_consent' => false,
			'browser_redirect_consent_cookie'   => '',
			'marketing_tone'          => false,
			'preserve_html'           => true,
			'translate_slugs'         => true,
			'slug_redirect'           => true,
			'noindex_en'              => false,
			'sitemap_en'              => true,
			'sandbox_mode'            => false,
			'anonymize_logs'          => false,
			'glossary_case_sensitive' => false,
			'glossary_auto_detect_brands' => true,
			'show_translation_badge'  => true,
			'show_editor_notice'      => true,
			'queue_retention_days'    => 14,
			'meta_whitelist'          => '_thumbnail_id,seo_title,seo_desc,_wp_attachment_image_alt,_product_attributes',
			'exclude_regex'           => '',
			'excluded_shortcodes'     => 'vc_row,vc_column,vc_section,vc_row_inner,vc_column_inner,vc_tabs,vc_tab,vc_accordion,vc_accordion_tab,vc_tta_tabs,vc_tta_accordion,vc_tta_section,full_width_section,tabbed_section,tab,toggles,toggle',
			'rate_openai'             => '0.00011',
			'rate_google'             => '',
			'remove_data'             => false,
			'auto_translate_on_publish' => false,
			'auto_optimize_seo'         => true,
			'enable_health_check'       => true,
			'enable_auto_detection'     => true,
			'enable_auto_relink'        => true,
			'sync_featured_images'      => true,
			'duplicate_featured_images' => false,
			'enable_rush_mode'          => true,
			'enable_acf_support'        => true,
			'setup_completed'           => false,
			'enable_email_notifications' => false,
			'manual_translation_mode'   => true,
			'auto_translate_strings'    => true,
			'log_level'                 => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'debug' : 'info',
			'auto_integrate_menu_switcher' => true,
			'menu_switcher_style'          => 'inline',
			'menu_switcher_show_flags'     => true,
			'menu_switcher_position'       => 'end',
			'enabled_languages'            => array( 'en', 'de' ), // Default: English and German as main languages (Italian is always source)
		);
	}

	/**
	 * Retrieve a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		// Force sandbox_mode to false (critical fix from original Settings)
		if ( 'sandbox_mode' === $key ) {
			return false;
		}

		return $this->options->get( $key, $default );
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function all(): array {
		$all = $this->options->all();
		// Force sandbox_mode to false
		$all['sandbox_mode'] = false;
		return $all;
	}

	/**
	 * Register settings via Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'fpml_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
	}

	/**
	 * Ensure WooCommerce attribute taxonomies are translatable.
	 *
	 * @param array $taxonomies Current taxonomies.
	 * @return array
	 */
	public function maybe_include_woocommerce_taxonomies( $taxonomies ): array {
		$taxonomies = is_array( $taxonomies ) ? $taxonomies : array();

		if ( ! class_exists( 'WooCommerce' ) && ! defined( 'WC_VERSION' ) && ! class_exists( 'WC_Product' ) ) {
			return array_values( array_unique( $taxonomies ) );
		}

		$all = get_taxonomies( array(), 'names' );

		foreach ( $all as $taxonomy ) {
			if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
				$taxonomies[] = $taxonomy;
			}
		}

		return array_values( array_unique( $taxonomies ) );
	}

	/**
	 * Flush rewrite rules when routing mode changes.
	 *
	 * @param array  $old_value Previous option value.
	 * @param array  $value     New option value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function maybe_flush_rewrites( $old_value, $value, $option ): void {
		unset( $option );

		$old_value = is_array( $old_value ) ? $old_value : array();
		$value     = is_array( $value ) ? $value : array();
		$defaults  = $this->get_defaults();

		$old_mode = isset( $old_value['routing_mode'] ) ? $old_value['routing_mode'] : $defaults['routing_mode'];
		$new_mode = isset( $value['routing_mode'] ) ? $value['routing_mode'] : $old_mode;

		$old_languages = isset( $old_value['enabled_languages'] ) && is_array( $old_value['enabled_languages'] ) ? $old_value['enabled_languages'] : $defaults['enabled_languages'];
		$new_languages = isset( $value['enabled_languages'] ) && is_array( $value['enabled_languages'] ) ? $value['enabled_languages'] : $defaults['enabled_languages'];
		$old_languages = array_values( array_unique( $old_languages ) );
		$new_languages = array_values( array_unique( $new_languages ) );

		$should_flush = ( $old_mode !== $new_mode ) || ( $old_languages !== $new_languages );

		if ( ! $should_flush ) {
			return;
		}

		if ( class_exists( '\FPML_Rewrites' ) ) {
			( function_exists( 'fpml_get_rewrites' ) ? fpml_get_rewrites() : \FPML_Rewrites::instance() )->register_rewrites();
		}

		flush_rewrite_rules( false );
	}

	/**
	 * Invalidate settings cache when updated.
	 *
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value     New value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function invalidate_cache( $old_value, $value, $option ): void {
		// Reload from database
		$this->options = new Options( self::OPTION_KEY, $this->defaults );
	}

	/**
	 * Sanitize user input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ): array {
		$defaults = $this->get_defaults();
		$data     = wp_parse_args( is_array( $input ) ? $input : array(), $defaults );

		// Sanitize all fields (same as original Settings::sanitize)
		$data['provider']               = sanitize_text_field( $data['provider'] );
		$data['openai_api_key']         = sanitize_text_field( $data['openai_api_key'] );
		$data['openai_model']           = sanitize_text_field( $data['openai_model'] );
		$data['google_api_key']         = sanitize_text_field( $data['google_api_key'] );
		$data['google_project_id']      = sanitize_text_field( $data['google_project_id'] );
		$data['batch_size']             = max( 1, absint( $data['batch_size'] ) );
		$data['max_chars']              = max( 500, absint( $data['max_chars'] ) );
		$data['max_chars_per_batch']    = max( 0, absint( $data['max_chars_per_batch'] ) );

		$frequencies = array( '5min', '15min', 'hourly' );
		if ( ! in_array( $data['cron_frequency'], $frequencies, true ) ) {
			$data['cron_frequency'] = $defaults['cron_frequency'];
		}

		$data['routing_mode']            = in_array( $data['routing_mode'], array( 'segment', 'query' ), true ) ? $data['routing_mode'] : $defaults['routing_mode'];
		$data['browser_redirect']        = ! empty( $data['browser_redirect'] );
		$data['browser_redirect_requires_consent'] = ! empty( $data['browser_redirect_requires_consent'] );
		$cookie_name = sanitize_text_field( $data['browser_redirect_consent_cookie'] );
		$cookie_name = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $cookie_name );
		$data['browser_redirect_consent_cookie'] = $cookie_name;
		$data['marketing_tone']          = ! empty( $data['marketing_tone'] );
		$data['preserve_html']           = ! empty( $data['preserve_html'] );
		$data['translate_slugs']         = ! empty( $data['translate_slugs'] );
		$data['slug_redirect']           = ! empty( $data['slug_redirect'] );
		$data['noindex_en']              = ! empty( $data['noindex_en'] );
		$data['sitemap_en']              = ! empty( $data['sitemap_en'] );
		$data['sandbox_mode']            = false; // Always false
		$data['anonymize_logs']          = ! empty( $data['anonymize_logs'] );
		$data['glossary_case_sensitive'] = ! empty( $data['glossary_case_sensitive'] );
		$data['glossary_auto_detect_brands'] = ! empty( $data['glossary_auto_detect_brands'] );
		$data['show_translation_badge']  = ! empty( $data['show_translation_badge'] );
		$data['show_editor_notice']      = ! empty( $data['show_editor_notice'] );
		$data['queue_retention_days']    = min( 365, max( 0, absint( $data['queue_retention_days'] ) ) );
		$data['meta_whitelist']          = sanitize_textarea_field( $data['meta_whitelist'] );
		$data['exclude_regex']           = sanitize_textarea_field( $data['exclude_regex'] );
		$data['excluded_shortcodes']     = sanitize_textarea_field( $data['excluded_shortcodes'] );
		$data['rate_openai']             = sanitize_text_field( $data['rate_openai'] );
		$data['rate_google']             = sanitize_text_field( $data['rate_google'] );
		$data['remove_data']             = ! empty( $data['remove_data'] );

		$data['auto_translate_on_publish'] = ! empty( $data['auto_translate_on_publish'] );
		$data['auto_optimize_seo']         = ! empty( $data['auto_optimize_seo'] );
		$data['enable_health_check']       = ! empty( $data['enable_health_check'] );
		$data['enable_auto_detection']     = ! empty( $data['enable_auto_detection'] );
		$data['enable_auto_relink']        = ! empty( $data['enable_auto_relink'] );
		$data['sync_featured_images']      = ! empty( $data['sync_featured_images'] );
		$data['duplicate_featured_images'] = ! empty( $data['duplicate_featured_images'] );
		$data['manual_translation_mode']   = ! empty( $data['manual_translation_mode'] );
		$data['auto_translate_strings']    = ! empty( $data['auto_translate_strings'] );

		$log_levels = array( 'debug', 'info', 'warning', 'error' );
		$data['log_level'] = in_array( $data['log_level'], $log_levels, true ) ? $data['log_level'] : $defaults['log_level'];
		$data['enable_rush_mode']          = ! empty( $data['enable_rush_mode'] );
		$data['enable_acf_support']        = ! empty( $data['enable_acf_support'] );
		$data['setup_completed']           = ! empty( $data['setup_completed'] );
		$data['enable_email_notifications'] = ! empty( $data['enable_email_notifications'] );

		$data['auto_integrate_menu_switcher'] = ! empty( $data['auto_integrate_menu_switcher'] );
		$data['menu_switcher_style']          = in_array( $data['menu_switcher_style'], array( 'inline', 'dropdown' ), true ) ? $data['menu_switcher_style'] : $defaults['menu_switcher_style'];
		$data['menu_switcher_show_flags']     = ! empty( $data['menu_switcher_show_flags'] );
		$data['menu_switcher_position']       = in_array( $data['menu_switcher_position'], array( 'start', 'end' ), true ) ? $data['menu_switcher_position'] : $defaults['menu_switcher_position'];

		$available_languages = array( 'en', 'de', 'fr', 'es' );
		$enabled_languages = isset( $data['enabled_languages'] ) && is_array( $data['enabled_languages'] ) ? $data['enabled_languages'] : array();
		$enabled_languages = array_intersect( $enabled_languages, $available_languages );
		if ( empty( $enabled_languages ) ) {
			$enabled_languages = array( 'en' );
		}
		$data['enabled_languages'] = array_values( array_unique( $enabled_languages ) );

		update_option( '\FPML_remove_data', $data['remove_data'] );

		return $data;
	}

	/**
	 * Get wrapped options instance.
	 *
	 * @return OptionsInterface
	 */
	public function getWrapped(): OptionsInterface {
		return $this->options;
	}

	/**
	 * Set a setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return void
	 */
	public function set( $key, $value ): void {
		$this->options->set( $key, $value );
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key.
	 * @return void
	 */
	public function delete( $key ): void {
		$this->options->delete( $key );
	}

	/**
	 * Save all settings.
	 *
	 * @return void
	 */
	public function save(): void {
		// Options are auto-saved, but we can trigger a save if needed
		if ( method_exists( $this->options, 'save' ) ) {
			$this->options->save();
		}
	}
}









