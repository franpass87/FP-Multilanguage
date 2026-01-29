<?php
/**
 * Automatic Plugin Detection and Custom Fields Integration
 *
 * Automatically detects popular WordPress plugins and registers their
 * custom fields for translation without manual configuration.
 *
 * @package FP_Multilanguage
 * @since 0.4.2
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\PluginDetector\DetectionRules;
use FP\Multilanguage\PluginDetector\PluginChecker;
use FP\Multilanguage\PluginDetector\FieldDetector;
use FP\Multilanguage\PluginDetector\WhitelistManager;
use FP\Multilanguage\PluginDetector\DetectionNotice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-detects installed plugins and their translatable fields.
 *
 * @since 0.4.2
 * @since 0.10.0 Refactored to use modular components.
 */
class PluginDetector {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Detected plugins cache.
	 *
	 * @var array
	 */
	protected $detected_plugins = array();

	/**
	 * Logger reference.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Settings reference.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Plugin detection rules.
	 *
	 * @var array
	 */
	protected $detection_rules = array();

	/**
	 * Detection rules manager.
	 *
	 * @since 0.10.0
	 *
	 * @var DetectionRules
	 */
	protected DetectionRules $rules_manager;

	/**
	 * Plugin checker.
	 *
	 * @since 0.10.0
	 *
	 * @var PluginChecker
	 */
	protected PluginChecker $plugin_checker;

	/**
	 * Field detector.
	 *
	 * @since 0.10.0
	 *
	 * @var FieldDetector
	 */
	protected FieldDetector $field_detector;

	/**
	 * Whitelist manager.
	 *
	 * @since 0.10.0
	 *
	 * @var WhitelistManager
	 */
	protected WhitelistManager $whitelist_manager;

	/**
	 * Detection notice.
	 *
	 * @since 0.10.0
	 *
	 * @var DetectionNotice
	 */
	protected DetectionNotice $notice;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.2
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
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		$container = $this->getContainer();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : fpml_get_logger();
		$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : Settings::instance();

		// Initialize modules
		$this->rules_manager = new DetectionRules();
		$this->plugin_checker = new PluginChecker();
		$this->field_detector = new FieldDetector();
		$this->whitelist_manager = new WhitelistManager();
		$this->notice = new DetectionNotice();

		// Initialize detection rules
		$this->init_detection_rules();

		// Hook into meta whitelist filter
		add_filter( '\FPML_meta_whitelist', array( $this, 'add_detected_fields_to_whitelist' ), 15, 2 );

		// Hook to detect plugins on init
		add_action( 'init', array( $this, 'detect_active_plugins' ), 5 );

		// Admin notice for detected plugins
		add_action( 'admin_notices', array( $this, 'show_detection_notice' ) );
	}

	/**
	 * Initialize plugin detection rules.
	 *
	 * @since 0.4.2
	 * @since 0.10.0 Delegates to DetectionRules module.
	 *
	 * @return void
	 */
	protected function init_detection_rules(): void {
		$this->detection_rules = $this->rules_manager->get_rules( $this );
	}

	/**
	 * Detect active plugins.
	 *
	 * @since 0.4.2
	 * @since 0.10.0 Delegates to PluginChecker module.
	 *
	 * @return void
	 */
	public function detect_active_plugins(): void {
		$this->detected_plugins = $this->plugin_checker->detect_active_plugins( $this->detection_rules, $this->logger );
	}

	/**
	 * Add detected plugin fields to whitelist.
	 *
	 * @since 0.4.2
	 * @since 0.10.0 Delegates to WhitelistManager module.
	 *
	 * @param array  $whitelist Current whitelist.
	 * @param object $plugin    Plugin instance.
	 * @return array
	 */
	public function add_detected_fields_to_whitelist( array $whitelist, $plugin = null ): array {
		return $this->whitelist_manager->add_detected_fields_to_whitelist( $whitelist, $this->detected_plugins );
	}

	/**
	 * Handle Elementor data (JSON structure).
	 *
	 * @since 0.4.2
	 * @since 0.10.0 Delegates to FieldDetector module.
	 *
	 * @param object $plugin Plugin instance.
	 * @return array
	 */
	public function handle_elementor_data( $plugin = null ): array {
		return $this->field_detector->handle_elementor_data();
	}

	/**
	 * Detect Meta Box fields dynamically.
	 *
	 * @since 0.4.2
	 * @since 0.10.0 Delegates to FieldDetector module.
	 *
	 * @param object $plugin Plugin instance.
	 * @return array
	 */
	public function detect_metabox_fields( $plugin = null ): array {
		return $this->field_detector->detect_metabox_fields();
	}

	/**
	 * Detect Pods fields dynamically.
	 *
	 * @since 0.4.2
	 * @since 0.10.0 Delegates to FieldDetector module.
	 *
	 * @param object $plugin Plugin instance.
	 * @return array
	 */
	public function detect_pods_fields( $plugin = null ): array {
		return $this->field_detector->detect_pods_fields();
	}

	/**
	 * Show detection notice.
	 *
	 * @since 0.4.2
	 * @since 0.10.0 Delegates to DetectionNotice module.
	 *
	 * @return void
	 */
	public function show_detection_notice(): void {
		$this->notice->show_detection_notice( $this->detected_plugins );
	}

	/**
	 * LEGACY: Initialize plugin detection rules (old format).
	 *
	 * @deprecated 0.10.0 Use init_detection_rules() instead.
	 *
	 * @return void
	 */
	protected function init_detection_rules_legacy() {
		$this->detection_rules = array(
			// SEO Plugins
			'rank_math'       => array(
				'name'      => 'Rank Math SEO',
				'check'     => array( 'class' => 'RankMath' ),
				'fields'    => array(
					'rank_math_title',
					'rank_math_description',
					'rank_math_focus_keyword',
					'rank_math_facebook_title',
					'rank_math_facebook_description',
					'rank_math_twitter_title',
					'rank_math_twitter_description',
				),
				'priority'  => 10,
			),
			'aioseo'          => array(
				'name'      => 'All in One SEO',
				'check'     => array( 'class' => 'AIOSEO\Plugin\AIOSEO' ),
				'fields'    => array(
					'_aioseo_title',
					'_aioseo_description',
					'_aioseo_og_title',
					'_aioseo_og_description',
					'_aioseo_twitter_title',
					'_aioseo_twitter_description',
				),
				'priority'  => 10,
			),
			'seopress'        => array(
				'name'      => 'SEOPress',
				'check'     => array( 'function' => 'seopress_init' ),
				'fields'    => array(
					'_seopress_titles_title',
					'_seopress_titles_desc',
					'_seopress_social_fb_title',
					'_seopress_social_fb_desc',
					'_seopress_social_twitter_title',
					'_seopress_social_twitter_desc',
				),
				'priority'  => 10,
			),

			// Page Builders
			'elementor'       => array(
				'name'      => 'Elementor',
				'check'     => array( 'class' => 'Elementor\Plugin' ),
				'fields'    => array(
					'_elementor_data',
					'_elementor_page_settings',
					'_elementor_template_type',
				),
				'priority'  => 15,
				'callback'  => array( $this, 'handle_elementor_data' ),
			),
			'beaver_builder'  => array(
				'name'      => 'Beaver Builder',
				'check'     => array( 'class' => 'FLBuilder' ),
				'fields'    => array(
					'_fl_builder_data',
					'_fl_builder_draft',
				),
				'priority'  => 15,
			),
			'oxygen'          => array(
				'name'      => 'Oxygen Builder',
				'check'     => array( 'class' => 'CT_Component' ),
				'fields'    => array(
					'ct_builder_shortcodes',
					'ct_builder_json',
				),
				'priority'  => 15,
			),

			// Forms
			'gravity_forms'   => array(
				'name'      => 'Gravity Forms',
				'check'     => array( 'class' => 'GFForms' ),
				'fields'    => array(
					'gform_confirmation_message',
					'gform_submit_button_text',
				),
				'priority'  => 10,
			),
			'ninja_forms'     => array(
				'name'      => 'Ninja Forms',
				'check'     => array( 'class' => 'Ninja_Forms' ),
				'fields'    => array(
					'_ninja_forms_field_label',
					'_ninja_forms_field_placeholder',
					'_ninja_forms_field_help_text',
				),
				'priority'  => 10,
			),

			// E-commerce
			'easy_digital_downloads' => array(
				'name'      => 'Easy Digital Downloads',
				'check'     => array( 'class' => 'Easy_Digital_Downloads' ),
				'fields'    => array(
					'edd_price',
					'edd_download_files',
					'_edd_download_instructions',
					'edd_product_notes',
				),
				'priority'  => 10,
			),

			// Custom Fields
			'meta_box'        => array(
				'name'      => 'Meta Box',
				'check'     => array( 'class' => 'RWMB_Core' ),
				'fields'    => array(),
				'priority'  => 15,
				'callback'  => array( $this, 'detect_metabox_fields' ),
			),
			'pods'            => array(
				'name'      => 'Pods',
				'check'     => array( 'function' => 'pods' ),
				'fields'    => array(),
				'priority'  => 15,
				'callback'  => array( $this, 'detect_pods_fields' ),
			),

			// Other Popular Plugins
			'the_events_calendar' => array(
				'name'      => 'The Events Calendar',
				'check'     => array( 'class' => 'Tribe__Events__Main' ),
				'fields'    => array(
					'_EventStartDate',
					'_EventEndDate',
					'_EventVenueID',
					'_EventOrganizerID',
				),
				'priority'  => 10,
			),
			'learndash'       => array(
				'name'      => 'LearnDash',
				'check'     => array( 'class' => 'SFWD_LMS' ),
				'fields'    => array(
					'_sfwd-courses',
					'_sfwd-lessons',
					'_sfwd-quiz',
				),
				'priority'  => 10,
			),
		);

		/**
		 * Allow developers to add custom detection rules.
		 *
		 * @since 0.4.2
		 *
		 * @param array $rules Detection rules.
		 */
		$this->detection_rules = apply_filters( '\FPML_plugin_detection_rules', $this->detection_rules );
	}


	/**
	 * Check if a plugin is active based on detection rule.
	 *
	 * @since 0.4.2
	 *
	 * @param array $rule Detection rule.
	 *
	 * @return bool
	 */
	protected function is_plugin_active( $rule ) {
		if ( ! isset( $rule['check'] ) ) {
			return false;
		}

		$check = $rule['check'];

		// Check by class existence
		if ( isset( $check['class'] ) ) {
			return class_exists( $check['class'] );
		}

		// Check by function existence
		if ( isset( $check['function'] ) ) {
			return function_exists( $check['function'] );
		}

		// Check by constant
		if ( isset( $check['constant'] ) ) {
			return defined( $check['constant'] );
		}

		// Check by plugin file
		if ( isset( $check['plugin'] ) ) {
			return is_plugin_active( $check['plugin'] );
		}

		return false;
	}




	/**
	 * Manually trigger detection (for admin interface).
	 *
	 * @since 0.4.2
	 *
	 * @return array
	 */
	public function trigger_detection() {
		$this->detect_active_plugins();
		set_transient( '\FPML_show_detection_notice', true, 10 );

		return $this->get_detection_summary();
	}

	/**
	 * Check if a specific plugin was detected.
	 *
	 * @since 0.4.2
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return bool
	 */
	public function is_plugin_detected( $slug ) {
		return isset( $this->detected_plugins[ $slug ] );
	}

	/**
	 * Get fields for a specific detected plugin.
	 *
	 * @since 0.4.2
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array
	 */
	public function get_plugin_fields( $slug ) {
		if ( ! isset( $this->detected_plugins[ $slug ] ) ) {
			return array();
		}

		return $this->detected_plugins[ $slug ]['fields'];
	}
}

