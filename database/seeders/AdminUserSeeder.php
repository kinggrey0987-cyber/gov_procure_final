<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use InvalidArgumentException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->where('code', 'ADMIN')->firstOrFail();
        $adminEmail = (string) env('SEED_ADMIN_EMAIL', 'admin@example.invalid');
        $adminName = (string) env('SEED_ADMIN_NAME', 'GovSupply Administrator');
        $adminPassword = (string) env('SEED_ADMIN_PASSWORD', Str::password(32));
        $adminEmpId = (string) env('SEED_ADMIN_EMP_ID', 'TECH-ADMIN-0001');

        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            if ($adminEmail === 'admin@example.invalid' || ! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('SEED_ADMIN_EMAIL must be set to a valid administrator email outside local/testing environments.');
            }

            if (trim($adminPassword) === '') {
                throw new InvalidArgumentException('SEED_ADMIN_PASSWORD must be set outside local/testing environments.');
            }
        }

        $technicalOffice = Office::query()->updateOrCreate(
            ['code' => 'TECH_ADMIN'],
            [
                'name' => 'Technical Administration',
                'description' => 'Reserved technical office for system administrator accounts.',
                'is_active' => true,
            ],
        );

        $user = User::query()->where('email', $adminEmail)->first();

        if (! $user) {
            User::query()->create([
                'email' => $adminEmail,
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'must_change_password' => true,
                'role_id' => $adminRole->id,
                'office_id' => $technicalOffice->id,
                'emp_id' => $adminEmpId,
                'email_verified_at' => now(),
            ]);

            return;
        }

        $user->update([
            'name' => $adminName,
            'role_id' => $adminRole->id,
            'office_id' => $technicalOffice->id,
            'emp_id' => $adminEmpId,
        ]);
    }
}
