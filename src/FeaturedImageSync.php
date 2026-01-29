<?php
/**
 * Sincronizzazione automatica featured images.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce sync automatico featured images tra originali e traduzioni.
 *
 * @since 0.4.0
 */
class FeaturedImageSync {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Featured_Image_Sync|null
	 */
	protected static $instance = null;

	/**
	 * Logger reference.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Settings reference.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return \FPML_Featured_Image_Sync
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$container = $this->getContainer();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : \FPML_fpml_get_logger();
		$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();

		// Sync automatico al save post.
		add_action( '\FPML_post_jobs_enqueued', array( $this, 'sync_featured_image' ), 10, 3 );

		// Sync quando featured image cambia.
		add_action( 'updated_post_meta', array( $this, 'on_thumbnail_updated' ), 10, 4 );
	}

	/**
	 * Sync featured image automatico.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $source_post Post sorgente.
	 * @param WP_Post $target_post Post tradotto.
	 * @param bool    $update      Se è update.
	 *
	 * @return void
	 */
	public function sync_featured_image( $source_post, $target_post, $update ) {
		// Controlla se sync è abilitato.
		if ( ! $this->settings || ! $this->settings->get( 'sync_featured_images', true ) ) {
			return;
		}

		$source_thumb_id = get_post_thumbnail_id( $source_post->ID );

		if ( ! $source_thumb_id ) {
			// Nessuna featured image sull'originale.
			// Rimuovi anche dalla traduzione se esiste.
			$target_thumb_id = get_post_thumbnail_id( $target_post->ID );
			if ( $target_thumb_id ) {
				delete_post_thumbnail( $target_post->ID );
				$this->logger->log(
					'debug',
					sprintf( 'Featured image rimossa da traduzione #%d', $target_post->ID ),
					array( 'target_post' => $target_post->ID )
				);
			}
			return;
		}

		// Ottieni o crea traduzione attachment.
		$target_thumb_id = $this->get_or_create_image_translation( $source_thumb_id );

		if ( $target_thumb_id ) {
			set_post_thumbnail( $target_post->ID, $target_thumb_id );

			$this->logger->log(
				'debug',
				sprintf( 'Featured image sincronizzata per post #%d → #%d', $source_post->ID, $target_post->ID ),
				array(
					'source_post'      => $source_post->ID,
					'target_post'      => $target_post->ID,
					'source_thumb'     => $source_thumb_id,
					'target_thumb'     => $target_thumb_id,
				)
			);
		}
	}

	/**
	 * Hook quando thumbnail viene aggiornato.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function on_thumbnail_updated( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( '_thumbnail_id' !== $meta_key ) {
			return;
		}

		// Controlla se è un post sorgente.
		if ( get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
			return; // È una traduzione, skip.
		}

		// Ottieni traduzione.
		$target_id = get_post_meta( $post_id, '_fpml_pair_id', true );
		if ( ! $target_id ) {
			return;
		}

		// Sync immediato.
		$source_post = get_post( $post_id );
		$target_post = get_post( $target_id );

		if ( $source_post && $target_post ) {
			$this->sync_featured_image( $source_post, $target_post, true );
		}
	}

	/**
	 * Ottiene o crea traduzione di un attachment.
	 *
	 * @since 0.4.0
	 *
	 * @param int $source_attachment_id ID attachment sorgente.
	 *
	 * @return int|false ID attachment tradotto o false.
	 */
	protected function get_or_create_image_translation( $source_attachment_id ) {
		// Controlla se esiste già traduzione.
		$existing_translation = get_post_meta( $source_attachment_id, '_fpml_pair_id', true );

		if ( $existing_translation ) {
			$attachment = get_post( $existing_translation );
			if ( $attachment && 'attachment' === $attachment->post_type ) {
				return (int) $existing_translation;
			}
		}

		// Modalità sync: usa stessa immagine o duplica?
		$duplicate_mode = $this->settings ? $this->settings->get( 'duplicate_featured_images', false ) : false;

		if ( ! $duplicate_mode ) {
			// Usa stessa immagine (modalità riferimento).
			update_post_meta( $source_attachment_id, '_fpml_pair_id', $source_attachment_id );
			return $source_attachment_id;
		}

		// Modalità duplicazione: crea copia attachment.
		$source_attachment = get_post( $source_attachment_id );
		if ( ! $source_attachment ) {
			return false;
		}

		// Duplica attachment.
		$file_path = get_attached_file( $source_attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		// Copia file.
		$upload_dir = wp_upload_dir();
		$filename   = basename( $file_path );
		$new_file   = $upload_dir['path'] . '/en-' . $filename;

		if ( ! copy( $file_path, $new_file ) ) {
			return false;
		}

		// Crea attachment.
		$wp_filetype = wp_check_filetype( $filename, null );

		$attachment_data = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => $source_attachment->post_title . ' (EN)',
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment_data, $new_file );

		if ( is_wp_error( $attach_id ) ) {
			return false;
		}

		// Genera metadata.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $new_file );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		// Link attachment.
		update_post_meta( $source_attachment_id, '_fpml_pair_id', $attach_id );
		update_post_meta( $attach_id, '_fpml_is_translation', 1 );
		update_post_meta( $attach_id, '_fpml_pair_source_id', $source_attachment_id );

		// Traduci alt text se presente.
		$source_alt = get_post_meta( $source_attachment_id, '_wp_attachment_image_alt', true );
		if ( $source_alt ) {
			// Il sistema tradurrà automaticamente tramite meta whitelist.
			update_post_meta( $attach_id, '_wp_attachment_image_alt', $source_alt );
		}

		$this->logger->log(
			'info',
			sprintf( 'Featured image duplicata: #%d → #%d', $source_attachment_id, $attach_id ),
			array(
				'source' => $source_attachment_id,
				'target' => $attach_id,
			)
		);

		return $attach_id;
	}

	/**
	 * Sync batch di immagini esistenti.
	 *
	 * @since 0.4.0
	 *
	 * @return array Summary.
	 */
	public function bulk_sync() {
		$summary = array(
			'scanned' => 0,
			'synced'  => 0,
		);

		// Ottieni tutti i post tradotti.
		$translations = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_fpml_is_translation',
				'meta_value'     => 1,
				'fields'         => 'ids',
			)
		);

		foreach ( $translations as $target_id ) {
			$source_id = get_post_meta( $target_id, '_fpml_pair_source_id', true );
			if ( ! $source_id ) {
				continue;
			}

			$summary['scanned']++;

			$source_post = get_post( $source_id );
			$target_post = get_post( $target_id );

			if ( $source_post && $target_post ) {
				$this->sync_featured_image( $source_post, $target_post, true );
				$summary['synced']++;
			}
		}

		return $summary;
	}
}

