<?php
/**
 * Settings handler.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Manage plugin settings.
 *
 * @since 0.2.0
 */
class FPML_Settings {
/**
 * Option key.
 */
const OPTION_KEY = 'fpml_settings';

/**
 * Singleton instance.
 *
 * @var FPML_Settings|null
 */
protected static $instance = null;

/**
 * Cached settings.
 *
 * @var array
 */
protected $settings = array();

/**
 * Retrieve singleton.
 *
 * @since 0.2.0
 *
 * @return FPML_Settings
 */
public static function instance() {
if ( null === self::$instance ) {
self::$instance = new self();
}

return self::$instance;
}

/**
 * Constructor.
 */
	protected function __construct() {
	$saved_settings = get_option( self::OPTION_KEY, array() );
	$defaults = $this->get_defaults();
	
	// Use wp_parse_args if available, otherwise manual merge
	if ( function_exists( 'wp_parse_args' ) ) {
		$this->settings = wp_parse_args( $saved_settings, $defaults );
	} else {
		$this->settings = is_array( $saved_settings ) ? array_merge( $defaults, $saved_settings ) : $defaults;
	}
	
	// Registrazione impostazioni per Settings API
	add_action( 'admin_init', array( $this, 'register_settings' ) );
	add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'maybe_flush_rewrites' ), 10, 3 );
	add_filter( 'fpml_translatable_taxonomies', array( $this, 'maybe_include_woocommerce_taxonomies' ) );
}

/**
 * Get default values.
 *
 * @since 0.2.0
 *
 * @return array
 */
public function get_defaults() {
		return array(
			'provider'                => '',
			'openai_api_key'          => '',
			'openai_model'            => 'gpt-5',
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
			'show_translation_badge'  => true,
			'show_editor_notice'      => true,
			'queue_retention_days'    => 14,
			'meta_whitelist'          => '_thumbnail_id,seo_title,seo_desc,_wp_attachment_image_alt,_product_attributes',
			'exclude_regex'           => '',
			'excluded_shortcodes'     => 'vc_row,vc_column,vc_section',
			'rate_openai'             => '',
			'rate_google'             => '',
			'remove_data'             => false,
			// Nuove opzioni 0.4.0+.
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
			// Menu language switcher integration (0.4.2+).
			'auto_integrate_menu_switcher' => true,
			'menu_switcher_style'          => 'inline',
			'menu_switcher_show_flags'     => true,
			'menu_switcher_position'       => 'end',
		);
}

/**
 * Retrieve a setting value.
 *
 * @since 0.2.0
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
public function get( $key, $default = null ) {
if ( isset( $this->settings[ $key ] ) ) {
return $this->settings[ $key ];
}

return $default;
}

/**
 * Get all settings.
 *
 * @since 0.2.0
 *
 * @return array
 */
public function all() {
return $this->settings;
}

/**
 * Register settings via Settings API.
 *
 * @since 0.2.0
 *
 * @return void
 */
public function register_settings() {
register_setting( 'fpml_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
}

/**
 * Ensure WooCommerce attribute taxonomies are translatable.
 *
 * @since 0.3.0
 *
 * @param array $taxonomies Current taxonomies.
 *
 * @return array
 */
public function maybe_include_woocommerce_taxonomies( $taxonomies ) {
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
 * @since 0.2.0
 *
 * @param array $old_value Previous option value.
 * @param array $value     New option value.
 * @param string $option   Option name.
 *
 * @return void
 */
public function maybe_flush_rewrites( $old_value, $value, $option ) {
unset( $option );

$old_value = is_array( $old_value ) ? $old_value : array();
$value     = is_array( $value ) ? $value : array();
$defaults  = $this->get_defaults();

$old_mode = isset( $old_value['routing_mode'] ) ? $old_value['routing_mode'] : $defaults['routing_mode'];
$new_mode = isset( $value['routing_mode'] ) ? $value['routing_mode'] : $old_mode;

if ( $old_mode === $new_mode ) {
return;
}

if ( class_exists( 'FPML_Rewrites' ) ) {
FPML_Rewrites::instance()->register_rewrites();
}

flush_rewrite_rules( false );
}

/**
 * Sanitize user input.
 *
 * @since 0.2.0
 *
 * @param array $input Raw input.
 *
 * @return array
 */
public function sanitize( $input ) {
$defaults = $this->get_defaults();
$data     = wp_parse_args( is_array( $input ) ? $input : array(), $defaults );

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
$data['sandbox_mode']            = ! empty( $data['sandbox_mode'] );
$data['anonymize_logs']          = ! empty( $data['anonymize_logs'] );
        $data['glossary_case_sensitive'] = ! empty( $data['glossary_case_sensitive'] );
        $data['show_translation_badge']  = ! empty( $data['show_translation_badge'] );
        $data['show_editor_notice']      = ! empty( $data['show_editor_notice'] );
        $data['queue_retention_days']    = min( 365, max( 0, absint( $data['queue_retention_days'] ) ) );
        $data['meta_whitelist']          = sanitize_textarea_field( $data['meta_whitelist'] );
$data['exclude_regex']           = sanitize_textarea_field( $data['exclude_regex'] );
$data['excluded_shortcodes']     = sanitize_textarea_field( $data['excluded_shortcodes'] );
	$data['rate_openai']             = sanitize_text_field( $data['rate_openai'] );
	$data['rate_google']             = sanitize_text_field( $data['rate_google'] );
	$data['remove_data']             = ! empty( $data['remove_data'] );

	// Nuove opzioni 0.4.0+.
	$data['auto_translate_on_publish'] = ! empty( $data['auto_translate_on_publish'] );
	$data['auto_optimize_seo']         = ! empty( $data['auto_optimize_seo'] );
	$data['enable_health_check']       = ! empty( $data['enable_health_check'] );
	$data['enable_auto_detection']     = ! empty( $data['enable_auto_detection'] );
	$data['enable_auto_relink']        = ! empty( $data['enable_auto_relink'] );
	$data['sync_featured_images']      = ! empty( $data['sync_featured_images'] );
	$data['duplicate_featured_images'] = ! empty( $data['duplicate_featured_images'] );
	$data['enable_rush_mode']          = ! empty( $data['enable_rush_mode'] );
	$data['enable_acf_support']        = ! empty( $data['enable_acf_support'] );
	$data['setup_completed']           = ! empty( $data['setup_completed'] );
	$data['enable_email_notifications'] = ! empty( $data['enable_email_notifications'] );

	// Menu language switcher integration (0.4.2+).
	$data['auto_integrate_menu_switcher'] = ! empty( $data['auto_integrate_menu_switcher'] );
	$data['menu_switcher_style']          = in_array( $data['menu_switcher_style'], array( 'inline', 'dropdown' ), true ) ? $data['menu_switcher_style'] : $defaults['menu_switcher_style'];
	$data['menu_switcher_show_flags']     = ! empty( $data['menu_switcher_show_flags'] );
	$data['menu_switcher_position']       = in_array( $data['menu_switcher_position'], array( 'start', 'end' ), true ) ? $data['menu_switcher_position'] : $defaults['menu_switcher_position'];

	update_option( 'fpml_remove_data', $data['remove_data'] );

	$this->settings = $data;

	return $data;
}
}
