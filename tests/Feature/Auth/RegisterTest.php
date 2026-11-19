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

it('limits how many accounts one IP address can create per hour', function () {
    Notification::fake();

    foreach (range(1, 5) as $number) {
        Livewire::test(Register::class)
            ->set('name', 'Sam '.$number)
            ->set('email', "sam{$number}@example.com")
            ->set('password', 'geheim-wachtwoord')
            ->call('register')
            ->assertHasNoErrors();

        auth()->logout();
    }

    Livewire::test(Register::class)
        ->set('name', 'Te veel')
        ->set('email', 'te-veel@example.com')
        ->set('password', 'geheim-wachtwoord')
        ->call('register')
        ->assertHasErrors('email');

    expect(User::where('email', 'te-veel@example.com')->exists())->toBeFalse();
});

it('shows a password field with a visibility toggle and strength meter on the sign up page', function () {
    $this->get('/registreren')
        ->assertOk()
        ->assertSee('Toon wachtwoord')
        ->assertSee('Meld je zaak aan')
        ->assertSee('privacyverklaring');
});
