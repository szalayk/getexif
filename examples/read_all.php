<?php
/**
 * EXIF reading example.
 * Works both with Composer autoload or direct include.
 */

$filepath = __DIR__ . '/sample.jpg';

if (!file_exists($filepath)) {
    die("File not found: $filepath\n");
}

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

$data = $exif->read($filepath, [
    'fields' => GetExif::FIELDS_ALL,
    'format' => GetExif::FORMAT_HUMAN
]);

echo "All EXIF data:\n";
print_r($data);
