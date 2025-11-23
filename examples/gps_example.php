<?php
/**
 * EXIF reading example.
 * Works both with Composer autoload or direct include.
 * 
 * The included sample image (sample.jpg) does not contain GPS data.
 * If you want to see GPS extraction in action,
 * replace it with an image that includes GPS coordinates.
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

echo "GPS data:\n";

if (!empty($data['geolocation'])) {
    echo "Latitude:  " . $data['geolocation']['lat'] . "\n";
    echo "Longitude: " . $data['geolocation']['lng'] . "\n";
} else {
    echo "No GPS data in file.\n";
}
