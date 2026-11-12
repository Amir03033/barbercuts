<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * De spaarkaart van een zaak (fase 9): zoveel stempels voor een gratis afspraak of een bedrag korting.
 *
 * @property int $stamps_required
 * @property int $reward_cents
 * @property bool $is_active
 */
#[Fillable(['name', 'stamps_required', 'reward_type', 'reward_cents', 'is_active'])]
class LoyaltyCard extends Model
{
    use BelongsToShop;

    public const array STAMP_OPTIONS = [5, 6, 8, 10, 12];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'name' => 'Spaarkaart',
        'stamps_required' => 10,
        'reward_type' => 'free',
        'reward_cents' => 0,
        'is_active' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['stamps_required' => 'integer', 'reward_cents' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * De diensten die een stempel opleveren; leeg = alle diensten.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'loyalty_card_service')->withoutGlobalScope(ShopScope::class);
    }

    public function isFree(): bool
    {
        return $this->reward_type === 'free';
    }

    public function rewardLabel(): string
    {
        return $this->isFree() ? 'een gratis afspraak' : money($this->reward_cents).' korting';
    }

    /**
     * Wat de klant betaalt als de beloning wordt verzilverd.
     */
    public function amountAfterReward(int $priceCents): int
    {
        return $this->isFree() ? 0 : max(0, $priceCents - $this->reward_cents);
    }
}
