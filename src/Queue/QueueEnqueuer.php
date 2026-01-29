<?php
/**
 * Queue Enqueuer - Handles job enqueueing operations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles job enqueueing operations.
 *
 * @since 0.10.0
 */
class QueueEnqueuer {
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
	 * Enqueue or update a job.
	 *
	 * @since 0.2.0
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field identifier.
	 * @param string $hash_source Hash of the source payload.
	 *
	 * @return int Job ID.
	 */
	public function enqueue( $object_type, $object_id, $field, $hash_source ): int {
		global $wpdb;

		\FP\Multilanguage\Logger::debug( 'Queue enqueue called', array(
			'type' => $object_type,
			'id'   => $object_id,
			'field' => $field,
			'hash' => $hash_source,
		) );

		$object_type = sanitize_key( $object_type );
		$object_id   = absint( $object_id );
		$field       = sanitize_text_field( $field );
		$hash_source = sanitize_text_field( $hash_source );

		if ( empty( $object_type ) || empty( $object_id ) || empty( $field ) ) {
			\FP\Multilanguage\Logger::warning( 'Queue enqueue validation failed', array(
				'type' => $object_type,
				'id'   => $object_id,
				'field' => $field,
			) );
			return 0;
		}

		$table = $this->table;
		$now   = current_time( 'mysql', true );

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, hash_source, state FROM {$table} WHERE object_type = %s AND object_id = %d AND field = %s",
				$object_type,
				$object_id,
				$field
			)
		);

		\FP\Multilanguage\Logger::debug( 'Queue existing job check', array(
			'found' => (bool) $existing,
			'job_id' => $existing ? $existing->id : 0,
		) );

		if ( $existing ) {
			$data    = array(
				'hash_source' => $hash_source,
				'updated_at'  => $now,
			);
			$formats = array( '%s', '%s' );

			if ( $existing->hash_source !== $hash_source || 'done' !== $existing->state ) {
				$data['state']      = 'pending';
				$data['retries']    = 0;
				$data['last_error'] = '';
				$formats[]          = '%s';
				$formats[]          = '%d';
				$formats[]          = '%s';
			}

			$wpdb->update(
				$table,
				$data,
				array( 'id' => (int) $existing->id ),
				$formats,
				array( '%d' )
			);

			// Invalidate state counts cache if state changed
			if ( isset( $data['state'] ) && $data['state'] !== $existing->state ) {
				wp_cache_delete( 'fpml_queue_state_counts', 'fpml_queue' );
			}

			return (int) $existing->id;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'field'       => $field,
				'hash_source' => $hash_source,
				'state'       => 'pending',
				'retries'     => 0,
				'last_error'  => '',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			\FP\Multilanguage\Logger::error( 'Queue insert failed', array(
				'type' => $object_type,
				'id'   => $object_id,
				'field' => $field,
				'error' => $wpdb->last_error,
			) );
			return 0;
		}

		$insert_id = (int) $wpdb->insert_id;
		\FP\Multilanguage\Logger::debug( 'Queue job inserted', array(
			'job_id' => $insert_id,
			'type' => $object_type,
			'id'   => $object_id,
			'field' => $field,
		) );

		// Invalidate state counts cache
		wp_cache_delete( 'fpml_queue_state_counts', 'fpml_queue' );

		return $insert_id;
	}

	/**
	 * Enqueue a term translation job.
	 *
	 * @since 0.2.0
	 *
	 * @param \WP_Term $term Term object.
	 * @param string   $field Field identifier (name|description).
	 * @return int Job ID.
	 */
	public function enqueue_term( \WP_Term $term, string $field ): int {
		if ( ! ( $term instanceof \WP_Term ) ) {
			return 0;
		}

		$taxonomy = sanitize_key( $term->taxonomy );
		$field    = sanitize_key( $field );

		if ( '' === $taxonomy || '' === $field ) {
			return 0;
		}

		switch ( $field ) {
			case 'name':
				$value = $term->name;
				break;
			case 'description':
				$value = $term->description;
				break;
			default:
				$value = '';
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );
			if ( false === $encoded ) {
				// JSON encoding failed, use serialize as fallback
				$value = serialize( $value );
			} else {
				$value = $encoded;
			}
		}

		$value = (string) $value;
		$hash  = md5( $value );

		$field_identifier = $taxonomy . ':' . $field;

		return $this->enqueue( 'term', $term->term_id, $field_identifier, $hash );
	}

	/**
	 * Enqueue a menu item label translation job.
	 *
	 * @since 0.3.0
	 *
	 * @param \WP_Post $item Menu item post.
	 * @return int Job ID.
	 */
	public function enqueue_menu_item_label( \WP_Post $item ): int {
		if ( ! ( $item instanceof \WP_Post ) ) {
			return 0;
		}

		$label = get_post_meta( $item->ID, '_menu_item_title', true );

		if ( '' === $label ) {
			$label = (string) $item->post_title;
		}

		$label = (string) $label;

		if ( '' === trim( $label ) ) {
			return 0;
		}

		return $this->enqueue( 'menu', $item->ID, 'title', md5( $label ) );
	}
}
















