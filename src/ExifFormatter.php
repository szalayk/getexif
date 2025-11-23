<?php
namespace Szalayk\GetExif;

/**
 * ExifFormatter
 *
 * Helpers to convert EXIF raw numbers (rationals) to human readable strings
 * or plain numeric values (no units). The functions are static convenience wrappers.
 *
 * Examples:
 *   - "280/10" -> "28 mm" or "28"
 *   - "4600/1000" -> "4.6" (aperture -> f/4.6)
 *   - "10/1250" -> "1/125 s" or "1/125"
 */
class ExifFormatter
{
    /**
     * Convert a rational string like "280/10" to float.
     */
    public static function rationalToFloat(string $value): float
    {
        $value = trim($value);
        if ($value === '') return 0.0;
        if (strpos($value, '/') !== false) {
            [$num, $den] = explode('/', $value);
            $den = (float)($den ?: 1);
            if ($den == 0.0) return 0.0;
            return (float)$num / $den;
        }
        return (float)$value;
    }

    // ---------- FOCAL LENGTH ----------
    public static function focalLength(string $value): string
    {
        $v = self::rationalToFloat($value);
        return round($v) . ' mm';
    }
    public static function focalLengthNoUnit(string $value): string
    {
        $v = self::rationalToFloat($value);
        return (string) round($v);
    }

    // ---------- APERTURE ----------
    public static function aperture(string $value): string
    {
        $v = self::rationalToFloat($value);
        return 'f/' . round($v, 1);
    }
    public static function apertureNoUnit(string $value): string
    {
        $v = self::rationalToFloat($value);
        return (string) round($v, 1);
    }

    // ---------- SHUTTER SPEED ----------
    /**
     * Return human shutter string with units, e.g. "1/125 s" or "13 s".
     */
    public static function shutter(string $value): string
    {
        $sec = self::rationalToFloat($value);

        if ($sec <= 0) return '0 s';

        if ($sec >= 1) {
            return round($sec, 1) . ' s';
        }

        $den = round(1 / $sec);
        if ($den <= 0) return (string)$sec . ' s';
        return '1/' . $den . ' s';
    }

    /**
     * Return shutter without units, e.g. "1/125" or "13"
     */
    public static function shutterNoUnit(string $value): string
    {
        $sec = self::rationalToFloat($value);

        if ($sec <= 0) return '0';

        if ($sec >= 1) {
            return (string) round($sec, 1);
        }
        $den = round(1 / $sec);
        return '1/' . $den;
    }

    // ---------- ISO ----------
    public static function iso(string $value): string
    {
        $v = self::rationalToFloat($value);
        return 'ISO ' . round($v);
    }
    public static function isoNoUnit(string $value): string
    {
        $v = self::rationalToFloat($value);
        return (string) round($v);
    }
}
