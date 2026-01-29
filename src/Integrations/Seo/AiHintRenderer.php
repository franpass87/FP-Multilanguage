<?php
/**
 * SEO AI Hint Renderer - Renders AI SEO generation hint in metabox.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders AI SEO generation hint in metabox.
 *
 * @since 0.10.0
 */
class AiHintRenderer {
	/**
	 * Render AI SEO generation hint in translation metabox.
	 *
	 * @since 0.10.0
	 *
	 * @param int      $post_id     Current post ID.
	 * @param int|null $english_id  English post ID.
	 *
	 * @return void
	 */
	public function render_ai_seo_hint( int $post_id, ?int $english_id ): void {
		if ( ! $english_id ) {
			return;
		}

		// Check if AI is enabled in SEO Manager
		if ( ! class_exists( '\FP\SEO\Utils\Options' ) ) {
			return;
		}

		$ai_enabled = \FP\SEO\Utils\Options::get_option( 'ai.enable_auto_generation', false );
		
		if ( ! $ai_enabled ) {
			return;
		}

		$edit_link = admin_url( 'post.php?post=' . $english_id . '&action=edit' );

		// Check which AI features are available for EN post
		$available_features = array();
		
		// Check if QA pairs exist in IT
		if ( get_post_meta( $post_id, '_fp_seo_qa_pairs', true ) ) {
			$available_features[] = '💬 Q&A Pairs';
		}
		
		// Check if entities exist in IT
		if ( get_post_meta( $post_id, '_fp_seo_entities', true ) ) {
			$available_features[] = '🏷️ Entities';
		}
		
		// Check if schema exists in IT
		if ( get_post_meta( $post_id, MetaWhitelist::FP_SEO_FAQ_QUESTIONS, true ) ) {
			$available_features[] = '❓ FAQ Schema';
		}

		?>
		<div class="fpml-seo-ai-hint" style="margin: 12px 0; padding: 12px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 6px; border: 1px solid #0ea5e9;">
			<div style="display: flex; align-items: start; gap: 10px;">
				<span style="font-size: 18px;">🤖</span>
				<div style="flex: 1;">
					<div style="font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 6px;">
						FP SEO Manager - AI Features Disponibili
					</div>
					<div style="font-size: 11px; color: #475569; line-height: 1.5; margin-bottom: 10px;">
						Il post inglese può beneficiare delle seguenti funzionalità AI:
					</div>
					
					<ul style="margin: 0 0 10px 0; padding-left: 20px; font-size: 11px; color: #64748b;">
						<li>✨ Meta Description AI-optimized</li>
						<li>💬 Q&A Pairs per rich snippets</li>
						<li>🏷️ Entity Recognition & Relationships</li>
						<li>🔍 Semantic Embeddings</li>
						<li>❓ FAQ Schema generation</li>
						<li>📊 GEO optimization</li>
					</ul>
					
					<?php if ( ! empty( $available_features ) ) : ?>
					<div style="font-size: 11px; color: #059669; margin-bottom: 8px;">
						<strong>✓ Già configurato in IT:</strong> <?php echo esc_html( implode( ', ', $available_features ) ); ?>
					</div>
					<?php endif; ?>
					
					<a href="<?php echo esc_url( $edit_link ); ?>" class="button button-primary button-small" style="font-size: 11px;">
						🚀 Apri Editor EN → Genera AI Features
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-seo-settings' ) ); ?>" class="button button-secondary button-small" style="font-size: 11px; margin-left: 6px;">
						⚙️ Settings FP-SEO
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
















