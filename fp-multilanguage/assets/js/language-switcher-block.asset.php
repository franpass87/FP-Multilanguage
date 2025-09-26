<?php
$dependencies = array(
        'wp-blocks',
        'wp-element',
        'wp-i18n',
        'wp-components',
        'wp-block-editor',
        'wp-server-side-render',
);

$version = false;
if ( file_exists( __DIR__ . '/language-switcher-block.js' ) ) {
        $version = filemtime( __DIR__ . '/language-switcher-block.js' );
}

if ( ! $version ) {
        $version = defined( 'FP_MULTILANGUAGE_VERSION' ) ? FP_MULTILANGUAGE_VERSION : '1.0.0';
}

return array(
        'dependencies' => $dependencies,
        'version'      => $version,
);
