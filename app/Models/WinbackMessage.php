<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

/**
 * Een verstuurd "we missen je"-bericht aan een klant (fase 8).
 */
class WinbackMessage extends Model
{
    use BelongsToShop;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
