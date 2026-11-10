<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case Online = 'online';
    case Staff = 'staff';
    case WalkIn = 'walk_in';
}
