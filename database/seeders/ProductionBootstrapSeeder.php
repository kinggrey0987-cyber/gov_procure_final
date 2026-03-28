<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductionBootstrapSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed only required baseline data for production auth/bootstrap.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OfficeSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}