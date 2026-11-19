<?php

namespace App\Enums;

enum PitcherRequestStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Done => 'Afgehandeld',
            self::Declined => 'Afgewezen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Done => 'success',
            self::Declined => 'gray',
        };
    }
}
