<?php
namespace FPMultilanguage\CLI;

use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use WP_CLI;

class Commands {

	private PostTranslationManager $manager;

	private TranslationService $service;

	private Logger $logger;

	public function __construct( PostTranslationManager $manager, TranslationService $service, Logger $logger ) {
		$this->manager = $manager;
		$this->service = $service;
		$this->logger  = $logger;
	}

	public function register(): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		WP_CLI::add_command( 'fp-multilanguage translate', array( $this, 'command_translate' ) );
	}

	public function command_translate( array $args, array $assocArgs ): void {
		if ( ! isset( $args[0] ) ) {
			WP_CLI::error( 'Specificare l\'ID del post.' );
		}

		$postId   = (int) $args[0];
		$language = isset( $assocArgs['language'] ) ? sanitize_key( $assocArgs['language'] ) : null;

		$translations = $this->manager->translate_post( $postId, $language, true );
		$this->logger->info(
			'Traduzioni aggiornate',
			array(
				'post_id'  => $postId,
				'language' => $language,
			)
		);

		WP_CLI::success( 'Traduzione completata. Linguaggi aggiornati: ' . implode( ', ', array_keys( $translations ) ) );
	}
}
