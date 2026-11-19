<?php

namespace App\Enums;

enum PitcherRequestType: string
{
    case TrialExtension = 'trial_extension';
    case Wish = 'wish';
    case Question = 'question';

    public function label(): string
    {
        return match ($this) {
            self::TrialExtension => 'Proef verlengen',
            self::Wish => 'Wens van een kapper',
            self::Question => 'Vraag',
        };
    }
}
