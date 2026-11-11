<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een abonnement dat zaken kunnen nemen. De prijs staat hier en is aan te passen in /admin (B10).
 */
#[Fillable(['name', 'price_cents', 'interval', 'trial_days', 'is_active'])]
class Plan extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Het plan dat nieuwe zaken krijgen: het actieve plan met het laagste id.
     */
    public static function current(): ?self
    {
        return self::query()->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceLabel(): string
    {
        return money($this->price_cents).' per maand';
    }
}
