<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Een review van een klant na een voltooide afspraak (fase 7). Eén per afspraak.
 *
 * @property int $rating
 * @property Carbon|null $replied_at
 * @property Carbon|null $reported_at
 * @property Carbon|null $hidden_at
 */
class Review extends Model
{
    use BelongsToShop;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'replied_at' => 'datetime',
            'reported_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * Zichtbaar voor iedereen: niet door de admin verborgen.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('reviews.hidden_at');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScope(ShopScope::class);
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class)->withoutGlobalScope(ShopScope::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withoutGlobalScope(ShopScope::class);
    }

    /**
     * De naam zoals die publiek getoond wordt: voornaam en de eerste letter van de achternaam.
     */
    public function authorLabel(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->customer?->name)) ?: [];
        $first = $parts[0] ?? 'Klant';
        $last = count($parts) > 1 ? mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.' : '';

        return trim($first.' '.$last);
    }
}
