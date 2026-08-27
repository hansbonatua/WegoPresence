<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $jakartaOffice = Office::where('office_code', 'JKT001')->firstOrFail();

        // Super Admin
        User::firstOrCreate(
            [
                'nip' => '010226',
            ],
            [
                'role_id' => $superAdminRole->id,
                'office_id' => $jakartaOffice->id,
                'name' => 'Antonius Bambang Tetuko',
                'position' => 'IT Support',
                'email' => 'antonius.bambang@wegomedika.com',
                'join_date' => '2022-01-02',
                'city' => 'Jakarta Pusat',
                'phone' => '08111506030',
                'password' => Hash::make('Wego123!'),
                'status' => 'active',
            ]
        );

        // Admin
        User::firstOrCreate(
            [
                'nip' => '010335',
            ],
            [
                'role_id' => $adminRole->id,
                'office_id' => $jakartaOffice->id,
                'name' => 'Fetty Ang',
                'position' => 'Asst Manager HRGA',
                'email' => 'meiling@wegomedika.com',
                'join_date' => '2017-01-01',
                'city' => 'Jakarta Pusat',
                'phone' => '08111915318',
                'password' => Hash::make('Wego123!'),
                'status' => 'active',
            ]
        );

        // Admin
        User::firstOrCreate(
            [
                'nip' => '010178',
            ],
            [
                'role_id' => $adminRole->id,
                'office_id' => $jakartaOffice->id,
                'name' => 'Keke Lerian Ariyanti',
                'position' => 'HRGA Leader',
                'email' => 'keke.lerian@wegomedika.com',
                'join_date' => '2022-03-28',
                'city' => 'Jakarta Pusat',
                'phone' => '082213081185',
                'password' => Hash::make('Wego123!'),
                'status' => 'active',
            ]
        );
    }
}
