<?php
/**
 * SEO Translation Enqueuer - Enqueues SEO meta fields for translation.
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
 * Enqueues SEO meta fields for translation.
 *
 * @since 0.10.0
 */
class TranslationEnqueuer {
	use ContainerAwareTrait;
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger|null
	 */
	protected $logger = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$container = $this->getContainer();
		if ( $container && $container->has( 'logger' ) ) {
			$this->logger = $container->get( 'logger' );
		} elseif ( class_exists( '\FP\Multilanguage\Logger' ) ) {
			$this->logger = fpml_get_logger();
		}
	}

	/**
	 * Enqueue SEO meta field for translation.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $translated_id Translated post ID (TARGET post).
	 * @param string $meta_key      Meta key to translate.
	 * @param mixed  $value         Original value to translate.
	 * @param int    $original_id   Optional. Original post ID (SOURCE). If not provided, will be retrieved from meta.
	 *
	 * @return void
	 */
	public function enqueue_seo_meta_translation( int $translated_id, string $meta_key, $value, ?int $original_id = null ): void {
		// Get queue instance
		$queue = \FP\Multilanguage\Core\Container::get( 'queue' );
		if ( ! $queue ) {
			$queue = fpml_get_queue();
		}

		if ( ! $queue ) {
			return;
		}

		// Get source post ID (use provided original_id or retrieve from meta)
		if ( null === $original_id ) {
			$original_id = (int) get_post_meta( $translated_id, '_fpml_pair_source_id', true );
		}

		if ( ! $original_id ) {
			// If no source found, log error and return
			$this->log_sync( $translated_id, "ERROR: Could not find source post ID for {$meta_key}" );
			return;
		}

		// Convert value to string for hashing
		if ( is_array( $value ) ) {
			$value_string = wp_json_encode( $value );
			// wp_json_encode can return false on error
			if ( false === $value_string ) {
				$value_string = '';
			}
		} else {
			$value_string = (string) $value;
		}
		$content_hash = md5( $value_string );

		// Enqueue meta field for translation (format: "meta:meta_key")
		$field_name = 'meta:' . $meta_key;
		$queue->enqueue( 'post', $original_id, $field_name, $content_hash );

		$this->log_sync( $translated_id, "Enqueued {$meta_key} for translation (source: {$original_id})" );
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
















