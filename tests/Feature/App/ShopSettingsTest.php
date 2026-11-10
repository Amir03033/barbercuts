<?php

use App\Livewire\App\Appearance;
use App\Livewire\App\OpeningHours;
use App\Livewire\App\Settings;
use App\Livewire\App\Today;
use App\Models\OpeningHour;
use App\Models\ShopImage;
use App\Models\ShopSetting;
use App\Models\TimeOff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    [$this->shop, $this->owner] = shopWithOwner();
    actingInShop($this->owner, $this->shop);
});

it('saves opening hours and replaces the old ones', function () {
    OpeningHour::create(['day_of_week' => 7, 'opens_at' => '12:00', 'closes_at' => '17:00']);

    Livewire::test(OpeningHours::class)
        ->assertSet('week.7.open', true)
        ->set('week.7.open', false)
        ->set('week.1.open', true)
        ->set('week.1.blocks', [['from' => '10:00', 'to' => '19:00']])
        ->call('save');

    expect(OpeningHour::query()->get(['day_of_week', 'opens_at', 'closes_at'])->toArray())
        ->toBe([['day_of_week' => 1, 'opens_at' => '10:00:00', 'closes_at' => '19:00:00']]);
});

it('copies one day to all open days', function () {
    Livewire::test(OpeningHours::class)
        ->set('week.1.open', true)->set('week.2.open', true)
        ->set('week.1.blocks', [['from' => '08:00', 'to' => '16:00']])
        ->call('copyDayToAll', 1)
        ->assertSet('week.2.blocks', [['from' => '08:00', 'to' => '16:00']])
        ->assertSet('week.3.open', false);
});

it('adds and removes a day the whole shop is closed', function () {
    $this->travelTo('2027-04-01 12:00');

    $component = Livewire::test(OpeningHours::class)
        ->set('closedFrom', '2027-04-27')->set('closedReason', 'Koningsdag')
        ->call('addClosure')
        ->assertHasNoErrors()
        ->assertSee('Koningsdag');

    $closure = TimeOff::query()->firstOrFail();

    expect($closure->staff_member_id)->toBeNull()
        ->and($closure->starts_at->toDateTimeString())->toBe('2027-04-26 22:00:00')
        ->and($closure->ends_at->toDateTimeString())->toBe('2027-04-27 22:00:00');

    $component->call('deleteTimeOff', $closure->id);

    expect(TimeOff::query()->count())->toBe(0);
});

it('saves booking rules and reminders', function () {
    Livewire::test(Settings::class)
        ->assertSet('reminder1', '1440')->assertSet('reminder2', 'off')
        ->set('slotInterval', '30')->set('minNotice', '120')->set('maxDaysAhead', '30')
        ->set('cancelLimit', '1440')->set('allowGuestBooking', false)
        ->set('reminder1', 'custom')->set('reminder1Hours', '6')
        ->set('reminder2', '120')
        ->call('save')
        ->assertHasNoErrors();

    $settings = ShopSetting::query()->firstOrFail();

    expect($settings->slot_interval)->toBe(30)
        ->and($settings->min_notice)->toBe(120)
        ->and($settings->max_days_ahead)->toBe(30)
        ->and($settings->customer_cancel_limit)->toBe(1440)
        ->and($settings->allow_guest_booking)->toBeFalse()
        ->and($settings->reminder_1_minutes)->toBe(360)
        ->and($settings->reminder_2_minutes)->toBe(120);

    Livewire::test(Settings::class)->assertSet('reminder1', 'custom')->assertSet('reminder1Hours', '6');
});

it('requires the second reminder to be later than the first', function () {
    Livewire::test(Settings::class)
        ->set('reminder1', '180')->set('reminder2', '180')
        ->call('save')
        ->assertHasErrors('reminder2');

    expect(ShopSetting::query()->firstOrFail()->reminder_2_minutes)->toBeNull();
});

it('rejects values outside the allowed options', function () {
    Livewire::test(Settings::class)->set('slotInterval', '7')->set('maxDaysAhead', '0')->call('save')
        ->assertHasErrors(['slotInterval', 'maxDaysAhead']);
});

it('saves the theme and picks readable text on the accent colour', function () {
    Livewire::test(Appearance::class)
        ->set('themeMode', 'dark')->set('accentColor', '#FACC15')->set('description', 'Sinds 2015')
        ->call('save')
        ->assertHasNoErrors();

    $shop = $this->shop->fresh();

    expect($shop->theme_mode->value)->toBe('dark')
        ->and($shop->accent_color)->toBe('#facc15')
        ->and($shop->onAccentColor())->toBe('#111114')
        ->and($shop->description)->toBe('Sinds 2015');

    $this->get("/{$shop->slug}")->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('--accent: #facc15; --on-accent: #111114', false);
});

it('warns when the accent colour has too little contrast with the background', function () {
    Livewire::test(Appearance::class)
        ->set('accentColor', '#fef9c3')->assertSee('valt weinig op')
        ->set('themeMode', 'dark')->assertDontSee('valt weinig op');
});

it('rejects an accent colour that is not a hex colour', function () {
    Livewire::test(Appearance::class)->set('accentColor', 'red; background: url(x)')->call('save')->assertHasErrors('accentColor');

    expect($this->shop->fresh()->accent_color)->toBe('#111827');
});

it('stores logo, cover and photos as webp and removes them again', function () {
    Storage::fake('public');

    Livewire::test(Appearance::class)
        ->set('logo', UploadedFile::fake()->image('logo.png', 900, 900))
        ->set('cover', UploadedFile::fake()->image('cover.jpg', 3000, 1500))
        ->call('save')
        ->assertHasNoErrors()
        ->set('photos', [UploadedFile::fake()->image('een.jpg', 2000, 1000), UploadedFile::fake()->image('twee.jpg')])
        ->assertHasNoErrors();

    $shop = $this->shop->fresh();
    $photos = ShopImage::query()->get();

    expect($shop->logo_path)->toEndWith('.webp')
        ->and(getimagesizefromstring(Storage::disk('public')->get($shop->logo_path))[0])->toBe(400)
        ->and(getimagesizefromstring(Storage::disk('public')->get($shop->cover_path))[0])->toBe(1600)
        ->and($photos)->toHaveCount(2)
        ->and(getimagesizefromstring(Storage::disk('public')->get($photos[0]->path))[0])->toBe(1200);

    Livewire::test(Appearance::class)->call('removeLogo')->call('deletePhoto', $photos[0]->id);

    Storage::disk('public')->assertMissing($shop->logo_path);
    Storage::disk('public')->assertMissing($photos[0]->path);
    expect($this->shop->fresh()->logo_path)->toBeNull()->and(ShopImage::query()->count())->toBe(1);
});

it('rejects uploads that are not images', function () {
    Storage::fake('public');

    Livewire::test(Appearance::class)
        ->set('logo', UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'))
        ->assertHasErrors('logo');
});

it('shows the checklist until everything is done and records sharing the link', function () {
    Livewire::test(Today::class)
        ->assertSee('Maak je zaak af')->assertSee('Logo uploaden')
        ->call('markLinkShared');

    expect($this->shop->fresh()->link_shared_at)->not->toBeNull();
});

it('serves the QR code of the booking link as svg', function () {
    $this->get('/app/qr-code')->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'attachment; filename="qr-'.$this->shop->slug.'.svg"');
});
