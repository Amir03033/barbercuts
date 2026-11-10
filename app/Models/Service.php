<?php

namespace App\Models;

use App\Enums\DepositType;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property DepositType $deposit_type
 */
#[Fillable(['name', 'description', 'duration', 'price_cents', 'buffer_after', 'is_active', 'sort_order'])]
class Service extends Model
{
    use BelongsToShop;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'price_cents' => 'integer',
            'buffer_after' => 'integer',
            'is_active' => 'boolean',
            'deposit_type' => DepositType::class,
            'deposit_value' => 'integer',
        ];
    }

    /**
     * De kappers die deze dienst doen. Geen koppeling = iedereen (§7.1).
     *
     * @return BelongsToMany<StaffMember, $this>
     */
    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(StaffMember::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
