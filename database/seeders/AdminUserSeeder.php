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
        $primaryOffice = Office::query()->where('code', 'MAYOR_OFFICE')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'admin@govsupplyconnect.local'],
            [
                'name' => 'GovSupply Administrator',
                'password' => Hash::make('admin12345'),
                'role_id' => $adminRole->id,
                'office_id' => $primaryOffice->id,
                'email_verified_at' => now(),
            ],
        );
    }
}
