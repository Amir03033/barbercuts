<?php

namespace App\Enums;

enum DepositStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Forfeited = 'forfeited';
}
