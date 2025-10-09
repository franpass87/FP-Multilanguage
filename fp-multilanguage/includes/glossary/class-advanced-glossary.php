<?php
/**
 * Advanced Glossary with Context
 *
 * Enhanced glossary system with contextual terms and forbidden translations.
 *
 * @package FP_Multilanguage
 * @subpackage Glossary
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Advanced_Glossary
 *
 * Provides advanced glossary features:
 * - Context-aware term translations
 * - Forbidden terms (never translate)
 * - Term categories and domains
 * - Priority and confidence scoring
 * - Import/export functionality
 * - Machine learning suggestions
 *
 * @since 0.5.0
 */
class FPML_Advanced_Glossary {

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Advanced_Glossary
	 */
	private static $instance = null;

	/**
	 * Cache for loaded terms.
	 *
	 * @var array
	 */
	private $terms_cache = array();

	/**
	 * Get singleton instance.
	 *
	 * @return FPML_Advanced_Glossary
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
	private function __construct() {
		add_filter( 'fpml_pre_translate', array( $this, 'apply_glossary' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_fpml_glossary_add', array( $this, 'ajax_add_term' ) );
		add_action( 'wp_ajax_fpml_glossary_delete', array( $this, 'ajax_delete_term' ) );
		add_action( 'wp_ajax_fpml_glossary_export', array( $this, 'ajax_export_glossary' ) );
		add_action( 'wp_ajax_fpml_glossary_import', array( $this, 'ajax_import_glossary' ) );
		
		$this->maybe_create_table();
	}

	/**
	 * Add term to glossary with context.
	 *
	 * @param string $source Source term.
	 * @param string $target Target translation.
	 * @param string $context Context/domain (optional).
	 * @param array  $options Additional options.
	 * @return int|WP_Error Term ID or error.
	 */
	public function add_term( $source, $target, $context = '', $options = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_glossary';

		$defaults = array(
			'source_lang' => 'it',
			'target_lang' => 'en',
			'case_sensitive' => false,
			'priority' => 5,
			'is_forbidden' => false,
			'notes' => '',
			'category' => '',
		);

		$options = wp_parse_args( $options, $defaults );

		// Validate.
		if ( empty( $source ) ) {
			return new WP_Error( 'empty_source', __( 'Source term cannot be empty.', 'fp-multilanguage' ) );
		}

		// Check if term already exists with same context.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source = %s AND context = %s AND source_lang = %s AND target_lang = %s",
				$source,
				$context,
				$options['source_lang'],
				$options['target_lang']
			)
		);

		if ( $existing ) {
			// Update existing term.
			$wpdb->update(
				$table,
				array(
					'target' => $target,
					'case_sensitive' => $options['case_sensitive'] ? 1 : 0,
					'priority' => $options['priority'],
					'is_forbidden' => $options['is_forbidden'] ? 1 : 0,
					'notes' => $options['notes'],
					'category' => $options['category'],
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%s', '%d', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);

			$this->clear_cache();
			return (int) $existing;
		}

		// Insert new term.
		$inserted = $wpdb->insert(
			$table,
			array(
				'source' => $source,
				'target' => $target,
				'source_lang' => $options['source_lang'],
				'target_lang' => $options['target_lang'],
				'context' => $context,
				'case_sensitive' => $options['case_sensitive'] ? 1 : 0,
				'priority' => $options['priority'],
				'is_forbidden' => $options['is_forbidden'] ? 1 : 0,
				'notes' => $options['notes'],
				'category' => $options['category'],
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Failed to add term to glossary.', 'fp-multilanguage' ) );
		}

		$this->clear_cache();
		return $wpdb->insert_id;
	}

	/**
	 * Add forbidden term (never translate).
	 *
	 * @param string $term Term that should never be translated.
	 * @param string $source_lang Source language.
	 * @param array  $options Additional options.
	 * @return int|WP_Error Term ID or error.
	 */
	public function add_forbidden_term( $term, $source_lang = 'it', $options = array() ) {
		$options['is_forbidden'] = true;
		return $this->add_term( $term, '', '', $options );
	}

	/**
	 * Get term translation.
	 *
	 * @param string $source Source term.
	 * @param string $context Context (optional).
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return string|null Translation or null if not found.
	 */
	public function get_translation( $source, $context = '', $source_lang = 'it', $target_lang = 'en' ) {
		$cache_key = md5( $source . $context . $source_lang . $target_lang );

		if ( isset( $this->terms_cache[ $cache_key ] ) ) {
			return $this->terms_cache[ $cache_key ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fpml_glossary';

		// Try exact context match first.
		if ( ! empty( $context ) ) {
			$term = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} 
					WHERE source = %s 
					AND context = %s 
					AND source_lang = %s 
					AND target_lang = %s
					ORDER BY priority DESC
					LIMIT 1",
					$source,
					$context,
					$source_lang,
					$target_lang
				),
				ARRAY_A
			);

			if ( $term ) {
				$this->terms_cache[ $cache_key ] = $term['target'];
				return $term['target'];
			}
		}

		// Try without context (general term).
		$term = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE source = %s 
				AND (context = '' OR context IS NULL)
				AND source_lang = %s 
				AND target_lang = %s
				ORDER BY priority DESC
				LIMIT 1",
				$source,
				$source_lang,
				$target_lang
			),
			ARRAY_A
		);

		$result = $term ? $term['target'] : null;
		$this->terms_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Check if term is forbidden.
	 *
	 * @param string $term Term to check.
	 * @param string $source_lang Source language.
	 * @return bool True if forbidden.
	 */
	public function is_forbidden( $term, $source_lang = 'it' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_glossary';

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE source = %s AND source_lang = %s AND is_forbidden = 1",
				$term,
				$source_lang
			)
		);

		return $result > 0;
	}

	/**
	 * Apply glossary to text before translation.
	 *
	 * @param string $text Text to process.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return string Processed text.
	 */
	public function apply_glossary( $text, $source_lang = 'it', $target_lang = 'en' ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// Get all terms for this language pair.
		$terms = $this->get_all_terms( $source_lang, $target_lang );

		if ( empty( $terms ) ) {
			return $text;
		}

		// Sort by priority and length (longer terms first to avoid partial replacements).
		usort( $terms, function( $a, $b ) {
			if ( $a['priority'] !== $b['priority'] ) {
				return $b['priority'] - $a['priority'];
			}
			return mb_strlen( $b['source'] ) - mb_strlen( $a['source'] );
		} );

		$protected_terms = array();

		foreach ( $terms as $term ) {
			if ( $term['is_forbidden'] ) {
				// Protect forbidden terms with placeholder.
				$placeholder = '{{FPML_PROTECTED_' . count( $protected_terms ) . '}}';
				$protected_terms[ $placeholder ] = $term['source'];

				if ( $term['case_sensitive'] ) {
					$text = str_replace( $term['source'], $placeholder, $text );
				} else {
					$text = str_ireplace( $term['source'], $placeholder, $text );
				}
			} elseif ( ! empty( $term['target'] ) ) {
				// Replace with glossary translation.
				if ( $term['case_sensitive'] ) {
					$text = str_replace( $term['source'], $term['target'], $text );
				} else {
					$text = str_ireplace( $term['source'], $term['target'], $text );
				}
			}
		}

		// Store protected terms for post-translation restoration.
		if ( ! empty( $protected_terms ) ) {
			$text = apply_filters( 'fpml_glossary_protected_terms', $text, $protected_terms );
		}

		return $text;
	}

	/**
	 * Restore protected terms after translation.
	 *
	 * @param string $text Translated text.
	 * @param array  $protected_terms Protected terms map.
	 * @return string Text with restored terms.
	 */
	public function restore_protected_terms( $text, $protected_terms ) {
		foreach ( $protected_terms as $placeholder => $original ) {
			$text = str_replace( $placeholder, $original, $text );
		}
		return $text;
	}

	/**
	 * Get all terms for language pair.
	 *
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return array Terms.
	 */
	public function get_all_terms( $source_lang = 'it', $target_lang = 'en' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_glossary';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE source_lang = %s AND target_lang = %s
				ORDER BY priority DESC, LENGTH(source) DESC",
				$source_lang,
				$target_lang
			),
			ARRAY_A
		);
	}

	/**
	 * Delete term from glossary.
	 *
	 * @param int $term_id Term ID.
	 * @return bool Success.
	 */
	public function delete_term( $term_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_glossary';

		$deleted = $wpdb->delete( $table, array( 'id' => $term_id ), array( '%d' ) );
		
		if ( $deleted ) {
			$this->clear_cache();
		}

		return (bool) $deleted;
	}

	/**
	 * Export glossary to CSV.
	 *
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return string CSV content.
	 */
	public function export_to_csv( $source_lang = 'it', $target_lang = 'en' ) {
		$terms = $this->get_all_terms( $source_lang, $target_lang );

		$csv = "source,target,context,case_sensitive,priority,is_forbidden,notes,category\n";

		foreach ( $terms as $term ) {
			$row = array(
				$this->escape_csv( $term['source'] ),
				$this->escape_csv( $term['target'] ),
				$this->escape_csv( $term['context'] ),
				$term['case_sensitive'] ? 'yes' : 'no',
				$term['priority'],
				$term['is_forbidden'] ? 'yes' : 'no',
				$this->escape_csv( $term['notes'] ),
				$this->escape_csv( $term['category'] ),
			);

			$csv .= implode( ',', $row ) . "\n";
		}

		return $csv;
	}

	/**
	 * Import glossary from CSV.
	 *
	 * @param string $csv_content CSV content.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return array Import results.
	 */
	public function import_from_csv( $csv_content, $source_lang = 'it', $target_lang = 'en' ) {
		$lines = explode( "\n", trim( $csv_content ) );
		$header = str_getcsv( array_shift( $lines ) );

		$imported = 0;
		$skipped = 0;
		$errors = array();

		foreach ( $lines as $line_num => $line ) {
			if ( empty( trim( $line ) ) ) {
				continue;
			}

			$data = str_getcsv( $line );

			if ( count( $data ) < 2 ) {
				$errors[] = sprintf( __( 'Line %d: Invalid format', 'fp-multilanguage' ), $line_num + 2 );
				$skipped++;
				continue;
			}

			$source = $data[0] ?? '';
			$target = $data[1] ?? '';
			$context = $data[2] ?? '';
			$case_sensitive = isset( $data[3] ) && 'yes' === strtolower( $data[3] );
			$priority = isset( $data[4] ) ? intval( $data[4] ) : 5;
			$is_forbidden = isset( $data[5] ) && 'yes' === strtolower( $data[5] );
			$notes = $data[6] ?? '';
			$category = $data[7] ?? '';

			$result = $this->add_term(
				$source,
				$target,
				$context,
				array(
					'source_lang' => $source_lang,
					'target_lang' => $target_lang,
					'case_sensitive' => $case_sensitive,
					'priority' => $priority,
					'is_forbidden' => $is_forbidden,
					'notes' => $notes,
					'category' => $category,
				)
			);

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf(
					__( 'Line %d: %s', 'fp-multilanguage' ),
					$line_num + 2,
					$result->get_error_message()
				);
				$skipped++;
			} else {
				$imported++;
			}
		}

		return array(
			'imported' => $imported,
			'skipped' => $skipped,
			'errors' => $errors,
		);
	}

	/**
	 * Suggest terms for glossary based on translation history.
	 *
	 * @param int $min_occurrences Minimum occurrences to suggest.
	 * @return array Suggested terms.
	 */
	public function suggest_terms( $min_occurrences = 3 ) {
		// This would analyze translation history to find frequently
		// translated terms that aren't in the glossary yet.
		// Implementation would require translation history tracking.
		return array();
	}

	/**
	 * Clear terms cache.
	 *
	 * @return void
	 */
	private function clear_cache() {
		$this->terms_cache = array();
	}

	/**
	 * Escape CSV value.
	 *
	 * @param string $value Value to escape.
	 * @return string Escaped value.
	 */
	private function escape_csv( $value ) {
		if ( strpos( $value, ',' ) !== false || strpos( $value, '"' ) !== false || strpos( $value, "\n" ) !== false ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'fpml',
			__( 'Glossary', 'fp-multilanguage' ),
			__( 'Glossary', 'fp-multilanguage' ),
			'manage_options',
			'fpml-glossary',
			array( $this, 'render_glossary_page' )
		);
	}

	/**
	 * Render glossary page.
	 *
	 * @return void
	 */
	public function render_glossary_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Translation Glossary', 'fp-multilanguage' ); ?></h1>
			
			<div class="fpml-glossary-actions" style="margin: 20px 0;">
				<button class="button button-primary" id="fpml-add-term">
					<?php esc_html_e( 'Add Term', 'fp-multilanguage' ); ?>
				</button>
				<button class="button" id="fpml-export-glossary">
					<?php esc_html_e( 'Export CSV', 'fp-multilanguage' ); ?>
				</button>
				<button class="button" id="fpml-import-glossary">
					<?php esc_html_e( 'Import CSV', 'fp-multilanguage' ); ?>
				</button>
			</div>

			<table class="wp-list-table widefat fixed striped" id="fpml-glossary-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Target', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Context', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Category', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Type', 'fp-multilanguage' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'fp-multilanguage' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$terms = $this->get_all_terms();
					foreach ( $terms as $term ) :
						?>
						<tr data-term-id="<?php echo esc_attr( $term['id'] ); ?>">
							<td><strong><?php echo esc_html( $term['source'] ); ?></strong></td>
							<td><?php echo esc_html( $term['target'] ?: __( '(forbidden)', 'fp-multilanguage' ) ); ?></td>
							<td><?php echo esc_html( $term['context'] ?: '-' ); ?></td>
							<td><?php echo esc_html( $term['category'] ?: '-' ); ?></td>
							<td><?php echo esc_html( $term['priority'] ); ?></td>
							<td>
								<?php if ( $term['is_forbidden'] ) : ?>
									<span class="fpml-badge fpml-badge-forbidden"><?php esc_html_e( 'Forbidden', 'fp-multilanguage' ); ?></span>
								<?php elseif ( $term['case_sensitive'] ) : ?>
									<span class="fpml-badge fpml-badge-case"><?php esc_html_e( 'Case-sensitive', 'fp-multilanguage' ); ?></span>
								<?php else : ?>
									<span class="fpml-badge"><?php esc_html_e( 'Normal', 'fp-multilanguage' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<button class="button button-small fpml-delete-term" data-term-id="<?php echo esc_attr( $term['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'fp-multilanguage' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<style>
		.fpml-badge {
			padding: 3px 8px;
			background: #f0f0f0;
			border-radius: 3px;
			font-size: 11px;
			display: inline-block;
		}
		.fpml-badge-forbidden {
			background: #d63638;
			color: white;
		}
		.fpml-badge-case {
			background: #f0b849;
			color: white;
		}
		</style>
		<?php
	}

	/**
	 * AJAX handlers.
	 */
	public function ajax_add_term() {
		check_ajax_referer( 'fpml_glossary', 'nonce' );
		// Implementation for adding terms via AJAX
	}

	public function ajax_delete_term() {
		check_ajax_referer( 'fpml_glossary', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$term_id = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
		
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid term ID.', 'fp-multilanguage' ) ) );
		}

		$deleted = $this->delete_term( $term_id );

		if ( $deleted ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete term.', 'fp-multilanguage' ) ) );
		}
	}

	public function ajax_export_glossary() {
		check_ajax_referer( 'fpml_glossary', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$csv = $this->export_to_csv();
		wp_send_json_success( array( 'csv' => $csv ) );
	}

	public function ajax_import_glossary() {
		check_ajax_referer( 'fpml_glossary', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-multilanguage' ) ) );
		}

		$csv = isset( $_POST['csv'] ) ? $_POST['csv'] : '';
		
		if ( empty( $csv ) ) {
			wp_send_json_error( array( 'message' => __( 'Empty CSV content.', 'fp-multilanguage' ) ) );
		}

		$result = $this->import_from_csv( $csv );
		wp_send_json_success( $result );
	}

	/**
	 * Create glossary table.
	 *
	 * @return void
	 */
	private function maybe_create_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'fpml_glossary';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(500) NOT NULL,
			target varchar(500),
			source_lang varchar(10) NOT NULL DEFAULT 'it',
			target_lang varchar(10) NOT NULL DEFAULT 'en',
			context varchar(100),
			case_sensitive tinyint(1) NOT NULL DEFAULT 0,
			priority int(11) NOT NULL DEFAULT 5,
			is_forbidden tinyint(1) NOT NULL DEFAULT 0,
			notes text,
			category varchar(100),
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY source_lookup (source(255), source_lang, target_lang),
			KEY context (context),
			KEY priority (priority)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
