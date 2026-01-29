<?php
/**
 * Cost Estimator - Calculate translation costs for queued jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */


namespace FP\Multilanguage\Diagnostics;

use FP\Multilanguage\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estimates characters, words and cost for jobs in the queue.
 *
 * @since 0.4.0
 */
class CostEstimator {
	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Cost_Estimator|null
	 */
	protected static $instance = null;

	/**
	 * Queue instance.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 * @since 1.0.0 Now public to support dependency injection
	 *
	 * @param \FPML_Queue|null $queue Optional queue instance for DI.
	 */
	public function __construct( $queue = null ) {
		// Use injected queue or get from container/singleton
		if ( null === $queue ) {
			$this->queue = Container::get( 'queue' ) ?: fpml_get_queue();
		} else {
			$this->queue = $queue;
		}
	}

	/**
	 * Retrieve singleton instance (for backward compatibility).
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Use dependency injection via container instead
	 *
	 * @return \FPML_Cost_Estimator
	 */
	public static function instance() {
		_doing_it_wrong( 
			'FP\Multilanguage\Diagnostics\CostEstimator::instance()', 
			'CostEstimator::instance() is deprecated. Use dependency injection via container instead.', 
			'1.0.0' 
		);
		
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Estimate characters, words and cost for jobs in the queue.
	 *
	 * @since 0.4.0
	 *
	 * @param array<string>|null $states   Queue states to inspect.
	 * @param int                $max_jobs Maximum number of jobs to analyse.
	 *
	 * @return array<string,float|int>|WP_Error
	 */
	public function estimate( $states = null, $max_jobs = 500 ) {
		$processor  = \FPML_fpml_get_processor();
		$translator = $processor->get_translator_instance();

		if ( is_wp_error( $translator ) ) {
			return $translator;
		}

		if ( null === $states ) {
			$states = array( 'pending', 'outdated', 'translating' );
		}

		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );

		if ( empty( $states ) ) {
			return array(
				'characters'     => 0,
				'estimated_cost' => 0.0,
				'jobs_scanned'   => 0,
				'word_count'     => 0,
			);
		}

		$max_jobs = max( 1, absint( $max_jobs ) );

		$batch_limit = (int) apply_filters( '\FPML_estimate_batch_size', 100 );
		$characters  = 0;
		$cost        = 0.0;
		$word_count  = 0;
		$offset      = 0;
		$scanned     = 0;

		while ( $scanned < $max_jobs ) {
			$limit = min( $batch_limit, $max_jobs - $scanned );
			$jobs  = $this->queue->get_jobs_for_states( $states, $limit, $offset );

			if ( empty( $jobs ) ) {
				break;
			}

			foreach ( $jobs as $job ) {
				$text = $this->get_queue_job_text( $job );

				if ( '' === trim( (string) $text ) ) {
					continue;
				}

				$length      = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
				$characters += $length;
				$cost       += (float) $translator->estimate_cost( $text );

				$words = trim( wp_strip_all_tags( $text ) );
				if ( '' !== $words ) {
					$word_count += count( preg_split( '/\s+/u', $words ) );
				}
			}

			$count   = count( $jobs );
			$scanned += $count;
			$offset  += $count;

			if ( $count < $limit ) {
				break;
			}
		}

		return array(
			'characters'     => (int) $characters,
			'estimated_cost' => (float) $cost,
			'jobs_scanned'   => (int) $scanned,
			'word_count'     => (int) $word_count,
		);
	}

	/**
	 * Retrieve the raw text associated with a queue job.
	 *
	 * @since 0.4.0
	 *
	 * @param object $job Queue job entry.
	 *
	 * @return string
	 */
	public function get_queue_job_text( $job ) {
		if ( ! is_object( $job ) || empty( $job->object_type ) ) {
			return '';
		}

		$field = isset( $job->field ) ? (string) $job->field : '';

		switch ( $job->object_type ) {
			case 'post':
				$post = get_post( isset( $job->object_id ) ? (int) $job->object_id : 0 );

				if ( ! $post instanceof WP_Post ) {
					return '';
				}

				if ( 0 === strpos( $field, 'meta:' ) ) {
					$meta_key = substr( $field, 5 );
					$value    = get_post_meta( $post->ID, $meta_key, true );

					if ( is_array( $value ) || is_object( $value ) ) {
						$value = wp_json_encode( $value );
					}

					return (string) $value;
				}

				switch ( $field ) {
					case 'post_title':
						return (string) $post->post_title;
					case 'post_excerpt':
						return (string) $post->post_excerpt;
					case 'post_content':
						return (string) $post->post_content;
				}

				break;

			case 'term':
				$object_id = isset( $job->object_id ) ? (int) $job->object_id : 0;
				list( $taxonomy, $term_field ) = array_pad( explode( ':', $field, 2 ), 2, '' );
				$taxonomy = sanitize_key( $taxonomy );

				if ( '' === $taxonomy ) {
					break;
				}

				$term = get_term( $object_id, $taxonomy );

				if ( $term instanceof WP_Term ) {
					switch ( $term_field ) {
						case 'name':
							return (string) $term->name;
						case 'description':
							return (string) $term->description;
					}
				}

				break;

			case 'menu':
				$item = get_post( isset( $job->object_id ) ? (int) $job->object_id : 0 );

				if ( ! $item instanceof WP_Post ) {
					return '';
				}

				$label = get_post_meta( $item->ID, '_menu_item_title', true );

				if ( '' === $label ) {
					$label = (string) $item->post_title;
				}

				return (string) $label;
		}

		return '';
	}
}



