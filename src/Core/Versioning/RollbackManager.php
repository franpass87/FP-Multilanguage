<?php
/**
 * Translation Versioning Rollback Manager - Manages rollback operations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Core\Versioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages rollback operations.
 *
 * @since 0.10.0
 */
class RollbackManager {
	/**
	 * Rollback to a specific version.
	 *
	 * @since 0.10.0
	 *
	 * @param array $version Version data.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function rollback( array $version ): bool|\WP_Error {
		$object_type = $version['object_type'];
		$object_id   = (int) $version['object_id'];
		$field       = $version['field'];
		$old_value   = $version['old_value'];

		// Restore the old value
		switch ( $object_type ) {
			case 'post':
				return $this->rollback_post( $object_id, $field, $old_value );

			case 'term':
				return $this->rollback_term( $object_id, $field, $old_value );

			default:
				return new \WP_Error(
					'unsupported_type',
					sprintf( __( 'Tipo di oggetto non supportato: %s', 'fp-multilanguage' ), $object_type )
				);
		}
	}

	/**
	 * Rollback post field.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $field     Field name.
	 * @param string $old_value Value to restore.
	 * @return bool|\WP_Error
	 */
	protected function rollback_post( int $post_id, string $field, string $old_value ): bool|\WP_Error {
		// Standard post fields
		if ( in_array( $field, array( 'post_title', 'post_content', 'post_excerpt' ), true ) ) {
			$result = \fpml_safe_update_post(
				array(
					'ID'    => $post_id,
					$field  => $old_value,
				)
			);

			return is_wp_error( $result ) ? $result : true;
		}

		// Post meta
		$result = update_post_meta( $post_id, $field, $old_value );

		return false !== $result;
	}

	/**
	 * Rollback term field.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $term_id   Term ID.
	 * @param string $field     Field name.
	 * @param string $old_value Value to restore.
	 * @return bool|\WP_Error
	 */
	protected function rollback_term( int $term_id, string $field, string $old_value ): bool|\WP_Error {
		$term = get_term( $term_id );

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( 'invalid_term', __( 'Termine non trovato.', 'fp-multilanguage' ) );
		}

		// Standard term fields
		if ( in_array( $field, array( 'name', 'description' ), true ) ) {
			$result = \fpml_safe_update_term(
				$term_id,
				$term->taxonomy,
				array(
					$field => $old_value,
				)
			);

			return is_wp_error( $result ) ? $result : true;
		}

		// Term meta
		$result = update_term_meta( $term_id, $field, $old_value );

		return false !== $result;
	}
}















