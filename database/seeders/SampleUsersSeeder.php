<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SampleUsersSeeder extends Seeder
{
    /**
     * Seed sample users aligned to canonical governance roles and offices.
     */
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'code');
        $offices = Office::query()->pluck('id', 'code');
        $defaultPassword = (string) env('SEED_SAMPLE_USER_PASSWORD', Str::password(24));

        $users = [
            [
                'name' => 'Earl',
                'email' => 'office.mpdo@example.invalid',
                'role_code' => 'OFFICE_USER',
                'office_code' => 'MPDO',
                'emp_id' => 'MPDO-0001',
            ],
            [
                'name' => 'GovSupply Administrator',
                'email' => (string) env('SEED_ADMIN_EMAIL', 'admin@example.invalid'),
                'role_code' => 'ADMIN',
                'office_code' => 'TECH_ADMIN',
                'emp_id' => 'TECH-ADMIN-0001',
            ],
            [
                'name' => 'Gso Rieny',
                'email' => 'gso.1@example.invalid',
                'role_code' => 'GSO_OFFICER',
                'office_code' => 'GSO',
                'emp_id' => 'GSO-0001',
            ],
            [
                'name' => 'Jayross',
                'email' => 'procurement.1@example.invalid',
                'role_code' => 'PROC_OFFICER',
                'office_code' => 'BAC',
                'emp_id' => 'BAC-0001',
            ],
            [
                'name' => 'Jeron Opjer',
                'email' => 'gso.2@example.invalid',
                'role_code' => 'GSO_OFFICER',
                'office_code' => 'GSO',
                'emp_id' => 'GSO-0002',
            ],
            [
                'name' => 'Mayor Jeron',
                'email' => 'executive.1@example.invalid',
                'role_code' => 'EXEC_OFFICER',
                'office_code' => 'MAYOR_OFFICE',
                'emp_id' => 'EXEC-0001',
            ],
            [
                'name' => 'Office Transaction User',
                'email' => 'office.mswdo@example.invalid',
                'role_code' => 'OFFICE_USER',
                'office_code' => 'MSWDO',
                'emp_id' => 'MSWDO-0001',
            ],
            [
                'name' => 'Proc Jeron',
                'email' => 'procurement.2@example.invalid',
                'role_code' => 'PROC_OFFICER',
                'office_code' => 'BAC',
                'emp_id' => 'BAC-0002',
            ],
            [
                'name' => 'Roen',
                'email' => 'office.eng@example.invalid',
                'role_code' => 'OFFICE_USER',
                'office_code' => 'ENG',
                'emp_id' => 'ENG-0001',
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['emp_id' => $user['emp_id']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make($defaultPassword),
                    'must_change_password' => true,
                    'role_id' => $roles[$user['role_code']] ?? null,
                    'office_id' => $offices[$user['office_code']] ?? null,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
