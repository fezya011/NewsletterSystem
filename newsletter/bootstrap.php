<?php
// bootstrap.php - loads everything needed

require_once __DIR__ . '/config.php';

// autoloading functions
$functionsDir = __DIR__ . '/functions';
$functionFiles = glob($functionsDir . '/*.php');
foreach ($functionFiles as $file) {
    require_once $file;
}

// autoload composer
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}