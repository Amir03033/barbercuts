<?php

use App\Livewire\Auth\VerifyEmail as VerifyEmailPage;
use App\Livewire\VerifyEmailBanner;
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

    $this->actingAs($user)->get($url)->assertRedirect('/account');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('verifies through the link without being logged in, for example from another browser', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->get($url)->assertOk()
        ->assertSee('Je e-mailadres is bevestigd')
        ->assertSee('Dat gaat vanzelf verder')
        ->assertSee('BroadcastChannel', false)
        ->assertSee(route('login', ['email' => $user->email]), false);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->get('/e-mail-bevestigen/'.$user->id.'/'.sha1($user->email))->assertOk()->assertSee('Je e-mailadres is bevestigd');
});

it('explains an expired link and offers a new one instead of a bare 403', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->get($url)->assertForbidden()->assertSee('Deze link is verlopen')->assertSee('Inloggen voor een nieuwe link');
    $this->actingAs($user)->get($url)->assertForbidden()->assertSee('Stuur een nieuwe link');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an unsigned link', function () {
    $user = User::factory()->unverified()->create();

    $this->get('/e-mail-bevestigen/'.$user->id.'/'.sha1($user->email))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('tells someone logged in with another account how to continue', function () {
    $user = User::factory()->unverified()->create();
    $other = User::factory()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($other)->get($url)->assertOk()->assertSee('ander account')->assertSee('Inloggen als '.$user->email);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('signals other open tabs after verifying in the same browser', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($url)->assertRedirect('/account')->assertSessionHas('email_verified', true);
    $this->actingAs($user)->withSession(['email_verified' => true])->get('/account')->assertSee("postMessage('email-verified')", false);
});

it('continues on its own once the address is verified elsewhere', function () {
    $user = User::factory()->unverified()->create();

    $component = Livewire::actingAs($user)->test(VerifyEmailPage::class)->assertSee($user->email)->assertSee('wire:poll', false);

    $component->call('check')->assertNoRedirect();

    $user->markEmailAsVerified();

    $component->call('check')->assertRedirect('/account');
});

it('lets a user fix a typo in the address and sends a new link', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'jan@gmial.com']);
    User::factory()->create(['email' => 'bezet@example.com']);

    $component = Livewire::actingAs($user)->test(VerifyEmailPage::class)->call('editEmail');

    $component->set('newEmail', 'bezet@example.com')->call('updateEmail')->assertHasErrors('newEmail');
    $component->set('newEmail', ' Jan@Gmail.com ')->call('updateEmail')->assertHasNoErrors()->assertSet('editingEmail', false);

    expect($user->fresh()->email)->toBe('jan@gmail.com');
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('cannot change an address that is already verified', function () {
    $user = User::factory()->create(['email' => 'vast@example.com']);

    Livewire::actingAs($user)->test(VerifyEmailPage::class)->assertRedirect('/account');

    expect($user->fresh()->email)->toBe('vast@example.com');
});

it('shows a banner in the account that notices the verification by itself', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/account')->assertSee('Bevestig je e-mailadres');

    $banner = Livewire::actingAs($user)->test(VerifyEmailBanner::class)->assertSee('Bevestig je e-mailadres');

    $user->markEmailAsVerified();

    $banner->call('check')->assertSet('verified', true)->assertSee('Je e-mailadres is bevestigd')->assertDontSee('Stuur de link opnieuw');
});

it('sends a Dutch verification mail with a link that stays valid for a day', function () {
    $user = User::factory()->unverified()->create(['name' => 'Jan de Vries']);

    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Bevestig je e-mailadres')
        ->and($mail->greeting)->toBe('Hoi Jan,')
        ->and($mail->actionText)->toBe('E-mailadres bevestigen')
        ->and(implode(' ', $mail->outroLines))->toContain('24 uur');
});

it('prefills the email address on the login screen', function () {
    $this->get(route('login', ['email' => 'jan@example.com']))->assertOk()->assertSee('jan@example.com');
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

it('shares the resend limit between the screen and the banner', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    foreach (range(1, 2) as $attempt) {
        Livewire::actingAs($user)->test(VerifyEmailPage::class)->call('resend');
        Livewire::actingAs($user)->test(VerifyEmailBanner::class)->call('resend');
    }

    Notification::assertSentToTimes($user, VerifyEmail::class, 3);
});
