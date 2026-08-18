<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditEvent: string implements HasColor, HasLabel
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Accessed = 'accessed';
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';
    case Exported = 'exported';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => __('Created'),
            self::Updated => __('Updated'),
            self::Deleted => __('Deleted'),
            self::Accessed => __('Accessed'),
            self::LoggedIn => __('Logged In'),
            self::LoggedOut => __('Logged Out'),
            self::Exported => __('Exported'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Created => 'success',
            self::Updated => 'warning',
            self::Deleted => 'danger',
            self::Accessed => 'gray',
            self::LoggedIn => 'info',
            self::LoggedOut => 'gray',
            self::Exported => 'info',
        };
    }
}
