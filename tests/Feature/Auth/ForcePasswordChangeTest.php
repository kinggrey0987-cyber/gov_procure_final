<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_must_change_password_is_redirected_before_dashboard_access(): void
    {
        $user = $this->createInternalUser('force.redirect@gsc.local', true);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('force-password.edit', absolute: false));
    }

    public function test_user_can_update_password_and_clear_must_change_flag(): void
    {
        $user = $this->createInternalUser('force.update@gsc.local', true);

        $this->actingAs($user)
            ->put(route('force-password.update'), [
                'current_password' => 'password',
                'password' => 'NewPass#12345',
                'password_confirmation' => 'NewPass#12345',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewPass#12345', $user->password));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('office.dashboard', absolute: false));
    }

    private function createInternalUser(string $email, bool $mustChangePassword): User
    {
        $office = Office::query()->firstOrCreate([
            'code' => 'FORCE_PASS_OFF',
        ], [
            'name' => 'Force Password Office',
            'description' => 'Office for password enforcement tests',
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate([
            'code' => UserRole::OFFICE_USER->value,
        ], [
            'name' => 'Office User',
            'description' => 'Role for password enforcement tests',
            'is_active' => true,
        ]);

        return User::factory()
            ->withRole(UserRole::OFFICE_USER)
            ->forOffice($office)
            ->create([
                'email' => $email,
                'role_id' => $role->id,
                'office_id' => $office->id,
                'must_change_password' => $mustChangePassword,
            ]);
    }
}
