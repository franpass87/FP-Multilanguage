<?php
/**
 * FP SEO Manager Integration - Updated for v0.9.0.
 *
 * Provides bidirectional integration between FP-Multilanguage and FP-SEO-Manager:
 * - Sync SEO meta (title, description, robots, canonical) from IT to EN
 * - Sync AI-generated content (QA pairs, entities, embeddings)
 * - Sync GEO data (claims, freshness signals)
 * - Show GSC metrics for both languages in translation metabox
 * - Enable AI SEO generation for English versions
 * - Track separate SEO scores per language
 *
 * @package FP_Multilanguage
 * @since 0.6.0
 * @updated 0.9.0 - Full sync with FP-SEO-Manager v0.9.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Integrations;

use FP\Multilanguage\Integrations\Seo\MetaWhitelist;
use FP\Multilanguage\Integrations\Seo\TranslationEnqueuer;
use FP\Multilanguage\Integrations\Seo\MetaSyncHandlers;
use FP\Multilanguage\Integrations\Seo\MetaSynchronizer;
use FP\Multilanguage\Integrations\Seo\GscRenderer;
use FP\Multilanguage\Integrations\Seo\AiHintRenderer;
use FP\Multilanguage\Integrations\Seo\SeoAdmin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FP SEO Manager integration class.
 *
 * @since 0.6.0
 * @since 0.10.0 Refactored to use modular components.
 */
class FpSeoSupport {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Meta whitelist instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaWhitelist
	 */
	protected MetaWhitelist $whitelist;

	/**
	 * Meta synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaSynchronizer
	 */
	protected MetaSynchronizer $synchronizer;

	/**
	 * GSC renderer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var GscRenderer
	 */
	protected GscRenderer $gsc_renderer;

	/**
	 * AI hint renderer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var AiHintRenderer
	 */
	protected AiHintRenderer $ai_hint_renderer;

	/**
	 * SEO admin instance.
	 *
	 * @since 0.10.0
	 *
	 * @var SeoAdmin
	 */
	protected SeoAdmin $admin;

	/**
	 * Get singleton instance.
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
		// Initialize modules
		$this->whitelist = new MetaWhitelist();
		$enqueuer = new TranslationEnqueuer();
		$handlers = new MetaSyncHandlers( $enqueuer );
		$this->synchronizer = new MetaSynchronizer( $handlers );
		$this->gsc_renderer = new GscRenderer();
		$this->ai_hint_renderer = new AiHintRenderer();
		$this->admin = new SeoAdmin();
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Only if FP-SEO-Manager is active
		if ( ! $this->is_fp_seo_active() ) {
			return;
		}

		// Add FP-SEO meta to translatable whitelist
		add_filter( '\FPML_meta_whitelist', array( $this->whitelist, 'add_fp_seo_meta_to_whitelist' ) );

		// Sync SEO meta after translation
		add_action( 'fpml_after_translation_saved', array( $this->synchronizer, 'sync_seo_meta_to_translation' ), 10, 2 );

		// Add GSC comparison in translation metabox
		add_action( 'fpml_translation_metabox_after_status', array( $this->gsc_renderer, 'render_gsc_comparison' ), 10, 2 );

		// Add AI SEO hint in translation metabox
		add_action( 'fpml_translation_metabox_after_actions', array( $this->ai_hint_renderer, 'render_ai_seo_hint' ), 10, 2 );

		// Admin notice quando SEO manager è attivo
		add_action( 'admin_notices', array( $this->admin, 'integration_notice' ) );
	}

	/**
	 * Check if FP-SEO-Manager is active.
	 *
	 * @return bool
	 */
	protected function is_fp_seo_active(): bool {
		return defined( 'FP_SEO_PERFORMANCE_VERSION' ) || class_exists( 'FP\SEO\Infrastructure\Plugin' );
	}
}
