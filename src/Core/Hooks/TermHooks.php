<?php
/**
 * Term Hooks Handler.
 *
 * Handles all WordPress hooks related to taxonomy terms.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages all term-related WordPress hooks.
 *
 * @since 1.0.0
 */
class TermHooks extends BaseHookHandler {
	/**
	 * Register term hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Only register if not in assisted mode
		if ( ! $this->shouldRegister() ) {
			return;
		}

		add_action( 'created_term', array( $this, 'handle_created_term' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'handle_edited_term' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'handle_delete_term' ), 10, 3 );
	}

	/**
	 * Handle created_term hook.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public function handle_created_term( $term_id, $tt_id, $taxonomy ) {
		$this->delegateWithFallback( 'content.term_handler', 'handle_created_term', $term_id, $tt_id, $taxonomy );
	}

	/**
	 * Handle edited_term hook.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public function handle_edited_term( $term_id, $tt_id, $taxonomy ) {
		$this->delegateWithFallback( 'content.term_handler', 'handle_edited_term', $term_id, $tt_id, $taxonomy );
	}

	/**
	 * Handle delete_term hook.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public function handle_delete_term( $term_id, $tt_id, $taxonomy ) {
		$this->delegateWithFallback( 'content.term_handler', 'handle_delete_term', $term_id, $tt_id, $taxonomy );
	}
}

