<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Dagelijks voorberekende platformstatistiek (§11): één waarde per datum en sleutel.
 */
#[Table('platform_daily_stats')]
class PlatformDailyStat extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date', 'value' => 'integer'];
    }
}
