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
                'name' => 'Municipal Planning and Development Office',
                'code' => 'MPDO',
                'description' => 'Planning and development office for municipal programs and PPMP ownership.',
            ],
            [
                'name' => 'Municipal Social Welfare and Development Office',
                'code' => 'MSWDO',
                'description' => 'Social welfare and development office for municipal services.',
            ],
            [
                'name' => 'General Services Office',
                'code' => 'GSO',
                'description' => 'Handles inventory, deliveries, and supply distribution.',
            ],
            [
                'name' => 'Procurement Unit',
                'code' => 'BAC',
                'description' => 'Represents BAC members and procurement personnel for procurement-side workflows.',
            ],
            [
                'name' => 'Municipal Engineering Office',
                'code' => 'ENG',
                'description' => 'Sample requesting office for capstone baseline data.',
            ],
            [
                'name' => 'Municipal Health Office',
                'code' => 'MHO',
                'description' => 'Public healthcare and community medical services.',
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
