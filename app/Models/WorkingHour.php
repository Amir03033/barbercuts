<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Werktijden van een kapper. Hoort via de kapper bij een zaak; altijd benaderen via StaffMember.
 */
#[Fillable(['day_of_week', 'starts_at', 'ends_at'])]
class WorkingHour extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
