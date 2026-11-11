<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $appointment_id
 * @property NotificationType $type
 * @property NotificationStatus $status
 * @property MessageChannel|null $channel
 * @property CarbonImmutable $send_at
 * @property CarbonImmutable|null $sent_at
 * @property string|null $error
 *
 * Een gepland of verstuurd bericht aan de klant bij een afspraak (§7.6).
 * Geen ShopScope: de scheduler draait buiten een zaak en kiest zelf op afspraak.
 */
class AppointmentNotification extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'status' => NotificationStatus::class,
            'channel' => MessageChannel::class,
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withoutGlobalScopes();
    }
}
