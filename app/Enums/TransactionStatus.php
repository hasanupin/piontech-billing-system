<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionStatus: string implements HasColor, HasLabel
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Paid => __('Paid'),
            self::Partial => __('Partial'),
            self::Unpaid => __('Unpaid'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Partial => 'warning',
            self::Unpaid => 'danger',
        };
    }
}
