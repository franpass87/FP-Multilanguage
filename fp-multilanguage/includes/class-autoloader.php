<?php
/**
 * PSR-4 Autoloader per FP Multilanguage
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.3.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader PSR-4 compatibile per le classi del plugin.
 *
 * @since 0.3.2
 */
class FPML_Autoloader {
	/**
	 * Namespace base del plugin.
	 *
	 * @var string
	 */
	protected $namespace_prefix = 'FPML_';

	/**
	 * Directory base delle classi.
	 *
	 * @var string
	 */
	protected $base_dir;

	/**
	 * Mappa delle classi caricate.
	 *
	 * @var array
	 */
	protected $loaded_classes = array();

	/**
	 * Costruttore.
	 *
	 * @param string $base_dir Directory base delle classi.
	 */
	public function __construct( $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . '/';
	}

	/**
	 * Registra l'autoloader.
	 *
	 * @return void
	 */
	public function register() {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Rimuove l'autoloader.
	 *
	 * @return void
	 */
	public function unregister() {
		spl_autoload_unregister( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload delle classi.
	 *
	 * @param string $class Nome completo della classe.
	 * @return bool True se la classe è stata caricata, false altrimenti.
	 */
	public function autoload( $class ) {
		// Controlla se la classe appartiene al nostro namespace
		if ( 0 !== strpos( $class, $this->namespace_prefix ) ) {
			return false;
		}

		// Se già caricata, salta
		if ( isset( $this->loaded_classes[ $class ] ) ) {
			return true;
		}

		// Converte il nome della classe in percorso file
		$file_path = $this->get_file_path( $class );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		require_once $file_path;

		$this->loaded_classes[ $class ] = true;

		return true;
	}

	/**
	 * Ottiene il percorso del file dalla classe.
	 *
	 * @param string $class Nome della classe.
	 * @return string|false Percorso del file o false.
	 */
	protected function get_file_path( $class ) {
		// Rimuove il prefisso del namespace
		$relative_class = substr( $class, strlen( $this->namespace_prefix ) );

		// Converte underscore in trattini per i nomi dei file
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		// Gestisce le sottodirectory (es. Provider_DeepL -> providers/)
		$parts = explode( '_', $relative_class );

		if ( count( $parts ) > 1 ) {
			$subdir = strtolower( $parts[0] );
			
			// Mappa speciale per alcuni namespace
			$subdir_map = array(
				'provider' => 'providers',
			);

			if ( isset( $subdir_map[ $subdir ] ) ) {
				$subdir = $subdir_map[ $subdir ];
				array_shift( $parts );
				$file_name = 'class-' . strtolower( str_replace( '_', '-', implode( '_', $parts ) ) ) . '.php';
				return $this->base_dir . $subdir . '/' . $file_name;
			}
		}

		return $this->base_dir . $file_name;
	}

	/**
	 * Ottiene l'elenco delle classi caricate.
	 *
	 * @return array
	 */
	public function get_loaded_classes() {
		return array_keys( $this->loaded_classes );
	}

	/**
	 * Verifica se una classe è stata caricata.
	 *
	 * @param string $class Nome della classe.
	 * @return bool
	 */
	public function is_loaded( $class ) {
		return isset( $this->loaded_classes[ $class ] );
	}
}