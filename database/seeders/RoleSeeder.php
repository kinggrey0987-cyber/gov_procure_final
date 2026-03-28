<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'code' => 'ADMIN',
                'description' => 'Technical system administrator for account, office, and access management only.',
            ],
            [
                'name' => 'Procurement Officer',
                'code' => 'PROC_OFFICER',
                'description' => 'Handles procurement workflow processing and record review.',
            ],
            [
                'name' => 'GSO Officer',
                'code' => 'GSO_OFFICER',
                'description' => 'Owns inventory/logistics monitoring, forecasting, reorder alerts, and delayed delivery analytics.',
            ],
            [
                'name' => 'Office User',
                'code' => 'OFFICE_USER',
                'description' => 'Creates own-office requests and tracks own-office status only.',
            ],
            [
                'name' => 'Executive Officer',
                'code' => 'EXEC_OFFICER',
                'description' => 'Owns executive monitoring, anomaly visibility, and business audit visibility.',
            ],
            [
                'name' => 'Public Viewer',
                'code' => 'PUBLIC',
                'description' => 'Can only view approved and published public disclosures.',
            ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role['code']],
                $role,
            );
        }
    }
}
