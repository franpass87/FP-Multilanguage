<?php
/**
 * Bulk Translation Dashboard.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Settings;
use FP\Multilanguage\Queue;
use FP\Multilanguage\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk translate multiple posts at once.
 *
 * @since 0.5.0
 */
class BulkTranslator {
	/**
	 * Singleton instance.
	 *
	 * @var BulkTranslator|null
	 */
	protected static $instance = null;

	/**
	 * Track whether the legacy alias has been registered.
	 *
	 * @var bool
	 */
	protected $legacy_slug_registered = false;

	/**
	 * Get singleton instance.
	 *
	 * @return BulkTranslator
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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_head', array( $this, 'hide_legacy_menu_via_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_fpml_bulk_translate', array( $this, 'ajax_bulk_translate' ) );
	}

	/**
	 * Add submenu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'fpml-settings',
			__( 'Bulk Translation', 'fp-multilanguage' ),
			__( 'Bulk Translation', 'fp-multilanguage' ),
			'manage_options',
			'fpml-bulk-translate',
			array( $this, 'render_page' )
		);

		// Legacy slug support to avoid permission errors on old bookmarks.
		$this->legacy_slug_registered = (bool) add_submenu_page(
			'fpml-settings',
			__( 'Bulk Translation', 'fp-multilanguage' ),
			__( 'Bulk Translation', 'fp-multilanguage' ),
			'manage_options',
			'fpml-bulk',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Hide the legacy slug entry from the submenu while keeping the page accessible.
	 */
	public function hide_legacy_menu_via_css() {
		if ( ! $this->legacy_slug_registered ) {
			return;
		}

		echo '<style>#toplevel_page_fpml-settings .wp-submenu a[href$="page=fpml-bulk"]{display:none!important;}</style>';
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'fpml_page_fpml-bulk-translate' !== $hook ) {
			return;
		}

		$settings = function_exists( 'fpml_get_settings' ) ? fpml_get_settings() : Settings::instance();
		$rate_option = $settings ? $settings->get( 'rate_openai', '0.00011' ) : '0.00011';
		$rate_value  = (float) str_replace( ',', '.', (string) $rate_option );

		if ( $rate_value <= 0 ) {
			$rate_value = 0.00011;
		}

		$rate_decimals = $rate_value < 0.01 ? 5 : 2;
		$rate_label    = \number_format_i18n( $rate_value, $rate_decimals );

		wp_enqueue_script(
			'fpml-bulk-translate',
			\FPML_PLUGIN_URL . 'assets/bulk-translate.js',
			array( 'jquery' ),
			\FPML_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'fpml-bulk-translate',
			'fpmlBulk',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'fpml_bulk_translate' ),
				'rate'       => $rate_value,
				'rate_label' => sprintf( '€%s / 1000', $rate_label ),
				'model'      => __( 'GPT-5 nano', 'fp-multilanguage' ),
			)
		);
	}

	/**
	 * Render bulk translation page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
		}

		if ( isset( $_GET['page'] ) && 'fpml-bulk' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=fpml-bulk-translate' ) );
			exit;
		}

		$settings = function_exists( 'fpml_get_settings' ) ? fpml_get_settings() : Settings::instance();
		$rate_option    = $settings ? $settings->get( 'rate_openai', '0.00011' ) : '0.00011';
		$rate_value     = (float) str_replace( ',', '.', (string) $rate_option );
		if ( $rate_value <= 0 ) {
			$rate_value = 0.00011;
		}
		$rate_decimals  = $rate_value < 0.01 ? 5 : 2;
		$rate_label     = \number_format_i18n( $rate_value, $rate_decimals );
		$rate_label_str = sprintf( '€%s / 1000', $rate_label );

		$untranslated_posts = $this->get_untranslated_posts();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bulk Translation', 'fp-multilanguage' ); ?></h1>
			<p><?php esc_html_e( 'Seleziona i contenuti da tradurre in blocco.', 'fp-multilanguage' ); ?></p>

			<form id="fpml-bulk-form">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="check-column">
								<input type="checkbox" id="fpml-select-all" />
							</td>
							<th><?php esc_html_e( 'Titolo', 'fp-multilanguage' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'fp-multilanguage' ); ?></th>
							<th><?php esc_html_e( 'Data', 'fp-multilanguage' ); ?></th>
							<th><?php esc_html_e( 'Lunghezza', 'fp-multilanguage' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $untranslated_posts as $post ) : ?>
						<tr>
							<th class="check-column">
								<input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $post->ID ); ?>" />
							</th>
							<td>
								<strong><?php echo esc_html( $post->post_title ); ?></strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
											<?php esc_html_e( 'Modifica', 'fp-multilanguage' ); ?>
										</a>
									</span>
								</div>
							</td>
							<td><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></td>
							<td><?php echo esc_html( get_the_date( '', $post ) ); ?></td>
							<td><?php echo esc_html( number_format( mb_strlen( strip_tags( $post->post_content ) ) ) ); ?> chars</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Bulk Summary Box -->
				<div id="fpml-bulk-summary" style="display:none; margin:20px 0; padding:20px; background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius:8px; border:1px solid #0ea5e9;">
					<h3 style="margin:0 0 15px; color:#0c4a6e; font-size:16px;">📊 Riepilogo Selezione</h3>
					<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
						<div>
							<div style="color:#64748b; font-size:12px; margin-bottom:4px;">📝 Post Selezionati</div>
							<div style="font-size:24px; font-weight:700; color:#0f172a;" id="fpml-selected-count">0</div>
						</div>
						<div>
							<div style="color:#64748b; font-size:12px; margin-bottom:4px;">📊 Caratteri Totali</div>
							<div style="font-size:24px; font-weight:700; color:#0f172a;" id="fpml-total-chars">0</div>
						</div>
						<div>
							<div style="color:#64748b; font-size:12px; margin-bottom:4px;">⏱️ Tempo Stimato</div>
							<div style="font-size:24px; font-weight:700; color:#0f172a;" id="fpml-total-time">0 min</div>
						</div>
					</div>
					<div style="padding:15px; background:#fff; border-radius:6px; border:1px solid #bfdbfe;">
						<div style="display:flex; justify-content:space-between; align-items:center;">
							<div style="color:#64748b; font-size:13px;">💰 <?php esc_html_e( 'Costo Totale Stimato', 'fp-multilanguage' ); ?> (<?php esc_html_e( 'GPT-5 nano', 'fp-multilanguage' ); ?>)</div>
							<div style="font-size:28px; font-weight:700; color:#0ea5e9;" id="fpml-total-cost">$0.00</div>
						</div>
						<div style="color:#64748b; font-size:11px; margin-top:8px;">
							<?php
							printf(
								/* translators: %s: rate label */
								esc_html__( 'Stima basata su %s. Il costo finale potrebbe variare leggermente.', 'fp-multilanguage' ),
								esc_html( $rate_label_str )
							);
							?>
						</div>
					</div>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-large" id="fpml-bulk-translate-btn">
						<span class="dashicons dashicons-translation" style="margin-top:3px;"></span>
						<?php esc_html_e( 'Traduci Selezionati', 'fp-multilanguage' ); ?>
					</button>
					<span class="spinner"></span>
				</p>

				<div id="fpml-bulk-progress" style="display:none;">
					<h3><?php esc_html_e( 'Progresso Traduzione', 'fp-multilanguage' ); ?></h3>
					<progress id="fpml-progress-bar" max="100" value="0" style="width:100%;"></progress>
					<p id="fpml-progress-text">0 / 0</p>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Get untranslated posts.
	 *
	 * @return array
	 */
	protected function get_untranslated_posts() {
		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_fpml_pair_id',
					'compare' => 'NOT EXISTS',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		return get_posts( $args );
	}

	/**
	 * AJAX handler for bulk translation.
	 */
	public function ajax_bulk_translate() {
		check_ajax_referer( 'fpml_bulk_translate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Nessun post selezionato.', 'fp-multilanguage' ) ) );
		}

		$queue  = fpml_get_queue();
		$logger = fpml_get_logger();
		$added  = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Add to queue
			$queue->enqueue( 'post', $post_id, 'post_content', md5( $post->post_content ) );
			$queue->enqueue( 'post', $post_id, 'post_title', md5( $post->post_title ) );
			$added ++;

			$logger->log( 'info', "Bulk: Added post {$post_id} to queue" );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d number of posts */
				__( '%d post aggiunti alla coda di traduzione.', 'fp-multilanguage' ),
				$added
			),
			'added' => $added,
		) );
	}
}

