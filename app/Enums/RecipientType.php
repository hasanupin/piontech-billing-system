<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RecipientType: string implements HasColor, HasLabel
{
    case Customer = 'customer';
    case External = 'external';

    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => __('Customer'),
            self::External => __('Non-Customer'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'info',
            self::External => 'gray',
        };
    }
}
