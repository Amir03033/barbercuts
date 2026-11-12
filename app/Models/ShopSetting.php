<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Boekregels en herinneringen van een zaak (1-op-1).
 */
#[Fillable([
    'slot_interval', 'min_notice', 'max_days_ahead', 'customer_cancel_limit', 'customer_reschedule_limit',
    'allow_guest_booking', 'allow_staff_choice', 'reminder_1_minutes', 'reminder_2_minutes', 'reminder_channels',
    'sms_enabled', 'whatsapp_enabled', 'review_request_hours',
])]
class ShopSetting extends Model
{
    use BelongsToShop;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'slot_interval' => 15,
        'min_notice' => 60,
        'max_days_ahead' => 60,
        'customer_cancel_limit' => 120,
        'customer_reschedule_limit' => 120,
        'allow_guest_booking' => true,
        'allow_staff_choice' => true,
        'reminder_1_minutes' => 1440,
        'reminder_channels' => '["mail","push"]',
        'review_request_hours' => 2,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_interval' => 'integer',
            'min_notice' => 'integer',
            'max_days_ahead' => 'integer',
            'customer_cancel_limit' => 'integer',
            'customer_reschedule_limit' => 'integer',
            'allow_guest_booking' => 'boolean',
            'allow_staff_choice' => 'boolean',
            'reminder_1_minutes' => 'integer',
            'reminder_2_minutes' => 'integer',
            'reminder_channels' => 'array',
            'review_request_hours' => 'integer',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
        ];
    }
}
