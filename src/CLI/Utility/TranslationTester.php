<?php
/**
 * CLI Translation Tester - Tests translation for a single post.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\CLI\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tests translation for a single post.
 *
 * @since 0.10.0
 */
class TranslationTester {
	/**
	 * Test translation for a single post.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $post_id    Post ID to test translation for.
	 * @param string $target_lang Target language code. Default: en.
	 * @param bool   $dry_run    Show what would be translated without actually translating.
	 *
	 * @return void
	 */
	public function test_translation( int $post_id, string $target_lang = 'en', bool $dry_run = false ): void {
		$post = get_post( $post_id );

		if ( ! $post ) {
			\WP_CLI::error( sprintf( __( 'Post #%d non trovato.', 'fp-multilanguage' ), $post_id ) );
		}

		\WP_CLI::line( sprintf( __( 'Test traduzione per post #%d: %s', 'fp-multilanguage' ), $post_id, $post->post_title ) );

		$manager = fpml_get_translation_manager();

		if ( $dry_run ) {
			\WP_CLI::line( __( '[DRY RUN] Verifica traduzione esistente...', 'fp-multilanguage' ) );
			$translation_id = $manager->get_translation_id( $post_id, $target_lang );

			if ( $translation_id ) {
				\WP_CLI::success( sprintf( __( 'Traduzione già esistente: post #%d', 'fp-multilanguage' ), $translation_id ) );
			} else {
				\WP_CLI::line( __( 'Traduzione non esistente, verrebbe creata.', 'fp-multilanguage' ) );
			}
			return;
		}

		// Create or get translation
		$translation = $manager->ensure_post_translation( $post, $target_lang );

		if ( ! $translation ) {
			$translation = $manager->create_post_translation( $post, $target_lang, 'draft' );
		}

		if ( ! $translation ) {
			\WP_CLI::error( __( 'Impossibile creare traduzione.', 'fp-multilanguage' ) );
		}

		\WP_CLI::success( sprintf( __( 'Traduzione creata/verificata: post #%d', 'fp-multilanguage' ), $translation->ID ) );
	}
}
















