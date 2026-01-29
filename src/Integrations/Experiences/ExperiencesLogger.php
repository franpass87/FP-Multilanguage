<?php
/**
 * Experiences Logger - Logs sync actions for FP Experiences integration.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Integrations\Experiences;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs sync actions for FP Experiences integration.
 *
 * @since 0.10.0
 */
class ExperiencesLogger {
	use ContainerAwareTrait;

	/**
	 * Log sync action.
	 *
	 * @param int    $post_id Translated post ID.
	 * @param string $message Log message.
	 */
	public function log_sync( $post_id, $message ) {
		$container = $this->getContainer();
		$logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : ( class_exists( '\FP\Multilanguage\Logger' ) ? fpml_get_logger() : null );
		if ( $logger ) {
			$logger->log(
				'info',
				'FP-Experiences Integration: ' . $message,
				array(
					'post_id' => $post_id,
					'context' => 'experiences_sync',
				)
			);
		}
	}
}















