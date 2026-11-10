<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Afspraak. Aanmaken en wijzigen loopt altijd via AppointmentService (fase 2), nooit via mass assignment.
 */
class Appointment extends Model
{
    use BelongsToShop;

    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'price_cents' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'blocked_until' => 'datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'amount_paid_cents' => 'integer',
            'tip_cents' => 'integer',
            'payment_method' => PaymentMethod::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancelled_by' => CancelledBy::class,
            'deposit_cents' => 'integer',
            'deposit_status' => DepositStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
