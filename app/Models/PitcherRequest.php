<?php

namespace App\Models;

use App\Enums\PitcherRequestStatus;
use App\Enums\PitcherRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Een verzoek van een pitcher aan de beheerder. Geen tenant-scope: het hoort bij de pitcher, niet bij één zaak.
 *
 * @property PitcherRequestType $type
 * @property PitcherRequestStatus $status
 * @property Carbon|null $handled_at
 */
class PitcherRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PitcherRequestType::class,
            'status' => PitcherRequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pitcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Barbershop, $this>
     */
    public function barbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PitcherRequestStatus::Open;
    }
}
