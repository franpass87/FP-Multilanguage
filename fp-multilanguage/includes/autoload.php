<?php

spl_autoload_register(
        static function ( string $class ): void {
                $prefix = 'FPMultilanguage\\';

                if ( 0 !== strpos( $class, $prefix ) ) {
                        return;
                }

                $relative_class = substr( $class, strlen( $prefix ) );
                $file            = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

                if ( is_readable( $file ) ) {
                        require_once $file;
                }
        }
);
