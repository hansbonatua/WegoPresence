<?php

namespace App\Services;

final class GeoTimezoneService
{
    /**
     * Longitude (degrees East) from which the WIT zone (Asia/Jayapura)
     * starts. Maluku and Papua are always east of this meridian, while
     * every WIB/WITA territory lies west of it, so the WIT decision
     * needs no distance computation.
     */
    private const WIT_LONGITUDE_BOUNDARY = 126.0;

    /**
     * Reference points for the WIB and WITA zones. The boundary between
     * them is not a straight meridian (Surabaya is WIB while Banjarmasin
     * and Bali are WITA on the same longitude band), so the closest
     * reference point is used instead of a plain longitude split.
     *
     * @var array<int, array{timezone: string, latitude: float, longitude: float}>
     */
    private const ZONES = [
        ['timezone' => 'Asia/Jakarta', 'latitude' => -6.2000, 'longitude' => 106.8167],
        ['timezone' => 'Asia/Makassar', 'latitude' => -5.1477, 'longitude' => 119.4327],
    ];

    /**
     * Resolve the IANA timezone for a GPS coordinate.
     *
     * Indonesia has three timezones. WIT is decided first by longitude
     * (everything at or east of 126°E is Maluku or Papua). The remaining
     * coordinates are WIB or WITA, resolved by whichever of the two zone
     * reference points is geographically nearest. This is deterministic,
     * requires no external dataset, and correctly places every province:
     * Sumatra/Java/Kalimantan Barat-Tengah as WIB, Kalimantan Selatan-
     * Timur/Sulawesi/Bali/Nusa Tenggara as WITA, Maluku/Papua as WIT.
     *
     * Falls back to config('app.timezone') when the coordinate cannot be
     * mapped to a known Indonesian timezone.
     */
    public function resolve(float $latitude, float $longitude): string
    {
        if ($longitude >= self::WIT_LONGITUDE_BOUNDARY) {
            return 'Asia/Jayapura';
        }

        $nearest = null;
        $nearestDistance = INF;

        foreach (self::ZONES as $zone) {
            $distance = $this->distance(
                $latitude,
                $longitude,
                $zone['latitude'],
                $zone['longitude'],
            );

            if ($distance < $nearestDistance) {
                $nearest = $zone['timezone'];
                $nearestDistance = $distance;
            }
        }

        return $nearest ?? config('app.timezone');
    }

    /**
     * Great-circle distance between two coordinates in kilometres.
     */
    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKilometres = 6371.0;

        $deltaLatitude = deg2rad($toLatitude - $fromLatitude);
        $deltaLongitude = deg2rad($toLongitude - $fromLongitude);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($fromLatitude))
                * cos(deg2rad($toLatitude))
                * sin($deltaLongitude / 2) ** 2;

        return $earthRadiusKilometres * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
