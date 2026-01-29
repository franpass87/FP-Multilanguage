<?php
/**
 * WooCommerce Download Synchronizer - Syncs downloadable files.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs downloadable files.
 *
 * @since 0.10.0
 */
class DownloadSynchronizer {
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger|null
	 */
	protected $logger = null;

	/**
	 * Constructor.
	 *
	 * @param \FP\Multilanguage\Logger|null $logger Logger instance.
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Sync downloadable files.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated product ID.
	 * @param int $original_id   Original product ID.
	 *
	 * @return void
	 */
	public function sync_downloadable_files( int $translated_id, int $original_id ): void {
		if ( 'product' !== get_post_type( $original_id ) ) {
			return;
		}

		$source_product = wc_get_product( $original_id );
		$target_product = wc_get_product( $translated_id );

		if ( ! $source_product || ! $target_product ) {
			return;
		}

		// Only for downloadable products
		if ( ! $source_product->is_downloadable() ) {
			return;
		}

		// Get downloadable files
		$downloads = $source_product->get_downloads();

		if ( empty( $downloads ) ) {
			return;
		}

		// Translate file names
		$translated_downloads = array();

		foreach ( $downloads as $download_id => $download ) {
			$translated_download = array(
				'id'   => $download_id,
				'file' => $download->get_file(),
				// Translate file name
				'name' => '[PENDING TRANSLATION] ' . $download->get_name(),
			);

			$translated_downloads[ $download_id ] = new \WC_Product_Download( $translated_download );
		}

		$target_product->set_downloads( $translated_downloads );
		$target_product->save();

		$this->log( 'Downloadable files synced', array(
			'files_count' => count( $translated_downloads ),
		) );
	}

	/**
	 * Log integration actions.
	 *
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 *
	 * @return void
	 */
	protected function log( string $message, array $context = array() ): void {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->log(
			'info',
			'WooCommerce Integration: ' . $message,
			array_merge(
				array( 'context' => 'woocommerce_integration' ),
				$context
			)
		);
	}
}
















