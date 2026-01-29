<?php
/**
 * Auto-detection di nuovi post types e tassonomie.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\AutoDetection\PostTypeDetector;
use FP\Multilanguage\AutoDetection\TaxonomyDetector;
use FP\Multilanguage\AutoDetection\DetectionStorage;
use FP\Multilanguage\AutoDetection\DetectionScanner;
use FP\Multilanguage\AutoDetection\DetectionNotices;
use FP\Multilanguage\AutoDetection\DetectionAjax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rileva automaticamente nuovi contenuti e suggerisce la traduzione.
 *
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */
class AutoDetection {
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
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Post type detector instance.
	 *
	 * @since 0.10.0
	 *
	 * @var PostTypeDetector
	 */
	protected PostTypeDetector $post_type_detector;

	/**
	 * Taxonomy detector instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TaxonomyDetector
	 */
	protected TaxonomyDetector $taxonomy_detector;

	/**
	 * Storage instance.
	 *
	 * @since 0.10.0
	 *
	 * @var DetectionStorage
	 */
	protected DetectionStorage $storage;

	/**
	 * Scanner instance.
	 *
	 * @since 0.10.0
	 *
	 * @var DetectionScanner
	 */
	protected DetectionScanner $scanner;

	/**
	 * Notices instance.
	 *
	 * @since 0.10.0
	 *
	 * @var DetectionNotices
	 */
	protected DetectionNotices $notices;

	/**
	 * Ajax instance.
	 *
	 * @since 0.10.0
	 *
	 * @var DetectionAjax
	 */
	protected DetectionAjax $ajax;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return self
	 */
	public static function instance() {
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
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : \FPML_fpml_get_logger();

		// Initialize modules
		$this->storage = new DetectionStorage();
		$this->post_type_detector = new PostTypeDetector( $this->logger, $this->storage );
		$this->taxonomy_detector = new TaxonomyDetector( $this->logger, $this->storage );
		$this->scanner = new DetectionScanner( $this->logger, $this->post_type_detector, $this->taxonomy_detector, $this->storage );
		$this->notices = new DetectionNotices( $this->storage );
		$this->ajax = new DetectionAjax( $this->logger, $this->storage );

		// Hook su registrazione post types e tassonomie.
		add_action( 'registered_post_type', array( $this->post_type_detector, 'on_post_type_registered' ), 10, 2 );
		add_action( 'registered_taxonomy', array( $this->taxonomy_detector, 'on_taxonomy_registered' ), 10, 3 );

		// Scan giornaliero per nuovi contenuti.
		add_action( 'init', array( $this->scanner, 'schedule_daily_scan' ) );
		add_action( '\FPML_daily_content_scan', array( $this->scanner, 'run_daily_scan' ) );

		// Admin notice per nuovi contenuti rilevati.
		add_action( 'admin_notices', array( $this->notices, 'show_detection_notices' ) );

		// AJAX per accettare/ignorare suggerimenti.
		add_action( 'wp_ajax_fpml_accept_post_type', array( $this->ajax, 'ajax_accept_post_type' ) );
		add_action( 'wp_ajax_fpml_ignore_post_type', array( $this->ajax, 'ajax_ignore_post_type' ) );
		add_action( 'wp_ajax_fpml_accept_taxonomy', array( $this->ajax, 'ajax_accept_taxonomy' ) );
		add_action( 'wp_ajax_fpml_ignore_taxonomy', array( $this->ajax, 'ajax_ignore_taxonomy' ) );
	}

	/**
	 * Ottieni post types rilevati.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_detected_post_types(): array {
		return $this->storage->get_detected_post_types();
	}

	/**
	 * Ottieni tassonomie rilevate.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_detected_taxonomies(): array {
		return $this->storage->get_detected_taxonomies();
	}
}
