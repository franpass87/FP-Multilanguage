<?php
/**
 * FP Plugins Integration - Auto-detect and translate FP-* plugin meta fields.
 *
 * Automatically adds translatable meta fields from FP-* plugins to whitelist.
 *
 * @package FP_Multilanguage
 * @since 0.9.1
 */

namespace FP\Multilanguage\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FpPluginsSupport {
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_filter( '\FPML_meta_whitelist', array( $this, 'add_fp_plugins_meta' ), 25 );
	}

	/**
	 * Add FP-* plugins meta fields to whitelist.
	 *
	 * @param array $meta_keys Current whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_fp_plugins_meta( $meta_keys ) {
		$fp_meta = array();

		// === FP SEO Manager ===
		if ( class_exists( '\FP\SEO\Manager' ) || defined( 'FP_SEO_VERSION' ) ) {
			$fp_meta = array_merge( $fp_meta, array(
				'_fp_seo_title',
				'_fp_seo_meta_description',
				'_fp_seo_focus_keyword',
				'_fp_seo_secondary_keywords',
				'_fp_seo_geo_claims',
				'_fp_seo_social_meta',
				'_fp_seo_faq_questions',
				'_fp_seo_howto',
				'_fp_seo_multiple_keywords', // Formato alternativo (serializzato con keywords)
			) );
		}

		// === FP Newspaper / Editorial ===
		// Rileva anche se non c'è classe specifica (usa meta fields come indicatore)
		$fp_meta = array_merge( $fp_meta, array(
			'_fp_article_address',        // Indirizzo articolo (traducibile)
			'_fp_article_location',        // Posizione (traducibile)
			'_fp_workflow_history',        // Storia workflow (serializzato, contiene testi traducibili)
		) );

		// === FP Civic Engagement ===
		// Rileva anche se non c'è classe specifica
		$fp_meta = array_merge( $fp_meta, array(
			'_fp_civic_components',       // Componenti civic (serializzato con testi traducibili)
			'_fp_map_locations',           // Posizioni mappa (title, caption traducibili - serializzato)
		) );

		// === FP Forms ===
		if ( class_exists( '\FP\Forms\Manager' ) || defined( 'FP_FORMS_VERSION' ) ) {
			$fp_meta = array_merge( $fp_meta, array(
				'_fp_form_title',              // Titolo form
				'_fp_form_description',        // Descrizione form
				'_fp_form_success_message',     // Messaggio successo
				'_fp_form_error_message',       // Messaggio errore
			) );
		}

		// === FP Digital Marketing Suite ===
		if ( class_exists( '\FP\DMS\Manager' ) || defined( 'FP_DMS_VERSION' ) ) {
			$fp_meta = array_merge( $fp_meta, array(
				'_fp_dms_campaign_title',      // Titolo campagna
				'_fp_dms_campaign_description', // Descrizione campagna
				'_fp_dms_ad_copy',             // Testo annuncio
			) );
		}

		// === FP Restaurant Reservations ===
		if ( class_exists( '\FP\Restaurant\Reservations' ) || defined( 'FP_RESTAURANT_VERSION' ) ) {
			$fp_meta = array_merge( $fp_meta, array(
				'_fp_restaurant_name',         // Nome ristorante
				'_fp_restaurant_description',  // Descrizione
				'_fp_restaurant_menu',         // Menu (potrebbe essere HTML)
			) );
		}

		// === FP Experiences ===
		if ( class_exists( '\FP\Experiences\Manager' ) || defined( 'FP_EXPERIENCES_VERSION' ) ) {
			$fp_meta = array_merge( $fp_meta, array(
				'_fp_experience_title',        // Titolo esperienza
				'_fp_experience_description',  // Descrizione
				'_fp_experience_location',     // Posizione
			) );
		}

		// === FP Publisher ===
		if ( class_exists( '\FP\Publisher\Manager' ) || defined( 'FP_PUBLISHER_VERSION' ) ) {
			$fp_meta = array_merge( $fp_meta, array(
				'_fp_publisher_author_bio',     // Bio autore
				'_fp_publisher_author_name',    // Nome autore
			) );
		}

		// Rimuovi duplicati e filtra
		$fp_meta = array_unique( $fp_meta );
		
		// Rimuovi meta già presenti
		$fp_meta = array_diff( $fp_meta, $meta_keys );

		return array_merge( $meta_keys, $fp_meta );
	}
}



