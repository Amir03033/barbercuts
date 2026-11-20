<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén gestarte demo via de openbare demolink. Blijft bestaan als de demozaak al gewist is, zodat de pitcher kan zien
 * hoe vaak zijn link werkt en welke demo tot een aanmelding leidde. Bevat geen gegevens van de bezoeker.
 *
 * @property int|null $pitcher_id
 * @property int|null $demo_invite_id
 * @property int|null $visitor_id
 * @property int|null $signed_up_barbershop_id
 */
class DemoVisit extends Model
{
    protected $fillable = ['pitcher_id', 'demo_invite_id', 'visitor_id'];

    /**
     * @return BelongsTo<DemoInvite, $this>
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(DemoInvite::class, 'demo_invite_id');
    }

    /**
     * @return BelongsTo<Barbershop, $this>
     */
    public function signedUpShop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class, 'signed_up_barbershop_id');
    }
}
