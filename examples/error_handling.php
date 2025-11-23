<?php
/**
 * Error handling.
 * Works both with Composer autoload or direct include.
 */

$composerAutoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($composerAutoload)) {
    // use Composer autoload if available
    require $composerAutoload;
} else {
    // Direct include of all needed files (without Composer)
    require __DIR__ . '/../src/GetExif.php';
    require __DIR__ . '/../src/ExifFormatter.php';
}

use Szalayk\GetExif\GetExif;

$exif = new GetExif();

try {
    $data = $exif->read(__DIR__ . '/nonexistent.jpg');
    print_r($data);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
