<?php
/**
 * Export Import Sandbox Manager - Manages sandbox previews.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\ExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages sandbox previews.
 *
 * @since 0.10.0
 */
class SandboxManager {
	/**
	 * Option key for sandbox previews storage.
	 */
	const SANDBOX_OPTION = '\FPML_sandbox_previews';

	/**
	 * Text cleaner instance.
	 *
	 * @var TextCleaner
	 */
	protected TextCleaner $text_cleaner;

	/**
	 * Constructor.
	 *
	 * @param TextCleaner $text_cleaner Text cleaner instance.
	 */
	public function __construct( TextCleaner $text_cleaner ) {
		$this->text_cleaner = $text_cleaner;
	}

	/**
	 * Retrieve stored sandbox previews.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_sandbox_previews(): array {
		$previews = get_option( self::SANDBOX_OPTION, array() );

		if ( ! is_array( $previews ) ) {
			return array();
		}

		return $previews;
	}

	/**
	 * Record a sandbox preview entry.
	 *
	 * @since 0.10.0
	 *
	 * @param array $data Preview data.
	 *
	 * @return void
	 */
	public function record_sandbox_preview( array $data ): void {
		$entry = array(
			'timestamp'         => current_time( 'mysql', true ),
			'object_type'       => isset( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : 'post',
			'object_id'         => isset( $data['object_id'] ) ? absint( $data['object_id'] ) : 0,
			'field'             => isset( $data['field'] ) ? sanitize_text_field( $data['field'] ) : '',
			'characters'        => isset( $data['characters'] ) ? absint( $data['characters'] ) : 0,
			'word_count'        => isset( $data['word_count'] ) ? absint( $data['word_count'] ) : 0,
			'estimated_cost'    => isset( $data['estimated_cost'] ) ? (float) $data['estimated_cost'] : 0.0,
			'source_excerpt'    => isset( $data['source_excerpt'] ) ? $this->text_cleaner->clean_preview_text( $data['source_excerpt'] ) : '',
			'translated_excerpt'=> isset( $data['translated_excerpt'] ) ? $this->text_cleaner->clean_preview_text( $data['translated_excerpt'] ) : '',
			'job_id'            => isset( $data['job_id'] ) ? absint( $data['job_id'] ) : 0,
			'provider'          => isset( $data['provider'] ) ? sanitize_text_field( $data['provider'] ) : '',
			'source_url'        => isset( $data['source_url'] ) ? esc_url_raw( $data['source_url'] ) : '',
			'translation_url'   => isset( $data['translation_url'] ) ? esc_url_raw( $data['translation_url'] ) : '',
		);

		$previews = $this->get_sandbox_previews();
		array_unshift( $previews, $entry );

		if ( count( $previews ) > 20 ) {
			$previews = array_slice( $previews, 0, 20 );
		}

		update_option( self::SANDBOX_OPTION, $previews, false );
	}

	/**
	 * Clear sandbox previews.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function clear_sandbox_previews(): void {
		delete_option( self::SANDBOX_OPTION );
	}
}
















