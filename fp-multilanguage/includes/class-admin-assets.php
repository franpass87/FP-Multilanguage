<?php
/**
 * Admin Assets Manager
 *
 * Handles enqueuing of admin scripts and styles for v0.5.0 features.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPML_Admin_Assets
 *
 * @since 0.5.0
 */
class FPML_Admin_Assets {

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Admin_Assets
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return FPML_Admin_Assets
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on FPML pages
		if ( strpos( $hook, 'fpml' ) === false && $hook !== 'edit.php' ) {
			return;
		}

		// Enqueue v0.5.0 JavaScript
		wp_enqueue_script(
			'fpml-admin-v050',
			FPML_PLUGIN_URL . 'assets/admin-v050.js',
			array( 'jquery' ),
			FPML_PLUGIN_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'fpml-admin-v050',
			'fpmlAdmin',
			array(
				'nonce' => wp_create_nonce( 'fpml_bulk_translate' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'confirmDelete' => __( 'Are you sure you want to delete this term?', 'fp-multilanguage' ),
				'confirmRevoke' => __( 'Are you sure you want to revoke this API key?', 'fp-multilanguage' ),
				'confirmClearDebug' => __( 'Are you sure you want to clear the debug log?', 'fp-multilanguage' ),
				'error' => __( 'An error occurred. Please try again.', 'fp-multilanguage' ),
				'termNamePrompt' => __( 'Enter source term:', 'fp-multilanguage' ),
				'termTranslationPrompt' => __( 'Enter translation (leave empty for forbidden term):', 'fp-multilanguage' ),
				'importSuccess' => __( '%d terms imported successfully!', 'fp-multilanguage' ),
				'apiKeyNamePrompt' => __( 'Enter API key name:', 'fp-multilanguage' ),
				'apiKeyDescPrompt' => __( 'Enter description (optional):', 'fp-multilanguage' ),
				'apiKeyGenerated' => __( 'API key generated! Copy it now (it won\'t be shown again):', 'fp-multilanguage' ),
				'generating' => __( 'Generating...', 'fp-multilanguage' ),
				'generateKey' => __( 'Generate New Key', 'fp-multilanguage' ),
				'noPostsSelected' => __( 'Please select posts to translate.', 'fp-multilanguage' ),
				'estimating' => __( 'Estimating cost...', 'fp-multilanguage' ),
				'estimateMessage' => __( 'Translate %1$d posts (%2$d characters)?\n\nEstimated cost: %3$s\nEstimated time: %4$s\n\nDo you want to proceed?', 'fp-multilanguage' ),
			)
		);

		// Enqueue Chart.js for analytics (if on analytics page)
		if ( strpos( $hook, 'fpml-analytics' ) !== false ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
				array(),
				'3.9.1',
				true
			);
		}

		// Enqueue admin styles
		wp_enqueue_style(
			'fpml-admin-v050',
			FPML_PLUGIN_URL . 'assets/admin-v050.css',
			array(),
			FPML_PLUGIN_VERSION
		);
	}
}

// Initialize
FPML_Admin_Assets::instance();
