<?php
/**
 * Experiences Enqueuer - Enqueues Experiences meta fields for translation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Integrations\Experiences;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues Experiences meta fields for translation.
 *
 * @since 0.10.0
 */
class ExperiencesEnqueuer {
	/**
	 * Experiences logger instance.
	 *
	 * @var ExperiencesLogger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param ExperiencesLogger $logger Logger instance.
	 */
	public function __construct( ExperiencesLogger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Enqueue Experiences meta field for translation.
	 *
	 * @param int    $translated_id Translated post ID (TARGET post).
	 * @param string $meta_key      Meta key to translate.
	 * @param mixed  $value         Original value to translate.
	 * @param int    $original_id   Optional. Original post ID (SOURCE). If not provided, will be retrieved from meta.
	 */
	public function enqueue_experiences_meta_translation( $translated_id, $meta_key, $value, $original_id = null ) {
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
			$this->logger->log_sync( $translated_id, "ERROR: Could not find source post ID for {$meta_key}" );
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

		$this->logger->log_sync( $translated_id, "Enqueued {$meta_key} for translation (source: {$original_id})" );
	}
}















