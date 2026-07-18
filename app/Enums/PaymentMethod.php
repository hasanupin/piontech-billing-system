<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Transfer => __('Transfer'),
        };
    }
}
