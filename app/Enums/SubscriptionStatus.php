<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    /** Eerste betaling is gestart maar nog niet betaald. */
    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
}
