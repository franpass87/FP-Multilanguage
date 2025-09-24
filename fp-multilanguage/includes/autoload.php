<?php

spl_autoload_register(
	static function ( string $className ): void {
		$prefix = 'FPMultilanguage\\';

		if ( 0 !== strpos( $className, $prefix ) ) {
			return;
		}

		$relative_class = substr( $className, strlen( $prefix ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
