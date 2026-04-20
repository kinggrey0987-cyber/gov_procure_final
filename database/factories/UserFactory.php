<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $defaultRole = Role::query()->firstOrCreate(
            ['code' => UserRole::OFFICE_USER->value],
            [
                'name' => 'Office User',
                'description' => 'Default office user role for tests.',
                'is_active' => true,
            ],
        );

        $defaultOffice = Office::query()->firstOrCreate(
            ['code' => 'TEST_OFFICE'],
            [
                'name' => 'Test Office',
                'description' => 'Default office for tests.',
                'is_active' => true,
            ],
        );

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'emp_id' => sprintf('TST-%08d', fake()->unique()->numberBetween(1, 99999999)),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => $defaultRole->id,
            'office_id' => $defaultOffice->id,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withRole(UserRole $role): static
    {
        return $this->state(function () use ($role): array {
            $resolvedRole = Role::query()->firstOrCreate(
                ['code' => $role->value],
                [
                    'name' => str_replace('_', ' ', $role->value),
                    'description' => 'Factory-generated role for tests.',
                    'is_active' => true,
                ],
            );

            return [
                'role_id' => $resolvedRole->id,
            ];
        });
    }

    public function forOffice(Office $office): static
    {
        return $this->state(fn (): array => [
            'office_id' => $office->id,
        ]);
    }
}
