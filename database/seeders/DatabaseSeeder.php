<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::insert([
            [
                'name' => 'Admin BAC',
                'email' => 'bac@example.com',
                'password' => Hash::make('123'),
                'role' => 2,
            ],
            [
                'name' => 'Admin Nam',
                'email' => 'nam@example.com',
                'password' => Hash::make('456'),
                'role' => 3,
            ],
            [
                'name' => 'Admin Trung',
                'email' => 'trung@example.com',
                'password' => Hash::make('789'),
                'role' => 4,
            ],
            [
                'name' => 'Admin Tong',
                'email' => 'all@example.com',
                'password' => Hash::make('123456789'),
                'role' => 1,
            ]

        ]);

    }
}
