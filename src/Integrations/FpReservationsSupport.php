<?php
/**
 * FP Restaurant Reservations Integration.
 *
 * Provides bidirectional integration between FP-Multilanguage and FP-Restaurant-Reservations:
 * - Translate fp_event post type (title, content, excerpt)
 * - Translate fp_event_category taxonomy (terms names and descriptions)
 * - Sync Event meta fields (location is translatable, dates/prices/capacity/currency are copied)
 * - Ensure fp_event post type and fp_event_category taxonomy are translatable
 *
 * @package FP_Multilanguage
 * @since 0.9.1
 */

namespace FP\Multilanguage\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FP Restaurant Reservations integration class.
 *
 * @since 0.9.1
 */
class FpReservationsSupport {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * FP Reservations Event meta keys.
	 */
	const FP_EVENT_START    = '_fp_event_start';
	const FP_EVENT_END      = '_fp_event_end';
	const FP_EVENT_CAPACITY = '_fp_event_capacity';
	const FP_EVENT_PRICE    = '_fp_event_price';
	const FP_EVENT_CURRENCY = '_fp_event_currency';
	const FP_EVENT_LOCATION = '_fp_event_location'; // TRANSLATABLE

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		// Only if FP-Restaurant-Reservations is active
		if ( ! $this->is_fp_reservations_active() ) {
			return;
		}

		// Add fp_event post type to translatable post types
		add_filter( '\FPML_translatable_post_types', array( $this, 'add_fp_event_post_type' ) );

		// Add fp_event_category taxonomy to translatable taxonomies
		add_filter( '\FPML_translatable_taxonomies', array( $this, 'add_fp_event_category_taxonomy' ) );

		// Add FP-Reservations meta to translatable whitelist
		add_filter( '\FPML_meta_whitelist', array( $this, 'add_fp_reservations_meta_to_whitelist' ) );

		// Sync event meta after translation
		add_action( 'fpml_after_translation_saved', array( $this, 'sync_event_meta_to_translation' ), 10, 2 );
	}

	/**
	 * Add fp_event post type to translatable post types.
	 *
	 * @param array $post_types Current translatable post types.
	 * @return array Extended post types.
	 */
	public function add_fp_event_post_type( $post_types ) {
		if ( ! in_array( 'fp_event', $post_types, true ) ) {
			$post_types[] = 'fp_event';
		}
		return $post_types;
	}

	/**
	 * Add fp_event_category taxonomy to translatable taxonomies.
	 *
	 * @param array $taxonomies Current translatable taxonomies.
	 * @return array Extended taxonomies.
	 */
	public function add_fp_event_category_taxonomy( $taxonomies ) {
		if ( ! in_array( 'fp_event_category', $taxonomies, true ) ) {
			$taxonomies[] = 'fp_event_category';
		}
		return $taxonomies;
	}

	/**
	 * Add FP-Reservations meta keys to translatable whitelist.
	 *
	 * @param array $whitelist Current meta whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_fp_reservations_meta_to_whitelist( $whitelist ) {
		$fp_resv_meta = array(
			// Event location is translatable
			self::FP_EVENT_LOCATION,
		);

		return array_merge( $whitelist, $fp_resv_meta );
	}

	/**
	 * Check if FP-Restaurant-Reservations is active.
	 *
	 * @return bool
	 */
	protected function is_fp_reservations_active() {
		return class_exists( '\FP\Resv\Core\Plugin' ) || 
		       class_exists( '\FP\Restaurant\Reservations' ) || 
		       defined( 'FP_RESTAURANT_VERSION' ) ||
		       defined( 'FP_RESV_VERSION' );
	}

	/**
	 * Sync event meta from original to translated post.
	 *
	 * @param int $translated_id Translated post ID.
	 * @param int $original_id   Original post ID.
	 */
	public function sync_event_meta_to_translation( $translated_id, $original_id ) {
		if ( ! $translated_id || ! $original_id ) {
			return;
		}

		// Only sync for fp_event post type
		$original_post = get_post( $original_id );
		if ( ! $original_post || 'fp_event' !== $original_post->post_type ) {
			return;
		}

		$synced_count = 0;

		// 1. EVENT LOCATION - TRANSLATE
		$original_location = get_post_meta( $original_id, self::FP_EVENT_LOCATION, true );
		$translated_location = get_post_meta( $translated_id, self::FP_EVENT_LOCATION, true );
		
		if ( empty( $translated_location ) && ! empty( $original_location ) ) {
			// Copy original value first
			update_post_meta( $translated_id, self::FP_EVENT_LOCATION, $original_location );
			// Enqueue for translation
			$this->enqueue_reservations_meta_translation( $translated_id, self::FP_EVENT_LOCATION, $original_location, $original_id );
			$synced_count++;
		}

		// 2. EVENT DATES - COPY (same for all languages)
		$start = get_post_meta( $original_id, self::FP_EVENT_START, true );
		if ( ! empty( $start ) ) {
			update_post_meta( $translated_id, self::FP_EVENT_START, $start );
			$synced_count++;
		}

		$end = get_post_meta( $original_id, self::FP_EVENT_END, true );
		if ( ! empty( $end ) ) {
			update_post_meta( $translated_id, self::FP_EVENT_END, $end );
			$synced_count++;
		}

		// 3. EVENT CAPACITY - COPY (number, include 0)
		$capacity = get_post_meta( $original_id, self::FP_EVENT_CAPACITY, true );
		if ( ! empty( $capacity ) || ( is_numeric( $capacity ) && 0 === (int) $capacity ) ) {
			update_post_meta( $translated_id, self::FP_EVENT_CAPACITY, $capacity );
			$synced_count++;
		}

		// 4. EVENT PRICE - COPY (number, include 0)
		$price = get_post_meta( $original_id, self::FP_EVENT_PRICE, true );
		if ( ! empty( $price ) || ( is_numeric( $price ) && 0 === (float) $price ) ) {
			update_post_meta( $translated_id, self::FP_EVENT_PRICE, $price );
			$synced_count++;
		}

		// 5. EVENT CURRENCY - COPY (code)
		$currency = get_post_meta( $original_id, self::FP_EVENT_CURRENCY, true );
		if ( ! empty( $currency ) ) {
			update_post_meta( $translated_id, self::FP_EVENT_CURRENCY, $currency );
			$synced_count++;
		}

		/**
		 * Fires after event meta sync.
		 *
		 * @param int $translated_id Translated post ID.
		 * @param int $original_id   Original post ID.
		 * @param int $synced_count  Number of meta fields synced.
		 */
		do_action( 'fpml_reservations_meta_synced', $translated_id, $original_id, $synced_count );

		$this->log_sync( $translated_id, "Event meta sync completed: {$synced_count} meta fields" );
	}

	/**
	 * Enqueue Reservations meta field for translation.
	 *
	 * @param int    $translated_id Translated post ID (TARGET post).
	 * @param string $meta_key      Meta key to translate.
	 * @param mixed  $value         Original value to translate.
	 * @param int    $original_id   Optional. Original post ID (SOURCE). If not provided, will be retrieved from meta.
	 */
	protected function enqueue_reservations_meta_translation( $translated_id, $meta_key, $value, $original_id = null ) {
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
	 */
	protected function log_sync( $post_id, $message ) {
		if ( class_exists( '\FP\Multilanguage\Logger' ) ) {
			fpml_get_logger()->log(
				'info',
				'FP-Reservations Integration: ' . $message,
				array(
					'post_id' => $post_id,
					'context' => 'reservations_sync',
				)
			);
		}
	}
}

