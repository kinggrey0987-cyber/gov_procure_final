<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OfficeSeeder::class,
            AdminUserSeeder::class,
            ItemUnitSeeder::class,
            ItemCatalogSeeder::class,
        ]);

        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $this->call([
            SampleUsersSeeder::class,
            LongitudinalDemoWorkflowSeeder::class,
            MultiYearHistoricalProcurementSeeder::class,
            ExecutiveDisclosureDemoSeeder::class,
            GovernanceOpsDemoSeeder::class,
        ]);
    }
}
