<?php

use App\Enums\AppointmentStatus;
use App\Enums\MessageChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Livewire\Account;
use App\Livewire\App\Settings;
use App\Models\Appointment;
use App\Models\AppointmentNotification;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\User;
use App\Notifications\AppointmentMessage;
use App\Notifications\StaffAppointmentUpdate;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\BookingData;
use App\Services\Booking\CustomerInput;
use App\Services\Messaging\ProviderResult;
use App\Services\Messaging\SmsProvider;
use App\Services\Notifications\ChannelResolver;
use App\Services\Notifications\NotificationDeliverer;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;

/*
| Herinneringen en kanalen (docs/BOUWOPDRACHT.md §7.6). "Nu" is maandag 16 november 2026, 08:00.
*/

beforeEach(function () {
    $this->travelTo(at('2026-11-16 08:00'));

    [$this->shop, $this->owner] = shopWithOwner(['name' => 'JF Cuts']);
    $this->sam = staffWithHours($this->shop, 'Sam');
    $this->service = Service::factory()->create(['barbershop_id' => $this->shop->id, 'name' => 'Knippen', 'duration' => 30, 'price_cents' => 2500]);
    $this->appointments = app(AppointmentService::class);

    $this->settings = fn (array $values) => ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->shop->id)->update($values);

    $this->book = fn (string $at = '2026-11-17 10:00', ?CustomerInput $customer = null): Appointment => $this->appointments->book(new BookingData(
        shop: $this->shop, service: $this->service, staff: $this->sam, startsAt: at($at),
        customer: $customer ?? new CustomerInput('Daan de Vries', '0612345678', 'daan@example.com'),
    ), null);

    $this->rows = fn (Appointment $appointment) => AppointmentNotification::query()->where('appointment_id', $appointment->id)->orderBy('id')->get();
    $this->sentTypes = fn () => collect(Notification::sent(new AnonymousNotifiable, AppointmentMessage::class))->map(fn (AppointmentMessage $message) => $message->type->value)->all();
});

// ── Inplannen ────────────────────────────────────────────────────────────────────────────────────────────

it('schedules a confirmation and the first reminder 24 hours before, and sends the confirmation straight away', function () {
    Notification::fake();

    $appointment = ($this->book)();
    $rows = ($this->rows)($appointment);

    expect($rows->pluck('type')->map->value->all())->toBe(['confirmation', 'reminder_1'])
        ->and($rows[0]->status)->toBe(NotificationStatus::Sent)->and($rows[0]->channel)->toBe(MessageChannel::Mail)
        ->and($rows[1]->status)->toBe(NotificationStatus::Pending)
        ->and($rows[1]->send_at->toDateTimeString())->toBe('2026-11-16 09:00:00'); // 10:00 NL = 09:00 UTC, 24 uur eerder = 16 nov 10:00 NL

    expect(($this->sentTypes)())->toBe(['confirmation']);
});

it('also schedules a second reminder when the shop has set one', function () {
    Notification::fake();
    ($this->settings)(['reminder_1_minutes' => 720, 'reminder_2_minutes' => 120]);

    $rows = ($this->rows)(($this->book)());

    expect($rows->pluck('type')->map->value->all())->toBe(['confirmation', 'reminder_1', 'reminder_2'])
        ->and($rows[1]->send_at->toDateTimeString())->toBe('2026-11-16 21:00:00')
        ->and($rows[2]->send_at->toDateTimeString())->toBe('2026-11-17 07:00:00');
});

it('does not schedule a reminder that would already be in the past', function () {
    Notification::fake();

    // Afspraak over 12 uur: de herinnering van 24 uur vooraf is al voorbij.
    $rows = ($this->rows)(($this->book)('2026-11-16 16:00'));

    expect($rows->pluck('type')->map->value->all())->toBe(['confirmation']);
});

// ── Versturen ────────────────────────────────────────────────────────────────────────────────────────────

it('sends a due reminder once, even when the command runs twice', function () {
    Notification::fake();
    $appointment = ($this->book)();

    $this->artisan('notifications:send-due')->expectsOutput('Niets te versturen.')->assertSuccessful();
    expect(($this->sentTypes)())->toBe(['confirmation']);

    $this->travelTo(at('2026-11-16 10:00'));
    $this->artisan('notifications:send-due')->expectsOutput('sent: 1')->assertSuccessful();
    $this->artisan('notifications:send-due')->expectsOutput('Niets te versturen.')->assertSuccessful();

    expect(($this->sentTypes)())->toBe(['confirmation', 'reminder_1'])
        ->and(($this->rows)($appointment)->last()->sent_at)->not->toBeNull();
});

it('lets only one run claim a notification', function () {
    Notification::fake();
    $appointment = ($this->book)();
    $reminder = ($this->rows)($appointment)->last();
    $this->travelTo(at('2026-11-16 10:00'));

    $deliverer = app(NotificationDeliverer::class);

    expect($deliverer->deliver($reminder->id))->toBe(NotificationStatus::Sent)
        ->and($deliverer->deliver($reminder->id))->toBeNull();
    expect(collect(($this->sentTypes)())->filter(fn ($type) => $type === 'reminder_1'))->toHaveCount(1);
});

it('skips a reminder when the appointment is no longer scheduled', function () {
    Notification::fake();
    $appointment = ($this->book)();
    $appointment->forceFill(['status' => AppointmentStatus::NoShow])->save();
    $this->travelTo(at('2026-11-16 10:00'));

    $this->artisan('notifications:send-due')->expectsOutput('skipped: 1');

    expect(($this->rows)($appointment)->last())->status->toBe(NotificationStatus::Skipped)->error->toBe('Afspraak is niet meer gepland.');
    expect(($this->sentTypes)())->toBe(['confirmation']);
});

it('replans reminders and tells the customer when an appointment is rescheduled', function () {
    Notification::fake();
    $appointment = ($this->book)();

    $this->appointments->reschedule($appointment->fresh(), at('2026-11-18 14:00'), null, $this->owner);
    $rows = ($this->rows)($appointment);

    expect($rows->pluck('type')->map->value->all())->toBe(['confirmation', 'reminder_1', 'rescheduled', 'reminder_1'])
        ->and($rows[1])->status->toBe(NotificationStatus::Skipped)
        ->and($rows[3])->status->toBe(NotificationStatus::Pending)
        ->and($rows[3]->send_at->toDateTimeString())->toBe('2026-11-17 13:00:00');
    expect(($this->sentTypes)())->toBe(['confirmation', 'rescheduled']);
});

it('skips the open reminders and tells the customer when an appointment is cancelled', function () {
    Notification::fake();
    ($this->settings)(['reminder_2_minutes' => 120]);
    $appointment = ($this->book)();

    $this->appointments->cancel($appointment->fresh(), $this->owner);
    $rows = ($this->rows)($appointment);

    expect($rows->pluck('status')->map->value->all())->toBe(['sent', 'skipped', 'skipped', 'sent'])
        ->and($rows->last()->type)->toBe(NotificationType::Cancelled);
    expect(($this->sentTypes)())->toBe(['confirmation', 'cancelled']);
});

// ── Kanaalkeuze ──────────────────────────────────────────────────────────────────────────────────────────

it('prefers push, then mail, then sms and otherwise skips', function () {
    Notification::fake();
    config(['barbercuts.features.sms' => true]);
    $resolver = app(ChannelResolver::class);
    $appointment = ($this->book)();
    $customer = Customer::withoutGlobalScopes()->findOrFail($appointment->customer_id);
    $resolve = fn () => $resolver->resolve($appointment->fresh(['customer.user']));

    expect($resolve()->channel)->toBe(MessageChannel::Mail);

    $user = User::factory()->create();
    Customer::withoutGlobalScopes()->whereKey($customer->id)->update(['user_id' => $user->id]);
    expect($resolve()->channel)->toBe(MessageChannel::Mail);

    $user->updatePushSubscription('https://push.example.test/abc', 'key', 'token');
    expect($resolve()->channel)->toBe(MessageChannel::Push);

    ($this->settings)(['reminder_channels' => ['mail']]);
    expect($resolve()->channel)->toBe(MessageChannel::Mail);

    ($this->settings)(['reminder_channels' => ['mail', 'push']]);
    $user->update(['notification_preferences' => ['push' => false]]);
    expect($resolve()->channel)->toBe(MessageChannel::Mail);

    $user->update(['notification_preferences' => ['push' => false, 'mail' => false]]);
    expect($resolve()->channel)->toBeNull();

    ($this->settings)(['sms_enabled' => true]);
    expect($resolve()->channel)->toBe(MessageChannel::Sms);

    $user->update(['notification_preferences' => ['push' => false, 'mail' => true, 'sms' => true]]);
    expect($resolve()->channel)->toBe(MessageChannel::Sms);
});

it('uses sms for a customer without an e-mail address when the shop has it on, and skips otherwise', function () {
    Notification::fake();
    $noEmail = new CustomerInput('Daan', '0612345678');

    $appointment = ($this->book)('2026-11-17 10:00', $noEmail);
    expect(($this->rows)($appointment)->first())->status->toBe(NotificationStatus::Skipped)->error->toContain('Geen e-mailadres');

    config(['barbercuts.features.sms' => true]);
    ($this->settings)(['sms_enabled' => true]);
    $second = ($this->book)('2026-11-17 11:00', new CustomerInput('Yusuf', '0687654321'));
    expect(($this->rows)($second)->first())->status->toBe(NotificationStatus::Sent)->channel->toBe(MessageChannel::Sms);
});

// ── Kanalen en logs ──────────────────────────────────────────────────────────────────────────────────────

it('sends sms through the log provider and records it in message_logs with its cost', function () {
    config(['barbercuts.features.sms' => true]);
    ($this->settings)(['sms_enabled' => true]);

    ($this->book)('2026-11-17 10:00', new CustomerInput('Daan', '0612345678'));

    $log = MessageLog::query()->where('channel', 'sms')->firstOrFail();
    expect($log->channel)->toBe('sms')->and($log->to)->toBe('+31612345678')->and($log->type)->toBe('confirmation')
        ->and($log->status)->toBe('sent')->and($log->cost_cents)->toBe(0)->and($log->barbershop_id)->toBe($this->shop->id);
});

it('records a mail in message_logs', function () {
    ($this->book)();

    $log = MessageLog::query()->where('channel', 'mail')->where('to', 'daan@example.com')->firstOrFail();
    expect($log->to)->toBe('daan@example.com')->and($log->type)->toBe('confirmation')->and($log->barbershop_id)->toBe($this->shop->id);
});

it('marks a notification failed and logs the failure when the provider refuses, without retrying', function () {
    config(['barbercuts.features.sms' => true]);
    ($this->settings)(['sms_enabled' => true]);
    app()->bind(SmsProvider::class, fn () => new class implements SmsProvider
    {
        public function send(string $toE164, string $message): ProviderResult
        {
            return ProviderResult::failed('Saldo op');
        }
    });

    $appointment = ($this->book)('2026-11-17 10:00', new CustomerInput('Daan', '0612345678'));

    expect(($this->rows)($appointment)->first())->status->toBe(NotificationStatus::Failed)->error->toBe('Saldo op')
        ->and(MessageLog::query()->where('channel', 'sms')->firstOrFail())->status->toBe('failed')->error->toBe('Saldo op');

    $this->artisan('notifications:send-due')->expectsOutput('Niets te versturen.');
});

// ── Push ─────────────────────────────────────────────────────────────────────────────────────────────────

it('sends the customer a web push when they have an account with a subscription', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.test/abc', 'key', 'token');
    $appointment = ($this->book)();
    Customer::withoutGlobalScopes()->whereKey($appointment->customer_id)->update(['user_id' => $user->id]);

    $reminder = ($this->rows)($appointment)->last();
    $this->travelTo(at('2026-11-16 10:00'));
    app(NotificationDeliverer::class)->deliver($reminder->id);

    Notification::assertSentTo($user, AppointmentMessage::class, fn (AppointmentMessage $message, array $channels) => $channels === [WebPushChannel::class] && $message->type === NotificationType::Reminder1);
    expect($reminder->fresh()->channel)->toBe(MessageChannel::Push);
});

it('tells the barber by push when they have it on, and by mail otherwise', function () {
    Notification::fake();
    $update = new StaffAppointmentUpdate(1, StaffAppointmentUpdate::BOOKED);
    $barber = User::factory()->create();

    expect($update->via($barber))->toBe(['mail']);

    $barber->updatePushSubscription('https://push.example.test/barber', 'key', 'token');
    expect($update->via($barber->fresh()))->toBe([WebPushChannel::class]);
});

it('stores and removes a push subscription of the logged-in user only', function () {
    $user = User::factory()->create();
    $body = ['endpoint' => 'https://push.example.test/xyz', 'keys' => ['p256dh' => 'pub', 'auth' => 'secret'], 'contentEncoding' => 'aes128gcm'];

    $this->postJson(route('push.subscribe'), $body)->assertUnauthorized();

    $this->actingAs($user)->postJson(route('push.subscribe'), $body)->assertCreated();
    expect($user->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'http://insecure.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertUnprocessable();

    $this->actingAs($user)->postJson(route('push.unsubscribe'), ['endpoint' => $body['endpoint']])->assertOk();
    expect($user->pushSubscriptions()->count())->toBe(0);
});

// ── Voorkeuren en instellingen ───────────────────────────────────────────────────────────────────────────

it('saves notification preferences on the account page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Account::class)
        ->assertSet('preferPush', true)->assertSet('preferSms', false)
        ->set('preferPush', false)->set('preferSms', true)->call('savePreferences');

    expect($user->fresh()->notification_preferences)->toEqual(['push' => false, 'mail' => true, 'sms' => true, 'whatsapp' => false]);
});

it('lets the owner choose the channels of the shop', function () {
    config(['barbercuts.features.sms' => true]);
    actingInShop($this->owner, $this->shop);

    Livewire::test(Settings::class)
        ->assertSet('channelPush', true)
        ->set('channelPush', false)->set('smsEnabled', true)->call('save')->assertHasNoErrors();

    $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $this->shop->id)->firstOrFail();
    expect($settings->reminder_channels)->toBe(['mail'])->and($settings->sms_enabled)->toBeTrue();
});

it('shows the push toggle only for logged-in users when VAPID keys are set', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('account'))->assertOk()->assertDontSee('Meldingen op dit apparaat');

    config(['webpush.vapid.public_key' => 'BPublicKeyForTest']);
    $this->actingAs($user)->get(route('account'))->assertOk()->assertSee('Meldingen op dit apparaat')->assertSee('BPublicKeyForTest');
});
