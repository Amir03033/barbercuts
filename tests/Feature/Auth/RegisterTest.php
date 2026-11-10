<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('registers a new user and sends a verification mail', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Sam Bakker')
        ->set('email', 'sam@example.com')
        ->set('password', 'geheim-wachtwoord')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'sam@example.com')->firstOrFail();

    expect($user->is_admin)->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('validates the registration form', function (array $input, string $field) {
    User::factory()->create(['email' => 'bestaat@example.com']);

    Livewire::test(Register::class)
        ->set('name', $input['name'] ?? 'Sam')
        ->set('email', $input['email'] ?? 'sam@example.com')
        ->set('password', $input['password'] ?? 'geheim-wachtwoord')
        ->call('register')
        ->assertHasErrors($field);
})->with([
    'name required' => [['name' => ''], 'name'],
    'email invalid' => [['email' => 'geen-email'], 'email'],
    'email taken' => [['email' => 'bestaat@example.com'], 'email'],
    'password too short' => [['password' => 'kort'], 'password'],
]);

it('cannot make itself admin through registration', function () {
    Livewire::test(Register::class)
        ->set('name', 'Sam')
        ->set('email', 'sam@example.com')
        ->set('password', 'geheim-wachtwoord')
        ->set('is_admin', true)
        ->call('register');
})->throws(Exception::class);
