<?php
/**
 * SEO Auto-optimization per traduzioni (Feature Killer #3).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\SEO\Optimizer\MetaDescriptionGenerator;
use FP\Multilanguage\SEO\Optimizer\FocusKeywordGenerator;
use FP\Multilanguage\SEO\Optimizer\SlugOptimizer;
use FP\Multilanguage\SEO\Optimizer\OgTagsGenerator;
use FP\Multilanguage\SEO\Optimizer\SeoPreviewRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ottimizza automaticamente i meta SEO per le traduzioni.
 *
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */
class SEOOptimizer {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Settings reference.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Logger reference.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Meta description generator.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaDescriptionGenerator
	 */
	protected MetaDescriptionGenerator $meta_description;

	/**
	 * Focus keyword generator.
	 *
	 * @since 0.10.0
	 *
	 * @var FocusKeywordGenerator
	 */
	protected FocusKeywordGenerator $focus_keyword;

	/**
	 * Slug optimizer.
	 *
	 * @since 0.10.0
	 *
	 * @var SlugOptimizer
	 */
	protected SlugOptimizer $slug_optimizer;

	/**
	 * OG tags generator.
	 *
	 * @since 0.10.0
	 *
	 * @var OgTagsGenerator
	 */
	protected OgTagsGenerator $og_tags;

	/**
	 * SEO preview renderer.
	 *
	 * @since 0.10.0
	 *
	 * @var SeoPreviewRenderer
	 */
	protected SeoPreviewRenderer $preview_renderer;

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
		$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : Settings::instance();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : fpml_get_logger();

		// Initialize modules
		$this->meta_description = new MetaDescriptionGenerator();
		$this->focus_keyword = new FocusKeywordGenerator();
		$this->slug_optimizer = new SlugOptimizer( $this->settings );
		$this->og_tags = new OgTagsGenerator( $this->meta_description );
		$this->preview_renderer = new SeoPreviewRenderer( $this->meta_description, $this->focus_keyword );

		// Hook after post translation
		add_action( '\FPML_post_translated', array( $this, 'optimize_seo' ), 20, 4 );

		// Meta box for SEO preview
		add_action( 'add_meta_boxes', array( $this, 'add_seo_preview_meta_box' ) );
	}

	/**
	 * Optimize SEO meta after translation.
	 *
	 * @since 0.4.0
	 * @since 0.10.0 Delegates to specialized generators.
	 *
	 * @param \WP_Post $target_post Post tradotto.
	 * @param string    $field       Campo tradotto.
	 * @param string    $value       Valore tradotto.
	 * @param object    $job         Job della coda.
	 * @return void
	 */
	public function optimize_seo( \WP_Post $target_post, string $field, string $value, $job ): void {
		// Only when main content is translated
		if ( 'post_content' !== $field && 'post_title' !== $field ) {
			return;
		}

		// Check if optimization is enabled
		if ( ! $this->settings || ! $this->settings->get( 'auto_optimize_seo', true ) ) {
			return;
		}

		// Generate meta description if missing
		$this->meta_description->generate( $target_post );

		// Generate focus keyword based on title
		$this->focus_keyword->generate( $target_post );

		// Optimize slug if enabled
		$this->slug_optimizer->optimize( $target_post );

		// Generate OG tags
		$this->og_tags->generate( $target_post );

		$this->logger->log(
			'debug',
			sprintf( 'SEO ottimizzato per post #%d', $target_post->ID ),
			array( 'post_id' => $target_post->ID )
		);
	}

	/**
	 * Add meta box for SEO preview.
	 *
	 * @since 0.4.0
	 * @since 0.10.0 Delegates to SeoPreviewRenderer.
	 *
	 * @return void
	 */
	public function add_seo_preview_meta_box(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'\FPML_seo_preview',
				__( 'SEO Preview (EN)', 'fp-multilanguage' ),
				array( $this->preview_renderer, 'render' ),
				$post_type,
				'normal',
				'low'
			);
		}
	}
}
