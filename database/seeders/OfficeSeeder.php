<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Seed the five official offices. Idempotent: running it repeatedly
     * never creates duplicates.
     */
    public function run(): void
    {
        $offices = [
            [
                'office_code' => 'JKT001',
                'office_name' => 'Jakarta Head Office',
                'city' => 'Jakarta',
                'address' => 'Wisma 67, Jl. Tanah Abang II No. 67, Petojo Selatan, Gambir, Jakarta Pusat 10160',
            ],
            ['office_code' => 'MDN001', 'office_name' => 'Medan Office', 'city' => 'Medan', 'address' => 'Medan'],
            ['office_code' => 'SBY001', 'office_name' => 'Surabaya Office', 'city' => 'Surabaya', 'address' => 'Surabaya'],
            ['office_code' => 'MKS001', 'office_name' => 'Makassar Office', 'city' => 'Makassar', 'address' => 'Makassar'],
            ['office_code' => 'PDG001', 'office_name' => 'Padang Office', 'city' => 'Padang', 'address' => 'Padang'],
        ];

        foreach ($offices as $office) {
            $attributes = [
                'office_name' => $office['office_name'],
                'city' => $office['city'],
                'address' => $office['address'],
                'status' => 'active',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ];

            Office::firstOrCreate(
                ['office_code' => $office['office_code']],
                $attributes,
            )->forceFill($attributes)->save();
        }
    }
}
