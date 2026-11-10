<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword as ResetPasswordPage;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('sends a reset link to a known address', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertSet('sent', true)
        ->assertSee('Kijk in je mailbox');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('shows the same message for an unknown address', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'onbekend@example.com')
        ->call('sendResetLink')
        ->assertSet('sent', true)
        ->assertSee('Kijk in je mailbox');

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

    Livewire::test(ResetPasswordPage::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'nieuw-wachtwoord-123')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('nieuw-wachtwoord-123', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token', function () {
    $user = User::factory()->create();

    Livewire::test(ResetPasswordPage::class, ['token' => 'ongeldig'])
        ->set('email', $user->email)
        ->set('password', 'nieuw-wachtwoord-123')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
