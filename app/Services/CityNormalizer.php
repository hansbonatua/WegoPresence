<?php

namespace App\Services;

final class CityNormalizer
{
    /**
     * Aliases of the DKI Jakarta special capital region, each mapping to
     * the canonical "DKI Jakarta". Keys are lower-cased, whitespace- and
     * punctuation-normalized values.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'jakarta pusat' => 'dki jakarta',
        'central jakarta' => 'dki jakarta',
        'jakarta timur' => 'dki jakarta',
        'east jakarta' => 'dki jakarta',
        'jakarta selatan' => 'dki jakarta',
        'south jakarta' => 'dki jakarta',
        'jakarta barat' => 'dki jakarta',
        'west jakarta' => 'dki jakarta',
        'jakarta utara' => 'dki jakarta',
        'north jakarta' => 'dki jakarta',
        'kepulauan seribu' => 'dki jakarta',
        'thousand islands' => 'dki jakarta',
        'jakarta' => 'dki jakarta',
        'dki jakarta' => 'dki jakarta',
        'daerah khusus ibukota jakarta' => 'dki jakarta',
        'special capital region of jakarta' => 'dki jakarta',
    ];

    /**
     * Normalize a city name so that administrative variations of the same
     * region compare equal, without making distinct cities match.
     *
     * Examples:
     *  - "Jakarta Pusat"            -> "dki jakarta"
     *  - "Kota Administrasi Jakarta Pusat" -> "dki jakarta"
     *  - "Central Jakarta"          -> "dki jakarta"
     *  - "Bandar Lampung"           -> "bandar lampung"
     *  - "Bekasi"                   -> "bekasi" (never equals "dki jakarta")
     */
    public static function normalize(string $city): string
    {
        $city = mb_strtolower(trim($city));

        $city = preg_replace('/\s+/u', ' ', $city) ?? $city;
        $city = preg_replace('/[^a-z0-9\s]/u', '', $city) ?? $city;
        $city = preg_replace('/^(kota administrasi|kota|kabupaten|provinsi|propinsi|daerah khusus ibukota)\s+/u', '', $city) ?? $city;

        $city = trim($city);

        return self::ALIASES[$city] ?? $city;
    }
}
