<?php
/**
 * Timeout Manager - Manages PHP execution timeout extension.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Content\Indexer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages PHP execution timeout extension for long-running operations.
 *
 * @since 0.10.0
 */
class TimeoutManager {
	/**
	 * Estende il timeout di esecuzione PHP se necessario.
	 *
	 * Controlla se siamo vicini al limite di tempo di esecuzione e lo estende
	 * per evitare che il reindex vada in timeout su grandi dataset.
	 *
	 * @since 0.4.3
	 *
	 * @param int $start_time Timestamp di inizio dell'operazione.
	 *
	 * @return void
	 */
	public function maybe_extend_timeout( $start_time ) {
		// Verifica se set_time_limit è disponibile
		if ( ! function_exists( 'set_time_limit' ) || false !== strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			return;
		}

		$max_execution_time = (int) ini_get( 'max_execution_time' );
		
		// Se il timeout è 0 (illimitato), non serve fare nulla
		if ( 0 === $max_execution_time ) {
			return;
		}

		$elapsed_time = time() - $start_time;
		$remaining_time = $max_execution_time - $elapsed_time;

		// Se rimangono meno di 60 secondi, estendi il timeout di altri 5 minuti
		if ( $remaining_time < 60 ) {
			@set_time_limit( 300 );
		}
	}
}















