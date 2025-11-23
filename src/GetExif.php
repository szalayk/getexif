<?php
namespace Szalayk\GetExif;

use DateTime;
use Exception;

/**
 * Class GetExif
 *
 * Read EXIF metadata from JPEG files and expose either all fields or
 * only exposure-related fields. Returns raw values and formatted values.
 *
 * Usage:
 *   $g = new GetExif();
 *   $data = $g->read($path, ['fields' => 'exposure', 'format' => GetExif::FORMAT_HUMAN_WITH_UNITS]);
 *
 * @package Szalayk\GetExif
 */
class GetExif
{
    // format modes
    public const FORMAT_RAW = 'raw';
    public const FORMAT_HUMAN = 'human';
    public const FORMAT_HUMAN_WITH_UNITS = 'human_unit';

    // fields options
    public const FIELDS_ALL = 'all';
    public const FIELDS_EXPOSURE = 'exposure';

    /**
     * Read EXIF values from a JPEG file.
     *
     * @param string $filepath Path to jpeg file.
     * @param array $opts Options:
     *    - fields: 'all' or 'exposure' (default 'all')
     *    - format: FORMAT_RAW | FORMAT_HUMAN | FORMAT_HUMAN_WITH_UNITS (default FORMAT_HUMAN_WITH_UNITS)
     *    - keep_raw_keys: bool keep all original EXIF keys (default false)
     *
     * @return array Associative array with keys:
     *   - raw: array of raw exif tags (if requested)
     *   - exposure: array (if requested) with both raw and formatted values for focal_length, aperture, shutter_speed, iso
     *   - other common fields (camera, orientation, geolocation, created)
     *
     * @throws Exception if file missing or exif functions not available.
     */
    public function read(string $filepath, array $opts = []): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new Exception("File not found or not readable: {$filepath}");
        }

        if (!function_exists('exif_read_data')) {
            throw new Exception("PHP EXIF functions are not available (exif_read_data missing).");
        }

        $fields = $opts['fields'] ?? self::FIELDS_ALL;
        $format = $opts['format'] ?? self::FORMAT_HUMAN_WITH_UNITS;
        $keepRawKeys = $opts['keep_raw_keys'] ?? false;

        // Read ANY_TAG to get as much as possible, grouped
        $rawAll = @exif_read_data($filepath, 'ANY_TAG', true);

        // Normalize safe getter
        $get = function (array $arr, string $group, string $key) {
            return $arr[$group][$key] ?? null;
        };

        $result = [];

        // Common high-level fields
        $result['camera'] = $get($rawAll ?? [], 'IFD0', 'Model') ?: null;
        $result['orientation'] = $get($rawAll ?? [], 'IFD0', 'Orientation') ?: null;

        // geolocation (if present under GPS)
        if (!empty($rawAll['GPS'])) {
            $result['geolocation'] = $this->extractGps($rawAll['GPS']);
        } else {
            $result['geolocation'] = null;
        }

        // created date (use helper order: DateTimeOriginal, DateTimeDigitized, DateTime)
        $result['created'] = $this->getExifDateFromRaw($rawAll);

        // If user wants raw keys kept optionally
        if ($keepRawKeys) {
            $result['raw_all'] = $rawAll;
        }

        // Exposure-related fields
        if ($fields === self::FIELDS_ALL || $fields === self::FIELDS_EXPOSURE) {
            $rawFocal = $get($rawAll ?? [], 'EXIF', 'FocalLength') ?? $get($rawAll ?? [], 'IFD0', 'FocalLength');
            $rawAperture = $get($rawAll ?? [], 'EXIF', 'FNumber') ?? $get($rawAll ?? [], 'IFD0', 'FNumber');
            $rawShutter = $get($rawAll ?? [], 'EXIF', 'ExposureTime') ?? $get($rawAll ?? [], 'EXIF', 'ShutterSpeedValue') ?? null;
            $rawIso = $get($rawAll ?? [], 'EXIF', 'ISOSpeedRatings') ?? $get($rawAll ?? [], 'EXIF', 'PhotographicSensitivity') ?? null;

            $exposure = [
                'focal_length' => ['raw' => $rawFocal, 'value' => null],
                'aperture' => ['raw' => $rawAperture, 'value' => null],
                'shutter_speed' => ['raw' => $rawShutter, 'value' => null],
                'iso' => ['raw' => $rawIso, 'value' => null],
            ];

            // Format numeric / human values
            foreach ($exposure as $k => &$v) {
                if ($v['raw'] === null) {
                    $v['value'] = null;
                    continue;
                }

                // Two-tier: keep raw; compute formatted according to requested format
                switch ($k) {
                    case 'focal_length':
                        $v['value'] = $this->formatValue('focal', $v['raw'], $format);
                        break;
                    case 'aperture':
                        $v['value'] = $this->formatValue('aperture', $v['raw'], $format);
                        break;
                    case 'shutter_speed':
                        $v['value'] = $this->formatValue('shutter', $v['raw'], $format);
                        break;
                    case 'iso':
                        $v['value'] = $this->formatValue('iso', $v['raw'], $format);
                        break;
                }
            }
            unset($v);

            $result['exposure'] = $exposure;
        }

        // Optionally include other useful EXIF tags
        if ($fields === self::FIELDS_ALL) {
            $result['lens'] = $get($rawAll ?? [], 'EXIF', 'LensModel') ?? $get($rawAll ?? [], 'IFD0', 'LensModel') ?? null;
            $result['make'] = $get($rawAll ?? [], 'IFD0', 'Make') ?? null;
        }

        return $result;
    }

    /**
     * Format a single exposure value using ExifFormatter and return appropriate representation.
     *
     * @param string $type 'focal'|'aperture'|'shutter'|'iso'
     * @param mixed $raw Raw EXIF value (often string like "280/10" or numeric)
     * @param string $format FORMAT_RAW|FORMAT_HUMAN|FORMAT_HUMAN_WITH_UNITS
     * @return mixed
     */
    protected function formatValue(string $type, $raw, string $format)
    {
        // if user requested raw, return as-is
        if ($format === self::FORMAT_RAW) {
            return $raw;
        }

        // normalize raw to string
        $rawStr = is_string($raw) ? $raw : (string)$raw;

        switch ($type) {
            case 'focal':
                $human = ExifFormatter::focalLength($rawStr);          // e.g. "28 mm"
                $noUnit = ExifFormatter::focalLengthNoUnit($rawStr);  // e.g. "28"
                return $format === self::FORMAT_HUMAN ? $noUnit : $human;

            case 'aperture':
                $human = ExifFormatter::aperture($rawStr);            // e.g. "f/4.6"
                $noUnit = ExifFormatter::apertureNoUnit($rawStr);    // e.g. "4.6"
                return $format === self::FORMAT_HUMAN ? $noUnit : $human;

            case 'shutter':
                $human = ExifFormatter::shutter($rawStr);            // e.g. "1/125 s" or "13 s"
                $noUnit = ExifFormatter::shutterNoUnit($rawStr);    // e.g. "1/125" or "13"
                return $format === self::FORMAT_HUMAN ? $noUnit : $human;

            case 'iso':
                $human = ExifFormatter::iso($rawStr);                // e.g. "ISO 200"
                $noUnit = ExifFormatter::isoNoUnit($rawStr);        // e.g. "200"
                return $format === self::FORMAT_HUMAN ? $noUnit : $human;
        }

        return $raw;
    }

    /**
     * Extract a normalized creation datetime string from raw exif array.
     * Order of preference: DateTimeOriginal, DateTimeDigitized, IFD0 DateTime.
     *
     * Returns "YYYY-MM-DD HH:MM:SS" or null if not found / unparsable.
     *
     * @param array|null $rawAll
     * @return string|null
     */
    protected function getExifDateFromRaw(?array $rawAll): ?string
    {
        if (empty($rawAll)) {
            return null;
        }
        $get = function ($arr, $group, $key) {
            return $arr[$group][$key] ?? null;
        };

        $candidates = [
            $get($rawAll, 'EXIF', 'DateTimeOriginal'),
            $get($rawAll, 'EXIF', 'DateTimeDigitized'),
            $get($rawAll, 'IFD0', 'DateTime'),
        ];

        foreach ($candidates as $c) {
            if (empty($c)) continue;
            $dt = $this->convertExifDate($c);
            if ($dt !== null) return $dt;
        }
        return null;
    }

    /**
     * Convert EXIF date string "YYYY:MM:DD HH:MM:SS" to "YYYY-MM-DD HH:MM:SS"
     *
     * @param string $exifDate
     * @return string|null
     */
    protected function convertExifDate(string $exifDate): ?string
    {
        // Typical EXIF date: "2020:05:26 14:32:18"
        $exifDate = trim($exifDate);
        // sometimes there are sub-seconds or different separators, sanitize:
        $parts = preg_split('/\s+/', $exifDate);
        if (!$parts) return null;
        $datePart = $parts[0] ?? '';
        $timePart = $parts[1] ?? '00:00:00';

        $datePart = str_replace(':', '-', $datePart);
        $candidate = $datePart . ' ' . $timePart;

        // final validation
        try {
            $d = new DateTime($candidate);
            return $d->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract GPS latitude/longitude if present in the raw GPS EXIF block.
     * Returns ['lat' => float, 'lng' => float] or null.
     *
     * @param array $gps
     * @return array|null
     */
    protected function extractGps(array $gps): ?array
    {
        if (empty($gps['GPSLatitude']) || empty($gps['GPSLongitude'])) return null;
        // helper to convert rational arrays to float
        $convert = function ($coord) {
            // $coord is array of 3 rational strings or arrays
            $d = $this->rationalToFloatSafe($coord[0] ?? '0/1');
            $m = $this->rationalToFloatSafe($coord[1] ?? '0/1');
            $s = $this->rationalToFloatSafe($coord[2] ?? '0/1');
            return $d + ($m / 60.0) + ($s / 3600.0);
        };

        $lat = $convert($gps['GPSLatitude']);
        $lng = $convert($gps['GPSLongitude']);

        // Hemispheres
        if (!empty($gps['GPSLatitudeRef']) && strtolower($gps['GPSLatitudeRef']) === 's') $lat = -$lat;
        if (!empty($gps['GPSLongitudeRef']) && strtolower($gps['GPSLongitudeRef']) === 'w') $lng = -$lng;

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Helper - convert a rational (string or array) to float safely.
     *
     * Accepts:
     *  - "280/10"
     *  - ["280/10"] or [280,10]
     *
     * @param mixed $val
     * @return float
     */
    protected function rationalToFloatSafe($val): float
    {
        // if array like [num, den]
        if (is_array($val) && count($val) === 2 && is_numeric($val[0]) && is_numeric($val[1])) {
            $den = (float)$val[1];
            if ($den == 0) return 0.0;
            return (float)$val[0] / $den;
        }

        // if array like ["280/10", ...] pick first
        if (is_array($val) && isset($val[0]) && is_string($val[0])) {
            $val = $val[0];
        }

        if (is_string($val) && strpos($val, '/') !== false) {
            [$n, $d] = explode('/', $val);
            $d = (float)($d ?: 1);
            if ($d == 0) return 0.0;
            return (float)$n / $d;
        }

        return (float)$val;
    }

}
