<?php
/**
 * Translation Versioning Version Saver - Saves translation versions.
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
 * Saves translation versions.
 *
 * @since 0.10.0
 */
class VersionSaver {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Constructor.
	 *
	 * @param string $table Table name.
	 */
	public function __construct( string $table ) {
		$this->table = $table;
	}

	/**
	 * Save post translation version.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param int    $target_post_id Target post ID.
	 * @param string $field          Field name.
	 * @param array  $data           Translation data.
	 * @return void
	 */
	public function save_post_version( int $source_post_id, int $target_post_id, string $field, array $data ): void {
		if ( empty( $target_post_id ) ) {
			return;
		}

		$old_value = '';
		$new_value = isset( $data['translated'] ) ? $data['translated'] : '';

		// Get old value based on field
		switch ( $field ) {
			case 'post_title':
				$old_value = get_the_title( $target_post_id );
				break;
			case 'post_content':
				$post      = get_post( $target_post_id );
				$old_value = $post ? $post->post_content : '';
				break;
			case 'post_excerpt':
				$post      = get_post( $target_post_id );
				$old_value = $post ? $post->post_excerpt : '';
				break;
			default:
				$old_value = get_post_meta( $target_post_id, $field, true );
				break;
		}

		$this->save_version(
			'post',
			$target_post_id,
			$field,
			$old_value,
			$new_value,
			isset( $data['provider'] ) ? $data['provider'] : ''
		);
	}

	/**
	 * Save term translation version.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $source_term_id Source term ID.
	 * @param int    $target_term_id Target term ID.
	 * @param array  $data           Translation data.
	 * @return void
	 */
	public function save_term_version( int $source_term_id, int $target_term_id, array $data ): void {
		if ( empty( $target_term_id ) ) {
			return;
		}

		$term = get_term( $target_term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$field     = isset( $data['field'] ) ? $data['field'] : 'name';
		$old_value = isset( $term->{$field} ) ? $term->{$field} : '';
		$new_value = isset( $data['translated'] ) ? $data['translated'] : '';

		$this->save_version(
			'term',
			$target_term_id,
			$field,
			$old_value,
			$new_value,
			isset( $data['provider'] ) ? $data['provider'] : ''
		);
	}

	/**
	 * Save a version entry.
	 *
	 * @since 0.10.0
	 *
	 * @param string $object_type Object type (post, term, menu, etc).
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field name.
	 * @param string $old_value   Previous value.
	 * @param string $new_value   New value.
	 * @param string $provider    Translation provider.
	 * @return int|false Insert ID or false on failure.
	 */
	public function save_version( string $object_type, int $object_id, string $field, string $old_value, string $new_value, string $provider = '' ): int|false {
		global $wpdb;

		// Don't save if values are identical
		if ( $old_value === $new_value ) {
			return false;
		}

		$result = $wpdb->insert(
			$this->table,
			array(
				'object_type'          => sanitize_key( $object_type ),
				'object_id'            => absint( $object_id ),
				'field'                => sanitize_key( $field ),
				'old_value'            => $old_value,
				'new_value'            => $new_value,
				'translation_provider' => sanitize_text_field( $provider ),
				'user_id'              => get_current_user_id(),
				'created_at'           => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}
}















