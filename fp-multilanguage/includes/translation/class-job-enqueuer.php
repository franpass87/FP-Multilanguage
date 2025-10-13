<?php
/**
 * Job Enqueuer - Manages enqueueing of translation jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles enqueueing translation jobs for posts and terms.
 *
 * @since 0.4.0
 */
class FPML_Job_Enqueuer {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Job_Enqueuer|null
	 */
	protected static $instance = null;

	/**
	 * Queue handler.
	 *
	 * @var FPML_Queue
	 */
	protected $queue;

	/**
	 * Settings instance.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->queue    = FPML_Container::get( 'queue' ) ?: FPML_Queue::instance();
		$this->settings = FPML_Container::get( 'settings' ) ?: FPML_Settings::instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Job_Enqueuer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enqueue translation jobs for a pair of posts.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $source_post Italian source post.
	 * @param WP_Post $target_post English counterpart.
	 * @param bool    $update      Whether this is an update.
	 *
	 * @return void
	 */
	public function enqueue_post_jobs( $source_post, $target_post, $update ) {
		$fields = array( 'post_title', 'post_excerpt', 'post_content' );

		foreach ( $fields as $field ) {
			$hash = $this->hash_value( $this->get_post_field_value( $source_post, $field ) );

			if ( ! $hash ) {
				continue;
			}

			$this->queue->enqueue( 'post', $source_post->ID, $field, $hash );
			$this->update_post_status_flag( $target_post->ID, $field, 'needs_update' );
		}

		$meta_keys = $this->get_meta_whitelist();

		foreach ( $meta_keys as $meta_key ) {
			if ( '' === $meta_key ) {
				continue;
			}

			$value = get_post_meta( $source_post->ID, $meta_key, true );
			$hash  = $this->hash_value( $value );

			if ( ! $hash ) {
				continue;
			}

			$field_key = 'meta:' . $meta_key;

			$this->queue->enqueue( 'post', $source_post->ID, $field_key, $hash );
			$this->update_post_status_flag( $target_post->ID, $field_key, 'needs_update' );
		}

		/**
		 * Allow third parties to react when jobs are enqueued.
		 *
		 * @since 0.2.0
		 *
		 * @param WP_Post $source_post Source post.
		 * @param WP_Post $target_post Target post.
		 * @param bool    $update      Whether the post is being updated.
		 */
		do_action( 'fpml_post_jobs_enqueued', $source_post, $target_post, $update );
	}

	/**
	 * Enqueue translation jobs for a term.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Term $term        Source term.
	 * @param WP_Term $target_term Target term.
	 *
	 * @return void
	 */
	public function enqueue_term_jobs( $term, $target_term ) {
		$this->queue->enqueue_term( $term, 'name' );
		$this->queue->enqueue_term( $term, 'description' );

		update_term_meta( $target_term->term_id, '_fpml_status_name', 'needs_update' );
		update_term_meta( $target_term->term_id, '_fpml_status_description', 'needs_update' );
	}

	/**
	 * Retrieve a value for hashing from a post field.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post  Post object.
	 * @param string  $field Field name.
	 *
	 * @return string
	 */
	protected function get_post_field_value( $post, $field ) {
		switch ( $field ) {
			case 'post_title':
				return (string) $post->post_title;
			case 'post_excerpt':
				return (string) $post->post_excerpt;
			case 'post_content':
				return (string) $post->post_content;
		}

		return '';
	}

	/**
	 * Parse whitelist meta keys.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	protected function get_meta_whitelist() {
		$raw = $this->settings ? $this->settings->get( 'meta_whitelist', '' ) : '';

		if ( ! is_string( $raw ) ) {
			return array();
		}

	$parts = preg_split( '/[\n,]+/', $raw );
	if ( false === $parts ) {
		$parts = array( $raw );
	}
	$parts = array_map( 'trim', $parts );
	$parts = array_filter( $parts );

	$sanitized = array();

	foreach ( $parts as $key ) {
		$key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key );
		
		// Handle PCRE error
		if ( null === $key || false === $key ) {
			continue;
		}

		if ( '' !== $key ) {
			$sanitized[] = $key;
		}
	}

		/**
		 * Allow other components to extend the meta whitelist.
		 *
		 * @since 0.2.0
		 *
		 * @param array $sanitized Current whitelist.
		 */
		$sanitized = apply_filters( 'fpml_meta_whitelist', array_unique( $sanitized ) );

		$required_keys = array(
			'_wp_attachment_image_alt',
			'_product_attributes',
		);

		foreach ( $required_keys as $required_key ) {
			if ( '' === $required_key ) {
				continue;
			}

			if ( ! in_array( $required_key, $sanitized, true ) ) {
				$sanitized[] = $required_key;
			}
		}

		return array_values( array_unique( array_filter( $sanitized ) ) );
	}

	/**
	 * Generate a normalized hash for queue purposes.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $value Value to hash.
	 *
	 * @return string
	 */
	protected function hash_value( $value ) {
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

		if ( '' === $value ) {
			return md5( '' );
		}

		return md5( $value );
	}

	/**
	 * Update translation status flag on the translated post.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $post_id Target post ID.
	 * @param string $field   Field identifier.
	 * @param string $status  Status slug.
	 *
	 * @return void
	 */
	protected function update_post_status_flag( $post_id, $field, $status ) {
		$meta_key = '_fpml_status_' . sanitize_key( str_replace( ':', '_', $field ) );

		update_post_meta( $post_id, $meta_key, sanitize_key( $status ) );
	}
}
