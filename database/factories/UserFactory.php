<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user'])->id,
            'office_id' => Office::query()->firstOrCreate(
                ['office_code' => 'JKT001'],
                [
                    'office_code' => 'JKT001',
                    'office_name' => 'Head Office',
                    'city' => 'Jakarta',
                    'address' => 'Jakarta',
                    'status' => 'active',
                ],
            )->id,
            'nip' => fake()->unique()->numerify('############'),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'join_date' => fake()->date(),
            'city' => 'Jakarta',
            'status' => 'active',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
