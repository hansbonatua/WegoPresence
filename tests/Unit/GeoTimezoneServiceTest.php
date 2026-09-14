<?php

namespace Tests\Unit;

use App\Services\GeoTimezoneService;
use Tests\TestCase;

class GeoTimezoneServiceTest extends TestCase
{
    private GeoTimezoneService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GeoTimezoneService;
    }

    public function test_wib_coordinates_resolve_to_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', $this->service->resolve(-6.1666667, 106.8));
        $this->assertSame('Asia/Jakarta', $this->service->resolve(-6.9175, 107.6191));
    }

    public function test_wita_coordinates_resolve_to_asia_makassar(): void
    {
        $this->assertSame('Asia/Makassar', $this->service->resolve(-5.1477, 119.4327));
        $this->assertSame('Asia/Makassar', $this->service->resolve(-8.6500, 115.2167));
    }

    public function test_wit_coordinates_resolve_to_asia_jayapura(): void
    {
        $this->assertSame('Asia/Jayapura', $this->service->resolve(-2.5367, 140.7173));
    }

    public function test_the_wib_wita_boundary_is_geographic_not_a_straight_meridian(): void
    {
        // Surabaya and Bali sit on the same longitude band (~115°E), yet
        // Surabaya is WIB and Bali is WITA: each resolves to its nearest
        // zone reference point.
        $this->assertSame('Asia/Jakarta', $this->service->resolve(-7.2504, 112.7688));
        $this->assertSame('Asia/Makassar', $this->service->resolve(-8.6500, 115.2167));
    }

    public function test_the_126_degree_meridian_belongs_to_wit(): void
    {
        $this->assertSame('Asia/Makassar', $this->service->resolve(0.0, 125.9));
        $this->assertSame('Asia/Jayapura', $this->service->resolve(0.0, 126.0));
    }
}
