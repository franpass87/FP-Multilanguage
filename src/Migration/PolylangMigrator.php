<?php
/**
 * Polylang Migration Wizard.
 *
 * Migrates translations from Polylang to FP-Multilanguage:
 * - Import existing translations
 * - Map Polylang configurations
 * - Validate migrated data
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Migration;

use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\MultiLanguage\LanguageManager;
use FP\Multilanguage\Logger;
use FP\Multilanguage\Migration\Polylang\PolylangChecker;
use FP\Multilanguage\Migration\Polylang\LanguageMapper;
use FP\Multilanguage\Migration\Polylang\PostMigrator;
use FP\Multilanguage\Migration\Polylang\TermMigrator;
use FP\Multilanguage\Migration\Polylang\PairCreator;
use FP\Multilanguage\Migration\Polylang\MigrationStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Polylang migration class.
 *
 * @since 0.10.0
 * @since 0.10.0 Refactored to use modular components.
 */
class PolylangMigrator {
	use ContainerAwareTrait;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Translation manager instance.
	 *
	 * @var TranslationManager
	 */
	protected $translation_manager;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Polylang checker.
	 *
	 * @since 0.10.0
	 *
	 * @var PolylangChecker
	 */
	protected PolylangChecker $checker;

	/**
	 * Language mapper.
	 *
	 * @since 0.10.0
	 *
	 * @var LanguageMapper
	 */
	protected LanguageMapper $language_mapper;

	/**
	 * Post migrator.
	 *
	 * @since 0.10.0
	 *
	 * @var PostMigrator
	 */
	protected PostMigrator $post_migrator;

	/**
	 * Term migrator.
	 *
	 * @since 0.10.0
	 *
	 * @var TermMigrator
	 */
	protected TermMigrator $term_migrator;

	/**
	 * Migration status manager.
	 *
	 * @since 0.10.0
	 *
	 * @var MigrationStatus
	 */
	protected MigrationStatus $status_manager;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		$this->translation_manager = fpml_get_translation_manager();
		$container = $this->getContainer();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : ( class_exists( '\FP\Multilanguage\Logger' ) ? fpml_get_logger() : null );

		// Initialize modules
		$this->checker = new PolylangChecker();
		$this->language_mapper = new LanguageMapper();
		$pair_creator = new PairCreator();
		$this->post_migrator = new PostMigrator( $this->language_mapper, $pair_creator );
		$this->term_migrator = new TermMigrator( $this->language_mapper, $pair_creator );
		$this->status_manager = new MigrationStatus();
	}

	/**
	 * Check if Polylang is installed.
	 *
	 * @return bool
	 */
	public function is_polylang_installed(): bool {
		return $this->checker->is_polylang_installed();
	}

	/**
	 * Check if Polylang data exists in database.
	 *
	 * @return bool
	 */
	public function has_polylang_data(): bool {
		return $this->checker->has_polylang_data();
	}

	/**
	 * Get migration status.
	 *
	 * @return array Migration status.
	 */
	public function get_migration_status(): array {
		return $this->status_manager->get_migration_status();
	}

	/**
	 * Start migration process.
	 *
	 * @param array $options Migration options.
	 * @return array Migration result.
	 */
	public function migrate( array $options = array() ): array {
		$default_options = array(
			'migrate_posts'   => true,
			'migrate_terms'   => true,
			'migrate_menus'   => false,
			'dry_run'         => false,
			'batch_size'      => 50,
		);

		$options = array_merge( $default_options, $options );

		// Check prerequisites
		if ( ! $this->checker->is_polylang_installed() ) {
			return array(
				'success' => false,
				'message' => __( 'Polylang non è installato.', 'fp-multilanguage' ),
			);
		}

		if ( ! $this->checker->has_polylang_data() ) {
			return array(
				'success' => false,
				'message' => __( 'Nessun dato Polylang trovato nel database.', 'fp-multilanguage' ),
			);
		}

		// Initialize migration status
		$status = $this->status_manager->initialize_status();

		try {
			// Map Polylang languages
			$language_map = $this->language_mapper->map_languages();

			if ( empty( $language_map ) ) {
				return array(
					'success' => false,
					'message' => __( 'Impossibile mappare le lingue Polylang.', 'fp-multilanguage' ),
				);
			}

			// Migrate posts
			if ( $options['migrate_posts'] ) {
				$posts_result = $this->post_migrator->migrate_posts( $language_map, $options );
				$status['posts_migrated'] = $posts_result['migrated'];
				$status['errors'] = array_merge( $status['errors'], $posts_result['errors'] );
			}

			// Migrate terms
			if ( $options['migrate_terms'] ) {
				$terms_result = $this->term_migrator->migrate_terms( $language_map, $options );
				$status['terms_migrated'] = $terms_result['migrated'];
				$status['errors'] = array_merge( $status['errors'], $terms_result['errors'] );
			}

			// Mark as completed
			$status['completed'] = true;
			$status['completed_at'] = current_time( 'mysql' );

			$this->status_manager->update_status( $status );

			if ( $this->logger ) {
				$this->logger->info( 'Polylang migration completed', array(
					'posts_migrated' => $status['posts_migrated'],
					'terms_migrated' => $status['terms_migrated'],
				) );
			}

			return array(
				'success'        => true,
				'message'        => sprintf(
					__( 'Migrazione completata: %d post e %d termini migrati.', 'fp-multilanguage' ),
					$status['posts_migrated'],
					$status['terms_migrated']
				),
				'status'         => $status,
			);

		} catch ( \Exception $e ) {
			$status['errors'][] = $e->getMessage();
			$this->status_manager->update_status( $status );

			if ( $this->logger ) {
				$this->logger->error( 'Polylang migration failed', array(
					'error' => $e->getMessage(),
				) );
			}

			return array(
				'success' => false,
				'message' => sprintf( __( 'Errore durante la migrazione: %s', 'fp-multilanguage' ), $e->getMessage() ),
				'status'  => $status,
			);
		}
	}
}
