<?php
/**
 * Metabox Diagnostic Tool
 *
 * Verifica il corretto funzionamento del metabox di traduzione.
 *
 * Usage (in browser):
 *   Aggiungi ?fpml_diagnose_metabox=1 a qualsiasi pagina admin
 *   Es: /wp-admin/index.php?fpml_diagnose_metabox=1
 *
 * Usage (CLI):
 *   wp eval-file tools/diagnose-metabox.php
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @since 0.9.2
 */

// Load WordPress if running standalone
if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( __DIR__ ) . '/../../../wp-load.php';
	if ( file_exists( $wp_load ) ) {
		require_once $wp_load;
	} else {
		die( 'WordPress not found.' );
	}
}

/**
 * Run metabox diagnostics.
 *
 * @return array Diagnostic results.
 */
function fpml_diagnose_metabox(): array {
	$results = array(
		'status'   => 'ok',
		'checks'   => array(),
		'errors'   => array(),
		'warnings' => array(),
	);

	// 1. Check autoload
	$autoload_path = FPML_PLUGIN_DIR . 'vendor/autoload.php';
	$results['checks']['autoload'] = array(
		'label'  => 'Composer Autoload',
		'status' => file_exists( $autoload_path ) ? 'ok' : 'error',
		'value'  => file_exists( $autoload_path ) ? 'Presente' : 'MANCANTE',
	);
	if ( ! file_exists( $autoload_path ) ) {
		$results['errors'][] = 'Composer autoload mancante. Esegui: composer install --no-dev';
		$results['status'] = 'error';
	}

	// 2. Check Kernel Plugin instance
	$kernel_exists = class_exists( '\FP\Multilanguage\Kernel\Plugin' );
	$kernel_instance = $kernel_exists ? \FP\Multilanguage\Kernel\Plugin::getInstance() : null;
	$results['checks']['kernel'] = array(
		'label'  => 'Kernel Plugin',
		'status' => $kernel_instance ? 'ok' : 'error',
		'value'  => $kernel_instance ? 'Inizializzato' : 'NON inizializzato',
	);
	if ( ! $kernel_instance ) {
		$results['errors'][] = 'Il Kernel del plugin non è stato inizializzato. Verifica errori PHP nel log.';
		$results['status'] = 'error';
	}

	// 3. Check Container
	$container = null;
	if ( $kernel_instance ) {
		$container = $kernel_instance->getContainer();
	}
	$results['checks']['container'] = array(
		'label'  => 'Service Container',
		'status' => $container ? 'ok' : 'error',
		'value'  => $container ? 'Disponibile' : 'NON disponibile',
	);

	// 4. Check TranslationMetabox class
	$metabox_class_exists = class_exists( '\FP\Multilanguage\Admin\TranslationMetabox' );
	$results['checks']['metabox_class'] = array(
		'label'  => 'Classe TranslationMetabox',
		'status' => $metabox_class_exists ? 'ok' : 'error',
		'value'  => $metabox_class_exists ? 'Caricata' : 'NON trovata',
	);

	// 5. Check if TranslationMetabox is in container
	$metabox_in_container = $container && $container->has( 'admin.translation_metabox' );
	$results['checks']['metabox_container'] = array(
		'label'  => 'Metabox nel Container',
		'status' => $metabox_in_container ? 'ok' : 'warning',
		'value'  => $metabox_in_container ? 'Registrato' : 'NON registrato',
	);
	if ( ! $metabox_in_container ) {
		$results['warnings'][] = 'TranslationMetabox non è registrato nel container. Potrebbe non essere stato inizializzato.';
	}

	// 6. Check if TranslationMetabox instance exists
	$metabox_instance = null;
	if ( $metabox_class_exists ) {
		try {
			$reflection = new ReflectionClass( '\FP\Multilanguage\Admin\TranslationMetabox' );
			$property = $reflection->getProperty( 'instance' );
			$property->setAccessible( true );
			$metabox_instance = $property->getValue();
		} catch ( Exception $e ) {
			// Ignore reflection errors
		}
	}
	$results['checks']['metabox_instance'] = array(
		'label'  => 'Istanza TranslationMetabox',
		'status' => $metabox_instance ? 'ok' : 'warning',
		'value'  => $metabox_instance ? 'Creata' : 'NON creata',
	);
	if ( ! $metabox_instance ) {
		$results['warnings'][] = 'TranslationMetabox::instance() non è mai stato chiamato. Il metabox non sarà visibile.';
	}

	// 7. Check add_meta_boxes hook
	$metabox_hook_registered = has_action( 'add_meta_boxes' );
	$results['checks']['metabox_hook'] = array(
		'label'  => 'Hook add_meta_boxes',
		'status' => $metabox_hook_registered ? 'ok' : 'warning',
		'value'  => $metabox_hook_registered ? "Registrato (priority count: {$metabox_hook_registered})" : 'NON registrato',
	);

	// 8. Check LanguageManager
	$language_manager = null;
	if ( function_exists( 'fpml_get_language_manager' ) ) {
		$language_manager = fpml_get_language_manager();
	}
	$results['checks']['language_manager'] = array(
		'label'  => 'LanguageManager',
		'status' => $language_manager ? 'ok' : 'error',
		'value'  => $language_manager ? 'Disponibile' : 'NON disponibile',
	);
	if ( ! $language_manager ) {
		$results['errors'][] = 'LanguageManager non disponibile. Il metabox non può funzionare.';
		$results['status'] = 'error';
	}

	// 9. Check enabled languages
	$enabled_languages = array();
	if ( $language_manager ) {
		$enabled_languages = $language_manager->get_enabled_languages();
	}
	$results['checks']['enabled_languages'] = array(
		'label'  => 'Lingue abilitate',
		'status' => ! empty( $enabled_languages ) ? 'ok' : 'warning',
		'value'  => ! empty( $enabled_languages ) ? implode( ', ', $enabled_languages ) : 'Nessuna',
	);
	if ( empty( $enabled_languages ) ) {
		$results['warnings'][] = 'Nessuna lingua abilitata. Vai in FP Multilanguage > Impostazioni per abilitare le lingue.';
	}

	// 10. Check TranslationManager
	$translation_manager = null;
	if ( function_exists( 'fpml_get_translation_manager' ) ) {
		$translation_manager = fpml_get_translation_manager();
	}
	$results['checks']['translation_manager'] = array(
		'label'  => 'TranslationManager',
		'status' => $translation_manager ? 'ok' : 'error',
		'value'  => $translation_manager ? 'Disponibile' : 'NON disponibile',
	);
	if ( ! $translation_manager ) {
		$results['errors'][] = 'TranslationManager non disponibile. Il metabox non può verificare le traduzioni esistenti.';
		$results['status'] = 'error';
	}

	// 11. Check if is_admin
	$results['checks']['is_admin'] = array(
		'label'  => 'is_admin()',
		'status' => is_admin() ? 'ok' : 'warning',
		'value'  => is_admin() ? 'true' : 'false',
	);
	if ( ! is_admin() ) {
		$results['warnings'][] = 'Il metabox viene caricato solo in admin. Verifica di essere loggato come admin.';
	}

	// 12. Check current user capabilities
	$can_edit_posts = current_user_can( 'edit_posts' );
	$results['checks']['user_capabilities'] = array(
		'label'  => 'Permesso edit_posts',
		'status' => $can_edit_posts ? 'ok' : 'warning',
		'value'  => $can_edit_posts ? 'Sì' : 'No',
	);
	if ( ! $can_edit_posts ) {
		$results['warnings'][] = "L'utente corrente non ha il permesso edit_posts. Potrebbe non vedere il metabox.";
	}

	// 13. Check PHP version
	$php_version = PHP_VERSION;
	$php_ok = version_compare( $php_version, '8.0.0', '>=' );
	$results['checks']['php_version'] = array(
		'label'  => 'Versione PHP',
		'status' => $php_ok ? 'ok' : 'error',
		'value'  => $php_version . ( $php_ok ? '' : ' (Richiesto >= 8.0)' ),
	);
	if ( ! $php_ok ) {
		$results['errors'][] = 'PHP 8.0+ è richiesto. Versione attuale: ' . $php_version;
		$results['status'] = 'error';
	}

	// 14. Check WordPress registered metaboxes (only if in post editor context)
	global $wp_meta_boxes;
	$metabox_found = false;
	if ( ! empty( $wp_meta_boxes ) ) {
		foreach ( $wp_meta_boxes as $screen_id => $contexts ) {
			foreach ( $contexts as $context => $priorities ) {
				foreach ( $priorities as $priority => $boxes ) {
					if ( isset( $boxes['fpml_translation_status'] ) ) {
						$metabox_found = true;
						break 3;
					}
				}
			}
		}
	}
	$results['checks']['metabox_registered'] = array(
		'label'  => 'Metabox registrato in WP',
		'status' => $metabox_found ? 'ok' : 'info',
		'value'  => $metabox_found ? 'Trovato' : 'Non ancora (normale fuori dal post editor)',
	);

	// Determine overall status
	if ( ! empty( $results['errors'] ) ) {
		$results['status'] = 'error';
	} elseif ( ! empty( $results['warnings'] ) ) {
		$results['status'] = 'warning';
	}

	return $results;
}

/**
 * Output diagnostic results as HTML.
 *
 * @param array $results Diagnostic results.
 * @return void
 */
function fpml_output_metabox_diagnostics_html( array $results ): void {
	$status_colors = array(
		'ok'      => '#46b450',
		'warning' => '#ffb900',
		'error'   => '#dc3232',
		'info'    => '#0073aa',
	);

	$status_icons = array(
		'ok'      => '✅',
		'warning' => '⚠️',
		'error'   => '❌',
		'info'    => 'ℹ️',
	);

	?>
	<div class="wrap">
		<h1>🔍 FP Multilanguage - Diagnostica Metabox</h1>
		
		<div style="background: <?php echo esc_attr( $status_colors[ $results['status'] ] ); ?>; color: white; padding: 15px; border-radius: 4px; margin: 20px 0;">
			<h2 style="margin: 0; color: white;">
				<?php echo esc_html( $status_icons[ $results['status'] ] ); ?>
				Stato complessivo: <?php echo esc_html( strtoupper( $results['status'] ) ); ?>
			</h2>
		</div>

		<h2>Verifiche dettagliate</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Componente</th>
					<th>Stato</th>
					<th>Valore</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $results['checks'] as $check ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
						<td style="color: <?php echo esc_attr( $status_colors[ $check['status'] ] ); ?>;">
							<?php echo esc_html( $status_icons[ $check['status'] ] ); ?>
							<?php echo esc_html( ucfirst( $check['status'] ) ); ?>
						</td>
						<td><?php echo esc_html( $check['value'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $results['errors'] ) ) : ?>
			<h2 style="color: <?php echo esc_attr( $status_colors['error'] ); ?>;">❌ Errori</h2>
			<ul style="background: #fff; padding: 15px 30px; border-left: 4px solid <?php echo esc_attr( $status_colors['error'] ); ?>;">
				<?php foreach ( $results['errors'] as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $results['warnings'] ) ) : ?>
			<h2 style="color: <?php echo esc_attr( $status_colors['warning'] ); ?>;">⚠️ Avvertimenti</h2>
			<ul style="background: #fff; padding: 15px 30px; border-left: 4px solid <?php echo esc_attr( $status_colors['warning'] ); ?>;">
				<?php foreach ( $results['warnings'] as $warning ) : ?>
					<li><?php echo esc_html( $warning ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2>📋 Come risolvere i problemi comuni</h2>
		<div style="background: #fff; padding: 15px; border: 1px solid #ccc;">
			<h3>1. Composer autoload mancante</h3>
			<pre style="background: #f0f0f0; padding: 10px;">cd /path/to/wp-content/plugins/FP-Multilanguage
composer install --no-dev --optimize-autoloader</pre>
			
			<h3>2. Metabox non visibile in Gutenberg</h3>
			<p>In Gutenberg, i metabox tradizionali sono mostrati in fondo alla pagina. Per visualizzarli:</p>
			<ol>
				<li>Clicca sui <strong>tre puntini</strong> (⋮) in alto a destra</li>
				<li>Seleziona <strong>Preferenze</strong></li>
				<li>Vai nella sezione <strong>Pannelli</strong></li>
				<li>Abilita <strong>Traduzioni</strong></li>
			</ol>
			<p>Oppure usa il <strong>Classic Editor</strong> dove il metabox appare nella sidebar destra.</p>
			
			<h3>3. Nessuna lingua abilitata</h3>
			<p>Vai in <strong>FP Multilanguage > Impostazioni</strong> e abilita almeno una lingua target (es. Inglese, Tedesco).</p>
		</div>
	</div>
	<?php
}

/**
 * Output diagnostic results as CLI.
 *
 * @param array $results Diagnostic results.
 * @return void
 */
function fpml_output_metabox_diagnostics_cli( array $results ): void {
	$status_icons = array(
		'ok'      => '✅',
		'warning' => '⚠️',
		'error'   => '❌',
		'info'    => 'ℹ️',
	);

	echo "\n";
	echo "╔════════════════════════════════════════════════╗\n";
	echo "║  FP Multilanguage - Diagnostica Metabox       ║\n";
	echo "╚════════════════════════════════════════════════╝\n\n";

	echo "Stato complessivo: {$status_icons[$results['status']]} " . strtoupper( $results['status'] ) . "\n\n";

	echo "Verifiche:\n";
	echo str_repeat( '-', 60 ) . "\n";
	
	foreach ( $results['checks'] as $check ) {
		$icon = $status_icons[ $check['status'] ];
		printf( "%s %-30s %s\n", $icon, $check['label'] . ':', $check['value'] );
	}

	if ( ! empty( $results['errors'] ) ) {
		echo "\n❌ Errori:\n";
		foreach ( $results['errors'] as $error ) {
			echo "  • $error\n";
		}
	}

	if ( ! empty( $results['warnings'] ) ) {
		echo "\n⚠️ Avvertimenti:\n";
		foreach ( $results['warnings'] as $warning ) {
			echo "  • $warning\n";
		}
	}

	echo "\n";
}

// Hook into admin for web-based diagnostics
add_action( 'admin_init', function() {
	if ( isset( $_GET['fpml_diagnose_metabox'] ) && current_user_can( 'manage_options' ) ) {
		$results = fpml_diagnose_metabox();
		fpml_output_metabox_diagnostics_html( $results );
		exit;
	}
} );

// Run if called directly via CLI
if ( php_sapi_name() === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	$results = fpml_diagnose_metabox();
	fpml_output_metabox_diagnostics_cli( $results );
	
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		if ( $results['status'] === 'ok' ) {
			WP_CLI::success( 'Tutti i controlli passati!' );
		} elseif ( $results['status'] === 'warning' ) {
			WP_CLI::warning( 'Controlli passati con avvertimenti.' );
		} else {
			WP_CLI::error( 'Controlli falliti!' );
		}
	}
}
