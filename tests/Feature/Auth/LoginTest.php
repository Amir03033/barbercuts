<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

it('shows the login page', function () {
    $this->get('/inloggen')->assertOk()->assertSee('Inloggen');
});

it('logs a user in with valid credentials', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.today', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password with a Dutch message', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee('klopt niet');

    $this->assertGuest();
});

it('locks out after five failed attempts', function () {
    $user = User::factory()->create();

    $component = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong-password');

    foreach (range(1, 5) as $attempt) {
        $component->call('login');
    }

    $component->set('password', 'password')->call('login')->assertSee('Te veel inlogpogingen');

    $this->assertGuest();
});

it('redirects guests from the app to the login page', function () {
    $this->get('/app')->assertRedirect('/inloggen');
});

it('redirects logged in users away from the login page', function () {
    $this->actingAs(User::factory()->create())->get('/inloggen')->assertRedirect('/app');
});

it('logs a user out', function () {
    $this->actingAs(User::factory()->create())->post('/uitloggen')->assertRedirect('/');

    $this->assertGuest();
});
