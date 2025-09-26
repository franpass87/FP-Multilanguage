<?php
namespace FPMultilanguage\Services;

use Throwable;

class RuntimeLogger {

	private Logger $logger;

		/**
		 * @var callable|null
		 */
		private $previousErrorHandler = null;

		/**
		 * @var callable|null
		 */
		private $previousExceptionHandler = null;

	private bool $registered = false;

	public function __construct( Logger $logger ) {
			$this->logger = $logger;
	}

	public function register(): void {
		if ( $this->registered ) {
				return;
		}

			$this->previousErrorHandler     = set_error_handler( array( $this, 'handle_error' ) );
			$this->previousExceptionHandler = set_exception_handler( array( $this, 'handle_exception' ) );

		if ( function_exists( 'add_action' ) ) {
				add_action( 'doing_it_wrong_run', array( $this, 'handle_doing_it_wrong' ), 10, 3 );
				add_action( 'deprecated_function_run', array( $this, 'handle_deprecated_function' ), 10, 3 );
				add_action( 'deprecated_argument_run', array( $this, 'handle_deprecated_argument' ), 10, 3 );
				add_action( 'deprecated_hook_run', array( $this, 'handle_deprecated_hook' ), 10, 3 );
		}

			$this->registered = true;
	}

	public function handle_error( int $errno, string $message, ?string $file = null, ?int $line = null ): bool {
		if ( 0 === ( error_reporting() & $errno ) ) {
				return $this->delegate_error_handler( $errno, $message, $file, $line );
		}

			$level   = $this->resolve_level( $errno );
			$context = $this->format_context( $file, $line );

		switch ( $level ) {
			case 'error':
					$this->logger->error( $message, $context );
				break;
			case 'warning':
					$this->logger->warning( $message, $context );
				break;
			default:
					$this->logger->info( $message, $context );
		}

			return $this->delegate_error_handler( $errno, $message, $file, $line );
	}

	public function handle_exception( Throwable $exception ): void {
			$this->logger->error(
				sprintf( 'Uncaught %s: %s', get_class( $exception ), $exception->getMessage() ),
				array(
					'file' => $this->normalize_path( $exception->getFile() ),
					'line' => $exception->getLine(),
				)
			);

		if ( is_callable( $this->previousExceptionHandler ) ) {
				call_user_func( $this->previousExceptionHandler, $exception );
		}
	}

	public function handle_doing_it_wrong( string $function_name, string $message, string $version ): void {
		$this->logger->warning(
			sprintf( '%s was called incorrectly. %s', $function_name, $message ),
			array( 'version' => $version )
		);
	}

	public function handle_deprecated_function( string $function_name, string $replacement, string $version ): void {
		$this->logger->warning(
			sprintf( 'Deprecated function %s called', $function_name ),
			array(
				'replacement' => $replacement,
				'version'     => $version,
			)
		);
	}

	public function handle_deprecated_argument( string $function_name, string $message, string $version ): void {
		$this->logger->warning(
			sprintf( 'Deprecated argument in %s', $function_name ),
			array(
				'message' => $message,
				'version' => $version,
			)
		);
	}

	public function handle_deprecated_hook( string $hook, string $replacement, string $version ): void {
			$this->logger->warning(
				sprintf( 'Deprecated hook %s used', $hook ),
				array(
					'replacement' => $replacement,
					'version'     => $version,
				)
			);
	}

	private function delegate_error_handler( int $errno, string $message, ?string $file, ?int $line ): bool {
		if ( is_callable( $this->previousErrorHandler ) ) {
				return (bool) call_user_func( $this->previousErrorHandler, $errno, $message, $file, $line );
		}

			return false;
	}

	private function resolve_level( int $errno ): string {
		switch ( $errno ) {
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				return 'error';
			case E_WARNING:
			case E_USER_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
				return 'warning';
			default:
				return 'notice';
		}
	}

	private function format_context( ?string $file, ?int $line ): array {
			return array(
				'file' => $this->normalize_path( $file ),
				'line' => $line ?? 0,
			);
	}

	private function normalize_path( ?string $file ): string {
		if ( ! is_string( $file ) || '' === $file ) {
				return 'unknown';
		}

		if ( defined( 'ABSPATH' ) ) {
				return ltrim( str_replace( ABSPATH, '', $file ), '/' );
		}

			return $file;
	}
}
