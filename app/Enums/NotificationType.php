<?php

namespace App\Enums;

enum NotificationType: string
{
    case Confirmation = 'confirmation';
    case Reminder1 = 'reminder_1';
    case Reminder2 = 'reminder_2';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case ReviewRequest = 'review_request';

    public function isReminder(): bool
    {
        return $this === self::Reminder1 || $this === self::Reminder2;
    }
}
