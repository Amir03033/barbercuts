<?php

namespace App\Enums;

enum CancelledBy: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case System = 'system';
}
