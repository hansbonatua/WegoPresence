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
        Office::updateOrCreate(
            [
                'office_code' => 'JKT001',
            ],
            [
                'office_name' => 'Jakarta Head Office',
                'city' => 'DKI Jakarta',
                'address' => 'Wisma 67, Jl. Tanah Abang II No. 67, Petojo Selatan, Gambir, Jakarta Pusat 10160',
                'status' => 'active',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]
        );
    }
}
