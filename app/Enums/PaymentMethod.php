<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pin = 'pin';
    case Cash = 'cash';
    case Tikkie = 'tikkie';
    case Online = 'online';
    case Other = 'other';
}
