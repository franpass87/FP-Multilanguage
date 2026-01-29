<?php
/**
 * Dashboard Overview - Landing Page.
 *
 * @package FP_Multilanguage
 * @since 0.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $stats è passato da render_dashboard_tab()
?>

<style>
.fpml-dashboard {
	margin-top: 20px;
}
.fpml-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}
.fpml-stat-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.fpml-stat-card h3 {
	margin: 0 0 10px 0;
	color: #64748b;
	font-size: 12px;
	text-transform: uppercase;
	font-weight: 600;
}
.fpml-stat-value {
	font-size: 36px;
	font-weight: 700;
	margin: 10px 0;
}
.fpml-stat-card.primary .fpml-stat-value {
	color: #0ea5e9;
}
.fpml-stat-card.success .fpml-stat-value {
	color: #10b981;
}
.fpml-stat-card.warning .fpml-stat-value {
	color: #f59e0b;
}
.fpml-stat-card.danger .fpml-stat-value {
	color: #ef4444;
}
.fpml-quick-actions {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
	margin-bottom: 30px;
}
.fpml-section {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 20px;
}
.fpml-section h2 {
	margin-top: 0;
	font-size: 18px;
	border-bottom: 2px solid #0ea5e9;
	padding-bottom: 10px;
}
.fpml-trend {
	font-size: 14px;
	margin-top: 5px;
}
.fpml-trend.positive {
	color: #10b981;
}
.fpml-trend.negative {
	color: #ef4444;
}
.fpml-progress-bar {
	width: 100%;
	height: 20px;
	background: #e5e7eb;
	border-radius: 10px;
	overflow: hidden;
	margin: 10px 0;
}
.fpml-progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #0ea5e9 0%, #3b82f6 100%);
	transition: width 0.3s ease;
}
.fpml-error-list {
	list-style: none;
	padding: 0;
	margin: 0;
}
.fpml-error-item {
	padding: 10px;
	background: #fef2f2;
	border-left: 3px solid #ef4444;
	margin-bottom: 10px;
	border-radius: 4px;
}
.fpml-error-item code {
	display: block;
	margin-top: 5px;
	font-size: 11px;
	color: #dc2626;
}
.fpml-guide-links {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 15px;
	margin-top: 15px;
}
.fpml-guide-link {
	display: block;
	padding: 15px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	text-decoration: none;
	color: #1e293b;
	transition: all 0.2s;
}
.fpml-guide-link:hover {
	background: #f1f5f9;
	border-color: #0ea5e9;
	transform: translateY(-2px);
}
.fpml-guide-link strong {
	color: #0ea5e9;
}
</style>

<div class="fpml-dashboard">
	
	<!-- Stats Grid -->
	<div class="fpml-stats-grid">
		
		<div class="fpml-stat-card primary">
			<h3>📝 Post Tradotti</h3>
			<div class="fpml-stat-value"><?php echo number_format( $stats['translated_posts'] ); ?></div>
			<p style="margin: 0; color: #64748b; font-size: 13px;">Contenuti disponibili in inglese</p>
		</div>
		
		<div class="fpml-stat-card <?php echo $stats['pending_jobs'] > 0 ? 'warning' : 'success'; ?>">
			<h3>⏳ In Coda</h3>
			<div class="fpml-stat-value"><?php echo number_format( $stats['pending_jobs'] ); ?></div>
			<p style="margin: 0; color: #64748b; font-size: 13px;">Job in attesa di traduzione</p>
		</div>
		
		<div class="fpml-stat-card <?php echo $stats['failed_jobs'] > 0 ? 'danger' : 'success'; ?>">
			<h3>❌ Errori</h3>
			<div class="fpml-stat-value"><?php echo number_format( $stats['failed_jobs'] ); ?></div>
			<p style="margin: 0; color: #64748b; font-size: 13px;">Traduzioni fallite</p>
		</div>
		
		<div class="fpml-stat-card success">
			<h3>💰 Costo Mese</h3>
			<div class="fpml-stat-value">$<?php echo number_format( $stats['monthly_cost'], 2 ); ?></div>
			<p style="margin: 0; color: #64748b; font-size: 13px;"><?php echo date( 'F Y' ); ?></p>
		</div>
		
	</div>
	
	<!-- Quick Actions -->
	<div class="fpml-quick-actions">
		<a href="<?php echo admin_url( 'post-new.php' ); ?>" class="button button-primary button-hero">
			✏️ Crea Nuovo Post
		</a>
		<a href="<?php echo admin_url( 'admin.php?page=fpml-bulk-translate' ); ?>" class="button button-secondary button-hero">
			🚀 Traduci in Blocco
		</a>
		<a href="<?php echo admin_url( 'admin.php?page=fpml-settings&tab=diagnostics' ); ?>" class="button button-secondary button-hero">
			📊 Vedi Queue Completa
		</a>
		<a href="<?php echo admin_url( 'admin.php?page=fpml-settings&tab=general' ); ?>" class="button button-secondary button-hero">
			⚙️ Configurazione
		</a>
	</div>
	
	<!-- Ultimi 7 giorni -->
	<div class="fpml-section">
		<h2>📊 Attività Ultimi 7 Giorni</h2>
		
		<p style="font-size: 16px; margin: 15px 0;">
			<strong><?php echo number_format( $stats['weekly_count'] ); ?> traduzioni completate</strong>
		</p>
		
		<?php if ( $stats['weekly_trend'] != 0 ) : ?>
		<div class="fpml-trend <?php echo $stats['weekly_trend'] > 0 ? 'positive' : 'negative'; ?>">
			<?php echo $stats['weekly_trend'] > 0 ? '↑' : '↓'; ?> 
			<?php echo abs( $stats['weekly_trend'] ); ?>% rispetto alla settimana scorsa
		</div>
		<?php endif; ?>
		
		<div class="fpml-progress-bar">
			<div class="fpml-progress-fill" style="width: <?php echo min( 100, $stats['weekly_count'] * 2 ); ?>%;"></div>
		</div>
	</div>
	
	<!-- Alerts / Warnings -->
	<?php if ( ! $stats['api_key_set'] ) : ?>
	<div class="notice notice-warning inline" style="padding: 15px; margin-bottom: 20px;">
		<p>
			<strong>⚠️ API Key OpenAI Non Configurata</strong><br>
			Per iniziare a tradurre, devi configurare la tua API key di OpenAI.
		</p>
		<p>
			<a href="<?php echo admin_url( 'admin.php?page=fpml-settings&tab=general' ); ?>" class="button button-primary">
				Configura Adesso →
			</a>
			<a href="https://platform.openai.com/account/api-keys" target="_blank" class="button button-secondary">
				Ottieni API Key →
			</a>
		</p>
	</div>
	<?php endif; ?>
	
	<?php if ( $stats['failed_jobs'] > 0 ) : ?>
	<div class="fpml-section">
		<h2>⚠️ Attenzione: <?php echo absint( $stats['failed_jobs'] ); ?> Traduzion<?php echo $stats['failed_jobs'] > 1 ? 'i' : 'e'; ?> Fallite</h2>
		
		<?php if ( ! empty( $stats['recent_errors'] ) ) : ?>
		<ul class="fpml-error-list">
			<?php foreach ( $stats['recent_errors'] as $error ) : ?>
			<li class="fpml-error-item">
				<strong><?php echo esc_html( get_the_title( $error->object_id ) ); ?></strong> - 
				Campo: <code><?php echo esc_html( $error->field ); ?></code>
				<?php if ( ! empty( $error->error ) ) : ?>
				<code><?php echo esc_html( $error->error ); ?></code>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
		
		<p style="margin-top: 15px;">
			<a href="<?php echo admin_url( 'admin.php?page=fpml-settings&tab=diagnostics' ); ?>" class="button button-secondary">
				Vedi Tutti gli Errori →
			</a>
		</p>
	</div>
	<?php endif; ?>
	
	<!-- Quick Start Guide -->
	<div class="fpml-section">
		<h2>📚 Quick Start - Inizia Subito</h2>
		
		<p>Nuovo a FP Multilanguage? Ecco come iniziare in pochi minuti:</p>
		
		<div class="fpml-guide-links">
			<a href="<?php echo admin_url( 'admin.php?page=fpml-settings&tab=general' ); ?>" class="fpml-guide-link">
				<strong>1. Configura OpenAI</strong><br>
				Inserisci la tua API key per abilitare le traduzioni automatiche.
			</a>
			
			<a href="<?php echo admin_url( 'post-new.php' ); ?>" class="fpml-guide-link">
				<strong>2. Crea un Post</strong><br>
				Scrivi un articolo in italiano e pubblicalo.
			</a>
			
			<div class="fpml-guide-link" style="cursor: default;">
				<strong>3. Traduci Automaticamente</strong><br>
				Nella sidebar destra, clicca "🚀 Traduci in Inglese ORA".
			</div>
			
			<div class="fpml-guide-link" style="cursor: default;">
				<strong>4. Visualizza Risultato</strong><br>
				Il post tradotto sarà disponibile su <code>/en/titolo-post/</code>
			</div>
		</div>
		
		<p style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<strong>Hai bisogno di aiuto?</strong><br>
			Visita la <a href="<?php echo admin_url( 'admin.php?page=fpml-settings&tab=diagnostics' ); ?>">pagina Diagnostiche</a> 
			per verificare lo stato del sistema, oppure controlla la 
			<a href="https://github.com/francescopasseri/FP-Multilanguage/blob/main/docs/troubleshooting.md" target="_blank">documentazione completa</a>.
		</p>
	</div>
	
	<!-- System Info (compact) -->
	<div class="fpml-section" style="background: #f8fafc;">
		<h2>🔧 Info Sistema</h2>
		
		<table class="widefat" style="border: none; background: transparent;">
			<tbody>
				<tr>
					<td style="border: none; padding: 8px 12px;">
						<strong>Versione Plugin:</strong>
					</td>
					<td style="border: none; padding: 8px 12px;">
						<?php echo esc_html( FPML_PLUGIN_VERSION ); ?>
					</td>
				</tr>
			<tr style="background: #fff;">
				<td style="border: none; padding: 8px 12px;">
					<strong>Provider Traduzione:</strong>
				</td>
				<td style="border: none; padding: 8px 12px;">
					OpenAI GPT-5 nano
				</td>
			</tr>
				<tr>
					<td style="border: none; padding: 8px 12px;">
						<strong>Stato API:</strong>
					</td>
					<td style="border: none; padding: 8px 12px;">
						<?php if ( $stats['api_key_set'] ) : ?>
							<span style="color: #10b981;">✓ Configurata</span>
						<?php else : ?>
							<span style="color: #ef4444;">✗ Non Configurata</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr style="background: #fff;">
					<td style="border: none; padding: 8px 12px;">
						<strong>Routing /en/:</strong>
					</td>
					<td style="border: none; padding: 8px 12px;">
						<span style="color: #10b981;">✓ Attivo</span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	
</div>

