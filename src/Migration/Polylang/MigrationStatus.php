<?php
/**
 * Polylang Migrator Migration Status - Manages migration status.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Migration\Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages migration status.
 *
 * @since 0.10.0
 */
class MigrationStatus {
	/**
	 * Migration status option key.
	 *
	 * @var string
	 */
	const STATUS_OPTION_KEY = 'fpml_polylang_migration_status';

	/**
	 * Get migration status.
	 *
	 * @since 0.10.0
	 *
	 * @return array Migration status.
	 */
	public function get_migration_status(): array {
		$status = get_option( self::STATUS_OPTION_KEY, array() );

		return array_merge(
			array(
				'completed'      => false,
				'posts_migrated' => 0,
				'terms_migrated' => 0,
				'errors'         => array(),
				'started_at'     => null,
				'completed_at'   => null,
			),
			$status
		);
	}

	/**
	 * Update migration status.
	 *
	 * @since 0.10.0
	 *
	 * @param array $status Status to update.
	 * @return void
	 */
	public function update_status( array $status ): void {
		update_option( self::STATUS_OPTION_KEY, $status );
	}

	/**
	 * Initialize migration status.
	 *
	 * @since 0.10.0
	 *
	 * @return array Initial status.
	 */
	public function initialize_status(): array {
		$status = array(
			'completed'      => false,
			'posts_migrated' => 0,
			'terms_migrated' => 0,
			'errors'         => array(),
			'started_at'     => current_time( 'mysql' ),
			'completed_at'   => null,
		);

		$this->update_status( $status );

		return $status;
	}
}















