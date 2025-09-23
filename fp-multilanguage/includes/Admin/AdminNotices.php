<?php
namespace FPMultilanguage\Admin;

use FPMultilanguage\Services\Logger;

class AdminNotices {

	/**
	 * @var array<int, array{message:string,type:string,dismissible:bool}>
	 */
	private array $notices = array();

	private Logger $logger;

	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function add_notice( string $message, string $type = 'info', bool $dismissible = true ): void {
		$type            = in_array( $type, array( 'info', 'warning', 'error', 'success' ), true ) ? $type : 'info';
		$this->notices[] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $dismissible,
		);

		if ( $type === 'error' ) {
			$this->logger->error( $message );
		} else {
			$this->logger->info( $message );
		}
	}

	public function add_error( string $message ): void {
		$this->add_notice( $message, 'error', false );
	}

	public function render(): void {
		if ( empty( $this->notices ) ) {
			return;
		}

		foreach ( $this->notices as $notice ) {
			$classes = array( 'notice', 'notice-' . $notice['type'] );
			if ( $notice['dismissible'] ) {
				$classes[] = 'is-dismissible';
			}

			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( implode( ' ', $classes ) ),
				wp_kses_post( $notice['message'] )
			);
		}

		$this->notices = array();
	}
}
