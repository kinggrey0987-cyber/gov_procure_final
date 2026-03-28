<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleUsersSeeder extends Seeder
{
    /**
     * Seed sample users aligned to canonical governance roles and offices.
     */
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'code');
        $offices = Office::query()->pluck('id', 'code');

        $users = [
            [
                'name' => 'Office Transaction User',
                'email' => 'office.user@govsupplyconnect.local',
                'role_code' => 'OFFICE_USER',
                'office_code' => 'ENG',
            ],
            [
                'name' => 'Procurement Workflow Officer',
                'email' => 'proc.officer@govsupplyconnect.local',
                'role_code' => 'PROC_OFFICER',
                'office_code' => 'BAC',
            ],
            [
                'name' => 'GSO Inventory Officer',
                'email' => 'gso.officer@govsupplyconnect.local',
                'role_code' => 'GSO_OFFICER',
                'office_code' => 'GSO',
            ],
            [
                'name' => 'Executive Monitoring Officer',
                'email' => 'exec.officer@govsupplyconnect.local',
                'role_code' => 'EXEC_OFFICER',
                'office_code' => 'MAYOR_OFFICE',
            ],
            [
                'name' => 'Public Transparency User',
                'email' => 'public.viewer@govsupplyconnect.local',
                'role_code' => 'PUBLIC',
                'office_code' => 'MAYOR_OFFICE',
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role_id' => $roles[$user['role_code']] ?? null,
                    'office_id' => $offices[$user['office_code']] ?? null,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
