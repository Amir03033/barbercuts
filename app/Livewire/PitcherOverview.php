<?php

namespace App\Livewire;

use App\Enums\AccessState;
use App\Enums\PitcherRequestStatus;
use App\Enums\PitcherRequestType;
use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Models\DemoInvite;
use App\Models\DemoVisit;
use App\Models\PitcherRequest;
use App\Models\PromoVideo;
use App\Models\User;
use App\Notifications\PitcherRequestReceived;
use App\Support\Color;
use App\Support\Features;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Het eigen overzicht van een pitcher: de aanmeldlink en QR-code, en welke zaken hij heeft aangebracht en hoe het daarmee gaat.
 * Alleen wat nodig is om op te volgen: naam, plaats, telefoon en status. Nooit omzet, klanten of afspraken.
 */
#[Layout('layouts::platform')]
#[Title('Mijn aanmeldingen')]
class PitcherOverview extends Component
{
    /** Binnen zoveel dagen na het einde van de proef is het tijd om te bellen. */
    private const int URGENT_DAYS = 3;

    #[Locked]
    public ?int $extensionShopId = null;

    public string $extensionReason = '';

    public string $wish = '';

    // Persoonlijke demo voor één zaak
    public string $inviteShopName = '';

    public string $inviteCity = '';

    public string $inviteColor = '';

    #[Locked]
    public ?int $newInviteId = null;

    /**
     * Zet een demo klaar met de naam (en eventueel plaats en kleur) van de zaak van een kapper.
     */
    public function createInvite(): void
    {
        $this->inviteShopName = trim($this->inviteShopName);
        $this->inviteCity = trim($this->inviteCity);

        $this->validate([
            'inviteShopName' => ['required', 'string', 'min:2', 'max:60'],
            'inviteCity' => ['nullable', 'string', 'max:60'],
            'inviteColor' => ['nullable', Rule::in(Color::PRESETS)],
        ], attributes: ['inviteShopName' => 'naam van de zaak', 'inviteCity' => 'plaats', 'inviteColor' => 'kleur']);

        $key = 'demo-invite:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('inviteShopName', 'Je hebt er vandaag al veel gemaakt. Probeer het morgen weer.');

            return;
        }

        RateLimiter::hit($key, 86400);

        /** @var User $pitcher */
        $pitcher = Auth::user();

        $invite = new DemoInvite([
            'shop_name' => $this->inviteShopName,
            'city' => $this->inviteCity !== '' ? $this->inviteCity : null,
            'accent_color' => $this->inviteColor !== '' ? $this->inviteColor : null,
        ]);
        $invite->user_id = $pitcher->id;
        $invite->save();

        $this->newInviteId = $invite->id;
        $this->reset('inviteShopName', 'inviteCity', 'inviteColor');
        $this->dispatch('toast', message: 'Demo voor '.$invite->shop_name.' staat klaar');
    }

    public function deleteInvite(int $inviteId): void
    {
        DemoInvite::query()->where('user_id', Auth::id())->whereKey($inviteId)->delete();

        if ($this->newInviteId === $inviteId) {
            $this->newInviteId = null;
        }
    }

    /**
     * Open het venster om een langere proef voor één van jouw zaken aan te vragen.
     */
    public function openExtension(int $shopId): void
    {
        $shop = $this->ownShop($shopId);

        $this->extensionShopId = $shop->id;
        $this->extensionReason = '';
        $this->resetValidation();
        $this->dispatch('open-sheet', name: 'extension');
    }

    public function submitExtension(): void
    {
        $this->validate(['extensionReason' => ['required', 'string', 'min:5', 'max:500']], attributes: ['extensionReason' => 'reden']);

        $shop = $this->ownShop((int) $this->extensionShopId);

        if ($this->throttled()) {
            return;
        }

        $alreadyOpen = PitcherRequest::query()->where('user_id', Auth::id())->where('barbershop_id', $shop->id)
            ->where('type', PitcherRequestType::TrialExtension->value)->where('status', PitcherRequestStatus::Open->value)->exists();

        if ($alreadyOpen) {
            $this->dispatch('close-sheet');
            $this->dispatch('toast', message: 'Je hebt hiervoor al een verzoek lopen');

            return;
        }

        $this->record(PitcherRequestType::TrialExtension, trim($this->extensionReason), $shop);

        $this->reset('extensionReason', 'extensionShopId');
        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: 'Verzoek verstuurd. Je hoort het zo snel mogelijk.');
    }

    public function submitWish(): void
    {
        $this->validate(['wish' => ['required', 'string', 'min:5', 'max:1000']], attributes: ['wish' => 'bericht']);

        if ($this->throttled()) {
            return;
        }

        $this->record(PitcherRequestType::Wish, trim($this->wish), null);

        $this->reset('wish');
        $this->dispatch('toast', message: 'Bedankt! We hebben je wens ontvangen.');
    }

    private function ownShop(int $shopId): Barbershop
    {
        return Barbershop::query()->where('referred_by_user_id', Auth::id())->whereKey($shopId)->firstOrFail();
    }

    private function throttled(): bool
    {
        $key = 'pitcher-requests:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('toast', message: 'Even rustig: je hebt veel verzoeken gedaan. Probeer het later opnieuw.', type: 'error');

            return true;
        }

        RateLimiter::hit($key, 3600);

        return false;
    }

    private function record(PitcherRequestType $type, string $message, ?Barbershop $shop): void
    {
        $request = new PitcherRequest;
        $request->user_id = Auth::id();
        $request->barbershop_id = $shop?->id;
        $request->type = $type;
        $request->message = $message;
        $request->save();

        Notification::route('mail', (string) config('barbercuts.company.email'))->notify(new PitcherRequestReceived($request->id));
    }

    public function render(): View
    {
        /** @var User $pitcher */
        $pitcher = Auth::user();

        $link = route('onboarding', ['ref' => $pitcher->referralCode()]);
        $billing = Features::billing();

        $rows = $pitcher->referredShops()
            ->with(['users' => fn ($query) => $query->wherePivot('role', ShopRole::Owner->value)])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Barbershop $shop): array => $this->row($shop, $billing));

        // Eerst wat aandacht nodig heeft, daarna de nieuwste.
        $rows = $rows->sortBy([['attention', 'desc'], ['created', 'desc']])->values();

        $requests = PitcherRequest::query()->where('user_id', $pitcher->id)->with('barbershop')->latest('id')->limit(8)->get();

        $invites = DemoInvite::query()->where('user_id', $pitcher->id)->latest('id')->limit(12)
            ->withCount(['visits as signed_up_count' => fn ($query) => $query->whereNotNull('signed_up_barbershop_id')])->get();

        $visits = DemoVisit::query()->where('pitcher_id', $pitcher->id)->with(['invite', 'signedUpShop'])->latest('id')->limit(8)->get();

        return view('livewire.pitcher-overview', [
            'invites' => $invites,
            'videos' => PromoVideo::query()->shown('for_pitchers')->get(),
            'visits' => $visits,
            'colors' => Color::PRESETS,
            'requests' => $requests,
            'openExtensions' => $requests->where('type', PitcherRequestType::TrialExtension)->where('status', PitcherRequestStatus::Open)->pluck('barbershop_id')->all(),
            'pitcher' => $pitcher,
            'link' => $link,
            'demoLink' => $pitcher->demoLink(),
            'qr' => (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($link),
            'billing' => $billing,
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ]);
    }

    /**
     * @return array{shop: Barbershop, state: AccessState, finished: bool, attention: bool, daysLeft: int|null, owner: string|null, created: int}
     */
    private function row(Barbershop $shop, bool $billing): array
    {
        $state = $shop->accessState();
        $daysLeft = $shop->trialDaysLeft();
        $finished = $shop->hasFinishedOnboarding();

        $attention = $billing && $finished && (
            in_array($state, [AccessState::Expired, AccessState::PastDue], true)
            || ($state === AccessState::Trial && $daysLeft !== null && $daysLeft <= self::URGENT_DAYS)
        );

        return [
            'shop' => $shop,
            'state' => $state,
            'finished' => $finished,
            'attention' => $attention,
            'daysLeft' => $state === AccessState::Trial ? $daysLeft : null,
            'owner' => $shop->users->first()?->name,
            'created' => $shop->created_at?->getTimestamp() ?? 0,
        ];
    }

    /**
     * @param  Collection<int, array{shop: Barbershop, state: AccessState, finished: bool, attention: bool, daysLeft: int|null, owner: string|null, created: int}>  $rows
     * @return array{signedUp: int, live: int, trial: int, paying: int, attention: int}
     */
    private function totals(Collection $rows): array
    {
        $finished = $rows->where('finished', true);

        return [
            'signedUp' => $rows->count(),
            'live' => $finished->count(),
            'trial' => $finished->where('state', AccessState::Trial)->count(),
            'paying' => $finished->where('state', AccessState::Active)->count(),
            'attention' => $rows->where('attention', true)->count(),
        ];
    }
}
