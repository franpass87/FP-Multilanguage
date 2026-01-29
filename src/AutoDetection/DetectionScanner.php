<?php
/**
 * Auto Detection Scanner - Runs daily scans.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\AutoDetection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs daily scans.
 *
 * @since 0.10.0
 */
class DetectionScanner {
	/**
	 * Logger instance.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Post type detector instance.
	 *
	 * @var PostTypeDetector
	 */
	protected PostTypeDetector $post_type_detector;

	/**
	 * Taxonomy detector instance.
	 *
	 * @var TaxonomyDetector
	 */
	protected TaxonomyDetector $taxonomy_detector;

	/**
	 * Storage instance.
	 *
	 * @var DetectionStorage
	 */
	protected DetectionStorage $storage;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Logger      $logger            Logger instance.
	 * @param PostTypeDetector  $post_type_detector Post type detector instance.
	 * @param TaxonomyDetector  $taxonomy_detector Taxonomy detector instance.
	 * @param DetectionStorage  $storage           Storage instance.
	 */
	public function __construct( $logger, PostTypeDetector $post_type_detector, TaxonomyDetector $taxonomy_detector, DetectionStorage $storage ) {
		$this->logger = $logger;
		$this->post_type_detector = $post_type_detector;
		$this->taxonomy_detector = $taxonomy_detector;
		$this->storage = $storage;
	}

	/**
	 * Schedula lo scan giornaliero.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function schedule_daily_scan(): void {
		if ( ! wp_next_scheduled( '\FPML_daily_content_scan' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', '\FPML_daily_content_scan' );
		}
	}

	/**
	 * Esegue uno scan giornaliero per rilevare nuovi contenuti.
	 *
	 * @since 0.10.0
	 *
	 * @return array Summary dello scan.
	 */
	public function run_daily_scan(): array {
		$summary = array(
			'post_types_found' => 0,
			'taxonomies_found' => 0,
			'posts_to_translate' => 0,
		);

		// Scan post types.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type => $args ) {
			$this->post_type_detector->on_post_type_registered( $post_type, $args );
		}

		// Scan tassonomie.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		foreach ( $taxonomies as $taxonomy => $args ) {
			$this->taxonomy_detector->on_taxonomy_registered( $taxonomy, array(), $args );
		}

		$detected_post_types = $this->storage->get_detected_post_types();
		$detected_taxonomies = $this->storage->get_detected_taxonomies();

		$summary['post_types_found'] = count( $detected_post_types );
		$summary['taxonomies_found'] = count( $detected_taxonomies );

		// Conta contenuti da tradurre.
		foreach ( $detected_post_types as $post_type => $data ) {
			$summary['posts_to_translate'] += $data['post_count'];
		}

		$this->logger->log(
			'info',
			'Scan giornaliero completato',
			$summary
		);

		return $summary;
	}
}
















