<?php

namespace App\Enums;

enum VatRateType: string
{
    case Standard = 'standard';
    case Reduced = 'reduced';
    case Zero = 'zero';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard (20%)',
            self::Reduced => 'Reduced (5%)',
            self::Zero => 'Zero (0%)',
        };
    }
}
