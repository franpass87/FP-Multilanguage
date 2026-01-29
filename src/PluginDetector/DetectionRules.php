<?php
/**
 * Plugin Detector Detection Rules - Manages plugin detection rules.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\PluginDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin detection rules.
 *
 * @since 0.10.0
 */
class DetectionRules {
	/**
	 * Get all detection rules.
	 *
	 * @since 0.10.0
	 *
	 * @param object $detector Detector instance for callbacks.
	 * @return array Detection rules.
	 */
	public function get_rules( $detector ): array {
		$rules = array(
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
				'callback'  => array( $detector, 'handle_elementor_data' ),
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
				'callback'  => array( $detector, 'detect_metabox_fields' ),
			),
			'pods'            => array(
				'name'      => 'Pods',
				'check'     => array( 'function' => 'pods' ),
				'fields'    => array(),
				'priority'  => 15,
				'callback'  => array( $detector, 'detect_pods_fields' ),
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
		return apply_filters( '\FPML_plugin_detection_rules', $rules );
	}
}















