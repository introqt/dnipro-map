<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Muted = 'muted';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Muted => 'Muted',
            self::Banned => 'Banned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Muted => 'warning',
            self::Banned => 'danger',
        };
    }
}
