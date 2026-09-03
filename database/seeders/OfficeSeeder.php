<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offices = [
            [
                'office_code' => 'JKT001',
                'office_name' => 'Jakarta Head Office',
                'city' => 'DKI Jakarta',
                'address' => 'Wisma 67, Jl. Tanah Abang II No. 67, Petojo Selatan, Gambir, Jakarta Pusat 10160',
            ],
            ['office_code' => 'MDN001', 'office_name' => 'Office Medan', 'city' => 'Medan', 'address' => 'Medan'],
            ['office_code' => 'BDG001', 'office_name' => 'Office Bandung', 'city' => 'Bandung', 'address' => 'Bandung'],
            ['office_code' => 'YOG001', 'office_name' => 'Office Yogyakarta', 'city' => 'Yogyakarta', 'address' => 'Yogyakarta'],
            ['office_code' => 'SMG001', 'office_name' => 'Office Semarang', 'city' => 'Semarang', 'address' => 'Semarang'],
            ['office_code' => 'SUB001', 'office_name' => 'Office Surabaya', 'city' => 'Surabaya', 'address' => 'Surabaya'],
            ['office_code' => 'MLG001', 'office_name' => 'Office Malang', 'city' => 'Malang', 'address' => 'Malang'],
            ['office_code' => 'DPS001', 'office_name' => 'Office Denpasar', 'city' => 'Denpasar', 'address' => 'Denpasar'],
            ['office_code' => 'PLM001', 'office_name' => 'Office Palembang', 'city' => 'Palembang', 'address' => 'Palembang'],
            ['office_code' => 'JMB001', 'office_name' => 'Office Jambi', 'city' => 'Jambi', 'address' => 'Jambi'],
            ['office_code' => 'PDG001', 'office_name' => 'Office Padang', 'city' => 'Padang', 'address' => 'Padang'],
            ['office_code' => 'PKB001', 'office_name' => 'Office Pekanbaru', 'city' => 'Pekanbaru', 'address' => 'Pekanbaru'],
            ['office_code' => 'BTJ001', 'office_name' => 'Office Banda Aceh', 'city' => 'Banda Aceh', 'address' => 'Banda Aceh'],
            ['office_code' => 'BPN001', 'office_name' => 'Office Balikpapan', 'city' => 'Balikpapan', 'address' => 'Balikpapan'],
            ['office_code' => 'MKS001', 'office_name' => 'Office Makassar', 'city' => 'Makassar', 'address' => 'Makassar'],
            ['office_code' => 'MDO001', 'office_name' => 'Office Manado', 'city' => 'Manado', 'address' => 'Manado'],
            ['office_code' => 'MLG002', 'office_name' => 'Office Malang', 'city' => 'Malang', 'address' => 'Malang'],
        ];

        foreach ($offices as $office) {
            Office::firstOrCreate(
                ['office_code' => $office['office_code']],
                [
                    'office_name' => $office['office_name'],
                    'city' => $office['city'],
                    'address' => $office['address'],
                    'status' => 'active',
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                ],
            );
        }
    }
}
