<?php

namespace Tests\Unit;

use App\Services\CityNormalizer;
use PHPUnit\Framework\TestCase;

class CityNormalizerTest extends TestCase
{
    public function test_normalizes_case_and_spacing(): void
    {
        $this->assertSame('bandar lampung', CityNormalizer::normalize('Bandar Lampung'));
        $this->assertSame('bandar lampung', CityNormalizer::normalize('  BANDAR    LAMPUNG  '));
    }

    public function test_strips_administrative_prefixes(): void
    {
        $this->assertSame('bandar lampung', CityNormalizer::normalize('Kota Bandar Lampung'));
        $this->assertSame('bandung', CityNormalizer::normalize('Kabupaten Bandung'));
    }

    public function test_all_dki_jakarta_administrative_areas_normalize_to_dki_jakarta(): void
    {
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Jakarta Pusat'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Central Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Jakarta Timur'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('East Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Jakarta Selatan'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('South Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Jakarta Barat'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('West Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Jakarta Utara'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('North Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Kepulauan Seribu'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Thousand Islands'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('DKI Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Daerah Khusus Ibukota Jakarta'));
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Special Capital Region of Jakarta'));
    }

    public function test_kota_administrasi_jakarta_normalizes_to_dki_jakarta(): void
    {
        $this->assertSame('dki jakarta', CityNormalizer::normalize('Kota Administrasi Jakarta Pusat'));
    }

    public function test_areas_outside_dki_jakarta_do_not_normalize_to_dki_jakarta(): void
    {
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Bekasi'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Kota Bekasi'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Depok'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Kota Depok'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Tangerang'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Tangerang Selatan'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Bogor'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Kota Bogor'));
        $this->assertNotSame('dki jakarta', CityNormalizer::normalize('Bandar Lampung'));
    }

    public function test_distinct_cities_never_match(): void
    {
        $this->assertNotSame(
            CityNormalizer::normalize('Bandar Lampung'),
            CityNormalizer::normalize('Bekasi'),
        );
    }
}
