<?php

namespace App\Models;

use App\Enums\TimeOffType;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\TimeOffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vrij, vakantie, ziek of afwijkende uren. staff_member_id null = de hele zaak.
 */
#[Table('time_off')]
#[Fillable(['staff_member_id', 'starts_at', 'ends_at', 'type', 'reason'])]
class TimeOff extends Model
{
    use BelongsToShop;

    /** @use HasFactory<TimeOffFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'type' => TimeOffType::class,
        ];
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
