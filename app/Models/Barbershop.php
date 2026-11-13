<?php

namespace App\Models;

use App\Enums\AccessState;
use App\Enums\SubscriptionStatus;
use App\Enums\ThemeMode;
use App\Jobs\GeocodeBarbershop;
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
use Illuminate\Support\Str;

/**
 * De zaak (tenant).
 *
 * @property ThemeMode $theme_mode
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $free_until
 * @property Carbon|null $suspended_at
 * @property bool $is_demo
 * @property Carbon|null $demo_expires_at
 * @property float|null $rating_avg
 * @property int $rating_count
 * @property float|null $latitude
 * @property float|null $longitude
 */
#[Fillable([
    'slug', 'name', 'description', 'phone', 'email', 'address', 'postal_code', 'city', 'latitude', 'longitude',
    'timezone', 'theme_mode', 'accent_color', 'logo_path', 'cover_path',
    'banner_active', 'banner_text', 'banner_path', 'instagram', 'website',
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
        'banner_active' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme_mode' => ThemeMode::class,
            'is_public' => 'boolean',
            'is_demo' => 'boolean',
            'banner_active' => 'boolean',
            'rating_avg' => 'float',
            'rating_count' => 'integer',
            'rating_score' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'demo_expires_at' => 'datetime',
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
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Genoeg reviews om in "top beoordeeld" mee te doen.
     */
    public function hasRating(): bool
    {
        return $this->rating_count > 0 && $this->rating_avg !== null;
    }

    /**
     * Zaken die in de kappersgids mogen staan: echt, publiek en klaar met aanmelden, niet geblokkeerd.
     *
     * @param  Builder<Barbershop>  $query
     * @return Builder<Barbershop>
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('barbershops.is_demo', false)
            ->where('barbershops.is_public', true)
            ->whereNull('barbershops.onboarding_step')
            ->whereNull('barbershops.suspended_at');
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

        // De plaats als slug voor de kappersgids (/kappers/{stad}); bij een nieuw adres opnieuw de coördinaten ophalen (fase 7).
        static::saving(function (self $shop): void {
            if ($shop->isDirty('city')) {
                $shop->city_slug = Str::slug((string) $shop->city) ?: null;
            }
        });

        static::saved(function (self $shop): void {
            if ($shop->wasChanged(['address', 'postal_code', 'city']) || ($shop->wasRecentlyCreated && $shop->city !== null && $shop->latitude === null)) {
                GeocodeBarbershop::dispatch($shop->id)->afterCommit();
            }
        });
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

        // Een demozaak valt altijd buiten facturering (fase 6).
        if ($this->is_demo || ! Features::billing()) {
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

    /**
     * Echte zaken: zonder de tijdelijke demozaken van pitchers. Voor statistiek, lijsten en straks de kappersgids.
     *
     * @param  Builder<Barbershop>  $query
     * @return Builder<Barbershop>
     */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('barbershops.is_demo', false);
    }

    /**
     * Een demozaak waarvan de tijd om is; wordt binnen het uur opgeruimd.
     */
    public function isExpiredDemo(): bool
    {
        return $this->is_demo && ($this->demo_expires_at === null || $this->demo_expires_at->isPast());
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

    /**
     * De zaak als schema.org HairSalon, met beoordeling als die er is (voor zoekmachines).
     *
     * @return array<string, mixed>
     */
    public function structuredData(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'HairSalon',
            'name' => $this->name,
            'url' => $this->bookingUrl(),
            'telephone' => $this->phone,
            'image' => $this->coverUrl() ?? $this->logoUrl(),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $this->address,
                'postalCode' => $this->postal_code,
                'addressLocality' => $this->city,
                'addressCountry' => 'NL',
            ]),
            'geo' => $this->latitude !== null && $this->longitude !== null
                ? ['@type' => 'GeoCoordinates', 'latitude' => $this->latitude, 'longitude' => $this->longitude]
                : null,
            'aggregateRating' => $this->hasRating()
                ? ['@type' => 'AggregateRating', 'ratingValue' => round((float) $this->rating_avg, 1), 'reviewCount' => $this->rating_count, 'bestRating' => 5, 'worstRating' => 1]
                : null,
        ]);
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

    public function bannerUrl(): ?string
    {
        return ImageStore::url($this->banner_path);
    }

    /**
     * De banner staat op de pagina als hij aan staat en er iets te tonen is.
     */
    public function hasBanner(): bool
    {
        return $this->banner_active && ($this->banner_text !== null || $this->banner_path !== null);
    }

    public function instagramUrl(): ?string
    {
        return $this->instagram ? 'https://instagram.com/'.$this->instagram : null;
    }

    public function coverUrl(): ?string
    {
        return ImageStore::url($this->cover_path);
    }
}
