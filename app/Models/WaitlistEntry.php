<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use App\Models\Concerns\BelongsToShop;
use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Een klant op de wachtlijst voor een volle dag (fase 8).
 *
 * @property WaitlistStatus $status
 * @property Carbon $date
 * @property Carbon|null $offer_expires_at
 */
class WaitlistEntry extends Model
{
    use BelongsToShop;

    /** Zo lang heeft een klant voorrang op een vrijgekomen plek. */
    public const int OFFER_MINUTES = 15;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaitlistStatus::class,
            'date' => 'date',
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScope(ShopScope::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScope(ShopScope::class);
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class)->withoutGlobalScope(ShopScope::class);
    }

    public function hasOpenOffer(): bool
    {
        return $this->status === WaitlistStatus::Offered && $this->offer_expires_at?->isFuture();
    }

    public function url(): string
    {
        return route('waitlist.show', ['token' => $this->token]);
    }
}
