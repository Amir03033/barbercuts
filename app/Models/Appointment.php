<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToShop;
use App\Models\Scopes\ShopScope;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Afspraak. Aanmaken en wijzigen loopt altijd via AppointmentService, nooit via mass assignment.
 *
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property CarbonInterface $blocked_until
 * @property AppointmentStatus $status
 * @property AppointmentSource $source
 * @property PaymentMethod|null $payment_method
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $cancelled_at
 * @property CancelledBy|null $cancelled_by
 * @property DepositStatus $deposit_status
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
            'loyalty_stamp' => 'boolean',
            'loyalty_redeemed_stamps' => 'integer',
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

    /*
    | De relaties hieronder slaan de tenant-scope over: een afspraak die al is opgehaald (via de scope, de
    | beheerlink of /account) mag altijd zijn eigen klant, kapper en dienst laden — ook buiten een tenant-request.
    */

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScope(ShopScope::class);
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class)->withoutGlobalScope(ShopScope::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScope(ShopScope::class);
    }

    public function manageUrl(): string
    {
        return route('appointment.manage', $this->manage_token);
    }

    /**
     * Begintijd in de tijdzone van de zaak.
     */
    public function localStart(): CarbonImmutable
    {
        return $this->starts_at->toImmutable()->setTimezone($this->barbershop->timezone);
    }

    /**
     * "Morgen 14:30" of, lang, "donderdag 12 november om 14:30".
     */
    public function whenLabel(bool $long = false): string
    {
        $start = $this->localStart();

        return $long
            ? $start->locale('nl')->isoFormat('dddd D MMMM').' om '.$start->format('H:i')
            : human_datetime($start, $this->barbershop->timezone);
    }

    public function isScheduled(): bool
    {
        return $this->status === AppointmentStatus::Scheduled;
    }
}
