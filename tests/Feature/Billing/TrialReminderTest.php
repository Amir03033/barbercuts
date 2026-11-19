<?php

use App\Notifications\TrialEnding;
use App\Services\Billing\TrialReminderService;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Notification;

/*
| E-mails rond het einde van de proefperiode. "Nu" is dinsdag 17 november 2026, 10:00.
*/

beforeEach(function () {
    Notification::fake();
    $this->travelTo(at('2026-11-17 10:00'));
    billingOn();
});

it('mails the owner 3 days and 1 day before the trial ends, each only once', function () {
    [$shop, $owner] = shopInState('trial');
    $shop->forceFill(['trial_ends_at' => now()->addDays(3)])->save();

    expect(app(TrialReminderService::class)->sendDue())->toBe(1);
    app(TrialReminderService::class)->sendDue();

    Notification::assertSentToTimes($owner, TrialEnding::class, 1);

    $this->travelTo(now()->addDays(2));
    app(TrialReminderService::class)->sendDue();
    app(TrialReminderService::class)->sendDue();

    Notification::assertSentToTimes($owner, TrialEnding::class, 2);
    Notification::assertSentTo($owner, TrialEnding::class, fn (TrialEnding $n) => $n->stage === TrialEnding::ONE_DAY);
});

it('mails once when the trial has just expired', function () {
    [$shop, $owner] = shopInState('expired');
    $shop->forceFill(['trial_ends_at' => now()->subHours(5)])->save();

    app(TrialReminderService::class)->sendDue();
    app(TrialReminderService::class)->sendDue();

    Notification::assertSentToTimes($owner, TrialEnding::class, 1);
    Notification::assertSentTo($owner, TrialEnding::class, fn (TrialEnding $n) => $n->stage === TrialEnding::EXPIRED);
});

it('leaves paying, free, suspended and far-away trial shops alone', function () {
    [, $paying] = shopInState('active');
    [$free, $freeOwner] = shopInState('free');
    $free->forceFill(['trial_ends_at' => now()->addDay()])->save();
    [$suspended, $suspendedOwner] = shopInState('suspended');
    $suspended->forceFill(['trial_ends_at' => now()->addDay()])->save();
    [$far, $farOwner] = shopInState('trial');
    $far->forceFill(['trial_ends_at' => now()->addDays(10)])->save();

    expect(app(TrialReminderService::class)->sendDue())->toBe(0);

    Notification::assertNothingSent();
});

it('sends nothing while billing is off', function () {
    [$shop] = shopInState('trial');
    $shop->forceFill(['trial_ends_at' => now()->addDay()])->save();
    PlatformSettings::set('features.billing', false);

    expect(app(TrialReminderService::class)->sendDue())->toBe(0);
});

it('mails again after an extended trial, since it is a new end date', function () {
    [$shop, $owner] = shopInState('trial');
    $shop->forceFill(['trial_ends_at' => now()->addDay()])->save();
    app(TrialReminderService::class)->sendDue();

    $shop->fresh()->forceFill(['trial_ends_at' => now()->addDays(3)])->save();
    app(TrialReminderService::class)->sendDue();

    Notification::assertSentToTimes($owner, TrialEnding::class, 2);
});

it('renders a mail that names the shop, the price and the way to subscribe', function () {
    [$shop, $owner] = shopInState('trial');

    $mail = (new TrialEnding($shop->id, TrialEnding::ONE_DAY))->toMail($owner);

    expect($mail->subject)->toContain($shop->name)->and($mail->actionUrl)->toBe(route('app.subscription'))
        ->and(implode(' ', $mail->introLines))->toContain('per maand');
});

it('runs from the scheduler as billing:remind-trials', function () {
    [$shop, $owner] = shopInState('trial');
    $shop->forceFill(['trial_ends_at' => now()->addDay()])->save();

    $this->artisan('billing:remind-trials')->expectsOutput('1 bericht(en) verstuurd.')->assertSuccessful();

    Notification::assertSentTo($owner, TrialEnding::class);
});
