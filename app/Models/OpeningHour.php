<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Openingstijden van de zaak: publiek zichtbaar en de buitengrens voor de agenda's.
 */
#[Fillable(['day_of_week', 'opens_at', 'closes_at'])]
class OpeningHour extends Model
{
    use BelongsToShop;

    public $timestamps = false;
}
