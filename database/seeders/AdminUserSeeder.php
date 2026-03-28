<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::query()->where('code', 'ADMIN')->firstOrFail();
        $technicalOffice = Office::query()->updateOrCreate(
            ['code' => 'TECH_ADMIN'],
            [
                'name' => 'Technical Administration',
                'description' => 'Reserved technical office for system administrator accounts.',
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['name' => 'GovSupply Administrator'],
            [
                'email' => 'rienz123123@gmail.com',
                'name' => 'GovSupply Administrator',
                'password' => Hash::make('admin12345'),
                'must_change_password' => true,
                'role_id' => $adminRole->id,
                'office_id' => $technicalOffice->id,
                'email_verified_at' => now(),
            ],
        );
    }
}
