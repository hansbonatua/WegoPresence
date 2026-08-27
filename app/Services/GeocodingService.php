<?php

namespace App\Services;

use App\Exceptions\GeocodingException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeocodingService
{
    /**
     * OpenStreetMap Nominatim reverse geocoding endpoint.
     */
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT_SECONDS = 5;

    /**
     * A descriptive User-Agent is required by the Nominatim usage policy.
     */
    private const USER_AGENT = 'WegoPresence/1.0 (attendance GPS validation)';

    /**
     * Address fields considered as a city, from most to least specific.
     */
    private const CITY_FIELDS = ['city', 'county', 'municipality', 'town', 'village'];

    /**
     * Reverse geocode coordinates and return the city information.
     *
     * @return array{city: string, candidates: array<int, string>}
     *
     * @throws GeocodingException When the request fails or no city can be determined.
     */
    public function reverse(float $latitude, float $longitude): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                ]);
        } catch (Throwable $e) {
            throw new GeocodingException('Reverse geocoding request failed.', previous: $e);
        }

        if ($response->failed()) {
            throw new GeocodingException('Reverse geocoding request failed.');
        }

        $address = $response->json('address');

        if (! is_array($address)) {
            throw new GeocodingException('Reverse geocoding returned no address.');
        }

        $candidates = [];
        $primary = null;

        foreach (self::CITY_FIELDS as $field) {
            $value = $address[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $value = trim($value);

                $candidates[] = $value;

                if ($primary === null) {
                    $primary = $value;
                }
            }
        }

        if ($primary === null) {
            throw new GeocodingException('Reverse geocoding returned no usable city.');
        }

        return [
            'city' => $primary,
            'candidates' => array_values(array_unique($candidates)),
        ];
    }
}
