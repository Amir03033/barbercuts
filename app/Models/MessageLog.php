<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elk verstuurd bericht (mail, push, sms, WhatsApp), voor statistiek en kosten (§5). Gevuld vanaf fase 5.
 */
class MessageLog extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cost_cents' => 'integer'];
    }

    /**
     * @return BelongsTo<Barbershop, $this>
     */
    public function barbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class);
    }
}
