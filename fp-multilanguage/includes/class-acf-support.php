<?php
/**
 * Supporto avanzato per Advanced Custom Fields.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce relazioni ACF e campi complessi.
 *
 * @since 0.4.0
 */
class FPML_ACF_Support {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_ACF_Support|null
	 */
	protected static $instance = null;

	/**
	 * Logger reference.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Settings reference.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * ACF disponibile.
	 *
	 * @var bool
	 */
	protected $acf_available = false;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_ACF_Support
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
		$this->logger   = FPML_Logger::instance();
		$this->settings = FPML_Settings::instance();

		// Controlla se ACF è attivo.
		$this->acf_available = class_exists( 'ACF' ) || function_exists( 'acf_get_field_groups' );

		if ( ! $this->acf_available ) {
			return;
		}

		// Hook dopo traduzione per processare relazioni.
		add_action( 'fpml_post_translated', array( $this, 'process_acf_relations' ), 40, 4 );

		// Filter per meta whitelist - aggiungi automaticamente campi ACF.
		add_filter( 'fpml_meta_whitelist', array( $this, 'add_acf_fields_to_whitelist' ), 20, 2 );
	}

	/**
	 * Aggiunge automaticamente campi ACF alla whitelist.
	 *
	 * @since 0.4.0
	 *
	 * @param array       $whitelist Whitelist corrente.
	 * @param FPML_Plugin $plugin    Plugin instance.
	 *
	 * @return array
	 */
	public function add_acf_fields_to_whitelist( $whitelist, $plugin ) {
		if ( ! $this->acf_available || ! function_exists( 'acf_get_field_groups' ) ) {
			return $whitelist;
		}

		// Ottieni tutti i field groups.
		$field_groups = acf_get_field_groups();

		foreach ( $field_groups as $group ) {
			if ( ! function_exists( 'acf_get_fields' ) ) {
				continue;
			}

			$fields = acf_get_fields( $group['key'] );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				// Aggiungi campi traducibili.
				if ( $this->is_translatable_field_type( $field['type'] ) ) {
					$field_name = $field['name'];

					if ( ! in_array( $field_name, $whitelist, true ) ) {
						$whitelist[] = $field_name;
					}
				}
			}
		}

		return $whitelist;
	}

	/**
	 * Controlla se un tipo di campo è traducibile.
	 *
	 * @since 0.4.0
	 *
	 * @param string $type Tipo campo ACF.
	 *
	 * @return bool
	 */
	protected function is_translatable_field_type( $type ) {
		$translatable_types = array(
			'text',
			'textarea',
			'wysiwyg',
			'email',
			'url',
			'post_object',    // Relazione post.
			'relationship',   // Relazione multipla.
			'taxonomy',       // Termini.
			'repeater',       // Sottocampi traducibili.
			'flexible_content',
			'clone',
		);

		return in_array( $type, $translatable_types, true );
	}

	/**
	 * Processa relazioni ACF dopo traduzione.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 * @param string  $field       Campo tradotto.
	 * @param string  $value       Valore.
	 * @param object  $job         Job.
	 *
	 * @return void
	 */
	public function process_acf_relations( $target_post, $field, $value, $job ) {
		// Solo per meta ACF.
		if ( 0 !== strpos( $field, 'meta:' ) ) {
			return;
		}

		$meta_key = substr( $field, 5 );

		// Ottieni field config ACF.
		if ( ! function_exists( 'acf_get_field' ) ) {
			return;
		}

		$field_object = acf_get_field( $meta_key );

		if ( ! $field_object ) {
			return; // Non è un campo ACF.
		}

		// Gestisci in base al tipo.
		switch ( $field_object['type'] ) {
			case 'post_object':
			case 'relationship':
				$this->process_post_relation( $target_post, $meta_key, $field_object );
				break;

			case 'taxonomy':
				$this->process_taxonomy_relation( $target_post, $meta_key, $field_object );
				break;

			case 'repeater':
				$this->process_repeater_field( $target_post, $meta_key, $field_object );
				break;

			case 'flexible_content':
				$this->process_flexible_content( $target_post, $meta_key, $field_object );
				break;
		}
	}

	/**
	 * Processa relazione post_object/relationship.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 * @param string  $meta_key    Meta key.
	 * @param array   $field       Field config ACF.
	 *
	 * @return void
	 */
	protected function process_post_relation( $target_post, $meta_key, $field ) {
		$source_id = get_post_meta( $target_post->ID, '_fpml_pair_source_id', true );
		if ( ! $source_id ) {
			return;
		}

		$source_value = get_post_meta( $source_id, $meta_key, true );

		if ( empty( $source_value ) ) {
			return;
		}

		// Converti in array.
		$post_ids = is_array( $source_value ) ? $source_value : array( $source_value );
		$translated_ids = array();

		foreach ( $post_ids as $post_id ) {
			// Ottieni traduzione di questo post.
			$translation_id = get_post_meta( $post_id, '_fpml_pair_id', true );

			if ( $translation_id ) {
				$translated_ids[] = (int) $translation_id;
			}
		}

		if ( empty( $translated_ids ) ) {
			return;
		}

		// Salva relazione tradotta.
		$new_value = 'post_object' === $field['type'] && ! $field['multiple'] ? $translated_ids[0] : $translated_ids;

		update_post_meta( $target_post->ID, $meta_key, $new_value );

		$this->logger->log(
			'debug',
			sprintf( 'Relazione ACF aggiornata per %s', $meta_key ),
			array(
				'post_id'   => $target_post->ID,
				'meta_key'  => $meta_key,
				'original'  => $post_ids,
				'translated' => $translated_ids,
			)
		);
	}

	/**
	 * Processa relazione taxonomy.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 * @param string  $meta_key    Meta key.
	 * @param array   $field       Field config ACF.
	 *
	 * @return void
	 */
	protected function process_taxonomy_relation( $target_post, $meta_key, $field ) {
		$source_id = get_post_meta( $target_post->ID, '_fpml_pair_source_id', true );
		if ( ! $source_id ) {
			return;
		}

		$source_value = get_post_meta( $source_id, $meta_key, true );

		if ( empty( $source_value ) ) {
			return;
		}

		// Converti in array.
		$term_ids = is_array( $source_value ) ? $source_value : array( $source_value );
		$translated_ids = array();
		$taxonomy = $field['taxonomy'];

		foreach ( $term_ids as $term_id ) {
			// Ottieni traduzione term.
			$translation_id = get_term_meta( $term_id, '_fpml_pair_id', true );

			if ( $translation_id ) {
				$translated_ids[] = (int) $translation_id;
			}
		}

		if ( empty( $translated_ids ) ) {
			return;
		}

		// Salva relazione tradotta.
		$new_value = 'checkbox' !== $field['field_type'] && ! empty( $field['field_type'] ) ? $translated_ids[0] : $translated_ids;

		update_post_meta( $target_post->ID, $meta_key, $new_value );

		$this->logger->log(
			'debug',
			sprintf( 'Relazione taxonomy ACF aggiornata per %s', $meta_key ),
			array(
				'post_id'    => $target_post->ID,
				'meta_key'   => $meta_key,
				'taxonomy'   => $taxonomy,
				'translated' => $translated_ids,
			)
		);
	}

	/**
	 * Processa repeater field.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 * @param string  $meta_key    Meta key.
	 * @param array   $field       Field config ACF.
	 *
	 * @return void
	 */
	protected function process_repeater_field( $target_post, $meta_key, $field ) {
		$source_id = get_post_meta( $target_post->ID, '_fpml_pair_source_id', true );
		if ( ! $source_id ) {
			return;
		}

		// I repeater vengono gestiti come array dalla traduzione normale.
		// Qui possiamo processare le relazioni interne.
		$rows = get_post_meta( $target_post->ID, $meta_key, true );

		if ( ! is_numeric( $rows ) || $rows <= 0 ) {
			return;
		}

		// Itera sulle righe.
		for ( $i = 0; $i < $rows; $i++ ) {
			if ( ! isset( $field['sub_fields'] ) || ! is_array( $field['sub_fields'] ) ) {
				continue;
			}

			foreach ( $field['sub_fields'] as $sub_field ) {
				$sub_key = $meta_key . '_' . $i . '_' . $sub_field['name'];

				if ( in_array( $sub_field['type'], array( 'post_object', 'relationship' ), true ) ) {
					// Processa relazione nel sub-field.
					$this->process_post_relation( $target_post, $sub_key, $sub_field );
				}
			}
		}
	}

	/**
	 * Processa flexible content.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 * @param string  $meta_key    Meta key.
	 * @param array   $field       Field config ACF.
	 *
	 * @return void
	 */
	protected function process_flexible_content( $target_post, $meta_key, $field ) {
		// Simile a repeater ma con layouts dinamici.
		$source_id = get_post_meta( $target_post->ID, '_fpml_pair_source_id', true );
		if ( ! $source_id ) {
			return;
		}

		$rows = get_post_meta( $target_post->ID, $meta_key, true );

		if ( ! is_numeric( $rows ) || $rows <= 0 ) {
			return;
		}

		// Itera layouts.
		for ( $i = 0; $i < $rows; $i++ ) {
			$layout_key = $meta_key . '_' . $i . '_acf_fc_layout';
			$layout = get_post_meta( $target_post->ID, $layout_key, true );

			if ( ! $layout || ! isset( $field['layouts'] ) ) {
				continue;
			}

		// Trova layout config.
		$layout_config = null;
		foreach ( $field['layouts'] as $l ) {
			if ( isset( $l['name'] ) && $l['name'] === $layout ) {
				$layout_config = $l;
				break;
			}
		}

			if ( ! $layout_config || empty( $layout_config['sub_fields'] ) ) {
				continue;
			}

			// Processa sub-fields del layout.
			foreach ( $layout_config['sub_fields'] as $sub_field ) {
				$sub_key = $meta_key . '_' . $i . '_' . $sub_field['name'];

				if ( in_array( $sub_field['type'], array( 'post_object', 'relationship' ), true ) ) {
					$this->process_post_relation( $target_post, $sub_key, $sub_field );
				}
			}
		}

		$this->logger->log(
			'debug',
			sprintf( 'Flexible content ACF processato: %s', $meta_key ),
			array( 'post_id' => $target_post->ID, 'meta_key' => $meta_key, 'rows' => $rows )
		);
	}

	/**
	 * Ottieni campi ACF per post type.
	 *
	 * @since 0.4.0
	 *
	 * @param string $post_type Post type.
	 *
	 * @return array
	 */
	public function get_acf_fields_for_post_type( $post_type ) {
		if ( ! $this->acf_available || ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}

		$field_groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
		$fields = array();

		foreach ( $field_groups as $group ) {
			if ( ! function_exists( 'acf_get_fields' ) ) {
				continue;
			}

			$group_fields = acf_get_fields( $group['key'] );

			if ( ! empty( $group_fields ) ) {
				$fields = array_merge( $fields, $group_fields );
			}
		}

		return $fields;
	}

	/**
	 * Verifica se ACF è disponibile.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->acf_available;
	}

	/**
	 * Ottieni statistiche ACF.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_stats() {
		if ( ! $this->acf_available ) {
			return array(
				'available'    => false,
				'field_groups' => 0,
				'fields'       => 0,
			);
		}

		$field_groups = function_exists( 'acf_get_field_groups' ) ? acf_get_field_groups() : array();
		$total_fields = 0;

		foreach ( $field_groups as $group ) {
			if ( function_exists( 'acf_get_fields' ) ) {
				$fields = acf_get_fields( $group['key'] );
				$total_fields += is_array( $fields ) ? count( $fields ) : 0;
			}
		}

		return array(
			'available'    => true,
			'field_groups' => count( $field_groups ),
			'fields'       => $total_fields,
		);
	}
}
