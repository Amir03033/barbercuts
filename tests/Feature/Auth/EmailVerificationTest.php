<?php

use App\Livewire\Auth\VerifyEmail as VerifyEmailPage;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

it('lets unverified owners into the app with a reminder to verify', function () {
    [$shop, $owner] = shopWithOwner();
    $owner->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($owner)->get('/app')->assertOk()->assertSee('Bevestig je e-mailadres');
});

it('lets verified owners into the app without the reminder', function () {
    [$shop, $owner] = shopWithOwner();

    $this->actingAs($owner)->get('/app')->assertOk()->assertSee($shop->name)->assertDontSee('Bevestig je e-mailadres');
});

it('sends users without a shop to the onboarding', function () {
    $this->actingAs(User::factory()->create())->get('/app')->assertRedirect('/aanmelden');
});

it('verifies the email address through the signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect('/app');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a verification link with a wrong hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1('ander@example.com'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification mail, at most three times per minute', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $component = Livewire::actingAs($user)->test(VerifyEmailPage::class);

    foreach (range(1, 5) as $attempt) {
        $component->call('resend');
    }

    Notification::assertSentToTimes($user, VerifyEmail::class, 3);
});
