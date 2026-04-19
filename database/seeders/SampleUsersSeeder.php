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
                'name' => 'Earl',
                'email' => 'rienz6977@gmail.com',
                'role_code' => 'OFFICE_USER',
                'office_code' => 'MPDO',
                'emp_id' => 'MPDO-0001',
            ],
            [
                'name' => 'GovSupply Administrator',
                'email' => 'rienz123123@gmail.com',
                'role_code' => 'ADMIN',
                'office_code' => 'TECH_ADMIN',
                'emp_id' => 'TECH-ADMIN-0001',
            ],
            [
                'name' => 'Gso Rieny',
                'email' => 'rienyhulleza1022@gmail.com',
                'role_code' => 'GSO_OFFICER',
                'office_code' => 'GSO',
                'emp_id' => 'GSO-0001',
            ],
            [
                'name' => 'Jayross',
                'email' => 'jayrossrusselherbias@gmail.com',
                'role_code' => 'PROC_OFFICER',
                'office_code' => 'BAC',
                'emp_id' => 'BAC-0001',
            ],
            [
                'name' => 'Jeron Opjer',
                'email' => 'jeronopjer.chmsu@gmail.com',
                'role_code' => 'GSO_OFFICER',
                'office_code' => 'GSO',
                'emp_id' => 'GSO-0002',
            ],
            [
                'name' => 'Mayor Jeron',
                'email' => 'jeronopjer@gmail.com',
                'role_code' => 'EXEC_OFFICER',
                'office_code' => 'MAYOR_OFFICE',
                'emp_id' => 'EXEC-0001',
            ],
            [
                'name' => 'Office Transaction User',
                'email' => 'govprocure86@gmail.com',
                'role_code' => 'OFFICE_USER',
                'office_code' => 'MSWDO',
                'emp_id' => 'MSWDO-0001',
            ],
            [
                'name' => 'Proc Jeron',
                'email' => 'kinggrey0987@gmail.com',
                'role_code' => 'PROC_OFFICER',
                'office_code' => 'BAC',
                'emp_id' => 'BAC-0002',
            ],
            [
                'name' => 'Roen',
                'email' => 'roengwapo09@gmail.com',
                'role_code' => 'OFFICE_USER',
                'office_code' => 'ENG',
                'emp_id' => 'ENG-0001',
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
                    'emp_id' => $user['emp_id'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
