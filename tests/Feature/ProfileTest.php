<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mail\TwoFactorCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertNotSame('test@example.com', $user->email);

        $response->assertSessionHas('profile.email_change.pending.email', 'test@example.com');
        Mail::assertSent(TwoFactorCodeMail::class, fn (TwoFactorCodeMail $mail): bool => $mail->hasTo($user->email) && $mail->purpose === 'email_change');
    }

    public function test_pending_email_change_can_be_confirmed_with_two_factor_code(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return $mail->purpose === 'email_change';
        });

        $this
            ->actingAs($user)
            ->withSession([
                'profile.email_change.pending' => [
                    'email' => 'test@example.com',
                ],
            ])
            ->post(route('profile.email-change.confirm'), [
                'code' => $code,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_pending_email_change_can_be_cancelled(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->withSession([
                'profile.email_change.pending' => [
                    'email' => 'test@example.com',
                ],
            ])
            ->delete(route('profile.email-change.cancel'))
            ->assertSessionMissing('profile.email_change.pending')
            ->assertRedirect('/profile');

        $this->assertNotSame('test@example.com', $user->refresh()->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
