<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/stubs/wordpress.php';

use FPMultilanguage\Admin\Settings;

Settings::bootstrap_defaults();
