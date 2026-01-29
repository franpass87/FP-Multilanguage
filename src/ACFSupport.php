<?php
/**
 * Supporto avanzato per Advanced Custom Fields.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\ACF\FieldWhitelist;
use FP\Multilanguage\ACF\PostRelationProcessor;
use FP\Multilanguage\ACF\TaxonomyRelationProcessor;
use FP\Multilanguage\ACF\RepeaterProcessor;
use FP\Multilanguage\ACF\FlexibleContentProcessor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce relazioni ACF e campi complessi.
 *
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */
class ACFSupport {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

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
	 * ACF disponibile.
	 *
	 * @var bool
	 */
	protected $acf_available = false;

	/**
	 * Field whitelist manager.
	 *
	 * @since 0.10.0
	 *
	 * @var FieldWhitelist
	 */
	protected FieldWhitelist $field_whitelist;

	/**
	 * Post relation processor.
	 *
	 * @since 0.10.0
	 *
	 * @var PostRelationProcessor
	 */
	protected PostRelationProcessor $post_processor;

	/**
	 * Taxonomy relation processor.
	 *
	 * @since 0.10.0
	 *
	 * @var TaxonomyRelationProcessor
	 */
	protected TaxonomyRelationProcessor $taxonomy_processor;

	/**
	 * Repeater processor.
	 *
	 * @since 0.10.0
	 *
	 * @var RepeaterProcessor
	 */
	protected RepeaterProcessor $repeater_processor;

	/**
	 * Flexible content processor.
	 *
	 * @since 0.10.0
	 *
	 * @var FlexibleContentProcessor
	 */
	protected FlexibleContentProcessor $flexible_processor;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
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

		// Check if ACF is active
		$this->acf_available = class_exists( 'ACF' ) || function_exists( 'acf_get_field_groups' );

		if ( ! $this->acf_available ) {
			return;
		}

		// Initialize modules
		$this->field_whitelist = new FieldWhitelist();
		$this->post_processor = new PostRelationProcessor( $this->logger );
		$this->taxonomy_processor = new TaxonomyRelationProcessor( $this->logger );
		$this->repeater_processor = new RepeaterProcessor( $this->post_processor );
		$this->flexible_processor = new FlexibleContentProcessor( $this->logger, $this->post_processor );

		// Hook after translation to process relations
		add_action( '\FPML_post_translated', array( $this, 'process_acf_relations' ), 40, 4 );

		// Filter for meta whitelist - automatically add ACF fields
		add_filter( '\FPML_meta_whitelist', array( $this, 'add_acf_fields_to_whitelist' ), 20, 2 );
	}

	/**
	 * Automatically add ACF fields to whitelist.
	 *
	 * @since 0.4.0
	 * @since 0.10.0 Delegates to FieldWhitelist module.
	 *
	 * @param array  $whitelist Current whitelist.
	 * @param object $plugin    Plugin instance.
	 * @return array
	 */
	public function add_acf_fields_to_whitelist( array $whitelist, $plugin ): array {
		if ( ! $this->acf_available ) {
			return $whitelist;
		}

		return $this->field_whitelist->add_acf_fields_to_whitelist( $whitelist );
	}

	/**
	 * Process ACF relations after translation.
	 *
	 * @since 0.4.0
	 * @since 0.10.0 Delegates to specialized processors.
	 *
	 * @param \WP_Post $target_post Post tradotto.
	 * @param string    $field       Campo tradotto.
	 * @param string    $value       Valore.
	 * @param object    $job         Job.
	 * @return void
	 */
	public function process_acf_relations( \WP_Post $target_post, string $field, string $value, $job ): void {
		// Only for ACF meta
		if ( 0 !== strpos( $field, 'meta:' ) ) {
			return;
		}

		$meta_key = substr( $field, 5 );

		// Get ACF field config
		if ( ! function_exists( 'acf_get_field' ) ) {
			return;
		}

		$field_object = acf_get_field( $meta_key );

		if ( ! $field_object ) {
			return; // Not an ACF field
		}

		// Handle based on type
		switch ( $field_object['type'] ) {
			case 'post_object':
			case 'relationship':
				$this->post_processor->process_post_relation( $target_post, $meta_key, $field_object );
				break;

			case 'taxonomy':
				$this->taxonomy_processor->process_taxonomy_relation( $target_post, $meta_key, $field_object );
				break;

			case 'repeater':
				$this->repeater_processor->process_repeater_field( $target_post, $meta_key, $field_object );
				break;

			case 'flexible_content':
				$this->flexible_processor->process_flexible_content( $target_post, $meta_key, $field_object );
				break;
		}
	}
}
