<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
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

        $user = User::query()->where('email', 'rienz123123@gmail.com')->first();

        if (! $user) {
            User::query()->create([
                'email' => 'rienz123123@gmail.com',
                'name' => 'GovSupply Administrator',
                'password' => Hash::make('admin12345'),
                'must_change_password' => true,
                'role_id' => $adminRole->id,
                'office_id' => $technicalOffice->id,
                'email_verified_at' => now(),
            ]);

            return;
        }

        $user->update([
            'name' => 'GovSupply Administrator',
            'role_id' => $adminRole->id,
            'office_id' => $technicalOffice->id,
        ]);
    }
}