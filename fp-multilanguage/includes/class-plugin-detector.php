<?php
/**
 * Automatic Plugin Detection and Custom Fields Integration
 *
 * Automatically detects popular WordPress plugins and registers their
 * custom fields for translation without manual configuration.
 *
 * @package FP_Multilanguage
 * @since 0.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-detects installed plugins and their translatable fields.
 *
 * @since 0.4.2
 */
class FPML_Plugin_Detector {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Plugin_Detector|null
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
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Settings reference.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Plugin detection rules.
	 *
	 * @var array
	 */
	protected $detection_rules = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.2
	 *
	 * @return FPML_Plugin_Detector
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
		$this->logger   = FPML_Logger::instance();
		$this->settings = FPML_Settings::instance();

		// Initialize detection rules
		$this->init_detection_rules();

		// Hook into meta whitelist filter
		add_filter( 'fpml_meta_whitelist', array( $this, 'add_detected_fields_to_whitelist' ), 15, 2 );

		// Hook to detect plugins on init
		add_action( 'init', array( $this, 'detect_active_plugins' ), 5 );

		// Admin notice for detected plugins
		add_action( 'admin_notices', array( $this, 'show_detection_notice' ) );
	}

	/**
	 * Initialize plugin detection rules.
	 *
	 * @since 0.4.2
	 *
	 * @return void
	 */
	protected function init_detection_rules() {
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
		$this->detection_rules = apply_filters( 'fpml_plugin_detection_rules', $this->detection_rules );
	}

	/**
	 * Detect active plugins.
	 *
	 * @since 0.4.2
	 *
	 * @return void
	 */
	public function detect_active_plugins() {
		$detected = array();

		foreach ( $this->detection_rules as $slug => $rule ) {
			if ( $this->is_plugin_active( $rule ) ) {
				$detected[ $slug ] = $rule;

				$this->logger->log(
					'info',
					sprintf( 'Plugin rilevato: %s', $rule['name'] ),
					array( 'slug' => $slug, 'fields_count' => count( $rule['fields'] ) )
				);
			}
		}

		$this->detected_plugins = $detected;

		// Save detection cache
		update_option( 'fpml_detected_plugins', array_keys( $detected ), false );
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
	 * Add detected plugin fields to whitelist.
	 *
	 * @since 0.4.2
	 *
	 * @param array       $whitelist Current whitelist.
	 * @param FPML_Plugin $plugin    Plugin instance.
	 *
	 * @return array
	 */
	public function add_detected_fields_to_whitelist( $whitelist, $plugin = null ) {
		foreach ( $this->detected_plugins as $slug => $rule ) {
			// Add static fields
			if ( ! empty( $rule['fields'] ) ) {
				foreach ( $rule['fields'] as $field ) {
					if ( ! in_array( $field, $whitelist, true ) ) {
						$whitelist[] = $field;
					}
				}
			}

			// Execute callback for dynamic field detection
			if ( isset( $rule['callback'] ) && is_callable( $rule['callback'] ) ) {
				$dynamic_fields = call_user_func( $rule['callback'], $plugin );
				if ( is_array( $dynamic_fields ) ) {
					foreach ( $dynamic_fields as $field ) {
						if ( ! in_array( $field, $whitelist, true ) ) {
							$whitelist[] = $field;
						}
					}
				}
			}
		}

		return $whitelist;
	}

	/**
	 * Handle Elementor data (JSON structure).
	 *
	 * @since 0.4.2
	 *
	 * @param FPML_Plugin $plugin Plugin instance.
	 *
	  * @return array
	 */
	public function handle_elementor_data( $plugin = null ) {
		// Elementor data is stored as JSON and requires special handling
		// This is handled by the processor, we just register the field
		return array();
	}

	/**
	 * Detect Meta Box fields dynamically.
	 *
	 * @since 0.4.2
	 *
	 * @param FPML_Plugin $plugin Plugin instance.
	 *
	 * @return array
	 */
	public function detect_metabox_fields( $plugin = null ) {
		if ( ! class_exists( 'RWMB_Core' ) ) {
			return array();
		}

		$fields = array();

		// Get all registered meta boxes
		$meta_boxes = apply_filters( 'rwmb_meta_boxes', array() );

		foreach ( $meta_boxes as $meta_box ) {
			if ( empty( $meta_box['fields'] ) ) {
				continue;
			}

			foreach ( $meta_box['fields'] as $field ) {
				if ( $this->is_translatable_metabox_field( $field ) ) {
					$fields[] = $field['id'];
				}
			}
		}

		return $fields;
	}

	/**
	 * Check if Meta Box field is translatable.
	 *
	 * @since 0.4.2
	 *
	 * @param array $field Field config.
	 *
	 * @return bool
	 */
	protected function is_translatable_metabox_field( $field ) {
		$translatable_types = array(
			'text',
			'textarea',
			'wysiwyg',
			'email',
			'url',
			'post',
			'taxonomy',
		);

		return isset( $field['type'] ) && in_array( $field['type'], $translatable_types, true );
	}

	/**
	 * Detect Pods fields dynamically.
	 *
	 * @since 0.4.2
	 *
	 * @param FPML_Plugin $plugin Plugin instance.
	 *
	 * @return array
	 */
	public function detect_pods_fields( $plugin = null ) {
		if ( ! function_exists( 'pods_api' ) ) {
			return array();
		}

		$fields = array();

		try {
			$api = pods_api();
			$pods = $api->load_pods();

			foreach ( $pods as $pod ) {
				$pod_fields = $api->load_fields( $pod );

				foreach ( $pod_fields as $field ) {
					if ( $this->is_translatable_pods_field( $field ) ) {
						$fields[] = $field['name'];
					}
				}
			}
		} catch ( Exception $e ) {
			$this->logger->log(
				'warning',
				'Errore rilevamento campi Pods: ' . $e->getMessage()
			);
		}

		return $fields;
	}

	/**
	 * Check if Pods field is translatable.
	 *
	 * @since 0.4.2
	 *
	 * @param array $field Field config.
	 *
	 * @return bool
	 */
	protected function is_translatable_pods_field( $field ) {
		$translatable_types = array(
			'text',
			'paragraph',
			'wysiwyg',
			'email',
			'website',
			'pick',
		);

		return isset( $field['type'] ) && in_array( $field['type'], $translatable_types, true );
	}

	/**
	 * Get list of detected plugins.
	 *
	 * @since 0.4.2
	 *
	 * @return array
	 */
	public function get_detected_plugins() {
		return $this->detected_plugins;
	}

	/**
	 * Get detected plugins summary.
	 *
	 * @since 0.4.2
	 *
	 * @return array
	 */
	public function get_detection_summary() {
		$summary = array(
			'total'   => count( $this->detected_plugins ),
			'plugins' => array(),
		);

		foreach ( $this->detected_plugins as $slug => $rule ) {
			$summary['plugins'][ $slug ] = array(
				'name'   => $rule['name'],
				'fields' => count( $rule['fields'] ),
			);
		}

		return $summary;
	}

	/**
	 * Show admin notice for detected plugins.
	 *
	 * @since 0.4.2
	 *
	 * @return void
	 */
	public function show_detection_notice() {
		// Only show once after detection
		if ( ! get_transient( 'fpml_show_detection_notice' ) ) {
			return;
		}

		delete_transient( 'fpml_show_detection_notice' );

		if ( empty( $this->detected_plugins ) ) {
			return;
		}

		$plugin_names = array_column( $this->detected_plugins, 'name' );

		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'FP Multilanguage:', 'fp-multilanguage' ); ?></strong>
				<?php
				printf(
					/* translators: %d: number of plugins detected */
					esc_html( _n(
						'Rilevato %d plugin compatibile!',
						'Rilevati %d plugin compatibili!',
						count( $this->detected_plugins ),
						'fp-multilanguage'
					) ),
					count( $this->detected_plugins )
				);
				?>
			</p>
			<p>
				<?php echo esc_html( implode( ', ', $plugin_names ) ); ?>
			</p>
			<p>
				<em><?php esc_html_e( 'I campi personalizzati di questi plugin verranno tradotti automaticamente.', 'fp-multilanguage' ); ?></em>
			</p>
		</div>
		<?php
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
		set_transient( 'fpml_show_detection_notice', true, 10 );

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
