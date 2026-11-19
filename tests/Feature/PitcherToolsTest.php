<?php

use App\Enums\PitcherRequestStatus;
use App\Enums\PitcherRequestType;
use App\Filament\Resources\PitcherRequests\Pages\ListPitcherRequests;
use App\Filament\Resources\PitcherRequests\PitcherRequestResource;
use App\Livewire\PitcherOverview;
use App\Models\ActivityLog;
use App\Models\PitcherRequest;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\PitcherRequestHandled;
use App\Notifications\PitcherRequestReceived;
use App\Support\CurrentShop;
use App\Support\PitcherMessages;
use Filament\Facades\Filament;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/*
| Gereedschap voor pitchers: berichten, flyer, verzoeken (proef verlengen, wens) en het afhandelen daarvan.
| "Nu" is dinsdag 17 november 2026, 10:00.
*/

function toolsPitcher(string $name = 'Sam Bakker'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->forceFill(['is_pitcher' => true])->save();
    $user->referralCode();

    return $user->fresh();
}

beforeEach(function () {
    $this->travelTo(at('2026-11-17 10:00'));
    Notification::fake();
});

// ── Berichten ────────────────────────────────────────────────────────────────────────────────────────────

it('keeps the messages and flyer pages for pitchers only', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($path)->assertNotFound();
    $this->actingAs(toolsPitcher())->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['/pitcher/berichten', '/pitcher/flyer']);

it('fills the own name, link and price into ready-made messages', function () {
    $pitcher = toolsPitcher('Sam Bakker');

    $messages = collect(PitcherMessages::for($pitcher));

    expect($messages->count())->toBeGreaterThanOrEqual(8)
        ->and($messages->firstWhere('key', 'first')['text'])->toContain('ik ben Sam')->toContain('[naam]')
        ->and($messages->firstWhere('key', 'after_visit')['text'])->toContain('/aanmelden?ref=sam')
        ->and($messages->firstWhere('key', 'ending')['text'])->toContain('€ 10,00');   // het actieve plan in de tests

    $this->actingAs($pitcher)->get('/pitcher/berichten')
        ->assertOk()->assertSee('Berichten om te kopiëren')->assertSee('Eerste bericht')->assertSee('Open in WhatsApp')
        ->assertSee('https://wa.me/?text=', false);
});

it('uses the price and trial length of the active plan in messages', function () {
    Plan::query()->update(['price_cents' => 2000, 'trial_days' => 14]);

    $text = collect(PitcherMessages::for(toolsPitcher()))->firstWhere('key', 'expired')['text'];

    expect($text)->toContain('€ 20,00 per maand');
});

// ── Flyer ────────────────────────────────────────────────────────────────────────────────────────────────

it('prints a flyer with the own QR code, link, name and trial', function () {
    billingOn();
    Plan::query()->update(['price_cents' => 2000, 'trial_days' => 14]);

    $this->actingAs(toolsPitcher('Sam Bakker'))->get('/pitcher/flyer')
        ->assertOk()->assertSee('Je kapperszaak in je broekzak')->assertSee('<svg', false)
        ->assertSee('/aanmelden?ref=sam', false)->assertSee('14 dagen')->assertSee('€ 20,00 per maand')->assertSee('Sam Bakker')
        ->assertSee('Afdrukken');
});

it('says on the flyer that it is free during the launch while billing is off', function () {
    $this->actingAs(toolsPitcher())->get('/pitcher/flyer')->assertOk()->assertSee('Tijdens de lancering gratis');
});

// ── Verzoeken van de pitcher ─────────────────────────────────────────────────────────────────────────────

it('lets a pitcher ask for a longer trial for his own shop and mails the admin', function () {
    billingOn();
    $pitcher = toolsPitcher();
    [$shop] = shopInState('trial');
    $shop->forceFill(['referred_by_user_id' => $pitcher->id])->save();

    $this->actingAs($pitcher);

    Livewire::test(PitcherOverview::class)
        ->call('openExtension', $shop->id)
        ->set('extensionReason', 'Veel drukte deze week')
        ->call('submitExtension')->assertHasNoErrors()->assertDispatched('toast');

    $request = PitcherRequest::query()->sole();

    expect($request->type)->toBe(PitcherRequestType::TrialExtension)
        ->and($request->barbershop_id)->toBe($shop->id)->and($request->user_id)->toBe($pitcher->id)
        ->and($request->status)->toBe(PitcherRequestStatus::Open);

    Notification::assertSentOnDemand(PitcherRequestReceived::class);
});

it('does not let a pitcher ask for a shop that is not his, or ask twice for the same shop', function () {
    billingOn();
    $sam = toolsPitcher('Sam Bakker');
    $mo = toolsPitcher('Mo Kaya');
    [$foreign] = shopInState('trial');
    $foreign->forceFill(['referred_by_user_id' => $mo->id])->save();
    [$own] = shopInState('trial');
    $own->forceFill(['referred_by_user_id' => $sam->id])->save();

    $this->actingAs($sam);

    Livewire::test(PitcherOverview::class)->call('openExtension', $foreign->id)->assertStatus(404);

    $page = Livewire::test(PitcherOverview::class)->call('openExtension', $own->id)->set('extensionReason', 'Meer tijd nodig')->call('submitExtension');
    $page->call('openExtension', $own->id)->set('extensionReason', 'Nog een keer')->call('submitExtension');

    expect(PitcherRequest::query()->count())->toBe(1);
});

it('validates the reason and shows "Verzoek verstuurd" for an open request', function () {
    billingOn();
    $pitcher = toolsPitcher();
    [$shop] = shopInState('trial');
    $shop->forceFill(['referred_by_user_id' => $pitcher->id])->save();
    $this->actingAs($pitcher);

    Livewire::test(PitcherOverview::class)->call('openExtension', $shop->id)->set('extensionReason', 'x')->call('submitExtension')->assertHasErrors('extensionReason')
        ->assertSee('Proef verlengen')
        ->set('extensionReason', 'Een goede reden')->call('submitExtension')->assertSee('Verzoek verstuurd');
});

it('lets a pitcher pass on a wish or question, which is shown in his list', function () {
    $this->actingAs(toolsPitcher());

    Livewire::test(PitcherOverview::class)
        ->set('wish', 'Een kapper wil Tikkie-betalingen')->call('submitWish')->assertHasNoErrors()
        ->assertSee('Wens van een kapper')->assertSee('Een kapper wil Tikkie-betalingen');

    expect(PitcherRequest::query()->sole()->type)->toBe(PitcherRequestType::Wish);
    Notification::assertSentOnDemand(PitcherRequestReceived::class);
});

it('stops a pitcher who floods the admin with requests', function () {
    $pitcher = toolsPitcher();
    $this->actingAs($pitcher);
    RateLimiter::clear('pitcher-requests:'.$pitcher->id);

    $page = Livewire::test(PitcherOverview::class);

    foreach (range(1, 12) as $i) {
        $page->set('wish', "Wens nummer {$i}")->call('submitWish');
    }

    expect(PitcherRequest::query()->count())->toBe(10);
});

// ── Afhandelen door de admin ─────────────────────────────────────────────────────────────────────────────

function adminForRequests(): User
{
    Filament::setCurrentPanel('admin');
    app(CurrentShop::class)->disableScope();

    return User::factory()->create(['is_admin' => true]);
}

it('extends the trial from a request, marks it done, logs it and mails the pitcher', function () {
    billingOn();
    $admin = adminForRequests();
    $pitcher = toolsPitcher();
    [$shop] = shopInState('trial');
    $shop->forceFill(['referred_by_user_id' => $pitcher->id, 'trial_ends_at' => now()->addDays(2)])->save();

    $request = new PitcherRequest;
    $request->user_id = $pitcher->id;
    $request->barbershop_id = $shop->id;
    $request->type = PitcherRequestType::TrialExtension;
    $request->message = 'Meer tijd';
    $request->save();

    Livewire::actingAs($admin)->test(ListPitcherRequests::class)
        ->callTableAction('extendTrial', $request, data: ['days' => '7', 'answer' => 'Veel succes!'])->assertNotified();

    expect($shop->fresh()->trial_ends_at->toDateString())->toBe('2026-11-26')   // 17 nov + 2 dagen + 7 dagen
        ->and($request->fresh()->status)->toBe(PitcherRequestStatus::Done)
        ->and($request->fresh()->answer)->toBe('Veel succes!')
        ->and($request->fresh()->handled_at)->not->toBeNull()
        ->and(ActivityLog::query()->whereIn('action', ['shop.trial_extended', 'pitcher_request.done'])->count())->toBe(2);

    Notification::assertSentTo($pitcher, PitcherRequestHandled::class);
});

it('declines a request only with a reason, and mails the pitcher', function () {
    $admin = adminForRequests();
    $pitcher = toolsPitcher();
    $request = new PitcherRequest;
    $request->user_id = $pitcher->id;
    $request->type = PitcherRequestType::Wish;
    $request->message = 'Een wens';
    $request->save();

    Livewire::actingAs($admin)->test(ListPitcherRequests::class)
        ->callTableAction('decline', $request, data: ['answer' => ''])->assertHasTableActionErrors(['answer' => 'required']);

    Livewire::actingAs($admin)->test(ListPitcherRequests::class)
        ->callTableAction('decline', $request, data: ['answer' => 'Past nu niet in de planning'])->assertNotified('Afgewezen');

    expect($request->fresh()->status)->toBe(PitcherRequestStatus::Declined);
    Notification::assertSentTo($pitcher, PitcherRequestHandled::class, fn ($n) => $n->requestId === $request->id);
});

it('shows the number of open requests as a badge and hides the actions of a finished request', function () {
    $admin = adminForRequests();
    $pitcher = toolsPitcher();

    foreach ([PitcherRequestStatus::Open, PitcherRequestStatus::Open, PitcherRequestStatus::Done] as $status) {
        $request = new PitcherRequest;
        $request->user_id = $pitcher->id;
        $request->type = PitcherRequestType::Question;
        $request->message = 'Vraag';
        $request->status = $status;
        $request->save();
    }

    expect(PitcherRequestResource::getNavigationBadge())->toBe('2');

    $done = PitcherRequest::query()->where('status', 'done')->first();

    Livewire::actingAs($admin)->test(ListPitcherRequests::class)->filterTable('status', 'done')
        ->assertTableActionHidden('handle', $done)->assertTableActionHidden('decline', $done);
});

it('renders the mails for the admin and the pitcher', function () {
    $pitcher = toolsPitcher();
    [$shop] = shopWithOwner(['name' => 'Fade Factory']);
    $request = new PitcherRequest;
    $request->user_id = $pitcher->id;
    $request->barbershop_id = $shop->id;
    $request->type = PitcherRequestType::TrialExtension;
    $request->message = 'Meer tijd graag';
    $request->status = PitcherRequestStatus::Done;
    $request->answer = 'Gedaan';
    $request->save();

    $received = (new PitcherRequestReceived($request->id))->toMail(new AnonymousNotifiable);
    $handled = (new PitcherRequestHandled($request->id))->toMail($pitcher);

    expect($received->subject)->toContain('Proef verlengen')->and(implode(' ', $received->introLines))->toContain('Fade Factory')->toContain('Meer tijd graag')
        ->and($handled->subject)->toBe('Je verzoek is afgehandeld')->and(implode(' ', $handled->introLines))->toContain('Gedaan');
});
