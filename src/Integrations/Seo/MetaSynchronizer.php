<?php
/**
 * SEO Meta Synchronizer - Coordinates SEO meta synchronization.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\Seo;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates SEO meta synchronization.
 *
 * @since 0.10.0
 */
class MetaSynchronizer {
	use ContainerAwareTrait;
	/**
	 * Meta sync handlers instance.
	 *
	 * @var MetaSyncHandlers
	 */
	protected MetaSyncHandlers $handlers;

	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger|null
	 */
	protected $logger = null;

	/**
	 * Constructor.
	 *
	 * @param MetaSyncHandlers $handlers Meta sync handlers instance.
	 */
	public function __construct( MetaSyncHandlers $handlers ) {
		$this->handlers = $handlers;
		$container = $this->getContainer();
		if ( $container && $container->has( 'logger' ) ) {
			$this->logger = $container->get( 'logger' );
		} elseif ( class_exists( '\FP\Multilanguage\Logger' ) ) {
			$this->logger = fpml_get_logger();
		}
	}

	/**
	 * Sync SEO meta from original to translated post.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 *
	 * @return void
	 */
	public function sync_seo_meta_to_translation( int $translated_id, int $original_id ): void {
		if ( ! $translated_id || ! $original_id ) {
			return;
		}

		$synced_count = 0;

		// 1. CORE SEO META - Translate
		$synced_count += $this->handlers->sync_core_seo_meta( $translated_id, $original_id );

		// 2. KEYWORDS - Copy as-is (can be language-specific)
		$synced_count += $this->handlers->sync_keywords_meta( $translated_id, $original_id );

		// 3. AI FEATURES - Copy (will need re-generation for EN)
		$synced_count += $this->handlers->sync_ai_features_meta( $translated_id, $original_id );

		// 4. GEO/FRESHNESS - Copy settings
		$synced_count += $this->handlers->sync_geo_freshness_meta( $translated_id, $original_id );

		// 5. SOCIAL META - Translate
		$synced_count += $this->handlers->sync_social_meta( $translated_id, $original_id );

		// 6. SCHEMA - Copy structure
		$synced_count += $this->handlers->sync_schema_meta( $translated_id, $original_id );

		/**
		 * Fires after SEO meta sync.
		 *
		 * @param int $translated_id Translated post ID.
		 * @param int $original_id   Original post ID.
		 * @param int $synced_count  Number of meta fields synced.
		 */
		do_action( 'fpml_seo_meta_synced', $translated_id, $original_id, $synced_count );

		$this->log_sync( $translated_id, "SEO sync completed: {$synced_count} meta fields" );
	}

	/**
	 * Log sync action.
	 *
	 * @param int    $post_id Translated post ID.
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	protected function log_sync( int $post_id, string $message ): void {
		if ( $this->logger ) {
			$this->logger->log(
				'info',
				'FP-SEO Integration: ' . $message,
				array(
					'post_id' => $post_id,
					'context' => 'seo_sync',
				)
			);
		}
	}
}
















