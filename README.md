# GetExif

A lightweight, dependency-free EXIF reader with human-friendly formatting.
Designed for photographers who want clean, readable exposure data.

Created by **Krisztian Szalay**.

---

## Features

* Read EXIF metadata from JPEG files:
  * camera model
  * focal length
  * aperture
  * shutter speed
  * ISO
  * orientation
  * creation date
  * GPS (converted to decimal lat/lng)
* Returns **both raw** EXIF strings and **human-formatted** values.
* Exposure formatting modes:
  * **raw** – original EXIF values (`280/10`)
  * **human** – clean numeric values (`28`, `4.6`, `1/125`)
  * **human_with_units** – photographer-friendly (`28 mm`, `f/4.6`, `1/125 s`)
* Robust date extraction with fallback chain:
  **DateTimeOriginal → DateTimeDigitized → DateTime**
* Optional: keep full raw EXIF arrays in the output.
* Simple and tiny — ideal for hobby, portfolio, or micro-CMS photo systems.

---

## Requirements

* **PHP 8.1+**
* PHP **exif** extension
* PHP **gd** extension (optional, only needed for orientation tools)
* Composer

> Note: PNG, WebP and many web formats do *not* contain EXIF metadata.

---

## Installation

```bash
composer require szalayk/get-exif
```

---

## Basic usage

```php
use Szalayk\GetExif\GetExif;

$g = new GetExif();

// Read only exposure data, formatted with units
$data = $g->read('/path/to/photo.jpg', [
    'fields' => GetExif::FIELDS_EXPOSURE,
    'format' => GetExif::FORMAT_HUMAN_WITH_UNITS
]);

print_r($data);
```

---

## 📚 Full Example (all fields)

```php
$data = $g->read('/path/to/photo.jpg', [
    'fields' => GetExif::FIELDS_ALL,
    'format' => GetExif::FORMAT_HUMAN,
    'keep_raw_keys' => true
]);

echo $data['created'];       // "2020-05-26 14:32:18"
echo $data['camera'];        // "Nikon D850"
print_r($data['geolocation']); // ['lat' => ..., 'lng' => ...]
```

---

## API Details

### `read(string $filePath, array $options = []): array`

| Option          | Type | Default           | Description                               |
| --------------- | ---- | ----------------- | ----------------------------------------- |
| `fields`        | int  | `FIELDS_EXPOSURE` | Which EXIF groups to return.              |
| `format`        | int  | `FORMAT_RAW`      | Formatting style for exposure values.     |
| `keep_raw_keys` | bool | false             | Keep full, untouched EXIF keys in output. |

### Available field sets

| Constant                   | Description                          |
| -------------------------- | ------------------------------------ |
| `GetExif::FIELDS_EXPOSURE` | focal length, aperture, shutter, ISO |
| `GetExif::FIELDS_BASIC`    | camera, created, exposure            |
| `GetExif::FIELDS_ALL`      | all EXIF data the library supports   |

### Formatting modes

| Constant                  | Example                     | Description           |
| ------------------------- | --------------------------- | --------------------- |
| `FORMAT_RAW`              | `280/10`                    | untouched EXIF        |
| `FORMAT_HUMAN`            | `28`, `4.6`, `1/125`        | readable numbers      |
| `FORMAT_HUMAN_WITH_UNITS` | `28 mm`, `f/4.6`, `1/125 s` | photographer-friendly |

---

## Example Output

```json
{
  "camera": "Nikon D850",
  "created": "2020-05-26 14:32:18",
  "exposure": {
    "focal_length": {
      "raw": "280/10",
      "value": "28",
      "value_with_unit": "28 mm"
    },
    "aperture": {
      "raw": "46/10",
      "value": "4.6",
      "value_with_unit": "f/4.6"
    },
    "shutter_speed": {
      "raw": "10/1250",
      "value": "1/125",
      "value_with_unit": "1/125 s"
    },
    "iso": 400
  },
  "geolocation": {
    "lat": 47.12345,
    "lng": 19.98765
  }
}
```

---

## Error Handling

The library throws `GetExifException` for:

* file not found / unreadable
* not a JPEG file
* EXIF block missing or corrupted
* invalid EXIF format

All exceptions contain human-readable error messages.

---

## Tips

* For large uploads, configure
  `upload_max_filesize` and `post_max_size`.
* For web apps: consider adding auto-rotation from EXIF Orientation (feature in development).
* Best results when photographing in RAW+JPEG — some camera brands store richer EXIF in JPEG.

---

## Contributing

Pull requests are welcome.
For issues, bug reports or feature suggestions, please open a GitHub Issue.

---

## License

This project is released under the **MIT License**.
See the `LICENSE` file for full details.
