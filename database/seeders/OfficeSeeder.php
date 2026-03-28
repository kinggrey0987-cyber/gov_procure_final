<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $offices = [
            [
                'name' => 'Municipal Mayor\'s Office',
                'code' => 'MAYOR_OFFICE',
                'description' => 'Executive office overseeing municipal operations.',
            ],
            [
                'name' => 'General Services Office',
                'code' => 'GSO',
                'description' => 'Handles inventory, deliveries, and supply distribution.',
            ],
            [
                'name' => 'Bids and Awards Committee',
                'code' => 'BAC',
                'description' => 'Oversees procurement planning, bidding, and awards.',
            ],
            [
                'name' => 'Municipal Engineering Office',
                'code' => 'ENG',
                'description' => 'Sample requesting office for capstone baseline data.',
            ],
            [
                'name' => 'Technical Administration',
                'code' => 'TECH_ADMIN',
                'description' => 'Reserved technical office for system administrator accounts.',
            ],
        ];

        foreach ($offices as $office) {
            Office::query()->updateOrCreate(
                ['code' => $office['code']],
                $office,
            );
        }
    }
}
