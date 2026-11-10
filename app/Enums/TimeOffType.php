<?php

namespace App\Enums;

enum TimeOffType: string
{
    case Closed = 'closed';
    case CustomHours = 'custom_hours';
}
