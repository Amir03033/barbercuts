<?php

namespace App\Models;

use App\Enums\AccessState;
use App\Enums\SubscriptionStatus;
use App\Enums\ThemeMode;
use App\Support\Color;
use App\Support\Features;
use App\Support\ImageStore;
use App\Support\ShopWriteGuard;
use Database\Factories\BarbershopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * De zaak (tenant).
 *
 * @property ThemeMode $theme_mode
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $free_until
 * @property Carbon|null $suspended_at
 */
#[Fillable([
    'slug', 'name', 'description', 'phone', 'email', 'address', 'postal_code', 'city', 'latitude', 'longitude',
    'timezone', 'theme_mode', 'accent_color', 'logo_path', 'cover_path',
])]
class Barbershop extends Model
{
    /** @use HasFactory<BarbershopFactory> */
    use HasFactory;

    public const string DEFAULT_ACCENT = '#111827';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'Europe/Amsterdam',
        'theme_mode' => 'light',
        'accent_color' => self::DEFAULT_ACCENT,
        'is_public' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme_mode' => ThemeMode::class,
            'is_public' => 'boolean',
            'onboarding_step' => 'integer',
            'link_shared_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'free_until' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_user')->withPivot('role')->withTimestamps();
    }

    /**
     * @return HasMany<StaffMember, $this>
     */
    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<OpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    /**
     * @return HasMany<TimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(TimeOff::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<ShopImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ShopImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasOne<ShopSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(ShopSetting::class);
    }

    protected static function booted(): void
    {
        // Een zaak wijzigen (thema, adres, ...) kan niet zolang hij alleen-lezen is (§7.7).
        static::saving(fn () => ShopWriteGuard::assertWritable());
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * De toegangsstatus van de zaak: één methode, overal gebruikt (§7.7).
     * Staat facturering uit (gratis start, B12), dan is iedere niet-geblokkeerde zaak gratis.
     */
    public function accessState(): AccessState
    {
        if ($this->suspended_at !== null) {
            return AccessState::Suspended;
        }

        if (! Features::billing()) {
            return AccessState::Free;
        }

        if ($this->free_until?->isFuture()) {
            return AccessState::Free;
        }

        if ($this->activeSubscription() !== null) {
            return AccessState::Active;
        }

        if ($this->trial_ends_at?->isFuture()) {
            return AccessState::Trial;
        }

        if ($this->pastDueSubscriptionWithinGrace(days: 7) !== null) {
            return AccessState::PastDue;
        }

        return AccessState::Expired;
    }

    /**
     * Zaken in een bepaalde toegangsstatus, in SQL (voor filters en statistiek). Moet hetzelfde antwoord geven als accessState().
     *
     * @param  Builder<Barbershop>  $query
     * @return Builder<Barbershop>
     */
    public function scopeInAccessState(Builder $query, AccessState $state): Builder
    {
        if ($state === AccessState::Suspended) {
            return $query->whereNotNull('barbershops.suspended_at');
        }

        $query->whereNull('barbershops.suspended_at');

        if (! Features::billing()) {
            return $state === AccessState::Free ? $query : $query->whereRaw('0 = 1');
        }

        $now = now();
        $notFree = fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('barbershops.free_until')->orWhere('barbershops.free_until', '<=', $now));
        $hasSubscription = fn (Builder $q, bool $exists, callable $conditions) => $exists
            ? $q->whereExists(fn ($sub) => $conditions($sub->select(DB::raw(1))->from('subscriptions')->whereColumn('subscriptions.barbershop_id', 'barbershops.id')))
            : $q->whereNotExists(fn ($sub) => $conditions($sub->select(DB::raw(1))->from('subscriptions')->whereColumn('subscriptions.barbershop_id', 'barbershops.id')));
        $running = fn ($sub) => $sub->where(fn ($w) => $w
            ->where('subscriptions.status', SubscriptionStatus::Active->value)
            ->orWhere(fn ($c) => $c->where('subscriptions.status', SubscriptionStatus::Cancelled->value)->where('subscriptions.current_period_ends_at', '>', $now)));
        $pastDue = fn ($sub) => $sub->where('subscriptions.status', SubscriptionStatus::PastDue->value)->where('subscriptions.past_due_at', '>', $now->copy()->subDays(7));
        $inTrial = fn (Builder $q, bool $in) => $in
            ? $q->where('barbershops.trial_ends_at', '>', $now)
            : $q->where(fn ($w) => $w->whereNull('barbershops.trial_ends_at')->orWhere('barbershops.trial_ends_at', '<=', $now));

        return match ($state) {
            AccessState::Free => $query->where('barbershops.free_until', '>', $now),
            AccessState::Active => $hasSubscription($notFree($query), true, $running),
            AccessState::Trial => $inTrial($hasSubscription($notFree($query), false, $running), true),
            AccessState::PastDue => $hasSubscription($inTrial($hasSubscription($notFree($query), false, $running), false), true, $pastDue),
            default => $hasSubscription($inTrial($hasSubscription($notFree($query), false, $running), false), false, $pastDue),
        };
    }

    /**
     * Een lopend abonnement: actief, of opgezegd maar nog betaald tot het einde van de periode.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where(fn ($query) => $query
                ->where('status', SubscriptionStatus::Active->value)
                ->orWhere(fn ($cancelled) => $cancelled
                    ->where('status', SubscriptionStatus::Cancelled->value)
                    ->where('current_period_ends_at', '>', now())))
            ->latest('id')
            ->first();
    }

    /**
     * Een abonnement waarvan de betaling mislukt is, binnen de coulanceperiode: volledige toegang plus een banner.
     */
    public function pastDueSubscriptionWithinGrace(int $days): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->where('past_due_at', '>', now()->subDays($days))
            ->latest('id')
            ->first();
    }

    /**
     * Hele dagen proefperiode over, of null als er geen lopende proefperiode is.
     */
    public function trialDaysLeft(): ?int
    {
        return $this->trial_ends_at?->isFuture() ? (int) ceil(now()->diffInDays($this->trial_ends_at, false)) : null;
    }

    public function hasFinishedOnboarding(): bool
    {
        return $this->onboarding_step === null;
    }

    /**
     * Wit of zwart voor tekst op de accentkleur (WCAG, §7.9).
     */
    public function onAccentColor(): string
    {
        return Color::onColor($this->accent_color);
    }

    public function bookingUrl(): string
    {
        return url('/'.$this->slug);
    }

    /**
     * De boekingslink zonder https://, om te tonen.
     */
    public function bookingUrlLabel(): string
    {
        return (string) preg_replace('#^https?://(www\.)?#', '', $this->bookingUrl());
    }

    public function logoUrl(): ?string
    {
        return ImageStore::url($this->logo_path);
    }

    public function coverUrl(): ?string
    {
        return ImageStore::url($this->cover_path);
    }
}
