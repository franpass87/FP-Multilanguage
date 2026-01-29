<?php
/**
 * Auto-relink automatico dei link interni nelle traduzioni.
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
 * Gestisce relink automatico URL interni IT → EN.
 *
 * @since 0.4.0
 */
class AutoRelink {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Auto_Relink|null
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
	 * Cache dei mapping URL.
	 *
	 * @var array
	 */
	protected $url_map_cache = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return \FPML_Auto_Relink
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

		// Hook dopo traduzione post.
		add_action( '\FPML_post_translated', array( $this, 'relink_content' ), 30, 4 );

		// Hook per processare contenuto prima del save.
		add_filter( '\FPML_pre_save_translation', array( $this, 'process_links' ), 10, 3 );
	}

	/**
	 * Relink automatico dopo traduzione.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 * @param string  $field       Campo tradotto.
	 * @param string  $value       Valore tradotto.
	 * @param object  $job         Job della coda.
	 *
	 * @return void
	 */
	public function relink_content( $target_post, $field, $value, $job ) {
		// Solo per contenuti (non meta).
		if ( ! in_array( $field, array( 'post_content', 'post_excerpt' ), true ) ) {
			return;
		}

		// Controlla se relink è abilitato.
		if ( ! $this->settings || ! $this->settings->get( 'enable_auto_relink', true ) ) {
			return;
		}

		// Processa link nel contenuto.
		$relinked_content = $this->process_links( $value, $target_post, $field );

		if ( $relinked_content !== $value ) {
			// Aggiorna contenuto con link corretti.
			\fpml_safe_update_post(
				array(
					'ID'           => $target_post->ID,
					$field         => $relinked_content,
				)
			);

			$this->logger->log(
				'info',
				sprintf( 'Auto-relink completato per post #%d', $target_post->ID ),
				array(
					'post_id'      => $target_post->ID,
					'field'        => $field,
					'links_updated' => substr_count( $value, 'href=' ) - substr_count( $relinked_content, 'href=' ),
				)
			);
		}
	}

	/**
	 * Processa link nel contenuto.
	 *
	 * @since 0.4.0
	 *
	 * @param string  $content Contenuto con link.
	 * @param WP_Post $post    Post object.
	 * @param string  $field   Campo.
	 *
	 * @return string
	 */
	public function process_links( $content, $post, $field ) {
		if ( ! is_string( $content ) || empty( $content ) ) {
			return $content;
		}

		$site_url = get_site_url();

		// Pattern per trovare link interni.
		$pattern = '/<a\s+([^>]*href=(["\'])(' . preg_quote( $site_url, '/' ) . '[^"\']*)\2[^>]*)>/i';

		$relinked = preg_replace_callback(
			$pattern,
			array( $this, 'replace_internal_link' ),
			$content
		);

		return $relinked ? $relinked : $content;
	}

	/**
	 * Callback per sostituire singolo link.
	 *
	 * @since 0.4.0
	 *
	 * @param array $matches Matches regex.
	 *
	 * @return string
	 */
	protected function replace_internal_link( $matches ) {
		$full_tag = $matches[0];
		$url      = $matches[3];

		// Ottieni ID post dal URL.
		$post_id = url_to_postid( $url );

		if ( ! $post_id ) {
			// Non è un post, potrebbe essere term/archivio.
			$translated_url = $this->translate_taxonomy_url( $url );
			if ( $translated_url && $translated_url !== $url ) {
				return str_replace( $url, $translated_url, $full_tag );
			}

			return $full_tag; // Non modificare.
		}

		// Controlla se esiste traduzione.
		$translation_id = get_post_meta( $post_id, '_fpml_pair_id', true );

		if ( ! $translation_id ) {
			return $full_tag; // Nessuna traduzione disponibile.
		}

		// Ottieni URL tradotto.
		$translated_url = get_permalink( $translation_id );

		if ( ! $translated_url || is_wp_error( $translated_url ) ) {
			return $full_tag;
		}

		// Sostituisci URL.
		$relinked = str_replace( $url, $translated_url, $full_tag );

		$this->logger->log(
			'debug',
			'Link relinked',
			array(
				'original' => $url,
				'translated' => $translated_url,
			)
		);

		return $relinked;
	}

	/**
	 * Traduci URL tassonomia.
	 *
	 * @since 0.4.0
	 *
	 * @param string $url URL originale.
	 *
	 * @return string|false
	 */
	protected function translate_taxonomy_url( $url ) {
		// Cache check.
		if ( isset( $this->url_map_cache[ $url ] ) ) {
			return $this->url_map_cache[ $url ];
		}

		// Prova a estrarre taxonomy e term slug dall'URL.
		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['path'] ) ) {
			return false;
		}

		$path = trim( $parsed['path'], '/' );
		$parts = explode( '/', $path );

		// Cerca pattern /category/term-slug/ o /tag/term-slug/.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			$rewrite = $taxonomy->rewrite;
			if ( ! $rewrite || empty( $rewrite['slug'] ) ) {
				continue;
			}

			$tax_slug = $rewrite['slug'];

			// Cerca nel path.
			$key = array_search( $tax_slug, $parts, true );
			if ( false !== $key && isset( $parts[ $key + 1 ] ) ) {
				$term_slug = $parts[ $key + 1 ];

				// Ottieni term.
				$term = get_term_by( 'slug', $term_slug, $taxonomy->name );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}

				// Controlla se ha traduzione.
				$translation_id = get_term_meta( $term->term_id, '_fpml_pair_id', true );
				if ( ! $translation_id ) {
					continue;
				}

				$translated_term = get_term( $translation_id, $taxonomy->name );
				if ( ! $translated_term || is_wp_error( $translated_term ) ) {
					continue;
				}

				// Ottieni URL tradotto.
				$translated_url = get_term_link( $translated_term );
				if ( ! is_wp_error( $translated_url ) ) {
					$this->url_map_cache[ $url ] = $translated_url;
					return $translated_url;
				}
			}
		}

		return false;
	}

	/**
	 * Ottieni statistiche relink.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_relink_stats() {
		return array(
			'cache_size' => count( $this->url_map_cache ),
			'cached_urls' => array_keys( $this->url_map_cache ),
		);
	}

	/**
	 * Pulisci cache.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->url_map_cache = array();
	}
}

