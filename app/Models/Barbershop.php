<?php

namespace App\Models;

use App\Enums\ThemeMode;
use App\Support\Color;
use App\Support\ImageStore;
use Database\Factories\BarbershopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * De zaak (tenant).
 *
 * @property ThemeMode $theme_mode
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
